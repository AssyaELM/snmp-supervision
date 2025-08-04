<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'C:\xampp\config\snmp_config.php';
require_once 'C:\xampp\htdocs\snmp_simple\db.php';

function getSnmpData($ip, $community, $oid) {
    try {
        $result = snmp2_walk($ip, $community, $oid, 1000000, 3);
        if ($result === false) {
            throw new Exception("Échec SNMP pour $oid sur $ip");
        }
        return $result;
    } catch (Exception $e) {
        file_put_contents('snmp_error.log', date('Y-m-d H:i:s') . " - " . $e->getMessage() . "\n", FILE_APPEND);
        return false;
    }
}

$ip = $argv[1] ?? $_POST['ip'] ?? '';
if (!$ip) die("Aucune IP spécifiée.\n");

try {
    $stmt = $conn->prepare("SELECT ip FROM switches WHERE ip = ?");
    $stmt->execute([$ip]);
    if (!$stmt->fetch()) die("IP $ip non trouvée.\n");
} catch (Exception $e) {
    file_put_contents('snmp_error.log', date('Y-m-d H:i:s') . " - Erreur IP : " . $e->getMessage() . "\n", FILE_APPEND);
    die("Erreur IP : " . $e->getMessage() . "\n");
}

echo "Interrogation de $ip...\n";

$oids = [
    'port_name' => '1.3.6.1.2.1.2.2.1.2',    // ifDescr
    'status' => '1.3.6.1.2.1.2.2.1.8',       // ifOperStatus
    'speed' => '1.3.6.1.2.1.2.2.1.5',        // ifSpeed
    'in_octets' => '1.3.6.1.2.1.2.2.1.10',   // ifInOctets
    'out_octets' => '1.3.6.1.2.1.2.2.1.16',  // ifOutOctets
    'out_errors' => '1.3.6.1.2.1.2.2.1.20',  // ifOutErrors
    'poe' => '1.3.6.1.4.1.9.3.1.1.1.2.1.3.1', // Exemple PoE (à vérifier)
    'temperature' => '1.3.6.1.4.1.25506.2.6.1.1.2.1', // À ajuster
    'fan_status' => '1.3.6.1.4.1.25506.8.35.1.1.9.1.4.1', // À ajuster
    'power_status' => '1.3.6.1.4.1.25506.8.35.1.1.1.1.3.1', // À ajuster
    'psu_present' => '1.3.6.1.4.1.25506.2.6.1.1.60.1', // À ajuster
    'psu_status' => '1.3.6.1.4.1.25506.2.6.1.1.61.1'  // À ajuster
];

$community = defined('SNMP_COMMUNITY') ? SNMP_COMMUNITY : 'public';
$ifIndexes = getSnmpData($ip, $community, '1.3.6.1.2.1.2.2.1.1');
if ($ifIndexes === false) {
    echo "Échec récupération interfaces.\n";
    exit;
}

$ports = [];
$indexes = array_map('trim', $ifIndexes);
foreach ($indexes as $ifIndex) {
    $port = ['port_id' => $ifIndex];
    foreach ($oids as $key => $oid) {
        $value = getSnmpData($ip, $community, $oid . '.' . $ifIndex);
        $port[$key] = ($value !== false && isset($value[0])) ? trim($value[0]) : 'N/A';
        if ($key === 'status' && $port[$key] !== 'N/A') $port[$key] = ($port[$key] == 1) ? 'Up' : 'Down';
        if ($key === 'speed' && $port[$key] !== 'N/A') $port[$key] = ($port[$key] / 1000000) . ' Mbps';
        if ($key === 'temperature' && $port[$key] !== 'N/A') $port[$key] .= ' °C';
    }
    $ports[] = $port;
}

try {
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
        if ($port['status'] === 'Down') {
            $alertStmt = $conn->prepare("
                SELECT COUNT(*) FROM alert_log WHERE ip = ? AND port_name = ? AND alert_time >= NOW() - INTERVAL 1 HOUR
            ");
            $alertStmt->execute([$ip, $port['port_name']]);
            if ($alertStmt->fetchColumn() == 0) {
                $alertStmt = $conn->prepare("INSERT INTO alert_log (ip, port_name, alert_time) VALUES (?, ?, NOW())");
                $alertStmt->execute([$ip, $port['port_name']]);
            }
        }
    }
    echo "Données insérées pour $ip\n";
} catch (Exception $e) {
    file_put_contents('snmp_error.log', date('Y-m-d H:i:s') . " - Erreur insertion : " . $e->getMessage() . "\n", FILE_APPEND);
    echo "Erreur insertion : " . $e->getMessage() . "\n";
}

echo "Collecte terminée.\n";