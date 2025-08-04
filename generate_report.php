<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'C:\xampp\config\snmp_config.php';
require_once 'C:\xampp\htdocs\snmp_simple\db.php';
require_once 'C:\xampp\htdocs\snmp_simple\tcpdf\tcpdf.php';

// Vérifier l'heure pour s'exécuter uniquement à 8:45 (temporaire)
$current_hour = date('H');
if ($current_hour != '08' || (date('i') < 45 && $current_hour == '08')) {
    exit("Exécution prévue uniquement à 08:45. Heure actuelle : $current_hour:" . date('i') . "\n");
}

// Configuration TCPDF
class MYPDF extends TCPDF {
    public function Header() {
        $this->SetFont('helvetica', 'B', 14);
        $this->Cell(0, 10, 'Rapport Quotidien d\'État du Réseau', 0, 1, 'C');
        $this->SetFont('helvetica', '', 10);
        $this->Cell(0, 10, 'Généré le : ' . date('Y-m-d H:i:s'), 0, 1, 'C');
        $this->Ln(5);
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

try {
    $pdf = new MYPDF();
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('SNMP Simple');
    $pdf->SetTitle('Rapport Quotidien d\'État du Réseau');
    $pdf->SetMargins(10, 30, 10);
    $pdf->SetAutoPageBreak(TRUE, 15);
    $pdf->AddPage();

    $html = '<style>
        h2 { color: #1e3c72; font-family: helvetica; font-size: 14pt; }
        table { width: 100%; border-collapse: collapse; font-family: helvetica; font-size: 10pt; }
        th { background: #007bff; color: #ffffff; padding: 5px; }
        td { border: 1px solid #ccc; padding: 5px; }
        .status-down { color: #ff6b6b; font-weight: bold; }
        p { font-family: helvetica; font-size: 10pt; }
    </style>';

    // Liste des switches
    $stmt = $conn->query("SELECT ip, description FROM switches");
    $switches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $html .= '<h2>Liste des Switches</h2><table><tr><th>IP</th><th>Description</th></tr>';
    foreach ($switches as $switch) {
        $html .= "<tr><td>" . htmlspecialchars($switch['ip']) . "</td><td>" . htmlspecialchars($switch['description']) . "</td></tr>";
    }
    $html .= '</table>';

    // Alertes récentes (dernières 24h)
    $stmt = $conn->query("SELECT ip, port_name, alert_time FROM alert_log WHERE alert_time >= NOW() - INTERVAL 24 HOUR ORDER BY alert_time DESC");
    $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $html .= '<h2>Alertes Récentes (Dernières 24h)</h2>';
    if (empty($alerts)) {
        $html .= '<p>Aucune alerte récente.</p>';
    } else {
        $html .= '<table><tr><th>IP</th><th>Nom</th><th>Date/Heure</th></tr>';
        foreach ($alerts as $alert) {
            $html .= "<tr><td>" . htmlspecialchars($alert['ip']) . "</td><td>" . htmlspecialchars($alert['port_name'] ?? 'N/A') . "</td><td>" . htmlspecialchars($alert['alert_time']) . "</td></tr>";
        }
        $html .= '</table>';
    }

    // Statistiques des ports Down (dernières 24h)
    $html .= '<h2>Statistiques des Ports Down (Dernières 24h)</h2>';
    foreach ($switches as $switch) {
        $ip = $switch['ip'];
        $stmt = $conn->prepare("
            SELECT port_id, port_name, status, speed, in_octets, out_octets, out_errors, temperature, power_status, date_time
            FROM historique_ports
            WHERE ip = ? AND status = 'Down' AND date_time >= NOW() - INTERVAL 24 HOUR
            ORDER BY port_id, date_time DESC
        ");
        $stmt->execute([$ip]);
        $ports = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($ports)) {
            $html .= "<p>Aucun port Down pour le switch $ip dans les dernières 24h.</p>";
        } else {
            $html .= '<table><tr><th>Port</th><th>Nom</th><th>Statut</th><th>Vitesse</th><th>Octets Entrants</th><th>Octets Sortants</th><th>Erreurs Sortantes</th><th>Température</th><th>Alimentation</th><th>Date/Heure</th></tr>';
            foreach ($ports as $port) {
                $html .= "<tr><td>" . htmlspecialchars($port['port_id'] ?? 'N/A') . "</td><td>" . htmlspecialchars($port['port_name'] ?? 'N/A') . "</td><td class=\"status-down\">" . htmlspecialchars($port['status'] ?? 'N/A') . "</td><td>" . htmlspecialchars($port['speed'] ?? 'N/A') . "</td><td>" . htmlspecialchars($port['in_octets'] ?? '0') . "</td><td>" . htmlspecialchars($port['out_octets'] ?? '0') . "</td><td>" . htmlspecialchars($port['out_errors'] ?? '0') . "</td><td>" . htmlspecialchars($port['temperature'] ?? 'N/A') . "</td><td>" . htmlspecialchars($port['power_status'] ?? 'N/A') . "</td><td>" . htmlspecialchars($port['date_time'] ?? 'N/A') . "</td></tr>";
            }
            $html .= '</table>';
        }
    }

    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf_file = 'C:\xampp\htdocs\snmp_simple\reports\report_' . date('Ymd_His') . '.pdf';
    $pdf->Output($pdf_file, 'F');
    echo "PDF créé : $pdf_file\n";

    // Envoyer le PDF via Telegram
    $bot_token = '7946020039:AAF3dCP86yyiHPAICyfGjogyhiD5MQ5e2ZI';
    $chat_id = '1668881628';
    $url = "https://api.telegram.org/bot$bot_token/sendDocument";
    $caption = "Rapport quotidien généré le " . date('d/m/Y à H:i');

    $post_fields = [
        'chat_id' => $chat_id,
        'caption' => $caption,
        'document' => new CURLFile($pdf_file, 'application/pdf', basename($pdf_file))
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: multipart/form-data']);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    if ($response === false) {
        file_put_contents('C:\xampp\htdocs\snmp_simple\telegram_error.log', date('Y-m-d H:i:s') . " - Erreur Telegram : Échec de l'envoi\n", FILE_APPEND);
    } else {
        echo "PDF envoyé à Telegram avec succès.\n";
    }

    // Nettoyer les anciens rapports
    $files = glob('C:\xampp\htdocs\snmp_simple\reports\*.pdf');
    foreach ($files as $file) {
        if (filemtime($file) < time() - 7 * 24 * 60 * 60) {
            unlink($file);
        }
    }

} catch (Exception $e) {
    file_put_contents('C:\xampp\htdocs\snmp_simple\report_error.log', date('Y-m-d H:i:s') . " - Erreur : " . $e->getMessage() . "\n", FILE_APPEND);
    echo "Erreur capturée : " . $e->getMessage() . "\n";
}
?>