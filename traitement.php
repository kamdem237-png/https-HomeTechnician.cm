<?php
session_start(); 

$server_name = "localhost";
$user_name = "root";
$psw = "";
$DB_name = "depanage";

$conn = new mysqli($server_name, $user_name, $psw, $DB_name);
if ($conn->connect_error) {
    die("Erreur de connexion : " . $conn->connect_error);
}

if (!isset($_SESSION['id_clients'])) {
    echo "<script>
        alert('Veuillez vous connecter pour prendre un rendez-vous.');
        window.location.href = 'connexion_user.php';
    </script>";
    exit(); 
}

if (isset($_POST['zone']) && isset($_POST['specialite'])) {
    $zone = trim($_POST['zone']);
    $specialite = trim($_POST['specialite']);

    $sql = "SELECT * FROM techniciens 
            WHERE LOWER(TRIM(zone)) = LOWER(?) 
            AND LOWER(TRIM(specialite)) = LOWER(?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $zone, $specialite);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo "<h2>Techniciens disponibles à $zone, spécialité : $specialite</h2>";
        while ($row = $result->fetch_assoc()) {
            echo "<div style='border:1px solid #ccc; padding:10px; margin:10px;'>";
            echo "Nom :  {$row['nom']} <br>";
            echo "Email : {$row['email']} <br>";
            echo "Zone : {$row['zone']} <br>";
            echo "Spécialité : {$row['specialite']} <br><br>";

            echo "<form action='selectionner.php' method='POST'>";
            echo "<input type='hidden' name='id_techniciens' value='{$row['id_techniciens']}'>";
            echo "<input type='hidden' name='id_clients' value='{$_SESSION['id_clients']}'>";
            echo "<input type='submit' value='Sélectionner ce technicien'>";
            echo "</form>";
            echo "</div>";
        }
    } else {
        echo "<h2>Aucun technicien trouvé pour la zone $zone et la spécialité $specialite.</h2>";
        echo "<a href='agence de reparation des equipements.html'><button type='button'>Retour</button></a>";
    }

    $stmt->close();
} else {
    echo "<h2>Veuillez remplir tous les champs.</h2>";
}

$conn->close();
?>
