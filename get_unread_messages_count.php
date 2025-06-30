<?php
session_start();
header('Content-Type: application/json'); // Indique que la réponse est du JSON

$conn = new mysqli("localhost", "root", "", "depanage");

if ($conn->connect_error) {
    error_log("Erreur de connexion à la base de données : " . $conn->connect_error);
    echo json_encode(['error' => 'Erreur de connexion à la base de données.']);
    exit();
}

$user = $_SESSION['user'] ?? null;
if (!$user) {
    echo json_encode(['error' => 'Non authentifié.']);
    exit();
}

$user_id = $user['id'];
$user_role = $user['role'];
$unread_counts = []; // Tableau pour stocker les comptes de messages non lus par mission

if ($user_role === 'client') {
    // Pour un client : compter les messages du technicien qui sont plus récents que la dernière lecture du client
    $sql = "SELECT m.id_mission, COUNT(c.id_chat) AS unread_count
            FROM mission m
            JOIN chat c ON m.id_mission = c.id_mission
            WHERE m.id_client = ?
            AND c.id_technicien IS NOT NULL -- Message envoyé par un technicien
            AND c.id_chat > COALESCE(m.last_read_message_id_client, 0) -- Plus récent que le dernier lu par ce client
            GROUP BY m.id_mission";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $unread_counts[$row['id_mission']] = (int)$row['unread_count'];
        }
        $stmt->close();
    } else {
        error_log("Erreur de préparation client : " . $conn->error);
    }
} elseif ($user_role === 'technicien') {
    // Pour un technicien : compter les messages du client qui sont plus récents que la dernière lecture du technicien
    // Cela suppose que 'mission_technicien' est la table de liaison si un technicien a plusieurs missions
    $sql = "SELECT mt.id_mission, COUNT(c.id_chat) AS unread_count
            FROM mission_technicien mt
            JOIN mission m ON mt.id_mission = m.id_mission
            JOIN chat c ON mt.id_mission = c.id_mission
            WHERE mt.id_technicien = ?
            AND c.id_client IS NOT NULL -- Message envoyé par un client
            AND c.id_chat > COALESCE(m.last_read_message_id_technicien, 0) -- Plus récent que le dernier lu par ce technicien
            GROUP BY mt.id_mission";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $unread_counts[$row['id_mission']] = (int)$row['unread_count'];
        }
        $stmt->close();
    } else {
        error_log("Erreur de préparation technicien : " . $conn->error);
    }
}

$conn->close();
echo json_encode($unread_counts); // Retourne le JSON
?>