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
    <title>Historique des Données - Supervision Réseau</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="layout-container">
        <nav class="side-menu">
            <a href="accueil.php">Accueil</a>
            <a href="etat_ports.php">État des ports</a>
            <a href="gestion_switches.php">Gestion des Switches</a>
            <a href="historique.php" class="active">Historique</a>
            <a href="alertes.php">Alertes Récentes</a>
            <a href="logout.php">Déconnexion</a>
        </nav>
        <div class="main-content">
            <div class="container">
                <h1>Supervision Réseau</h1>
                <h2>Historique des Données</h2>
                <form id="historyForm">
                    <label for="ip">Sélectionner un Switch :</label>
                    <select id="ip" name="ip" required>
                        <option value="">Choisir un switch</option>
                        <?php foreach ($switches as $switch_ip): ?>
                            <option value="<?= htmlspecialchars($switch_ip) ?>">
                                <?= htmlspecialchars($switch_ip) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="submit" value="Afficher">
                </form>

                <div id="message" class="hidden"></div>
                <div id="loading" class="loading hidden">Chargement des données...</div>
                <div id="charts" class="chart-grid hidden"></div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            let charts = {};
            let intervalId = null;

            function fetchHistory(ip) {
                $('#loading').removeClass('hidden');
                $('#message').addClass('hidden');
                $('#charts').addClass('hidden').empty();

                $.ajax({
                    url: 'collect_snmp_endpoint.php',
                    type: 'POST',
                    data: { ip: ip },
                    dataType: 'json',
                    success: function(response) {
                        $('#loading').addClass('hidden');
                        if (response.success) {
                            $('#charts').removeClass('hidden');
                            updateCharts(ip, response.data);
                            $('#message').text(response.message).removeClass('error hidden')
                                .addClass(response.message.includes('Erreur') ? 'error' : 'success');
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

            function updateCharts(ip, ports) {
                const chartIdTraffic = `trafficChart_${ip.replace(/\./g, '_')}`;
                const chartIdErrors = `errorsChart_${ip.replace(/\./g, '_')}`;

                $('#charts').append(`
                    <div class="chart-container">
                        <canvas id="${chartIdTraffic}" style="max-height: 300px;"></canvas>
                    </div>
                    <div class="chart-container">
                        <canvas id="${chartIdErrors}" style="max-height: 300px;"></canvas>
                    </div>
                `);

                const ctxTraffic = document.getElementById(chartIdTraffic).getContext('2d');
                const ctxErrors = document.getElementById(chartIdErrors).getContext('2d');

                const labels = ports.map(port => port.port_name);
                const inOctets = ports.map(port => parseInt(port.in_octets) || 0);
                const outOctets = ports.map(port => parseInt(port.out_octets) || 0);
                const outErrors = ports.map(port => parseInt(port.out_errors) || 0);

                charts[chartIdTraffic] = new Chart(ctxTraffic, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Trafic Entrant (Octets)',
                                data: inOctets,
                                backgroundColor: '#accee2ff',
                                borderColor: '#050d61ff',
                                borderWidth: 2
                            },
                            {
                                label: 'Trafic Sortant (Octets)',
                                data: outOctets,
                                backgroundColor: '#f491a2ff',
                                borderColor: '#e61356ff',
                                borderWidth: 2
                            }
                        ]
                    },
                    options: {
                        plugins: {
                            title: {
                                display: true,
                                text: `Trafic pour ${ip}`,
                                color: '#333',
                                font: { size: 18, family: "'Roboto', sans-serif" }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });

                charts[chartIdErrors] = new Chart(ctxErrors, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Erreurs Sortantes',
                            data: outErrors,
                            backgroundColor: '#ff6b6b',
                            borderColor: '#dc3545',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        plugins: {
                            title: {
                                display: true,
                                text: `Erreurs pour ${ip}`,
                                color: '#333',
                                font: { size: 18, family: "'Roboto', sans-serif" }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }

            $('#historyForm').on('submit', function(e) {
                e.preventDefault();
                const ip = $('#ip').val();
                if (ip) {
                    if (intervalId) clearInterval(intervalId);
                    fetchHistory(ip);
                    intervalId = setInterval(() => fetchHistory(ip), 30000); // Toutes les 30 secondes
                }
            });
        });
    </script>
</body>
</html>