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

$id_client = (int)($_SESSION['user']['id_client'] ?? 0);
$id_mission = (int)($_POST['id_mission'] ?? 0);

if ($id_mission > 0) {
    // Vérifier que la mission appartient bien au client et est en attente (pour ne pas supprimer une mission en cours ou terminée)
    $sql = "SELECT * FROM mission WHERE id_mission = $id_mission AND id_client = $id_client AND statut = 'en_attente'";
    $result = $conn->query($sql);

    if ($result && $result->num_rows === 1) {
        // Suppression de la mission
        $delete = "DELETE FROM mission WHERE id_mission = $id_mission";
        if ($conn->query($delete)) {
            header("Location: missions_clientes.php?success=supprimee");
            exit();
        } else {
            die("Erreur lors de la suppression de la mission : " . $conn->error);
        }
    } else {
        die("Mission introuvable ou non supprimable.");
    }
} else {
    die("ID de mission invalide.");
}

$conn->close();
?>
