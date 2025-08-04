<?php
session_start();
session_destroy();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Déconnexion - Supervision Réseau</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>Supervision Réseau</h1>
        <h2>Déconnexion</h2>
        <p>Vous avez été déconnecté.</p>
        <p><a href="login.php">Retour à la connexion</a></p>
    </div>
</body>
</html>