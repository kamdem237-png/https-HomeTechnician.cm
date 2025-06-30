<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Inclure les fichiers PHPMailer
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

$message = '';
$error = '';

// Traitement du formulaire lorsque la méthode est POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nom = htmlspecialchars(trim($_POST['nom']));
    $email_expediteur = htmlspecialchars(trim($_POST['email']));
    $sujet = htmlspecialchars(trim($_POST['sujet']));
    $message_corps = htmlspecialchars(trim($_POST['message']));

    // Validation basique des champs
    if (empty($nom) || empty($email_expediteur) || empty($sujet) || empty($message_corps)) {
        $error = "Veuillez remplir tous les champs du formulaire.";
    } elseif (!filter_var($email_expediteur, FILTER_VALIDATE_EMAIL)) {
        $error = "L'adresse email fournie n'est pas valide.";
    } else {
        // Envoi de l'email à l'administrateur via PHPMailer
        $mail = new PHPMailer(true); // Passer true pour activer les exceptions

        try {
            // Configuration SMTP pour Mailtrap (ou votre serveur SMTP)
            $mail->isSMTP();
            $mail->Host = 'sandbox.smtp.mailtrap.io'; // Remplacez par votre hôte SMTP
            $mail->SMTPAuth = true;
            $mail->Username = '4c3235ea25bf72'; // Votre Mailtrap Username
            $mail->Password = 'd8c84603dd3ae8'; // Votre Mailtrap Password
            $mail->Port = 587; // Port SMTP
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];

            // Expéditeur (celui qui remplit le formulaire)
            $mail->setFrom($email_expediteur, $nom);
            // Destinataire (l'administrateur)
            $mail->addAddress('admin@votresite.com', 'Administrateur VotreSite'); // Remplacez par l'email de votre administrateur
            // Optionnel : ajouter une adresse de réponse à l'expéditeur
            $mail->addReplyTo($email_expediteur, $nom);

            // Contenu de l'email
            $mail->isHTML(true); // Définir le format de l'email en HTML
            $mail->CharSet = 'UTF-8';
            $mail->Subject = 'Nouveau message de contact : ' . $sujet;
            $mail->Body    = '
                <html>
                <head>
                    <title>Nouveau message de contact</title>
                </head>
                <body>
                    <h2>Message de contact de ' . $nom . '</h2>
                    <p><strong>Email :</strong> ' . $email_expediteur . '</p>
                    <p><strong>Sujet :</strong> ' . $sujet . '</p>
                    <hr>
                    <p><strong>Message :</strong></p>
                    <p>' . nl2br($message_corps) . '</p>
                </body>
                </html>
            ';
            $mail->AltBody = "Nouveau message de contact de $nom ($email_expediteur).\nSujet: $sujet\nMessage:\n$message_corps"; // Pour les clients mail qui ne supportent pas le HTML

            $mail->send();
            $message = "Votre message a été envoyé avec succès à l'administrateur.";
            // Optionnel : Effacer les champs après envoi réussi
            $_POST = array(); // Vide les données POST pour effacer le formulaire
        } catch (Exception $e) {
            $error = "Une erreur est survenue lors de l'envoi de votre message. Veuillez réessayer plus tard. Erreur Mailer : {$mail->ErrorInfo}";
            // Pour le débogage, vous pouvez logguer $e->getMessage()
            // error_log("Erreur lors de l'envoi de l'email de contact: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <title>Contact Administrateur - VotreSite</title>
    <link rel="icon" type="image/png" href="mon_logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --bs-primary: #007bff;
            --bs-secondary: #6c757d;
            --bs-success: #28a745;
            --bs-dark: #343a40;
            --bs-light: #f8f9fa;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
            background-color: var(--bs-light);
            padding-top: 76px; /* Espace pour la navbar fixe */
        }

        .navbar {
            background-color: var(--bs-dark) !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1030;
        }

        .navbar-brand img {
            max-width: 200px;
            height: auto;
            border-radius: 8px;
            object-fit: contain;
        }

        .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.75) !important;
            font-weight: 500;
            margin-right: 15px;
            transition: color 0.3s ease;
        }

        .navbar-nav .nav-link:hover,
        .navbar-nav .nav-link.active {
            color: var(--bs-primary) !important;
        }

        .btn-primary {
            background-color: var(--bs-primary);
            border-color: var(--bs-primary);
        }

        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }

        .container {
            margin-top: 40px;
            margin-bottom: 50px;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            background-color: var(--bs-primary);
            color: white;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
            padding: 1.5rem;
            font-size: 1.8rem;
            font-weight: bold;
            text-align: center;
        }

        .card-body {
            padding: 2.5rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--bs-dark);
        }

        .form-control:focus {
            border-color: var(--bs-primary);
            box-shadow: 0 0 0 0.25rem rgba(0, 123, 255, 0.25);
        }

        .footer {
            background-color: var(--bs-dark) !important;
            color: rgba(255, 255, 255, 0.7);
            padding: 3rem 0;
        }

        .footer h3 {
            color: white;
            font-weight: 600;
        }

        .footer a {
            color: rgba(255, 255, 255, 0.6);
            transition: color 0.3s ease;
            text-decoration: none;
        }

        .footer a:hover {
            color: white;
            text-decoration: underline;
        }

        .footer .social-links a {
            color: rgba(255, 255, 255, 0.7);
            font-size: 1.5rem;
            margin-right: 15px;
            transition: color 0.3s ease;
        }

        .footer .social-links a:hover {
            color: var(--bs-success);
        }
    </style>
