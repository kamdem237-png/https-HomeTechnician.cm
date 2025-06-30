<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Inclure le système de log pour enregistrer les actions importantes (si vous en avez un)
// require_once 'utils.php'; 

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// Vérification de l'authentification de l'administrateur
// Assurez-vous que $_SESSION['id_admin'] est défini lors de la connexion de l'administrateur.
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || ($_SESSION['role'] ?? '') !== 'admin') {
    header('location: connexion_admin.php');
    exit();
}

$server_name = "localhost";
$user_name = "root";
$psw = "";
$DB_name = "depanage";

$con = new mysqli($server_name, $user_name, $psw, $DB_name);
if ($con->connect_error) {
    error_log("Erreur de connexion à la base de données : " . $con->connect_error);
    die("Désolé, une erreur technique est survenue. Veuillez réessayer plus tard.");
}

// Définir l'encodage
$con->set_charset("utf8mb4");

// --- Initialisation des variables pour éviter les "Undefined variable" et "Deprecated" ---
$firstName = '';
$nom = '';
$num_techniciens = '';
$email = '';
$zone = '';
$specialty = ''; 
$experienceYears = ''; // Gardons-le si vous le laissez dans le formulaire, sinon supprimez l'initialisation
$message = '';

if (isset($_POST['valider'])) {
    // Commencez une transaction
    $con->begin_transaction();

    try {
        // La description n'est plus obligatoire ici, le technicien la remplira après
        if (!empty($_POST['firstName']) && 
            !empty($_POST['nom']) && 
            !empty($_POST['num_techniciens']) && 
            !empty($_POST['email']) && 
            !empty($_POST['zone']) && 
            !empty($_POST['specialty']) && 
            isset($_POST['experienceYears'])) // On utilise isset car '0' est une valeur valide
        {
            // Nettoyage des données pour éviter injections XSS
            $firstName = htmlspecialchars(trim($_POST['firstName']));
            $nom = htmlspecialchars(trim($_POST['nom']));
            $telephone = htmlspecialchars(trim($_POST['num_techniciens']));
            $email = htmlspecialchars(trim($_POST['email']));
            $zone = htmlspecialchars(trim($_POST['zone']));
            $specialty = htmlspecialchars(trim($_POST['specialty']));
            $experienceYears = (int)$_POST['experienceYears']; // Conversion en entier

            // Vérifier si l'email existe déjà
            $checkStmt = $con->prepare("SELECT id_technicien FROM technicien WHERE email = ?");
            if (!$checkStmt) {
                throw new Exception("Erreur de préparation pour vérifier l'email : " . $con->error);
            }
            $checkStmt->bind_param("s", $email);
            $checkStmt->execute();
            $checkStmt->store_result();

            if ($checkStmt->num_rows > 0) {
                throw new Exception("Cet email est déjà utilisé. Veuillez en saisir un autre.");
            }
            $checkStmt->close();

            // Générer un mot de passe temporaire et le hacher
            $default_password = bin2hex(random_bytes(4)); // Mot de passe de 8 caractères hexadécimaux
            $password_hashed = password_hash($default_password, PASSWORD_DEFAULT);

            // Préparer et exécuter la requête d'insertion
            // Suppression du champ 'description_services' dans la requête INSERT
            $stmt = $con->prepare("INSERT INTO technicien (password, prenom, nom, num_technicien, email, zone, specialite, annees_experience, first_login, is_active, is_banned, en_quarantaine) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 1, 0, 0)");
            
            if (!$stmt) {
                throw new Exception("Erreur de préparation de la requête d'insertion : " . $con->error);
            }
            // Suppression du paramètre 'description' ('s') dans bind_param
            $stmt->bind_param("sssssssi", 
                                $password_hashed, 
                                $firstName, 
                                $nom, 
                                $telephone, 
                                $email, 
                                $zone, 
                                $specialty, 
                                $experienceYears 
                            );

            if ($stmt->execute()) {
                // Envoi de l'e-mail avec le mot de passe temporaire
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host = 'sandbox.smtp.mailtrap.io'; // REMPLACEZ PAR VOTRE HÔTE SMTP PROD
                    $mail->SMTPAuth = true;
                    $mail->Username = '4c3235ea25bf72'; // 🚨 REMPLACEZ PAR VOTRE NOM D'UTILISATEUR SMTP
                    $mail->Password = 'd8c84603dd3ae8'; // 🚨 REMPLACEZ PAR VOTRE MOT DE PASSE SMTP
                    $mail->Port = 587;
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

                    $mail->SMTPOptions = [
                        'ssl' => [
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                            'allow_self_signed' => true
                        ]
                    ];

                    $mail->setFrom('noreply@votre-site.com', 'Agence Dépannage');
                    $mail->addAddress($email, $firstName . ' ' . $nom); // Nom complet pour le destinataire
                    $mail->isHTML(true);
                    $mail->CharSet = 'UTF-8';
                    $mail->Subject = "Votre compte technicien a été créé";

                    $mail->Body = '
                    <html>
                    <head>
                        <meta charset="UTF-8">
                        <title>Création de compte technicien</title>
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
                            <h1>Bonjour ' . htmlspecialchars($firstName) . ' ' . htmlspecialchars($nom) . ',</h1>
                            <p>Votre compte technicien a été **créé avec succès**.</p>
                            <p>Voici votre mot de passe temporaire :</p>
                            <p class="password">' . htmlspecialchars($default_password) . '</p>
                            <p>Lors de votre première connexion, veuillez finaliser le remplissage de vos informations dans votre compte.</p>
                            <p>Cordialement,<br>L\'équipe Dépannage</p>
                        </div>
                    </body>
                    </html>';

                    $mail->AltBody = "Bonjour {$firstName} {$nom},\n\nVotre compte technicien a été créé avec succès.\nMot de passe temporaire : {$default_password}\nVeuillez vous connecter et le modifier dès que possible. Il vous sera également demandé de compléter votre profil (description de services, années d'expérience).\n\nCordialement,\nL'équipe Dépannage";

                    $mail->send();

                    // Log l'action après succès complet
                    $technicien_id = $con->insert_id; // Récupérer l'ID du technicien ajouté
                    $admin_id = $_SESSION['id_admin'] ?? 'N/A';
                    if (function_exists('logUserAction')) { 
                        logUserAction(
                            'Ajout Technicien', 
                            $technicien_id, 
                            'technicien', 
                            $firstName . ' ' . $nom . ' (' . $email . ')',
                            "Ajouté par l'administrateur (ID: {$admin_id})"
                        );
                    }
                    
                    $con->commit(); // Confirmer la transaction
                    $_SESSION['message'] = "Le technicien a été ajouté avec succès et un e-mail a été envoyé.";
                    $_SESSION['message_type'] = "success";
                    header("Location: gest_technicien.php");
                    exit();

                } catch (Exception $e) {
                    $con->rollback(); // Annuler la transaction en cas d'erreur d'envoi d'email
                    error_log("Erreur PHPMailer lors de l'ajout technicien : {$e->getMessage()}");
                    $message = "Le technicien a été enregistré, mais une erreur est survenue lors de l'envoi de l'e-mail : {$mail->ErrorInfo}. Mot de passe temporaire : **{$default_password}**. Veuillez le communiquer manuellement au technicien.";
                }
            } else {
                throw new Exception("Erreur lors de l'enregistrement du technicien dans la base de données : " . $stmt->error);
            }
            $stmt->close();
        } else {
            throw new Exception("Tous les champs obligatoires n'ont pas été remplis.");
        }
    } catch (Exception $e) {
        $con->rollback(); // Annuler la transaction en cas de toute erreur dans le try
        error_log("Erreur lors de l'ajout technicien : " . $e->getMessage());
        $message = "Erreur: " . $e->getMessage();
    }
}

