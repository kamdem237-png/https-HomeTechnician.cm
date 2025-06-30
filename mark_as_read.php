<?php
session_start();
header('Content-Type: application/json'); // Indique que la réponse est du JSON

$conn = new mysqli("localhost", "root", "", "depanage");

if ($conn->connect_error) {
    error_log("Erreur de connexion à la base de données : " . $conn->connect_error);
    echo json_encode(['success' => false, 'error' => 'Erreur de connexion à la base de données.']);
    exit();
}

$user = $_SESSION['user'] ?? null;
if (!$user) {
    echo json_encode(['success' => false, 'error' => 'Non authentifié.']);
    exit();
}

$id_mission = (int)($_GET['id_mission'] ?? 0); // L'ID de la mission est passé via l'URL
$user_id = $user['id'];
$user_role = $user['role'];

if ($id_mission <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de mission invalide.']);
    exit();
}

// 1. Récupérer l'ID du dernier message de cette mission
$max_chat_id = 0;
$stmt_last_msg = $conn->prepare("SELECT MAX(id_chat) AS max_id FROM chat WHERE id_mission = ?");
if ($stmt_last_msg) {
    $stmt_last_msg->bind_param("i", $id_mission);
    $stmt_last_msg->execute();
    $result = $stmt_last_msg->get_result();
    $row = $result->fetch_assoc();
    $max_chat_id = (int)($row['max_id'] ?? 0); // Si aucun message, max_id sera 0
    $stmt_last_msg->close();
} else {
    error_log("Erreur de préparation de la recherche du dernier message : " . $conn->error);
    echo json_encode(['success' => false, 'error' => 'Erreur interne lors de la recherche du dernier message.']);
    exit();
}

if ($max_chat_id > 0) {
    $update_column = '';
    $sql_update = '';

    if ($user_role === 'client') {
        $update_column = 'last_read_message_id_client';
        $sql_update = "UPDATE mission SET {$update_column} = ? WHERE id_mission = ? AND id_client = ?";
    } elseif ($user_role === 'technicien') {
        $update_column = 'last_read_message_id_technicien';
        // Si un technicien peut être assigné à plusieurs missions via `mission_technicien`
        $sql_update = "UPDATE mission m
                       JOIN mission_technicien mt ON m.id_mission = mt.id_mission
                       SET m.{$update_column} = ?
                       WHERE mt.id_mission = ? AND mt.id_technicien = ?";
    }

    if (!empty($sql_update)) {
        $stmt_update = $conn->prepare($sql_update);
        if ($stmt_update) {
            $stmt_update->bind_param("iii", $max_chat_id, $id_mission, $user_id);
            if ($stmt_update->execute()) {
                echo json_encode(['success' => true, 'message' => 'Messages marqués comme lus.']);
            } else {
                error_log("Erreur d'exécution de la mise à jour 'lu' : " . $stmt_update->error);
                echo json_encode(['success' => false, 'error' => 'Échec de la mise à jour du statut de lecture.']);
            }
            $stmt_update->close();
        } else {
            error_log("Erreur de préparation de la mise à jour 'lu' : " . $conn->error);
            echo json_encode(['success' => false, 'error' => 'Erreur interne lors de la préparation de la mise à jour.']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Rôle non géré pour la mise à jour de lecture.']);
    }
} else {
    echo json_encode(['success' => true, 'message' => 'Aucun message à marquer comme lu.']);
}

$conn->close();
?>