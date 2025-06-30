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
        // Mettre à jour le statut de la mission à 'en_cours'
        $update_stmt = $conn->prepare("UPDATE mission SET statut = 'en_cours', date_debut_mission = NOW() WHERE id_mission = ?");
        $update_stmt->bind_param("i", $id_mission);
        if ($update_stmt->execute()) {
            header("Location: missions_clientes.php?success=mission_started");
            exit();
        } else {
            $_SESSION['error_message'] = "Erreur lors du démarrage de la mission.";
        }
        $update_stmt->close();
    } else {
        $_SESSION['error_message'] = "Mission introuvable ou non éligible au démarrage.";
    }
} else {
    $_SESSION['error_message'] = "Requête invalide.";
}

$conn->close();
header("Location: missions_clientes.php");
exit();
?>