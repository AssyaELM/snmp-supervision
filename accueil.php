<?php
session_start();
if (!isset($_SESSION['connecte'])) {
    header("Location: login.php");
    exit();
}

require_once 'db.php';

try {
    $stmt = $conn->query("SELECT ip FROM switches");
    $ips = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $error = "Erreur : " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Accueil - Supervision Réseau</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="layout-container">
        <nav class="side-menu">
            <a href="accueil.php" class="active">Accueil</a>
            <a href="etat_ports.php">État des ports</a>
            <a href="gestion_switches.php">Gestion des Switches</a>
            <a href="historique.php">Historique</a>
            <a href="alertes.php">Alertes Récentes</a>
            <a href="logout.php">Déconnexion</a>
        </nav>
        <div class="main-content">
            <h1>Supervision Réseau</h1>
            <h2>Bienvenue, Administrateur !</h2>

            <h3>Alertes Récentes (Ports DOWN)</h3>
            <div id="alerts" class="alerts">
                <div id="loadingAlerts" class="loading hidden">Chargement des alertes...</div>
                <table id="alertsTable" class="hidden">
                    <thead>
                        <tr>
                            <th>IP du Switch</th>
                            <th>Port</th>
                            <th>Nom</th>
                            <th>Date/Heure</th>
                        </tr>
                    </thead>
                    <tbody id="alertsBody"></tbody>
                </table>
            </div>

            <h3>Répartition des Ports par Switch</h3>
            <div id="loadingCharts" class="loading hidden">Chargement des graphiques...</div>
            <div class="chart-grid" id="chartGrid"></div>

            <h3>Tableau de bord</h3>
            <div id="loadingDashboard" class="loading hidden">Chargement du tableau de bord...</div>
            <div class="dashboard" id="dashboard"></div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            const ips = <?= json_encode($ips) ?>;
            let charts = {};

            function fetchData() {
                $('#loadingAlerts').removeClass('hidden');
                $('#loadingCharts').removeClass('hidden');
                $('#loadingDashboard').removeClass('hidden');
                $('#alertsTable').addClass('hidden');
                $('#chartGrid').empty();
                $('#dashboard').empty();

                ips.forEach(ip => {
                    $.ajax({
                        url: 'collect_snmp_endpoint.php',
                        type: 'POST',
                        data: { ip: ip },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                updateDashboard(ip, response.data);
                                updateCharts(ip, response.data);
                            }
                        }
                    });
                });

                $.ajax({
                    url: 'alertes.php',
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        $('#loadingAlerts').addClass('hidden');
                        if (response.alerts.length > 0) {
                            $('#alertsTable').removeClass('hidden');
                            $('#alertsBody').empty();
                            response.alerts.forEach(alert => {
                                $('#alertsBody').append(`
                                    <tr>
                                        <td>${alert.ip}</td>
                                        <td>${alert.port_id || 'N/A'}</td>
                                        <td>${alert.port_name || 'N/A'}</td>
                                        <td>${alert.alert_time}</td>
                                    </tr>
                                `);
                            });
                        } else {
                            $('#alerts').append('<p class="no-alert">Aucune alerte pour le moment. Tout va bien !</p>');
                        }
                    }
                });
            }

            function updateDashboard(ip, ports) {
                let up_count = 0, down_count = 0, max_date = null, temperature = 'N/A', down_ports = [];
                ports.forEach(port => {
                    if (port.status === 'Up') up_count++;
                    else if (port.status === 'Down') {
                        down_count++;
                        down_ports.push(port.port_name);
                    }
                    if (!max_date || new Date() > new Date(max_date)) max_date = new Date().toLocaleString();
                    if (port.temperature !== 'N/A') temperature = port.temperature;
                });

                $('#dashboard').append(`
                    <div class="switch-card">
                        <h4>Switch : ${ip}</h4>
                        <p>Dernière vérification : ${max_date}</p>
                        <p>Ports UP : <span class="status-up">${up_count}</span></p>
                        <p>Ports DOWN : <span class="status-down">${down_count}</span></p>
                        <p>Température : ${temperature}</p>
                    </div>
                `);
                $('#loadingDashboard').addClass('hidden');
            }

            function updateCharts(ip, ports) {
                let up_count = 0, down_count = 0;
                ports.forEach(port => {
                    if (port.status === 'Up') up_count++;
                    else if (port.status === 'Down') down_count++;
                });

                const chartId = `statusChart_${ip.replace(/\./g, '_')}`;
                $('#chartGrid').append(`
                    <div class="chart-container">
                        <canvas id="${chartId}" style="max-height: 300px;"></canvas>
                    </div>
                `);

                const ctx = document.getElementById(chartId).getContext('2d');
                charts[chartId] = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Ports UP', 'Ports DOWN'],
                        datasets: [{
                            label: 'Statut des Ports',
                            data: [up_count, down_count],
                            backgroundColor: ['#accee2ff', '#f491a2ff'],
                            borderColor: ['#050d61ff', '#e61356ff'],
                            borderWidth: 2
                        }]
                    },
                    options: {
                        plugins: {
                            title: {
                                display: true,
                                text: `Répartition des Ports pour ${ip}`,
                                color: '#333',
                                font: { size: 18, family: "'Roboto', sans-serif" }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.label || '';
                                        if (label === 'Ports DOWN' && down_count > 0) {
                                            return label + ': ' + <?= json_encode($down_ports ?? []) ?>.join(', ');
                                        }
                                        return label + ': ' + context.raw;
                                    }
                                }
                            }
                        }
                    }
                });
                $('#loadingCharts').addClass('hidden');
            }

            fetchData();
            setInterval(fetchData, 30000); // Toutes les 30 secondes
        });
    </script>
</body>
</html>