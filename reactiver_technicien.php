<?php
session_start();

// Inclure le système de log pour enregistrer les actions importantes
require_once 'utils.php'; 

// Vérification de l'authentification de l'administrateur
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || ($_SESSION['role'] ?? '') !== 'admin') {
    header('location: connexion_admin.php');
    exit();
}

// Vérifier si un ID de technicien a été passé en paramètre GET
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['message'] = "ID technicien invalide. Opération annulée.";
    $_SESSION['message_type'] = "danger";
    header('location: gest_technicien.php');
    exit();
}

$id_technicien_a_modifier = (int)$_GET['id'];

$server_name = "localhost";
$user_name = "root";
$psw = ""; // Remplacez par votre mot de passe si vous en avez un
$DB_name = "depanage";

$con = new mysqli($server_name, $user_name, $psw, $DB_name);

if ($con->connect_error) {
    error_log("Erreur de connexion à la base de données lors de la réactivation technicien : " . $con->connect_error);
    $_SESSION['message'] = "Erreur de connexion à la base de données. Veuillez réessayer.";
    $_SESSION['message_type'] = "danger";
    header('location: gest_technicien.php');
    exit();
}

// Définir l'encodage
$con->set_charset("utf8mb4");

// Commencer une transaction pour s'assurer de la cohérence
$con->begin_transaction();

try {
    // 1. Récupérer les informations du technicien avant de le modifier pour le log
    $stmt_get_tech = $con->prepare("SELECT email, nom, prenom FROM technicien WHERE id_technicien = ?");
    if (!$stmt_get_tech) {
        throw new Exception("Erreur de préparation pour récupérer le technicien : " . $con->error);
    }
    $stmt_get_tech->bind_param("i", $id_technicien_a_modifier);
    $stmt_get_tech->execute();
    $result_get_tech = $stmt_get_tech->get_result();
    $tech_info = $result_get_tech->fetch_assoc();
    $stmt_get_tech->close();

    if (!$tech_info) {
        $_SESSION['message'] = "Aucun technicien trouvé avec l'ID spécifié.";
        $_SESSION['message_type'] = "warning";
        $con->rollback();
        header('location: gest_technicien.php');
        exit();
    }

    $email_technicien = $tech_info['email'];
    $nom_complet_technicien = $tech_info['prenom'] . ' ' . $tech_info['nom'];

    // 2. Mettre à jour le statut du technicien pour le réactiver et le débannir
    // is_active à 1 pour le rendre actif, is_banned à 0 pour le débannir.
    // Vous pouvez choisir de remettre en quarantaine ici si c'était un bannissement temporaire
    // ou de simplement le rendre actif. Ici, on le rend complètement actif.
    $stmt = $con->prepare("UPDATE technicien SET is_active = 1, is_banned = 0 WHERE id_technicien = ?");

    if (!$stmt) {
        throw new Exception("Erreur de préparation de la requête de réactivation du technicien : " . $con->error);
    }
    $stmt->bind_param("i", $id_technicien_a_modifier);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $_SESSION['message'] = "Le technicien (ID: **{$id_technicien_a_modifier}**) a été **réactivé et débanni** avec succès. Il peut maintenant se connecter à nouveau.";
        $_SESSION['message_type'] = "success";

        // Enregistrement de l'action dans le log
        $admin_id = $_SESSION['id_admin'] ?? 'N/A';
        logUserAction(
            'Réactivation Technicien', 
            $id_technicien_a_modifier, 
            'technicien', 
            $nom_complet_technicien . ' (' . $email_technicien . ')',
            "Réactivé et débanni par l'administrateur (ID: {$admin_id})"
        );

    } else {
        $_SESSION['message'] = "Aucun technicien trouvé avec l'ID **{$id_technicien_a_modifier}** ou il était déjà actif/non banni.";
        $_SESSION['message_type'] = "warning";
    }
    $stmt->close();

    $con->commit(); // Confirmer la transaction

} catch (Exception $e) {
    $con->rollback(); // Annuler la transaction en cas d'erreur
    error_log("Erreur lors de la réactivation du technicien ID {$id_technicien_a_modifier}: " . $e->getMessage());
    $_SESSION['message'] = "Une erreur est survenue lors de la réactivation du technicien. Veuillez réessayer.";
    $_SESSION['message_type'] = "danger";
}

$con->close();

header('location: gest_technicien.php'); // Rediriger vers la page de gestion des techniciens
exit();
?>