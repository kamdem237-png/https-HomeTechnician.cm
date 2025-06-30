<?php
session_start();

// Rediriger si l'accès n'est pas via un formulaire POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: client.php?error=invalid_access");
    exit();
}

// Inclure les classes PHPMailer
// ASSUREZ-VOUS QUE CES CHEMINS SONT CORRECTS PAR RAPPORT À LA POSITION DE CE FICHIER (envoyer_demande_service.php)
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';
require 'phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- Configuration de la base de données ---
$server_name = "localhost";
$user_name = "root";
$psw = "";
$DB_name = "depanage";

// Connexion à la base de données
$conn = new mysqli($server_name, $user_name, $psw, $DB_name);

// Vérifier la connexion
if ($conn->connect_error) {
    error_log("Échec de la connexion à la base de données : " . $conn->connect_error);
    $_SESSION['form_error'] = "Erreur de connexion à la base de données. Veuillez réessayer plus tard.";
    header("Location: detail_technicien.php?id_technicien=" . ($_POST['id_technicien'] ?? '')); // Tente de rediriger vers la page du technicien
    exit();
}
$conn->set_charset("utf8mb4");

// Récupérer les données du formulaire
$id_technicien = (int)($_POST['id_technicien'] ?? 0);
// Utilisation des données de session pour le client, car elles sont plus fiables
$client_id = (int)($_SESSION['user']['id_client'] ?? 0); 
$client_email = $_SESSION['user']['email'] ?? '';
$client_nom_complet = $_SESSION['user']['nom'] ?? '';
$client_telephone = $_SESSION['user']['num_client'] ?? '';
$client_zone = $_SESSION['user']['zone'] ?? ''; // Pour la localisation par défaut

$description_probleme = trim($_POST['description_probleme'] ?? '');
$localisation_mission = trim($_POST['localisation_mission'] ?? $client_zone); // Utilise la zone du client comme défaut

$errors = [];

// Validation des données
if (empty($id_technicien) || empty($client_id) || empty($description_probleme) || empty($localisation_mission)) {
    $errors[] = "Tous les champs obligatoires (technicien, client, description, localisation) doivent être remplis.";
}
if (strlen($description_probleme) < 20) {
    $errors[] = "La description du problème est trop courte (min. 20 caractères).";
}
if (strlen($localisation_mission) < 5) {
    $errors[] = "La localisation de la mission est trop courte (min. 5 caractères).";
}

if (!empty($errors)) {
    $_SESSION['form_error'] = implode("<br>", $errors);
    header("Location: technicien_details.php?id_technicien=" . $id_technicien);
    exit();
}

// 1. Récupérer les informations complètes du technicien (email, nom) depuis la BDD
// C'est plus sûr de le récupérer de la BDD que de se fier à des champs hidden potentiellement falsifiés
$technicien_data_db = null;
$stmt_tech_info = $conn->prepare("SELECT email, nom, prenom FROM technicien WHERE id_technicien = ?");
if ($stmt_tech_info === false) {
    error_log("Erreur de préparation technicien_data_db: " . $conn->error);
    $_SESSION['form_error'] = "Erreur interne lors de la récupération des informations du technicien.";
    header("Location: technicien_details.php?id_technicien=" . $id_technicien);
    exit();
}
$stmt_tech_info->bind_param("i", $id_technicien);
$stmt_tech_info->execute();
$result_tech_info = $stmt_tech_info->get_result();
if ($result_tech_info->num_rows > 0) {
    $technicien_data_db = $result_tech_info->fetch_assoc();
    $technicien_email = $technicien_data_db['email']; // Utiliser l'email de la BDD
    $technicien_nom = $technicien_data_db['prenom'] . ' ' . $technicien_data_db['nom'];
} else {
    $_SESSION['form_error'] = "Technicien introuvable.";
    header("Location: client.php"); // Rediriger si technicien introuvable
    exit();
}
$stmt_tech_info->close();


// 2. Enregistrer la demande de mission dans la base de données
// Utilisation de requêtes préparées pour la sécurité
$sql_insert_mission = "INSERT INTO mission (id_client, id_technicien, description, statut, date_demande, localisation) VALUES (?, ?, ?, 'en_attente', NOW(), ?)";
$stmt_insert_mission = $conn->prepare($sql_insert_mission);

if ($stmt_insert_mission === false) {
    error_log("Erreur de préparation de l'insertion de mission : " . $conn->error);
    $_SESSION['form_error'] = "Une erreur est survenue lors de l'enregistrement de votre demande (DB_PREP).";
    header("Location: detail_technicien.php?id_technicien=" . $id_technicien);
    exit();
}

$stmt_insert_mission->bind_param("iiss", $client_id, $id_technicien, $description_probleme, $localisation_mission);

