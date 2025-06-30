<?php
session_start();

// Inclure le système de log pour enregistrer les actions importantes
require_once 'utils.php'; 

// Vérification de l'authentification de l'administrateur
// Assurez-vous que $_SESSION['id_admin'] est défini lors de la connexion de l'administrateur.
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || ($_SESSION['role'] ?? '') !== 'admin') {
    header('location: connexion_admin.php');
    exit();
}

// Vérifier si un ID de technicien a été passé en paramètre GET
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['message'] = "ID technicien invalide. Opération annulée.";
    $_SESSION['message_type'] = "danger";
    header('location: gest_technicien.php'); // Redirection vers la page de gestion des techniciens
    exit();
}

$id_technicien_a_modifier = (int)$_GET['id']; // Renommé pour plus de clarté

$server_name = "localhost";
$user_name = "root";
$psw = ""; // Remplacez par votre mot de passe si vous en avez un
$DB_name = "depanage";

$con = new mysqli($server_name, $user_name, $psw, $DB_name);

if ($con->connect_error) {
    error_log("Erreur de connexion à la base de données lors du bannissement technicien : " . $con->connect_error);
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
        $con->rollback(); // Annuler la transaction si le technicien n'existe pas
        header('location: gest_technicien.php');
        exit();
    }

    $email_technicien = $tech_info['email'];
    $nom_complet_technicien = $tech_info['prenom'] . ' ' . $tech_info['nom'];

    // 2. Mettre à jour le statut du technicien pour le désactiver et le bannir
    // Nous mettons is_active à 0 et is_banned à 1 pour un bannissement permanent.
    // Les champs de quarantaine (en_quarantaine, fin_quarantaine) sont réinitialisés car le compte est banni.
    $stmt = $con->prepare("UPDATE technicien SET is_active = 0, is_banned = 1, en_quarantaine = 0, fin_quarantaine = NULL WHERE id_technicien = ?");

    if (!$stmt) {
        throw new Exception("Erreur de préparation de la requête de bannissement du technicien : " . $con->error);
    }
    $stmt->bind_param("i", $id_technicien_a_modifier);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $_SESSION['message'] = "Le technicien (ID: **{$id_technicien_a_modifier}**) a été **désactivé et banni** avec succès. Il ne pourra plus se connecter avec ce compte.";
        $_SESSION['message_type'] = "success";

        // Enregistrement de l'action dans le log
        $admin_id = $_SESSION['id_admin'] ?? 'N/A';
        logUserAction(
            'Bannissement Technicien', 
            $id_technicien_a_modifier, 
            'technicien', 
            $nom_complet_technicien . ' (' . $email_technicien . ')',
            "Banni par l'administrateur (ID: {$admin_id})"
        );

    } else {
        $_SESSION['message'] = "Aucun technicien trouvé avec l'ID **{$id_technicien_a_modifier}** ou il était déjà désactivé/banni.";
        $_SESSION['message_type'] = "warning";
    }
    $stmt->close();

    $con->commit(); // Confirmer la transaction

} catch (Exception $e) {
    $con->rollback(); // Annuler la transaction en cas d'erreur
    error_log("Erreur lors du bannissement du technicien ID {$id_technicien_a_modifier}: " . $e->getMessage());
    $_SESSION['message'] = "Une erreur est survenue lors du bannissement du technicien. Veuillez réessayer.";
    $_SESSION['message_type'] = "danger";
}

$con->close();

header('location: gest_technicien.php'); // Rediriger vers la page de gestion des techniciens
exit();
?>