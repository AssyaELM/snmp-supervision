<?php
session_start();
require_once 'db.php';

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['lockout_time'] = 0;
}

if ($_SESSION['login_attempts'] >= 3 && time() - $_SESSION['lockout_time'] < 300) {
    echo "<div class='container'><p class='error'>Trop de tentatives. Réessayez dans 5 minutes.</p>";
    echo '<p><a href="login.php">Retour</a></p></div>';
    exit();
}

if ($username === '' || $password === '') {
    $_SESSION['login_attempts']++;
    echo "<div class='container'><p class='error'>Veuillez remplir tous les champs.</p>";
    echo '<p><a href="login.php">Retour</a></p></div>';
    exit();
}

$stmt = $conn->prepare("SELECT * FROM utilisateurs WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password'])) {
    $_SESSION['connecte'] = true;
    $_SESSION['login_attempts'] = 0;
    $_SESSION['lockout_time'] = 0;
    header("Location: accueil.php");
    exit();
} else {
    $_SESSION['login_attempts']++;
    if ($_SESSION['login_attempts'] >= 3) {
        $_SESSION['lockout_time'] = time();
    }
    echo "<div class='container'><p class='error'>Identifiants incorrects.</p>";
    echo '<p><a href="login.php">Retour</a></p></div>';
}
?>