if ($stmt_insert_mission->execute()) {
    $message_success = "Votre demande de service a été envoyée avec succès au technicien.";

    // --- ENVOI DE L'EMAIL AU TECHNICIEN VIA PHPMailer ---
    $mail = new PHPMailer(true);

    try {
        // Configuration SMTP Mailtrap
        $mail->isSMTP();
        $mail->Host       = 'sandbox.smtp.mailtrap.io';
        $mail->SMTPAuth   = true;
        // METTEZ VOS IDENTIFIANTS MAILTRAP ICI - C'EST LA SOURCE DE VOTRE ERREUR "Could not authenticate"
        $mail->Username   = '4c3235ea25bf72'; // Votre Username Mailtrap
        $mail->Password   = 'd8c84603dd3ae8'; // Votre Password Mailtrap
        $mail->Port       = 587;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

        // IMPORTANT : désactiver la vérification du certificat SSL (développement uniquement)
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];

        // Pour le débogage - à commenter en production
        // $mail->SMTPDebug = PHPMailer::SMTP::DEBUG_SERVER;
        // $mail->Debugoutput = 'html';

        $mail->CharSet = 'UTF-8'; // S'assurer que les caractères spéciaux sont bien gérés
        $mail->setFrom('noreply@votredomaine.com', 'Service de Depannage'); // Remplacez par votre email et nom
        $mail->addAddress($technicien_email, $technicien_nom); // Destinataire: le technicien
        $mail->isHTML(true);
        $mail->Subject = 'Nouvelle demande de service - ' . htmlspecialchars($client_nom_complet);

        // Corps de l'e-mail en HTML
        $mail->Body = "
            <html>
            <head>
                <title>Nouvelle demande de service</title>
            </head>
            <body>
                <h2>Bonjour " . htmlspecialchars($technicien_nom) . ",</h2>
                <p>Vous avez une nouvelle demande de service de la part de <strong>" . htmlspecialchars($client_nom_complet) . "</strong>.</p>
                
                <h3>Détails du client :</h3>
                <ul>
                    <li><strong>Nom complet :</strong> " . htmlspecialchars($client_nom_complet) . "</li>
                    <li><strong>Email :</strong> " . htmlspecialchars($client_email) . "</li>
                    <li><strong>Téléphone :</strong> " . htmlspecialchars($client_telephone) . "</li>
                </ul>

                <h3>Détails du problème :</h3>
                <p><strong>Description :</strong> " . nl2br(htmlspecialchars($description_probleme)) . "</p>
                <p><strong>Localisation de la mission :</strong> " . htmlspecialchars($localisation_mission) . "</p>

                <p>Veuillez vous connecter à votre tableau de bord technicien pour consulter les détails et répondre à cette demande.</p>
                <p>Merci,<br>Votre équipe de Dépannage</p>
            </body>
            </html>
        ";
        // Corps de l'e-mail en texte brut
        $mail->AltBody = "Nouvelle demande de service de " . htmlspecialchars($client_nom_complet) . ".\n\n"
                       . "Détails du client:\n"
                       . "Nom complet: " . htmlspecialchars($client_nom_complet) . "\n"
                       . "Email: " . htmlspecialchars($client_email) . "\n"
                       . "Téléphone: " . htmlspecialchars($client_telephone) . "\n\n"
                       . "Détails du problème:\n"
                       . "Description: " . htmlspecialchars($description_probleme) . "\n"
                       . "Localisation de la mission: " . htmlspecialchars($localisation_mission) . "\n\n"
                       . "Veuillez vous connecter à votre tableau de bord technicien pour consulter les détails et répondre à cette demande.\n"
                       . "Merci,\nVotre équipe de Dépannage";

        $mail->send();
        $_SESSION['form_message'] = $message_success . " Un e-mail a été envoyé au technicien.";
    } catch (Exception $e) {
        error_log("Erreur lors de l'envoi de l'email à {$technicien_email} : {$mail->ErrorInfo}");
        // Gardez le message de succès de la BDD, mais ajoutez l'erreur de l'email
        $_SESSION['form_error'] = $message_success . " Cependant, une erreur est survenue lors de l'envoi de l'email au technicien. " . $mail->ErrorInfo;
    }
} else {
    // Erreur lors de l'insertion dans la base de données
    error_log("Erreur lors de l'insertion de mission : " . $stmt_insert_mission->error);
    $_SESSION['form_error'] = "Une erreur est survenue lors de l'enregistrement de votre demande. Veuillez réessayer.";
}

$stmt_insert_mission->close();
$conn->close();

// Rediriger le client vers la page de détails du technicien avec un message de confirmation/erreur
header("Location: technicien_details.php?id_technicien=" . $id_technicien);
exit();
?>