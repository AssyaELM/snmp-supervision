<?php
session_start();
if (!isset($_SESSION['connecte'])) {
    header("Location: login.php");
    exit();
}

require_once 'db.php';

$alerts = [];
$error = null;

try {
    $stmt = $conn->query("SELECT ip, port_name, alert_time FROM alert_log ORDER BY alert_time DESC");
    $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (
        isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
    ) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'success',
            'alerts' => $alerts,
            'count' => count($alerts)
        ]);
        exit;
    }

} catch (Exception $e) {
    $error = "Erreur : " . $e->getMessage();

    if (
        isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
    ) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'error',
            'message' => $error
        ]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Historique des Alertes - Supervision Réseau</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>Supervision Réseau</h1>
        <h2>Historique des Alertes</h2>

        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php elseif (empty($alerts)): ?>
            <p class="no-alert">Aucune alerte enregistrée.</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>IP du Switch</th>
                    <th>Port</th>
                    <th>Date/Heure</th>
                </tr>
                <?php foreach ($alerts as $alert): ?>
                    <tr>
                        <td><?= htmlspecialchars($alert['ip']) ?></td>
                        <td><?= htmlspecialchars($alert['port_name']) ?></td>
                        <td><?= htmlspecialchars($alert['alert_time']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

        <div class="button-grid">
            <a href="accueil.php">Retour à l'accueil</a>
        </div>
    </div>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    const alertTableContainer = document.querySelector('.container');
    const refreshInterval = 10000; 

    function chargerAlertes() {
        fetch('alertes.php', { headers: { 'X-Requested-With': 'XMLHttpRequest' }})
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    mettreAJourTable(data.alerts);
                } else {
                    afficherErreur(data.message || 'Erreur inconnue.');
                }
            })
            .catch(err => {
                afficherErreur('Erreur AJAX : ' + err);
            });
    }

    function mettreAJourTable(alerts) {
        let html = '';

        if (alerts.length === 0) {
            html = '<p class="no-alert">Aucune alerte enregistrée.</p>';
        } else {
            html = `
                <table>
                    <tr>
                        <th>IP du Switch</th>
                        <th>Port</th>
                        <th>Date/Heure</th>
                    </tr>
            `;
            alerts.forEach(alert => {
                html += `
                    <tr>
                        <td>${escapeHTML(alert.ip)}</td>
                        <td>${escapeHTML(alert.port_name)}</td>
                        <td>${escapeHTML(alert.alert_time)}</td>
                    </tr>
                `;
            });
            html += '</table>';
        }

        const oldTable = alertTableContainer.querySelector('table, .no-alert, .error');
        if (oldTable) {
            oldTable.outerHTML = html;
        } else {
            alertTableContainer.insertAdjacentHTML('beforeend', html);
        }
    }

    function afficherErreur(message) {
        const html = `<p class="error">${escapeHTML(message)}</p>`;
        const oldTable = alertTableContainer.querySelector('table, .no-alert, .error');
        if (oldTable) {
            oldTable.outerHTML = html;
        } else {
            alertTableContainer.insertAdjacentHTML('beforeend', html);
        }
    }

    function escapeHTML(str) {
        return str.replace(/[&<>"']/g, function(m) {
            return ({
                '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
            })[m];
        });
    }
    chargerAlertes();
    setInterval(chargerAlertes, refreshInterval);
});
</script>

</body>
</html>
