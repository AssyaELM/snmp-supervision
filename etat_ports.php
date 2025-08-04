<?php
session_start();
if (!isset($_SESSION['connecte'])) {
    header("Location: login.php");
    exit();
}

require_once 'db.php';

try {
    $stmt = $conn->query("SELECT ip FROM switches");
    $switches = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $error = "Erreur : " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>État des Ports - Supervision Réseau</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="layout-container">
        <nav class="side-menu">
            <a href="accueil.php">Accueil</a>
            <a href="etat_ports.php" class="active">État des ports</a>
            <a href="gestion_switches.php">Gestion des Switches</a>
            <a href="historique.php">Historique</a>
            <a href="alertes.php">Alertes Récentes</a>
            <a href="logout.php">Déconnexion</a>
        </nav>
        <div class="main-content">
            <div class="container">
                <h1>Supervision Réseau</h1>
                <h2>État des Ports</h2>
                <form id="switchForm">
                    <label for="ip">Sélectionner un Switch :</label>
                    <select id="ip" name="ip" required>
                        <option value="">Choisir un switch</option>
                        <?php foreach ($switches as $switch_ip): ?>
                            <option value="<?= htmlspecialchars($switch_ip) ?>">
                                <?= htmlspecialchars($switch_ip) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="submit" value="Vérifier">
                </form>

                <div id="message" class="hidden"></div>
                <div id="loading" class="loading hidden">Chargement des données...</div>
                <div id="portsTable" class="hidden">
                    <table>
                        <thead>
                            <tr>
                                <th>Port</th>
                                <th>Nom</th>
                                <th>Statut</th>
                                <th>Vitesse</th>
                                <th>Température</th>
                                <th>Date/Heure</th>
                            </tr>
                        </thead>
                        <tbody id="portsBody"></tbody>
                    </table>
                </div>

                <div class="button-grid">
                    <a href="accueil.php">Retour à l'accueil</a>
                    <a href="gestion_switches.php">Gestion des Switches</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            let intervalId = null;

            function fetchPorts(ip) {
                $('#loading').removeClass('hidden');
                $('#message').addClass('hidden');
                $('#portsTable').addClass('hidden');

                $.ajax({
                    url: 'collect_snmp_endpoint.php',
                    type: 'POST',
                    data: { ip: ip },
                    dataType: 'json',
                    success: function(response) {
                        $('#loading').addClass('hidden');
                        if (response.success) {
                            $('#message').text(response.message).removeClass('error success hidden')
                                .addClass(response.message.includes('Erreur') ? 'error' : 'success');
                            $('#portsTable').removeClass('hidden');
                            $('#portsBody').empty();
                            response.data.forEach(port => {
                                $('#portsBody').append(`
                                    <tr>
                                        <td>${port.port_id}</td>
                                        <td>${port.port_name}</td>
                                        <td class="status-${port.status.toLowerCase()}">${port.status}</td>
                                        <td>${port.speed}</td>
                                        <td>${port.temperature}</td>
                                        <td>${new Date().toLocaleString()}</td>
                                    </tr>
                                `);
                            });
                        } else {
                            $('#message').text(response.message).removeClass('success hidden').addClass('error');
                        }
                    },
                    error: function() {
                        $('#loading').addClass('hidden');
                        $('#message').text('Erreur lors de la récupération des données.').removeClass('success hidden').addClass('error');
                    }
                });
            }

            $('#switchForm').on('submit', function(e) {
                e.preventDefault();
                const ip = $('#ip').val();
                if (ip) {
                    if (intervalId) clearInterval(intervalId);
                    fetchPorts(ip);
                    intervalId = setInterval(() => fetchPorts(ip), 30000); // Toutes les 30 secondes
                }
            });
        });
    </script>
</body>
</html>