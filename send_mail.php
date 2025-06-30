<?php
// Inclure les classes PHPMailer
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';
require 'phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Créer une instance de PHPMailer
$mail = new PHPMailer(true);

try {
    // Configuration SMTP Mailtrap
    $mail->isSMTP();
    $mail->Host       = 'sandbox.smtp.mailtrap.io';
    $mail->SMTPAuth   = true;
    $mail->Username   = '6605c847b0e8bb';
    $mail->Password   = '54a50cbd8f2051';
    $mail->Port       = 587;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // pour 587 ou 2525

    // IMPORTANT : désactiver la vérification du certificat SSL (dev uniquement)
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true
        ]
];

    // Informations de l’email
    $mail->setFrom('from@example.com', 'Ton Nom');
    $mail->addAddress('to@example.com', 'Destinataire');
    $mail->isHTML(true);
    $mail->Subject = 'Test Email';
    $mail->Body    = '<b>Bonjour ! Ceci est un email envoyé avec PHPMailer sans Composer</b>';
    $mail->AltBody = 'Bonjour ! Ceci est un email envoyé avec PHPMailer sans Composer (texte brut)';
    $mail->SMTPDebug = 3; // ou 3 pour plus d'infos
    $mail->Debugoutput = 'html';

    $mail->send();
    echo 'Le message a été envoyé avec succès !';
} catch (Exception $e) {
    echo "Erreur lors de l’envoi : {$mail->ErrorInfo}";
}
