<?php
session_start();

// Inclure le fichier d'utilitaires qui contient la fonction logUserAction
// Assurez-vous que le chemin est correct par rapport à l'emplacement de ce fichier.
require_once 'utils.php'; 

// --- Configuration de la base de données ---
$server_name = "localhost";
$user_name = "root";
$psw = ""; // Vérifiez votre mot de passe ici
$DB_name = "depanage";

// Vérification de l'authentification de l'administrateur

// Vérifier si un ID de client a été passé en paramètre GET
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['message'] = "ID client invalide. Opération annulée.";
    $_SESSION['message_type'] = "danger";
    header('location: gest_client.php'); 
    exit();
}

$id_client_a_modifier = (int)$_GET['id']; 

$con = new mysqli($server_name, $user_name, $psw, $DB_name);

if ($con->connect_error) {
    // Erreur de connexion : loguer et informer l'utilisateur
    error_log("[".date('Y-m-d H:i:s')."] Erreur de connexion à la base de données lors du bannissement client : " . $con->connect_error);
    $_SESSION['message'] = "Erreur de connexion à la base de données. Veuillez réessayer.";
    $_SESSION['message_type'] = "danger";
    header('location: gest_client.php');
    exit();
}
$con->set_charset("utf8mb4");

// Commencer une transaction
$con->begin_transaction();

try {
    // 1. Récupérer les informations du client avant de le modifier pour le log
    $stmt_get_client = $con->prepare("SELECT email, nom, is_active, is_banned FROM client WHERE id_client = ?");
    if (!$stmt_get_client) {
        throw new Exception("Erreur de préparation (SELECT client) : " . $con->error);
    }
    $stmt_get_client->bind_param("i", $id_client_a_modifier);
    $stmt_get_client->execute();
    $result_get_client = $stmt_get_client->get_result();
    $client_info = $result_get_client->fetch_assoc();
    $stmt_get_client->close();

    if (!$client_info) {
        // Le client n'existe pas
        $_SESSION['message'] = "Aucun client trouvé avec l'ID spécifié : **{$id_client_a_modifier}**.";
        $_SESSION['message_type'] = "warning";
        $con->rollback(); 
        header('location: gest_client.php');
        exit();
    }

    $email_client = $client_info['email'];
    $nom_complet_client = $client_info['nom'];
    $current_is_active = $client_info['is_active'];
    $current_is_banned = $client_info['is_banned'];

    // Vérifier si le client est déjà banni pour éviter des mises à jour inutiles
    if ($current_is_active == 0 && $current_is_banned == 1) {
        $_SESSION['message'] = "Le client (ID: **{$id_client_a_modifier}**) est **déjà désactivé et banni**.";
        $_SESSION['message_type'] = "info"; 
        $con->rollback(); 
        header('location: gest_client.php');
        exit();
    }

    // 2. Mettre à jour le statut du client pour le désactiver et le bannir
    $stmt_client = $con->prepare("UPDATE client SET is_active = 0, is_banned = 1 WHERE id_client = ?");
    if (!$stmt_client) {
        throw new Exception("Erreur de préparation (UPDATE client) : " . $con->error);
    }
    $stmt_client->bind_param("i", $id_client_a_modifier);
    $stmt_client->execute();

    if ($stmt_client->affected_rows > 0) {
        $_SESSION['message'] = "Le client (ID: **{$id_client_a_modifier}**) a été **désactivé et banni** avec succès. Il ne pourra plus se connecter ni s'inscrire avec cet email.";
        $_SESSION['message_type'] = "success";

        $admin_id = $_SESSION['id_admin'] ?? 'N/A';
        // Appel de la fonction logUserAction de utils.php
        logUserAction(
            'Bannissement Client', 
            $id_client_a_modifier, 
            'client', 
            $nom_complet_client . ' (' . $email_client . ')',
            "Banni par l'administrateur (ID: {$admin_id})" 
        );

    } else {
        $_SESSION['message'] = "La mise à jour du client (ID: {$id_client_a_modifier}) a échoué pour une raison inconnue (0 ligne affectée).";
        $_SESSION['message_type'] = "danger";
    }
    $stmt_client->close();

    $con->commit(); // Confirmer la transaction

} catch (Exception $e) {
    $con->rollback(); // Annuler la transaction en cas d'erreur
    error_log("[".date('Y-m-d H:i:s')."] Erreur lors du bannissement du client ID {$id_client_a_modifier}: " . $e->getMessage());
    $_SESSION['message'] = "Une erreur est survenue lors du bannissement du client. Veuillez réessayer. Détails techniques : " . $e->getMessage(); 
    $_SESSION['message_type'] = "danger";
}

$con->close();

header('location: gest_client.php'); 
exit();
?>