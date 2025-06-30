<?php
session_start();

// Redirect if the user is not logged in or is not a client
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'client') {
    header("Location: connexion_user.php");
    exit();
}

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // If not POST, redirect back to missions page or show an error
    header("Location: missions_clientes.php");
    exit();
}

// Database connection
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', ''); // Consider using a password, even in local development
define('DB_NAME', 'depanage');

$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    error_log("Connection failed: " . $conn->connect_error);
    die("An error occurred while connecting to the database. Please try again later.");
}

// Get and sanitize mission ID from POST data
$id_mission = (int)($_POST['id_mission'] ?? 0);
$id_client = (int)$_SESSION['user']['id_client'];

// Verify that the mission belongs to the client and is not already terminated or cancelled
// Using prepared statement for security
$sql_verify = "SELECT id_mission FROM mission WHERE id_mission = ? AND id_client = ? AND (statut = 'en_cours' OR statut = 'en_attente')";
if ($stmt_verify = $conn->prepare($sql_verify)) {
    $stmt_verify->bind_param("ii", $id_mission, $id_client);
    $stmt_verify->execute();
    $result_verify = $stmt_verify->get_result();

    if ($result_verify && $result_verify->num_rows > 0) {
        // Update the mission status to 'terminee' and set the completion date
        $sql_update = "UPDATE mission 
                       SET statut = 'terminee', 
                           date_terminee = NOW() 
                       WHERE id_mission = ?";
        if ($stmt_update = $conn->prepare($sql_update)) {
            $stmt_update->bind_param("i", $id_mission);
            if (!$stmt_update->execute()) {
                error_log("Error updating mission status: " . $stmt_update->error);
            }
            $stmt_update->close();
        } else {
            error_log("SQL Update Prepare failed: " . $conn->error);
        }
    } else {
        error_log("Attempted to close a mission that doesn't exist, doesn't belong to the client, or is not in an 'en_cours' or 'en_attente' status. Mission ID: " . $id_mission . ", Client ID: " . $id_client);
    }
    $stmt_verify->close();
} else {
    error_log("SQL Verify Prepare failed: " . $conn->error);
}

$conn->close();

// Redirect back to the missions page after processing
header("Location: missions_clientes.php");
exit();
?>