<?php
session_start();

// Inclure le fichier d'utilitaires qui contient la fonction logUserAction
require_once 'utils.php'; 

// --- Configuration de la base de données ---
$server_name = "localhost";
$user_name = "root";
$psw = ""; // Vérifiez votre mot de passe ici
$DB_name = "depanage";

// Vérification de l'authentification de l'administrateur
// Si vous avez un système d'authentification admin, assurez-vous qu'il est en place ici.
// Exemple :
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['message'] = "Accès non autorisé.";
    $_SESSION['message_type'] = "danger";
    header('location: connexion_admin.php'); // Rediriger vers la page de connexion admin
    exit();
}

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
    error_log("[".date('Y-m-d H:i:s')."] Erreur de connexion à la base de données lors de la modification de statut client : " . $con->connect_error);
    $_SESSION['message'] = "Erreur de connexion à la base de données. Veuillez réessayer.";
    $_SESSION['message_type'] = "danger";
    header('location: gest_client.php');
    exit();
}
$con->set_charset("utf8mb4");

// Commencer une transaction
$con->begin_transaction();

try {
    // 1. Récupérer les informations actuelles du client
    $stmt_get_client = $con->prepare("SELECT email, nom, is_active, is_banned FROM client WHERE id_client = ? FOR UPDATE"); // FOR UPDATE pour verrouiller la ligne
    if (!$stmt_get_client) {
        throw new Exception("Erreur de préparation (SELECT client) : " . $con->error);
    }
    $stmt_get_client->bind_param("i", $id_client_a_modifier);
    $stmt_get_client->execute();
    $result_get_client = $stmt_get_client->get_result();
    $client_info = $result_get_client->fetch_assoc();
    $stmt_get_client->close();

    if (!$client_info) {
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

    $new_is_active = $current_is_active;
    $new_is_banned = $current_is_banned;
    $action_description = "";
    $log_action_type = "";

    // Déterminer la nouvelle action : Bannir ou Débannir
    if ($current_is_active == 1 && $current_is_banned == 0) {
        // Le client est actuellement actif, le bannir
        $new_is_active = 0;
        $new_is_banned = 1;
        $action_description = "désactivé et banni";
        $log_action_type = 'Bannissement Client';
        $_SESSION['message'] = "Le client (ID: **{$id_client_a_modifier}**) a été **désactivé et banni** avec succès. Il ne pourra plus se connecter.";
        $_SESSION['message_type'] = "success";
    } elseif ($current_is_active == 0 && $current_is_banned == 1) {
        // Le client est actuellement banni/désactivé, le réactiver (débannir)
        $new_is_active = 1;
        $new_is_banned = 0;
        $action_description = "réactivé (débanni)";
        $log_action_type = 'Débannissement Client';
        $_SESSION['message'] = "Le client (ID: **{$id_client_a_modifier}**) a été **réactivé** avec succès. Il peut de nouveau se connecter.";
        $_SESSION['message_type'] = "success";
    } else {
        // Cas inattendu (par exemple, is_active=0 et is_banned=0), ou statut déjà en quarantaine qui doit être géré séparément
        $_SESSION['message'] = "Le statut actuel du client (ID: **{$id_client_a_modifier}**) ne permet pas de basculer son statut de bannissement/réactivation de manière standard.";
        $_SESSION['message_type'] = "warning";
        $con->rollback(); 
        header('location: gest_client.php');
        exit();
    }

    // 2. Mettre à jour le statut du client
    $stmt_update_client = $con->prepare("UPDATE client SET is_active = ?, is_banned = ? WHERE id_client = ?");
    if (!$stmt_update_client) {
        throw new Exception("Erreur de préparation (UPDATE client status) : " . $con->error);
    }
    $stmt_update_client->bind_param("iii", $new_is_active, $new_is_banned, $id_client_a_modifier);
    $stmt_update_client->execute();

    if ($stmt_update_client->affected_rows === 0) {
        // Si 0 lignes affectées, cela peut signifier que les statuts étaient déjà à la valeur cible (ex: tenter de bannir un client déjà banni)
        // Ou une erreur si affected_rows n'est pas ce que l'on attend après l'update.
        // On gère spécifiquement le cas où l'état était déjà le même.
        $_SESSION['message'] = "Le statut du client (ID: **{$id_client_a_modifier}**) n'a pas été modifié car il était déjà dans l'état souhaité.";
        $_SESSION['message_type'] = "info";
    } else {
        // Seulement si des lignes ont été affectées, nous journalisons l'action
        $admin_id = $_SESSION['id_admin'] ?? 'N/A';
        logUserAction(
            $log_action_type, 
            $id_client_a_modifier, 
            'client', 
            $nom_complet_client . ' (' . $email_client . ')',
            "Statut {$action_description} par l'administrateur (ID: {$admin_id})" 
        );
    }
    $stmt_update_client->close();

    $con->commit(); // Confirmer la transaction

} catch (Exception $e) {
    $con->rollback(); // Annuler la transaction en cas d'erreur
    error_log("[".date('Y-m-d H:i:s')."] Erreur lors de la modification de statut du client ID {$id_client_a_modifier}: " . $e->getMessage());
    $_SESSION['message'] = "Une erreur est survenue lors de la modification du statut du client. Veuillez réessayer. Détails techniques : " . $e->getMessage(); 
    $_SESSION['message_type'] = "danger";
}

$con->close();

header('location: gest_client.php'); 
exit();
?>