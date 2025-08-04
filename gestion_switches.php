<?php
session_start();
if (!isset($_SESSION['connecte'])) {
    header("Location: login.php");
    exit();
}

require_once 'db.php';

// Initialisation
$switches = [];
$error = null;

// 🔹 Récupération initiale pour affichage HTML
try {
    $stmt = $conn->query("SELECT * FROM switches ORDER BY id DESC");
    $switches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Erreur : " . $e->getMessage();
}

// 🔹 Traitement AJAX
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    $response = ['success' => false, 'message' => ''];

    try {
        $action = $_POST['action'] ?? '';
        switch ($action) {
            case 'add':
                $ip = filter_var($_POST['ip'] ?? '', FILTER_VALIDATE_IP);
                $name = trim($_POST['name'] ?? '');

                if ($ip && $name) {
                    $stmt = $conn->prepare("INSERT INTO switches (ip, name) VALUES (?, ?)");
                    $stmt->execute([$ip, $name]);
                    $response['success'] = true;
                    $response['message'] = "Switch $ip ajouté.";
                } else {
                    $response['message'] = "IP invalide ou nom manquant.";
                }
                break;

            case 'edit':
                $id = intval($_POST['id'] ?? 0);
                $ip = filter_var($_POST['ip'] ?? '', FILTER_VALIDATE_IP);
                $name = trim($_POST['name'] ?? '');

                if ($id && $ip && $name) {
                    $stmt = $conn->prepare("UPDATE switches SET ip = ?, name = ? WHERE id = ?");
                    $stmt->execute([$ip, $name, $id]);
                    $response['success'] = true;
                    $response['message'] = "Switch $ip modifié.";
                } else {
                    $response['message'] = "Données invalides.";
                }
                break;

            case 'delete':
                $id = intval($_POST['id'] ?? 0);
                if ($id) {
                    $stmt = $conn->prepare("DELETE FROM switches WHERE id = ?");
                    $stmt->execute([$id]);
                    $response['success'] = true;
                    $response['message'] = "Switch supprimé.";
                } else {
                    $response['message'] = "ID manquant.";
                }
                break;

            default:
                $response['message'] = "Action non reconnue.";
        }
    } catch (Exception $e) {
        $response['message'] = "Erreur : " . $e->getMessage();
    }

    echo json_encode($response);
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des Switches - Supervision Réseau</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
<div class="layout-container">
    <nav class="side-menu">
        <a href="accueil.php">Accueil</a>
        <a href="etat_ports.php">État des ports</a>
        <a href="gestion_switches.php" class="active">Gestion des Switches</a>
        <a href="historique.php">Historique</a>
        <a href="alertes.php">Alertes Récentes</a>
        <a href="logout.php">Déconnexion</a>
    </nav>

    <div class="main-content">
        <div class="container">
            <h1>Supervision Réseau</h1>
            <h2>Gestion des Switches</h2>

            <h3>Ajouter un Switch</h3>
            <form id="addSwitchForm">
                <label for="ip">IP :</label>
                <input type="text" id="ip" name="ip" required>
                <label for="name">Nom :</label>
                <input type="text" id="name" name="name" required>
                <input type="hidden" name="action" value="add">
                <input type="submit" value="Ajouter">
            </form>

            <div id="message" class="hidden"></div>
            <h3>Liste des Switches</h3>
            <div id="loading" class="loading hidden">Chargement des switches...</div>
            <table id="switchesTable">
                <thead>
                    <tr>
                        <th>IP</th>
                        <th>Nom</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="switchesBody">
                    <?php foreach ($switches as $switch): ?>
                        <tr data-id="<?= $switch['id'] ?>">
                            <td class="ip"><?= htmlspecialchars($switch['ip']) ?></td>
                            <td class="name"><?= htmlspecialchars($switch['name']) ?></td>
                            <td>
                                <a href="#" class="button edit" onclick="editSwitch(<?= $switch['id'] ?>)">Modifier</a>
                                <a href="#" class="button delete" onclick="deleteSwitch(<?= $switch['id'] ?>)">Supprimer</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    function refreshSwitches() {
        $('#loading').removeClass('hidden');
        $.get('gestion_switches.php', function(data) {
            const parser = new DOMParser();
            const doc = parser.parseFromString(data, 'text/html');
            const newTable = $(doc).find('#switchesBody').html();
            $('#switchesBody').html(newTable);
            $('#loading').addClass('hidden');
        });
    }

    $('#addSwitchForm').on('submit', function(e) {
        e.preventDefault();
        $('#message').addClass('hidden');

        $.post('gestion_switches.php', $(this).serialize(), function(response) {
            $('#message').text(response.message).removeClass('hidden')
                .addClass(response.success ? 'success' : 'error');
            if (response.success) {
                refreshSwitches();
                $('#addSwitchForm')[0].reset();
            }
        }, 'json')
        .fail(function() {
            $('#message').text('Erreur lors de l\'ajout.').removeClass('hidden').addClass('error');
        });
    });

    window.editSwitch = function(id) {
        const row = $(`#switchesTable tr[data-id="${id}"]`);
        const ip = row.find('.ip').text();
        const name = row.find('.name').text();

        const newIp = prompt('Nouvelle IP :', ip);
        const newName = prompt('Nouveau Nom :', name);

        if (newIp && newName) {
            $.post('gestion_switches.php', { action: 'edit', id: id, ip: newIp, name: newName }, function(response) {
                $('#message').text(response.message).removeClass('hidden')
                    .addClass(response.success ? 'success' : 'error');
                if (response.success) refreshSwitches();
            }, 'json')
            .fail(function() {
                $('#message').text('Erreur lors de la modification.').removeClass('hidden').addClass('error');
            });
        }
    };

    window.deleteSwitch = function(id) {
        if (confirm('Voulez-vous vraiment supprimer ce switch ?')) {
            $.post('gestion_switches.php', { action: 'delete', id: id }, function(response) {
                $('#message').text(response.message).removeClass('hidden')
                    .addClass(response.success ? 'success' : 'error');
                if (response.success) refreshSwitches();
            }, 'json')
            .fail(function() {
                $('#message').text('Erreur lors de la suppression.').removeClass('hidden').addClass('error');
            });
        }
    };
});
</script>
</body>
</html>
