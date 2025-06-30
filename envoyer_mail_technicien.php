<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';
require 'phpmailer/src/Exception.php';

$conn = new mysqli("localhost", "root", "", "depanage");
if ($conn->connect_error) {
    die("Connexion échouée : " . $conn->connect_error);
}

$id_technicien = (int)($_POST['id_technicien'] ?? 0);
$id_client = (int)($_SESSION['user']['id_client'] ?? 0);
$description = $conn->real_escape_string(trim($_POST['description'] ?? ''));

if (empty($description)) {
    die("Description du problème requise.");
}

// Récupération sécurisée
$technicien = $conn->query("SELECT * FROM technicien WHERE id_technicien = $id_technicien")->fetch_assoc();
$client = $conn->query("SELECT * FROM client WHERE id_client = $id_client")->fetch_assoc();

if (!$technicien || !$client) {
    die("Erreur : données manquantes.");
}

// Création de la mission
$localisation = $conn->real_escape_string($client['adresse'] ?? 'Localisation non précisée');
$conn->query("INSERT INTO mission (description, localisation, statut, id_client, nb_techniciens_demande) 
              VALUES ('$description', '$localisation', 'en_attente', $id_client, 1)");
$id_mission = $conn->insert_id;

// Lier mission au technicien
$conn->query("INSERT INTO mission_technicien (id_mission, id_technicien) VALUES ($id_mission, $id_technicien)");

// Envoi de l’email au technicien
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ];
    $mail->Host = 'sandbox.smtp.mailtrap.io';
    $mail->SMTPAuth = true;
    $mail->Username = '4c3235ea25bf72'; // Remplacer
    $mail->Password = 'd8c84603dd3ae8'; // Remplacer
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->CharSet = 'UTF-8';
    $mail->setFrom('noreply@depanage.com', 'Dépannage Service');
    $mail->addAddress($technicien['email'], $technicien['nom']);
    $mail->isHTML(true);
    $mail->Subject = 'Nouvelle mission - Affectation';

    $mail->Body = '
    <html><body>
        <h4>Bonjour ' . htmlspecialchars($technicien['nom']) . ',</h4>
        <p>Un client a demandé vos services. Voici les détails :</p>
        <ul>
            <li><strong>Nom du client :</strong> ' . htmlspecialchars($client['nom']) . '</li>
            <li><strong>Email :</strong> ' . htmlspecialchars($client['email']) . '</li>
            <li><strong>Description du problème :</strong><br>' . nl2br(htmlspecialchars($description)) . '</li>
        </ul>
        <p><a href="http://localhost/projet/technicien.php">Voir la mission</a></p>
        <p style="font-size:12px;color:#777;">Email automatique, ne pas répondre.</p>
    </body></html>';

    $mail->AltBody = "Bonjour " . $technicien['nom'] . ",\nUn client a demandé vos services.\n"
        . "Nom : " . $client['nom'] . "\nEmail : " . $client['email'] . "\nProblème : " . $description;

    $mail->send();
    $message = "Technicien sélectionné avec succès. Un email lui a été envoyé.";
} catch (Exception $e) {
    $message = "Erreur lors de l’envoi de l’e-mail : " . $mail->ErrorInfo;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Confirmation</title>
    <link rel="icon" type="image/png" href="mon_logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-6">
            <div class="card shadow">
                <div class="card-body text-center">
                    <h3 class="mb-4">Confirmation</h3>
                    <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
                    <a href="missions_clientes.php" class="btn btn-primary mt-3">Mes missions</a>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
