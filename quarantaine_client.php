<?php
session_start();

// --- Vérification des autorisations ---
// Assurez-vous que seul un administrateur authentifié peut accéder à ce script.
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || ($_SESSION['role'] ?? '') !== 'admin') {
    header('location: connexion_admin.php'); // Redirigez vers la page de connexion admin
    exit();
}

// Paramètres de connexion à la base de données
$server_name = "localhost";
$user_name = "root";
$psw = ""; // Ton mot de passe MySQL
$DB_name = "depanage";

// Connexion à la base de données
$conn = new mysqli($server_name, $user_name, $psw, $DB_name);

// Vérification de la connexion
if ($conn->connect_error) {
    error_log("Erreur de connexion à la base de données : " . $conn->connect_error);
    $_SESSION['message'] = "Désolé, une erreur technique est survenue. Veuillez réessayer plus tard.";
    $_SESSION['message_type'] = "danger";
    header('location: gest_client.php'); // Rediriger même en cas d'erreur DB pour afficher le message
    exit();
}

// Définir l'encodage
$conn->set_charset("utf8mb4");

// --- Délai de quarantaine par défaut (15 jours) ---
$quarantine_duration_days = 15;

// --- Traitement des actions de quarantaine ---
if (isset($_GET['action']) && isset($_GET['id'])) {
    $client_id = (int)$_GET['id']; // Assurer que l'ID est un entier

    if ($client_id <= 0) {
        $_SESSION['message'] = "ID client invalide.";
        $_SESSION['message_type'] = "danger";
    } else {
        if ($_GET['action'] === 'quarantine') {
            // Calcul de la date de fin de quarantaine
            $fin_quarantaine = date('Y-m-d H:i:s', strtotime("+" . $quarantine_duration_days . " days"));

            $stmt = $conn->prepare("UPDATE client SET en_quarantaine = 1, fin_quarantaine = ? WHERE id_client = ?");
            if ($stmt) {
                $stmt->bind_param("si", $fin_quarantaine, $client_id);
                if ($stmt->execute()) {
                    $_SESSION['message'] = "Le client avec l'ID " . $client_id . " a été mis en quarantaine pour " . $quarantine_duration_days . " jours.";
                    $_SESSION['message_type'] = "success";
                } else {
                    $_SESSION['message'] = "Erreur lors de la mise en quarantaine du client : " . $stmt->error;
                    $_SESSION['message_type'] = "danger";
                    error_log("Erreur d'exécution de la mise en quarantaine client: " . $stmt->error);
                }
                $stmt->close();
            } else {
                $_SESSION['message'] = "Erreur de préparation de la requête de quarantaine : " . $conn->error;
                $_SESSION['message_type'] = "danger";
                error_log("Erreur de préparation de la quarantaine client: " . $conn->error);
            }
        } elseif ($_GET['action'] === 'unquarantine') {
            $stmt = $conn->prepare("UPDATE client SET en_quarantaine = 0, fin_quarantaine = NULL WHERE id_client = ?");
            if ($stmt) {
                $stmt->bind_param("i", $client_id);
                if ($stmt->execute()) {
                    $_SESSION['message'] = "La quarantaine du client avec l'ID " . $client_id . " a été levée.";
                    $_SESSION['message_type'] = "success";
                } else {
                    $_SESSION['message'] = "Erreur lors de la levée de la quarantaine du client : " . $stmt->error;
                    $_SESSION['message_type'] = "danger";
                    error_log("Erreur d'exécution de la levée de quarantaine client: " . $stmt->error);
                }
                $stmt->close();
            } else {
                $_SESSION['message'] = "Erreur de préparation de la requête de dé-quarantaine : " . $conn->error;
                $_SESSION['message_type'] = "danger";
                error_log("Erreur de préparation de la levée de quarantaine client: " . $conn->error);
            }
        } else {
            $_SESSION['message'] = "Action non reconnue.";
            $_SESSION['message_type'] = "danger";
        }
    }
} else {
    $_SESSION['message'] = "Aucune action ou ID client spécifié.";
    $_SESSION['message_type'] = "info";
}

$conn->close();

// Redirige toujours vers la page de gestion des clients après l'action
header('location: gest_client.php');
exit();
?>