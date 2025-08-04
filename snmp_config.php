<?php
// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'snmp_simple');
define('DB_USER', 'root');
define('DB_PASSWORD', '');

// Configuration Telegram
define('TELEGRAM_BOT_TOKEN', '7946020039:AAF3dCP86yyiHPAICyfGjogyhiD5MQ5e2ZI');
define('TELEGRAM_CHAT_ID', '1668881628');

// Configuration SNMP
define('SNMP_COMMUNITY', 'public');
?>
</xai_schema>
```

**Vérification** :
- Place ce fichier dans `C:\xampp\config`.
- Adapte `SNMP_COMMUNITY` à la communauté configurée sur ton switch.
- Vérifie les autres constantes (surtout Telegram et DB).

### Étape 6 : Mettre à Jour les Fichiers Existants
Les fichiers suivants doivent être adaptés pour ne plus dépendre des insertions manuelles et utiliser les données SNMP.

#### **etat_ports.php**
Mettre à jour pour exécuter `snmp_collect.php` avant d’afficher les ports.

<xaiArtifact artifact_id="ec2632dd-3915-4c62-933c-7a2caa2ce92b" artifact_version_id="a4515d16-ba77-443e-873f-d8a63436d90c" title="etat_ports.php" contentType="text/html">
<?php
session_start();
if (!isset($_SESSION['connecte'])) {
    header("Location: login.php");
    exit();
}

require_once 'db.php';

// Exécuter la collecte SNMP si un switch est sélectionné
$ip = $_POST['ip'] ?? '';
$ports = [];
$message = '';

function sendTelegramMessage($botToken, $chatId, $message) {
    $url = "https://api.telegram.org/bot$botToken/sendMessage?chat_id=$chatId&text=" . urlencode($message);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    file_put_contents('telegram_log.txt', "URL: $url\nResponse: $response\n\n", FILE_APPEND);
    curl_close($ch);
    return $response;
}

try {
    // Récupérer les IPs des switches
    $stmt = $conn->query("SELECT ip FROM switches");
    $switches = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ip && in_array($ip, $switches)) {
        // Exécuter snmp_collect.php pour ce switch
        $output = shell_exec('C:\xampp\php\php.exe C:\xampp\htdocs\snmp_simple\snmp_collect.php');
        file_put_contents('snmp_collect.log', date('Y-m-d H:i:s') . " - Output: $output\n", FILE_APPEND);

        // Récupérer les ports pour cet IP
        $stmt = $conn->prepare("
            SELECT port_id, port_name, status, speed, temperature, date_time
            FROM historique_ports
            WHERE ip = ? AND date_time >= NOW() - INTERVAL 1 MINUTE
            ORDER BY port_id
        ");
        $stmt->execute([$ip]);
        $ports = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Vérifier les ports DOWN pour les alertes
        $new_downs = [];
        foreach ($ports as $port) {
            if ($port['status'] === 'Down') {
                $new_downs[] = $port['port_name'];
            }
        }

        if (!empty($new_downs)) {
            $message = "🚨 Alertes Switch $ip\nPorts DOWN détectés :\n- " . implode("\n- ", $new_downs) . "\nVérifié le : " . date('Y-m-d H:i:s');
            sendTelegramMessage(TELEGRAM_BOT_TOKEN, TELEGRAM_CHAT_ID, $message);
        } elseif (empty($ports)) {
            $message = "Aucun port trouvé pour ce switch.";
        } else {
            $message = "✅ Switch $ip\nAucun port DOWN détecté.\nVérifié le : " . date('Y-m-d H:i:s');
            sendTelegramMessage(TELEGRAM_BOT_TOKEN, TELEGRAM_CHAT_ID, $message);
        }
    }
} catch (Exception $e) {
    $message = "Erreur : " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>État des Ports - Supervision Réseau</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="layout-container">
        <!-- Sidebar -->
        <nav class="side-menu">
            <a href="accueil.php">Accueil</a>
            <a href="etat_ports.php" class="active">État des ports</a>
            <a href="gestion_switches.php">Gestion des Switches</a>
            <a href="historique.php">Historique</a>
            <a href="alertes.php">Alertes Récentes</a>
            <a href="logout.php">Déconnexion</a>
        </nav>
        <!-- Contenu principal -->
        <div class="main-content">
            <div class="container">
                <h1>Supervision Réseau</h1>
                <h2>État des Ports</h2>
                <form method="POST">
                    <label for="ip">Sélectionner un Switch :</label>
                    <select id="ip" name="ip" required>
                        <option value="">Choisir un switch</option>
                        <?php foreach ($switches as $switch_ip): ?>
                            <option value="<?= htmlspecialchars($switch_ip) ?>" <?= $ip === $switch_ip ? 'selected' : '' ?>>
                                <?= htmlspecialchars($switch_ip) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="submit" value="Vérifier">
                </form>

                <?php if ($message): ?>
                    <p class="<?= strpos($message, 'Erreur') === false ? 'success' : 'error' ?>">
                        <?= htmlspecialchars($message) ?>
                    </p>
                <?php endif; ?>

                <?php if (!empty($ports)): ?>
                    <table>
                        <tr>
                            <th>Port</th>
                            <th>Nom</th>
                            <th>Statut</th>
                            <th>Vitesse</th>
                            <th>Température</th>
                            <th>Date/Heure</th>
                        </tr>
                        <?php foreach ($ports as $port): ?>
                            <tr>
                                <td><?= htmlspecialchars($port['port_id']) ?></td>
                                <td><?= htmlspecialchars($port['port_name']) ?></td>
                                <td class="status-<?= strtolower($port['status']) ?>">
                                    <?= htmlspecialchars($port['status']) ?>
                                </td>
                                <td><?= htmlspecialchars($port['speed']) ?></td>
                                <td><?= htmlspecialchars($port['temperature']) ?></td>
                                <td><?= htmlspecialchars($port['date_time']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php elseif ($ip): ?>
                    <p class="error">Aucun port trouvé pour ce switch ou IP non valide.</p>
                <?php endif; ?>

                <div class="button-grid">
                    <a href="accueil.php">Retour à l'accueil</a>
                    <a href="gestion_switches.php">Gestion des Switches</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>