$con->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ajouter un technicien</title>
    <link rel="icon" type="image/png" href="mon_logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2 class="mb-4">Formulaire d'ajout de technicien</h2>

        <?php if (!empty($message)): ?>
            <div class="alert alert-warning"><?php echo $message; ?></div> 
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-3"> 
                <label for="firstName" class="form-label">Prénom</label>
                <input type="text" class="form-control" id="firstName" name="firstName" value="<?php echo htmlspecialchars($firstName); ?>" required>
            </div>
            <div class="mb-3">
                <label for="nom" class="form-label">Nom</label>
                <input type="text" class="form-control" name="nom" id="nom" value="<?php echo htmlspecialchars($nom); ?>" required>
            </div>
            <div class="mb-3">
                <label for="num_techniciens" class="form-label">Numéro du technicien</label>
                <input type="text" class="form-control" name="num_techniciens" id="num_techniciens" value="<?php echo htmlspecialchars($num_techniciens); ?>" required>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">E-mail</label>
                <input type="email" class="form-control" name="email" id="email" value="<?php echo htmlspecialchars($email); ?>" required>
            </div>
            <div class="mb-3">
                <label for="zone" class="form-label">Zone</label>
                <input type="text" class="form-control" name="zone" id="zone" value="<?php echo htmlspecialchars($zone); ?>" required>
            </div>
            <div class="mb-3">
                <label for="specialty" class="form-label">Spécialité Principale</label>
                <select class="form-select" id="specialty" name="specialty" required>
                    <option value="">Choisissez une spécialité...</option>
                    <option value="electricite" <?php echo ($specialty == 'electricite') ? 'selected' : ''; ?>>Électricité</option>
                    <option value="plomberie" <?php echo ($specialty == 'plomberie') ? 'selected' : ''; ?>>Plomberie</option>
                    <option value="informatique" <?php echo ($specialty == 'informatique') ? 'selected' : ''; ?>>Informatique</option>
                    <option value="electromenager" <?php echo ($specialty == 'electromenager') ? 'selected' : ''; ?>>Électroménager</option>
                    <option value="automobile" <?php echo ($specialty == 'automobile') ? 'selected' : ''; ?>>Automobile</option>
                    <option value="bricolage" <?php echo ($specialty == 'bricolage') ? 'selected' : ''; ?>>Bricolage / Général</option>
                    <option value="autres" <?php echo ($specialty == 'autres') ? 'selected' : ''; ?>>Autres</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="experienceYears" class="form-label">Années d'expérience</label>
                <input type="number" class="form-control" id="experienceYears" name="experienceYears" min="0" max="50" value="<?php echo htmlspecialchars($experienceYears); ?>" required>
            </div>
            <button type="submit" name="valider" class="btn btn-success">Ajouter</button>
            <a href="gest_technicien.php" class="btn btn-secondary">Annuler</a>
        </form>
    </div>
</body>
</html>