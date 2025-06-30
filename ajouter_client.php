<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

$server_name = "localhost";
$user_name = "root";
$psw = "";
$DB_name = "depanage";

$con = new mysqli($server_name, $user_name, $psw, $DB_name);
if ($con->connect_error) {
    die("Connexion échouée : " . $con->connect_error);
}

$message = "";

if (isset($_POST['valider'])) {
    if (!empty($_POST['nom']) && !empty($_POST['num_client']) && !empty($_POST['email']) && !empty($_POST['zone'])) {
        $nom = trim($_POST['nom']);
        $telephone = trim($_POST['num_client']);
        $email = trim($_POST['email']);
        $zone = trim($_POST['zone']);

        $default_password = bin2hex(random_bytes(4));
        $password_hashed = password_hash($default_password, PASSWORD_DEFAULT);

        $checkStmt = $con->prepare("SELECT id_client FROM client WHERE email = ?");
        $checkStmt->bind_param("s", $email);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {
            $message = "Cet e-mail est déjà utilisé.";
        } else {
            $stmt = $con->prepare("INSERT INTO client (password, nom, num_client, email, zone, first_login) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->bind_param("sssss", $password_hashed, $nom, $telephone, $email, $zone);

            if ($stmt->execute()) {
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host = 'sandbox.smtp.mailtrap.io';
                    $mail->SMTPAuth = true;
                    $mail->Username = '4c3235ea25bf72'; // <-- Ton identifiant Mailtrap
                    $mail->Password = 'd8c84603dd3ae8'; // <-- Ton mot de passe Mailtrap
                    $mail->Port = 587;
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

                    $mail->SMTPOptions = [
                        'ssl' => [
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                            'allow_self_signed' => true
                        ]
                    ];

                    $mail->setFrom('noreply@depanage.com', 'Agence Dépannage');
                    $mail->addAddress($email, $nom);
                    $mail->isHTML(true);
                    $mail->CharSet = 'UTF-8';
                    $mail->Subject = 'Votre compte client a été créé';

                    // Corps du mail HTML avec style inline simple
                    $mail->Body = '
                    <html>
                    <head>
                        <meta charset="UTF-8">
                        <title>Création de compte client</title>
                        <style>
                            body {
                                font-family: Arial, sans-serif;
                                color: #333;
                                background-color: #f9f9f9;
                                padding: 20px;
                            }
                            .container {
                                max-width: 600px;
                                margin: auto;
                                background: #fff;
                                padding: 20px;
                                border: 1px solid #ddd;
                                border-radius: 5px;
                            }
                            h1 {
                                color: #007bff;
                            }
                            .password {
                                display: inline-block;
                                background-color: #e9ecef;
                                padding: 8px 12px;
                                border-radius: 4px;
                                font-family: monospace;
                                font-size: 18px;
                                color: #d6336c;
                                margin: 10px 0;
                            }
                            p {
                                font-size: 16px;
                                line-height: 1.5;
                            }
                        </style>
                    </head>
                    <body>
                        <div class="container">
                            <h1>Bonjour ' . htmlspecialchars($nom) . ',</h1>
                            <p>Votre compte client a été <strong>créé avec succès</strong>.</p>
                            <p>Voici votre mot de passe temporaire :</p>
                            <p class="password">' . htmlspecialchars($default_password) . '</p>
                            <p>Merci de vous connecter et de le modifier dès que possible.</p>
                            <p>Cordialement,<br>L\'équipe Dépannage</p>
                        </div>
                    </body>
                    </html>';

                    // Version texte alternative
                    $mail->AltBody = "Bonjour $nom,\n\nVotre compte client a été créé avec succès.\nMot de passe temporaire : $default_password\nVeuillez le modifier à votre première connexion.\n\nCordialement,\nL'équipe Dépannage";

                    $mail->send();
                    header("Location: gest_client.php");
                    exit();
                } catch (Exception $e) {
                    $message = "Erreur lors de l'envoi de l'e-mail : {$mail->ErrorInfo}. Mot de passe temporaire : $default_password";
                }
            } else {
                $message = "Erreur lors de l'enregistrement : " . $stmt->error;
            }

            $stmt->close();
        }

        $checkStmt->close();
    } else {
        $message = "Tous les champs sont obligatoires.";
    }
}

$con->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ajouter un client</title>
    <link rel="icon" type="image/png" href="mon_logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2 class="mb-4">Formulaire d'ajout de client</h2>

        <?php if (!empty($message)): ?>
            <div class="alert alert-warning"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-3">
                <label for="nom" class="form-label">Nom</label>
                <input type="text" class="form-control" name="nom" id="nom" required>
            </div>
            <div class="mb-3">
                <label for="num_client" class="form-label">Numéro du client</label>
                <input type="text" class="form-control" name="num_client" id="num_client" required>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">E-mail</label>
                <input type="email" class="form-control" name="email" id="email" required>
            </div>
            <div class="mb-3">
                <label for="zone" class="form-label">Zone</label>
                <input type="text" class="form-control" name="zone" id="zone" required>
            </div>
            <button type="submit" name="valider" class="btn btn-primary">Ajouter</button>
            <a href="gest_client.php" class="btn btn-secondary">Annuler</a>
        </form>
    </div>
</body>
</html>
