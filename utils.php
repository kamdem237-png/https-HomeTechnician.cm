<?php
// utils.php

/**
 * Fonction pour enregistrer les actions des utilisateurs et des administrateurs dans la base de données.
 * Nécessite une connexion $con établie globalement ou dans la fonction elle-même.
 *
 * @param string $action_type Le type d'action (ex: 'Bannissement Client', 'Suppression Annonce').
 * @param int $target_id L'ID de l'entité ciblée par l'action (client_id, annonce_id, etc.).
 * @param string $target_role Le rôle ou type de l'entité ciblée (ex: 'client', 'annonce', 'technicien').
 * @param string $target_identifier Un identifiant textuel de l'entité (ex: 'jemima (johnw@gmail.com)', 'Titre de l'annonce').
 * @param string $description Une description détaillée de l'action.
 * @param int|null $admin_id L'ID de l'administrateur ayant effectué l'action, si applicable.
 */
function logUserAction($action_type, $target_id, $target_role, $target_identifier, $description, $admin_id = null) {
    global $con; // Accède à la connexion globale établie dans les scripts principaux (ex: admin.php, gest_annonce.php)

    // Si la connexion n'est pas disponible, tente de l'établir (utile si utils.php est appelé de manière isolée)
    if (!isset($con) || $con->connect_error) {
        $server_name = "localhost";
        $user_name = "root";
        $psw = "";
        $DB_name = "depanage";

        $con = new mysqli($server_name, $user_name, $psw, $DB_name);

        if ($con->connect_error) {
            error_log("[".date('Y-m-d H:i:s')."] Erreur FATALE de connexion à la DB pour logUserAction: " . $con->connect_error);
            return; // Arrête l'exécution si la connexion échoue
        }
        $con->set_charset("utf8mb4");
    }

    $stmt = $con->prepare("INSERT INTO action_logs (action_type, target_id, target_role, target_identifier, admin_id, description) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        error_log("[".date('Y-m-d H:i:s')."] Erreur de préparation de la requête de log: " . $con->error);
        return;
    }

    // Gestion de admin_id qui peut être NULL
    if ($admin_id === null) {
        $admin_id_bind = null; // Prépare une variable pour bind_param
        $stmt->bind_param("sisiss", $action_type, $target_id, $target_role, $target_identifier, $admin_id_bind, $description);
    } else {
        $stmt->bind_param("sisiss", $action_type, $target_id, $target_role, $target_identifier, $admin_id, $description);
    }
    
    if (!$stmt->execute()) {
        error_log("[".date('Y-m-d H:i:s')."] Erreur d'exécution du log: " . $stmt->error);
    }
    $stmt->close();
    // Ne ferme PAS la connexion $con ici, car elle est gérée par le script appelant.
}

/**
 * Fonction pour récupérer les annonces visibles selon un rôle spécifique.
 *
 * @param string $role Le rôle pour lequel récupérer les annonces ('client', 'technicien', 'admin').
 * @return array Un tableau d'annonces.
 */
function get_announcements($role) {
    global $con; // Accède à la connexion globale établie dans admin.php

    if (!isset($con) || $con->connect_error) {
        error_log("[".date('Y-m-d H:i:s')."] Erreur: Connexion DB non disponible dans get_announcements.");
        return []; // Retourne un tableau vide si la connexion n'existe pas ou est invalide
    }

    $announcements = [];
    $sql = "SELECT id_annonce, titre, contenu, date_publication FROM annonces WHERE statut_actif = 1";

    // Adapte la requête SQL en fonction du rôle
    switch ($role) {
        case 'client':
            $sql .= " AND visible_client = 1";
            break;
        case 'technicien':
            $sql .= " AND visible_technicien = 1";
            break;
        case 'admin':
            // L'administrateur peut voir toutes les annonces actives
            $sql .= " AND visible_admin = 1"; // Garde cette condition si vous avez une colonne 'visible_admin'
            // Si l'admin doit voir TOUTES les annonces (même non actives), supprimez 'AND statut_actif = 1' et 'AND visible_admin = 1'
            break;
        default:
            return []; // Rôle invalide, retourne un tableau vide
    }

    $sql .= " ORDER BY date_publication DESC";

    $result = $con->query($sql);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $announcements[] = $row;
        }
        $result->free(); // Libère la mémoire associée au résultat
    } else {
        error_log("[".date('Y-m-d H:i:s')."] Erreur de requête dans get_announcements: " . $con->error);
    }

    return $announcements;
}
?>