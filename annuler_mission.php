<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'client') {
    header("Location: connexion_user.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "depanage");
if ($conn->connect_error) {
    die("Erreur de connexion : " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_mission = (int)($_POST['id_mission'] ?? 0);
    $id_client = (int)$_SESSION['user']['id_client'];

    // Vérifier que la mission appartient bien à ce client et est en attente
    $stmt = $conn->prepare("SELECT statut FROM mission WHERE id_mission = ? AND id_client = ?");
    $stmt->bind_param("ii", $id_mission, $id_client);
    $stmt->execute();
    $result = $stmt->get_result();
    $mission = $result->fetch_assoc();
    $stmt->close();

    if ($mission && $mission['statut'] === 'en_attente') {
        // Supprimer les affectations de techniciens pour cette mission
        $delete_affectations_stmt = $conn->prepare("DELETE FROM mission_technicien WHERE id_mission = ?");
        $delete_affectations_stmt->bind_param("i", $id_mission);
        $delete_affectations_stmt->execute();
        $delete_affectations_stmt->close();

        // Supprimer la mission elle-même
        $delete_mission_stmt = $conn->prepare("DELETE FROM mission WHERE id_mission = ?");
        $delete_mission_stmt->bind_param("i", $id_mission);
        if ($delete_mission_stmt->execute()) {
            header("Location: missions_clientes.php?success=mission_cancelled");
            exit();
        } else {
            $_SESSION['error_message'] = "Erreur lors de l'annulation de la mission.";
        }
        $delete_mission_stmt->close();
    } else {
        $_SESSION['error_message'] = "Mission introuvable ou non éligible à l'annulation.";
    }
} else {
    $_SESSION['error_message'] = "Requête invalide.";
}

$conn->close();
header("Location: missions_clientes.php");
exit();
?>