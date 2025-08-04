<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require_once 'C:\xampp\config\snmp_config.php';
require_once 'C:\xampp\htdocs\snmp_simple\db.php';

$response = ['success' => false, 'message' => '', 'data' => []];

/**
 * Récupération SNMP sécurisée
 */
function getSnmpData($ip, $community, $oid) {
    try {
        $result = @snmp2_walk($ip, $community, $oid, 1000000, 3);
        if ($result === false) {
            throw new Exception("Échec SNMP pour $oid sur $ip");
        }
        return array_map('trim', $result);
    } catch (Exception $e) {
        file_put_contents('snmp_error.log', date('Y-m-d H:i:s') . " - " . $e->getMessage() . "\n", FILE_APPEND);
        return false;
    }
}

/**
 * Envoi d'alerte Telegram
 */
function sendTelegramMessage($botToken, $chatId, $message) {
    $url = "https://api.telegram.org/bot$botToken/sendMessage?chat_id=$chatId&text=" . urlencode($message);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5
    ]);
    $response = curl_exec($ch);
    file_put_contents('telegram_log.txt', "URL: $url\nResponse: $response\n\n", FILE_APPEND);
    curl_close($ch);
    return json_decode($response, true);
}

// Vérification IP reçue
$ip = $_POST['ip'] ?? '';
if (!$ip) {
    $response['message'] = 'Aucune IP spécifiée.';
    echo json_encode($response);
    exit;
}

try {
    // Vérification IP dans la base
    $stmt = $conn->prepare("SELECT ip FROM switches WHERE ip = ?");
    $stmt->execute([$ip]);
    if (!$stmt->fetch()) {
        $response['message'] = "IP $ip non trouvée.";
        echo json_encode($response);
        exit;
    }

    // Liste des OIDs à collecter
    $oids = [
        'port_name'    => '1.3.6.1.2.1.2.2.1.2',
        'status'       => '1.3.6.1.2.1.2.2.1.8',
        'speed'        => '1.3.6.1.2.1.2.2.1.5',
        'in_octets'    => '1.3.6.1.2.1.2.2.1.10',
        'out_octets'   => '1.3.6.1.2.1.2.2.1.16',
        'out_errors'   => '1.3.6.1.2.1.2.2.1.20',
        'poe'          => '1.3.6.1.4.1.9.3.1.1.1.2.1.3.1',
        'temperature'  => '1.3.6.1.4.1.25506.2.6.1.1.2.1',
        'fan_status'   => '1.3.6.1.4.1.25506.8.35.1.1.9.1.4.1',
        'power_status' => '1.3.6.1.4.1.25506.8.35.1.1.1.1.3.1',
        'psu_present'  => '1.3.6.1.4.1.25506.2.6.1.1.60.1',
        'psu_status'   => '1.3.6.1.4.1.25506.2.6.1.1.61.1'
    ];

    // Collecte des interfaces
    $community = defined('SNMP_COMMUNITY') ? SNMP_COMMUNITY : 'public';
    $ifIndexes = getSnmpData($ip, $community, '1.3.6.1.2.1.2.2.1.1');

    if ($ifIndexes === false) {
        $response['message'] = "Échec récupération interfaces pour $ip.";
        echo json_encode($response);
        exit;
    }

    $ports = [];
    $new_downs = [];

    foreach ($ifIndexes as $ifIndex) {
        $port = ['port_id' => $ifIndex];
        foreach ($oids as $key => $oid) {
            $value = getSnmpData($ip, $community, $oid . '.' . $ifIndex);
            $port[$key] = ($value !== false && isset($value[0])) ? trim($value[0]) : 'N/A';

            // Formattage des valeurs
            if ($key === 'status' && $port[$key] !== 'N/A') {
                $port[$key] = ($port[$key] == 1) ? 'Up' : 'Down';
            }
            if ($key === 'speed' && $port[$key] !== 'N/A') {
                $port[$key] = ($port[$key] / 1000000) . ' Mbps';
            }
            if ($key === 'temperature' && $port[$key] !== 'N/A') {
                $port[$key] .= ' °C';
            }
        }

        $ports[] = $port;
        if ($port['status'] === 'Down') {
            $new_downs[] = $port['port_name'];
        }
    }

    // Insertion dans historique_ports + gestion alertes
    $stmt = $conn->prepare("
        INSERT INTO historique_ports (ip, port_id, port_name, status, speed, poe, in_octets, out_octets, out_errors, temperature, fan_status, power_status, psu_present, psu_status, date_time)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    foreach ($ports as $port) {
        $stmt->execute([
            $ip, $port['port_id'], $port['port_name'], $port['status'], $port['speed'],
            $port['poe'], $port['in_octets'], $port['out_octets'], $port['out_errors'],
            $port['temperature'], $port['fan_status'], $port['power_status'],
            $port['psu_present'], $port['psu_status']
        ]);

        // Gestion des alertes Telegram
        if ($port['status'] === 'Down') {
            $alertStmt = $conn->prepare("
                SELECT COUNT(*) FROM alert_log 
                WHERE ip = ? AND port_name = ? AND alert_time >= NOW() - INTERVAL 1 HOUR
            ");
            $alertStmt->execute([$ip, $port['port_name']]);

            if ($alertStmt->fetchColumn() == 0) {
                $alertStmt = $conn->prepare("
                    INSERT INTO alert_log (ip, port_name, alert_time) VALUES (?, ?, NOW())
                ");
                $alertStmt->execute([$ip, $port['port_name']]);

                $message = "🚨 Alerte Switch $ip\nPort DOWN : {$port['port_name']}\nVérifié le : " . date('Y-m-d H:i:s');
                sendTelegramMessage(TELEGRAM_BOT_TOKEN, TELEGRAM_CHAT_ID, $message);
            }
        }
    }

    $response['success'] = true;
    $response['data'] = $ports;
    $response['message'] = !empty($new_downs) 
        ? "Ports DOWN détectés : " . implode(", ", $new_downs) 
        : "Aucun port DOWN détecté.";

    echo json_encode($response);
    exit;

} catch (Exception $e) {
    $response['message'] = "Erreur : " . $e->getMessage();
    file_put_contents('snmp_error.log', date('Y-m-d H:i:s') . " - Erreur : " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode($response);
    exit;
}