</head>
<body>
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container-fluid">
                <a class="navbar-brand" href="site de mise en relation techniciens&clients.html">
                    <img src="mon logo 3.png" alt="HomeTechnician Logo">
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavClient" aria-controls="navbarNavClient" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNavClient">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        <li class="nav-item"><a class="nav-link" aria-current="page" href="client.php">Accueil</a></li>
                        <li class="nav-item"><a class="nav-link" href="mon_compte_client.php">Mon compte</a></li>
                        <li class="nav-item"><a class="nav-link" href="missions_clientes.php">Missions</a></li>
                        <li class="nav-item"><a class="nav-link" href="soumettre_probleme.php">Soumettre une Mission</a></li>
                        <li class="nav-item"><a class="nav-link active" href="contact_admin_cl.php">Contact Admin</a></li>
                        <li class="nav-item"><a class="nav-link" href="#techniciens">je suis un technicien</a></li>
                    </ul>
                    <ul class="navbar-nav">
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">
                                <button type="button" class="btn btn-primary"><i class="fas fa-sign-out-alt me-1"></i> Déconnexion</button>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <main>
        <div class="container">
            <div class="card">
                <div class="card-header">
                    Contactez l'Administrateur
                </div>
                <div class="card-body">
                    <?php if (!empty($message)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($message) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <p class="mb-4">Si vous avez des questions, des problèmes techniques ou toute autre demande, veuillez remplir le formulaire ci-dessous. Nous vous répondrons dans les plus brefs délais.</p>

                    <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST">
                        <div class="mb-3">
                            <label for="nom" class="form-label">Votre Nom</label>
                            <input type="text" class="form-control" id="nom" name="nom" value="<?= isset($_POST['nom']) ? htmlspecialchars($_POST['nom']) : '' ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Votre Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="sujet" class="form-label">Sujet</label>
                            <input type="text" class="form-control" id="sujet" name="sujet" value="<?= isset($_POST['sujet']) ? htmlspecialchars($_POST['sujet']) : '' ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="message" class="form-label">Votre Message</label>
                            <textarea class="form-control" id="message" name="message" rows="6" required><?= isset($_POST['message']) ? htmlspecialchars($_POST['message']) : '' ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-paper-plane me-2"></i> Envoyer le message</button>
                    </form>

                    <hr class="my-4">

                    <h5 class="mb-3">Autres moyens de nous contacter :</h5>
                    <p><i class="fas fa-envelope me-2 text-success"></i> Email : **admin@votresite.com** (réponse sous 24h)</p>
                    <p><i class="fas fa-phone me-2 text-success"></i> Téléphone : **+237 6XX XXX XXX** (du lundi au vendredi, 9h-17h)</p>
                </div>
            </div>
        </div>
    </main>

    <footer class="footer bg-dark text-white-50 py-5">
        <div class="container">
            <div class="row">
                <div class="col-md-3 mb-4 mb-md-0">
                    <h3 class="text-white mb-3">VotreSite</h3>
                    <p>La plateforme qui connecte les clients avec des techniciens qualifiés pour tous leurs besoins de service au Cameroun.</p>
                </div>
                <div class="col-md-3 mb-4 mb-md-0">
                    <h3 class="text-white mb-3">Liens Utiles</h3>
                    <ul class="list-unstyled">
                        <li><a href="a_propos.php">À Propos de Nous</a></li>
                        <li><a href="#">FAQ Techniciens</a></li>
                        <li><a href="#">Mentions Légales</a></li>
                        <li><a href="#">Politique de Confidentialité</a></li>
                        <li><a href="#">Conditions Générales</a></li>
                    </ul>
                </div>
                <div class="col-md-3 mb-4 mb-md-0">
                    <h3 class="text-white mb-3">Services Populaires</h3>
                    <ul class="list-unstyled">
                        <li><a href="#">Réparation d'Ordinateur</a></li>
                        <li><a href="#">Installation Électrique</a></li>
                        <li><a href="#">Dépannage Plomberie</a></li>
                        <li><a href="#">Entretien Climatisation</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h3 class="text-white mb-3">Contactez-nous</h3>
                    <p><i class="fas fa-envelope me-2 text-success"></i> contact@votresite.com</p>
                    <p><i class="fas fa-phone me-2 text-success"></i> +237 6XX XXX XXX</p>
                    <div class="social-links mt-3">
                        <a href="#" class="me-3 fs-4"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="me-3 fs-4"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="me-3 fs-4"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#" class="fs-4"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom text-center pt-4 mt-4 border-top border-secondary">
                <p class="mb-0">&copy; 2025 VotreSite. Tous droits réservés.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>