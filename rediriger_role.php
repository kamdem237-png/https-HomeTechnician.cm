<?php
session_start();
if (!isset($_POST['role'])) {
    header("Location: connexion_user.php");
    exit();
}

$role = $_POST['role'];
if ($role === 'client' && isset($_SESSION['client'])) {
    $_SESSION['user'] = $_SESSION['client'];
    $_SESSION['role'] = 'client';
    header("Location: client.php");
    exit();
} elseif ($role === 'technicien' && isset($_SESSION['technicien'])) {
    $_SESSION['user'] = $_SESSION['technicien'];
    $_SESSION['role'] = 'technicien';
    header("Location: technicien.php");
    exit();
} else {
    header("Location: connexion_user.php");
    exit();
}
?>

