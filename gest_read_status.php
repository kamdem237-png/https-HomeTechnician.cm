<?php
session_start();
header('Content-Type: application/json');

$conn = new mysqli("localhost", "root", "", "depanage");

if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed.']);
    exit();
}

$user = $_SESSION['user'] ?? null;
$role = $_SESSION['role'] ?? '';

if (!$user) {
    echo json_encode(['error' => 'User not authenticated.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$message_ids = $input['message_ids'] ?? [];
$id_mission = (int)($input['id_mission'] ?? 0);

if (empty($message_ids) || $id_mission <= 0) {
    echo json_encode(['error' => 'Invalid message IDs or mission ID.']);
    exit();
}

// Ensure the message IDs belong to the current mission and user is authorized
$placeholders = implode(',', array_fill(0, count($message_ids), '?'));
$types = str_repeat('i', count($message_ids)); // For binding message IDs

// First, check if the mission is valid and user is part of it.
$authorized = false;
$user_id_check = ($role === 'client') ? (int)$user['id_client'] : (int)$user['id_technicien'];

if ($role === 'client') {
    $stmt_auth = $conn->prepare("SELECT COUNT(*) FROM mission WHERE id_mission = ? AND id_client = ?");
} elseif ($role === 'technicien') {
    $stmt_auth = $conn->prepare("SELECT COUNT(*) FROM mission_technicien WHERE id_mission = ? AND id_technicien = ?");
} else {
    echo json_encode(['error' => 'Unauthorized role.']);
    exit();
}
$stmt_auth->bind_param("ii", $id_mission, $user_id_check);
$stmt_auth->execute();
$result_auth = $stmt_auth->get_result();
if ($result_auth->fetch_row()[0] > 0) {
    $authorized = true;
}
$stmt_auth->close();

if (!$authorized) {
    echo json_encode(['error' => 'Unauthorized access to mission.']);
    exit();
}


$read_statuses = [];
foreach ($message_ids as $msg_id) {
    // Count how many clients have read the message for this mission
    $stmt_client_read = $conn->prepare("
        SELECT COUNT(DISTINCT ml.id_utilisateur)
        FROM message_lu ml
        JOIN messages m ON ml.id_message = m.id_message
        JOIN mission mi ON m.id_mission = mi.id_mission
        WHERE ml.id_message = ?
        AND ml.type_utilisateur = 'client'
        AND m.id_mission = ?
    ");
    $stmt_client_read->bind_param("ii", $msg_id, $id_mission);
    $stmt_client_read->execute();
    $clients_read = $stmt_client_read->get_result()->fetch_row()[0];
    $stmt_client_read->close();

    // Count how many technicians have read the message for this mission
    $stmt_tech_read = $conn->prepare("
        SELECT COUNT(DISTINCT ml.id_utilisateur)
        FROM message_lu ml
        JOIN messages m ON ml.id_message = m.id_message
        JOIN mission_technicien mt ON m.id_mission = mt.id_mission
        WHERE ml.id_message = ?
        AND ml.type_utilisateur = 'technicien'
        AND m.id_mission = ?
    ");
    $stmt_tech_read->bind_param("ii", $msg_id, $id_mission);
    $stmt_tech_read->execute();
    $technicians_read = $stmt_tech_read->get_result()->fetch_row()[0];
    $stmt_tech_read->close();

    $read_statuses[$msg_id] = [
        'clients' => $clients_read,
        'technicians' => $technicians_read
    ];
}

echo json_encode(['read_by' => $read_statuses]);

$conn->close();
?>