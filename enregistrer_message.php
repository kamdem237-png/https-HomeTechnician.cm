<?php
session_start();

$conn = new mysqli("localhost", "root", "", "depanage");

if ($conn->connect_error) {
    error_log("Erreur de connexion à la base de données : " . $conn->connect_error);
    die("Désolé, une erreur technique est survenue. Veuillez réessayer plus tard.");
}

$id_mission = (int)($_POST['id_mission'] ?? 0);
$message_content = trim($_POST['message'] ?? '');
$user = $_SESSION['user'] ?? null;
$role = $_SESSION['role'] ?? '';
$user_id = 0;

if (!$user || $id_mission <= 0 || empty($message_content)) {
    header("Location: chat.php?id_mission=" . $id_mission . "&error=invalid_data");
    exit();
}

if ($role === 'client') {
    $user_id = (int)$user['id_client'];

    // Récupérer tous les techniciens assignés à cette mission
    $stmt_get_technicians = $conn->prepare("SELECT id_technicien FROM mission_technicien WHERE id_mission = ?");
    if (!$stmt_get_technicians) {
        error_log("Erreur de préparation de la requête (récupération techniciens) : " . $conn->error);
        die("Erreur interne lors de l'envoi du message.");
    }
    $stmt_get_technicians->bind_param("i", $id_mission);
    $stmt_get_technicians->execute();
    $assigned_technicians_result = $stmt_get_technicians->get_result();

    if ($assigned_technicians_result->num_rows === 0) {
        header("Location: chat.php?id_mission=" . $id_mission . "&error=no_technician_assigned");
        exit();
    }

    // Insérer un message séparé pour chaque technicien assigné (client vers technicien)
    while ($tech_row = $assigned_technicians_result->fetch_assoc()) {
        $receiver_technician_id = (int)$tech_row['id_technicien'];
        $stmt_insert = $conn->prepare("INSERT INTO chat (id_mission, id_client, id_receiver_technicien, message, date, lu) VALUES (?, ?, ?, ?, NOW(), FALSE)");
        if (!$stmt_insert) {
            error_log("Erreur de préparation de la requête (insertion message client) : " . $conn->error);
            continue;
        }
        $stmt_insert->bind_param("iiis", $id_mission, $user_id, $receiver_technician_id, $message_content);
        if (!$stmt_insert->execute()) {
            error_log("Erreur lors de l'insertion du message pour le technicien " . $receiver_technician_id . " : " . $stmt_insert->error);
        }
        $stmt_insert->close();
    }
    $stmt_get_technicians->close();

} elseif ($role === 'technicien') {
    $user_id = (int)$user['id_technicien'];

    // 1. Envoyer le message au client de la mission
    $stmt_get_client = $conn->prepare("SELECT id_client FROM mission WHERE id_mission = ?");
    if (!$stmt_get_client) {
        error_log("Erreur de préparation de la requête (récupération client) : " . $conn->error);
        die("Erreur interne lors de l'envoi du message.");
    }
    $stmt_get_client->bind_param("i", $id_mission);
    $stmt_get_client->execute();
    $client_result = $stmt_get_client->get_result()->fetch_assoc();
    $id_receiver_client = (int)$client_result['id_client'];
    $stmt_get_client->close();

    $stmt_insert_client = $conn->prepare("INSERT INTO chat (id_mission, id_technicien, id_receiver_client, message, date, lu) VALUES (?, ?, ?, ?, NOW(), FALSE)");
    if (!$stmt_insert_client) {
        error_log("Erreur de préparation de la requête (insertion message technicien vers client) : " . $conn->error);
        die("Erreur interne lors de l'envoi du message.");
    }
    $stmt_insert_client->bind_param("iiis", $id_mission, $user_id, $id_receiver_client, $message_content);
    if (!$stmt_insert_client->execute()) {
        error_log("Erreur lors de l'insertion du message pour le client : " . $stmt_insert_client->error);
    }
    $stmt_insert_client->close();

    // 2. Envoyer le message aux AUTRES techniciens assignés à la même mission
    $stmt_get_other_technicians = $conn->prepare("SELECT id_technicien FROM mission_technicien WHERE id_mission = ? AND id_technicien != ?");
    if (!$stmt_get_other_technicians) {
        error_log("Erreur de préparation de la requête (récupération autres techniciens) : " . $conn->error);
        die("Erreur interne lors de l'envoi du message.");
    }
    $stmt_get_other_technicians->bind_param("ii", $id_mission, $user_id);
    $stmt_get_other_technicians->execute();
    $other_technicians_result = $stmt_get_other_technicians->get_result();

    while ($other_tech_row = $other_technicians_result->fetch_assoc()) {
        $receiver_other_technician_id = (int)$other_tech_row['id_technicien'];
        // Note: id_client et id_receiver_client seront NULL pour un message technicien-à-technicien
        $stmt_insert_tech = $conn->prepare("INSERT INTO chat (id_mission, id_technicien, id_receiver_technicien, message, date, lu) VALUES (?, ?, ?, ?, NOW(), FALSE)");
        if (!$stmt_insert_tech) {
            error_log("Erreur de préparation de la requête (insertion message technicien vers autre technicien) : " . $conn->error);
            continue;
        }
        $stmt_insert_tech->bind_param("iiis", $id_mission, $user_id, $receiver_other_technician_id, $message_content);
        if (!$stmt_insert_tech->execute()) {
            error_log("Erreur lors de l'insertion du message pour l'autre technicien " . $receiver_other_technician_id . " : " . $stmt_insert_tech->error);
        }
        $stmt_insert_tech->close();
    }
    $stmt_get_other_technicians->close();

} else {
    header("Location: connexion_user.php");
    exit();
}

$conn->close();

header("Location: chat.php?id_mission=" . $id_mission);
exit();
?>