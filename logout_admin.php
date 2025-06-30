<?php
session_start(); // DOIT ÊTRE LA PREMIÈRE LIGNE

// Détruit toutes les variables de session
$_SESSION = array();

// Efface le cookie de session (important pour la déconnexion complète)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Détruit la session sur le serveur
session_destroy();

// Rediriger vers la page de connexion générale ou la page d'accueil
header("Location: connexion_admin.php"); // Ou connexion_user.php, ou connexion_admin.php
exit();
?>