<?php
session_start(); // Démarre la session en début de script

// --- Configuration de la base de données ---
$server_name = "localhost";
$user_name = "root";
$psw = ""; // Ton mot de passe MySQL (laisse-le vide si tu n'en as pas)
$DB_name = "depanage"; // Le nom de ta base de données

// Configuration for error logging (important for production)
ini_set('display_errors', 0); // Disable display of errors in production
ini_set('log_errors', 1);     // Enable logging errors
ini_set('error_log', __DIR__ . '/php-error.log'); // Specify error log file path
error_reporting(E_ALL);       // Report all PHP errors

// Variables pour stocker les messages de succès ou d'erreur à afficher
$message = '';
$message_type = '';

// Variables pour pré-remplir le formulaire en cas d'erreur
$firstName = '';
$lastName = '';
$email = '';
$phone = '';
$address = ''; // Correctly mapped to 'zone' in DB logic below
$specialty = '';
$description = '';
$experienceYears = '';
$terms_accepted = false;

// --- Traitement du formulaire si soumis ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Connexion à la base de données
    $con = new mysqli($server_name, $user_name, $psw, $DB_name);

    // Vérifier la connexion
    if ($con->connect_error) {
        error_log("Erreur de connexion à la base de données : " . $con->connect_error);
        $message = "Désolé, une erreur technique est survenue. Veuillez réessayer plus tard.";
        $message_type = "danger";
    } else {
        $con->set_charset("utf8mb4");

        // Récupération et nettoyage des données du formulaire
        $firstName = trim(htmlspecialchars($_POST['firstName'] ?? ''));
        $lastName = trim(htmlspecialchars($_POST['lastName'] ?? ''));
        $email = trim(htmlspecialchars($_POST['email'] ?? ''));
        $phone = trim(htmlspecialchars($_POST['phone'] ?? '')); // Changed from numTechnicien for consistency
        $zone = trim(htmlspecialchars($_POST['address'] ?? '')); // Correctly map 'address' input to 'zone' variable
        $specialty = trim(htmlspecialchars($_POST['specialty'] ?? ''));
        $description = trim(htmlspecialchars($_POST['description'] ?? ''));
        $experienceYears = intval($_POST['experienceYears'] ?? 0);
        $password_plain = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirmPassword'] ?? '';
        $terms_accepted = isset($_POST['terms']);

        // 2. Validation des données
        $errors = [];
        if (empty($firstName)) { $errors[] = "Le prénom est requis."; }
        if (empty($lastName)) { $errors[] = "Le nom est requis."; }
        if (empty($email)) { $errors[] = "L'adresse email est requise."; }
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = "L'adresse email n'est pas valide."; }
        if (empty($phone)) { $errors[] = "Le numéro de téléphone est requis."; }
        // Validation spécifique pour les numéros de téléphone camerounais (commence par 6, 9 chiffres)
        elseif (!preg_match("/^(\+237|00237)?(6[5-9]\d{7})$/", $phone)) {
            $errors[] = "Le numéro de téléphone n'est pas valide (doit commencer par 6 et contenir 9 chiffres après l'indicatif).";
        }
        if (empty($zone)) { $errors[] = "La zone d'intervention est requise."; }
        if (empty($specialty)) { $errors[] = "Veuillez choisir votre spécialité principale."; }
        if (empty($description)) { $errors[] = "La description de votre expérience est requise."; }
        elseif (strlen($description) < 50) { $errors[] = "La description doit contenir au moins 50 caractères."; }
        if ($experienceYears < 0 || $experienceYears > 50) { $errors[] = "Le nombre d'années d'expérience n'est pas valide."; }
        if (empty($password_plain)) { $errors[] = "Le mot de passe est requis."; }
        elseif (strlen($password_plain) < 6) { $errors[] = "Le mot de passe doit contenir au moins 6 caractères."; }
        if ($password_plain !== $confirmPassword) { $errors[] = "Les mots de passe ne correspondent pas."; }
        if (!$terms_accepted) { $errors[] = "Vous devez accepter les conditions générales d'utilisation."; }

        // Gestion du téléchargement des fichiers
        $upload_dir = "uploads/techniciens/";
        $certifications_to_upload = []; // Array to hold details of certifications to process
        $profile_picture_path_db = 'uploads/default_profile.png'; // Valeur par défaut de la DB

        // Create the upload directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0777, true)) {
                $errors[] = "Impossible de créer le dossier d'upload. Contactez l'administrateur.";
            }
        }
        
        // Attempt to upload files only if no directory creation error
        if (!in_array("Impossible de créer le dossier d'upload. Contactez l'administrateur.", $errors)) {
            // Allowed extensions and max sizes
            $allowed_ext_certs = ['pdf', 'jpg', 'jpeg', 'png'];
            $max_size_certs = 5 * 1024 * 1024; // 5MB
            $allowed_ext_profile = ['jpg', 'jpeg', 'png'];
            $max_size_profile = 2 * 1024 * 1024; // 2MB

            // MIME type validation setup
            $finfo = finfo_open(FILEINFO_MIME_TYPE);

            // Uploading certifications
            if (isset($_FILES['certifications']) && !empty($_FILES['certifications']['name'][0])) {
                foreach ($_FILES['certifications']['name'] as $key => $name) {
                    $file_tmp = $_FILES['certifications']['tmp_name'][$key];
                    $file_size = $_FILES['certifications']['size'][$key];
                    $file_error = $_FILES['certifications']['error'][$key];

                    if ($file_error === UPLOAD_ERR_OK) {
                        $file_ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                        $mime_type = finfo_file($finfo, $file_tmp);

                        if (in_array($file_ext, $allowed_ext_certs) && in_array($mime_type, ['application/pdf', 'image/jpeg', 'image/png']) && $file_size <= $max_size_certs) {
                            $new_file_name = uniqid('cert_') . '.' . $file_ext;
                            $upload_file = $upload_dir . $new_file_name;
                            if (move_uploaded_file($file_tmp, $upload_file)) {
                                $certifications_to_upload[] = $upload_file;
                            } else {
                                $errors[] = "Erreur lors du téléchargement du fichier de certification '{$name}'.";
                            }
                        } else {
                            $errors[] = "Fichier de certification '{$name}' non valide (taille max 5MB, formats: PDF, JPG, PNG).";
                        }
                    } elseif ($file_error !== UPLOAD_ERR_NO_FILE) {
                        $errors[] = "Erreur de téléchargement pour le fichier '{$name}'. Code: " . $file_error;
                    }
                }
            }

            // Uploading profile picture
            if (isset($_FILES['profilePicture']) && $_FILES['profilePicture']['error'] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['profilePicture']['tmp_name'];
                $file_size = $_FILES['profilePicture']['size'];
                $file_ext = strtolower(pathinfo($_FILES['profilePicture']['name'], PATHINFO_EXTENSION));
                $mime_type = finfo_file($finfo, $file_tmp);

                if (in_array($file_ext, $allowed_ext_profile) && in_array($mime_type, ['image/jpeg', 'image/png']) && $file_size <= $max_size_profile) {
                    $new_file_name = uniqid('profile_') . '.' . $file_ext;
                    $upload_file = $upload_dir . $new_file_name;
                    if (move_uploaded_file($file_tmp, $upload_file)) {
                        $profile_picture_path_db = $upload_file;
                    } else {
                        $errors[] = "Erreur lors du téléchargement de la photo de profil.";
                    }
                } else {
                    $errors[] = "Photo de profil non valide (taille max 2MB, formats: JPG, PNG).";
                }
            }
            finfo_close($finfo); // Close fileinfo resource
        }

        if (!empty($errors)) {
            $message = implode("<br>", $errors);
            $message_type = "danger";
        } else {
            // --- Check if email or phone number already exists ---
            $checkStmt = $con->prepare("SELECT id_technicien FROM technicien WHERE email = ? OR num_technicien = ?");
            if ($checkStmt === false) {
                error_log("Erreur de préparation de la requête (vérif email/phone technicien) : " . $con->error);
                $message = "Une erreur est survenue lors de la vérification de l'email ou du numéro de technicien.";
                $message_type = "danger";
            } else {
                $checkStmt->bind_param("ss", $email, $phone);
                $checkStmt->execute();
                $checkStmt->store_result();

                if ($checkStmt->num_rows > 0) {
                    $message = "Cette adresse email ou ce numéro de téléphone est déjà enregistré. Si vous pensez qu'il s'agit d'une erreur ou si votre compte a été désactivé, veuillez contacter l'administration.";
                    $message_type = "danger";
                } else {
                    // Hashing the password
                    $password_hashed = password_hash($password_plain, PASSWORD_DEFAULT);
                    
                    // Insertion of data into the 'technicien' table
                    // Note: 'chemin_certifications' column removed from this insert
                    $stmt = $con->prepare("INSERT INTO technicien (prenom, nom, password, num_technicien, email, zone, specialite, description, annees_experience, photo_profil_path, first_login, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    if ($stmt === false) {
                        error_log("Erreur de préparation de la requête (insertion technicien) : " . $con->error);
                        $message = "Une erreur interne est survenue lors de l'enregistrement de votre compte.";
                        $message_type = "danger";
                    } else {
                        $firstLogin = 2; // Set to 1 if you want to force password change on first login
                        $isActive = 1;   // New technician is active by default

                        $stmt->bind_param("sssssssssisi", 
                            $firstName, $lastName, $password_hashed, $phone, $email, $zone,
                            $specialty, $description, $experienceYears,
                            $profile_picture_path_db, $firstLogin, $isActive
                        );

                        if ($stmt->execute()) {
                            $technicien_id = $con->insert_id; // Get the ID of the newly inserted technician

                            // --- Insert certifications into the 'certifications' table ---
                            if (!empty($certifications_to_upload)) {
                                $certStmt = $con->prepare("INSERT INTO certifications (id_technicien, chemin_certification) VALUES (?, ?)");
                                if ($certStmt === false) {
                                    error_log("Erreur de préparation de la requête (insertion certifications) : " . $con->error);
                                    // Optionally, you might want to delete the technician if certification upload fails
                                } else {
                                    foreach ($certifications_to_upload as $cert_path) {
                                        $certStmt->bind_param("is", $technicien_id, $cert_path);
                                        if (!$certStmt->execute()) {
                                            error_log("Erreur lors de l'insertion de la certification {$cert_path} pour le technicien {$technicien_id}: " . $certStmt->error);
                                            // You could add an error message here, but the technician is already registered
                                        }
                                    }
                                    $certStmt->close();
                                }
                            }

                            // Registration successful!
                            $_SESSION['message'] = "Votre inscription a été soumise avec succès ! Vous pouvez maintenant vous connecter.";
                            $_SESSION['message_type'] = "success";
                            header("Location: connexion_technicien_cl.php");
                            exit();
                        } else {
                            $message = "Échec de l'inscription du technicien. Veuillez réessayer. Erreur: " . $stmt->error;
                            $message_type = "danger";
                        }
                        $stmt->close();
                    }
                }
                $checkStmt->close();
            }
        }
        $con->close();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription Technicien - VotreSite</title>
    <link rel="icon" type="image/png" href="mon_logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
        /* Styles personnalisés pour cette page */
        :root {
            --bs-primary: #007bff; /* Bleu Bootstrap par défaut */
            --bs-secondary: #6c757d; /* Gris secondaire */
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

        /* Navbar améliorée */
        .navbar {
            background-color: var(--bs-dark) !important; /* Fond sombre */
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            position: fixed; /* Rend la navbar fixe en haut */
            top: 0;
            width: 100%;
            z-index: 1030; /* Assure que la barre de navigation est au-dessus de tout */
        }

        .navbar-collapse {
            z-index: 1029; /* Le contenu du menu déroulant doit être juste en dessous de la navbar, mais au-dessus des autres éléments de la page */
            background-color: var(--bs-dark); /* Donne un fond au menu lorsqu'il est ouvert */
            overflow-y: auto; /* Permet le défilement si le contenu du menu est trop long */
            max-height: calc(100vh - 76px); /* Ajuste la hauteur maximale pour ne pas dépasser l'écran, 76px est la hauteur estimée de votre navbar */
        }

        .navbar-brand {
            padding: 0; /* Supprime le padding par défaut pour l'image */
        }

        .navbar-brand img {
            max-width: 200px; /* Taille plus raisonnable pour un logo */
            height: auto;
            border-radius: 8px; /* Adoucissement des coins */
            object-fit: contain; /* Assure que le logo entier est visible */
        }

        .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.75) !important; /* Couleur de texte claire */
            font-weight: 500;
            margin-right: 15px; /* Espacement entre les liens */
            transition: color 0.3s ease;
        }

        .navbar-nav .nav-link:hover,
        .navbar-nav .nav-link.active {
            color: var(--bs-primary) !important; /* Couleur primaire au survol/actif */
        }

        .btn-dark {
            background-color: var(--bs-primary); /* Bouton primaire pour se connecter */
            border-color: var(--bs-primary);
            transition: background-color 0.3s ease, border-color 0.3s ease;
        }

        .btn-dark:hover {
            background-color: #0056b3; /* Teinte plus foncée au survol */
            border-color: #0056b3;
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(rgba(0, 123, 255, 0.8), rgba(0, 86, 179, 0.8)), url('path/to/your/hero-background.jpg') no-repeat center center/cover; /* Ajoutez une image de fond si vous en avez une */
            min-height: 450px; /* Hauteur minimale pour la section */
            display: flex;
            align-items: center;
            justify-content: center;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            color: white;
            padding: 6rem 0; /* Plus de padding pour l'esthétique */
        }

        .hero-section h1 {
            font-size: 3.5rem;
            margin-bottom: 1.5rem;
        }

        .hero-section p.lead {
            font-size: 1.25rem;
            margin-bottom: 2.5rem;
        }

        .hero-section .div2 {
            background-color: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            display: inline-block; /* Pour que la div s'adapte au contenu */
        }

        .hero-section .div2 input[type="text"] {
            width: 280px; /* Taille fixe pour les inputs */
            height: 48px;
            padding: 0 15px;
            margin: 10px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 1rem;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .hero-section .div2 input[type="submit"] {
            height: 48px;
            padding: 0 30px;
            font-size: 1.1rem;
            border-radius: 8px;
            margin-top: 20px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        /* Sections de contenu générales */
        section {
            padding: 4rem 0;
        }

        section h2 {
            font-weight: 700;
            color: var(--bs-dark);
            margin-bottom: 3rem;
            position: relative;
            padding-bottom: 10px;
        }

        section h2::after {
            content: '';
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            bottom: 0;
            width: 80px;
            height: 4px;
            background-color: var(--bs-primary);
            border-radius: 2px;
        }

        .card {
            border: none;
            border-radius: 12px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08); /* Ombre plus prononcée */
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .card-title {
            color: var(--bs-primary);
            font-weight: 600;
            margin-bottom: 15px;
        }

        /* How It Works Section */
        #how-it-works .icon-circle {
            background-color: var(--bs-light);
            border: 2px solid var(--bs-primary) !important;
            color: var(--bs-primary);
            font-size: 3rem;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        #how-it-works .icon-circle:hover {
            background-color: var(--bs-primary);
            color: white;
        }

        #how-it-works h4 {
            color: var(--bs-primary);
            font-weight: 600;
            margin-top: 15px;
        }

        /* Contact Form */
        #contact .form-control,
        #contact .form-control:focus {
            border-radius: 8px;
            border-color: #ddd;
            box-shadow: none;
        }
        #contact .form-control:focus {
            border-color: var(--bs-primary);
            box-shadow: 0 0 0 0.25rem rgba(0, 123, 255, 0.25);
        }

        /* Footer */
        .footer {
            background-color: var(--bs-dark) !important;
            color: rgba(255, 255, 255, 0.7);
        }

        .footer h3 {
            color: white;
            font-weight: 600;
        }

        .footer a {
            color: rgba(255, 255, 255, 0.6);
            transition: color 0.3s ease;
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
            color: var(--bs-success); /* Vert pour les icônes sociales au survol */
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
                        <li class="nav-item"><a class="nav-link active" href="inscription_technicien_cl.php">je suis un technicien</a></li>
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

    <main class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-9 col-lg-8">
                    <div class="card shadow p-4">
                        <h2 class="card-title text-center mb-4 fw-bold text-primary">Devenez un Technicien Certifié !</h2>
                        <p class="text-center text-muted mb-4">Développez votre activité et trouvez de nouvelles missions en rejoignant notre réseau.</p>

                        <?php if (!empty($message)): ?>
                            <div class="alert alert-<?php echo $message_type; ?> text-center" role="alert">
                                <?php echo $message; ?>
                            </div>
                        <?php endif; ?>

                        <form action="inscription_technicien.php" method="POST" enctype="multipart/form-data">
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label for="firstName" class="form-label">Prénom</label>
                                    <input type="text" class="form-control" id="firstName" name="firstName" value="<?php echo htmlspecialchars($firstName); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="lastName" class="form-label">Nom</label>
                                    <input type="text" class="form-control" id="lastName" name="lastName" value="<?php echo htmlspecialchars($lastName); ?>" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Adresse Email</label>
                                <input type="email" class="form-control" id="email" name="email" placeholder="votre@email.com" value="<?php echo htmlspecialchars($email); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="phone" class="form-label">Numéro de téléphone</label>
                                <input type="tel" class="form-control" id="phone" name="phone" placeholder="+237 6XXXXXXXX" value="<?php echo htmlspecialchars($phone); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="address" class="form-label">Localisation (Ville, Quartier)</label>
                                <input type="text" class="form-control" id="address" name="zone" placeholder="Ex: Douala, Akwa" value="<?php echo htmlspecialchars($address); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="specialty" class="form-label">Votre Spécialité Principale</label>
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
                                <label for="description" class="form-label">Décrivez votre expérience et vos services (min. 50 caractères)</label>
                                <textarea class="form-control" id="description" name="description" rows="4" minlength="50" required><?php echo htmlspecialchars($description); ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="experienceYears" class="form-label">Années d'expérience</label>
                                <input type="number" class="form-control" id="experienceYears" name="experienceYears" min="0" max="50" value="<?php echo htmlspecialchars($experienceYears); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="certifications" class="form-label">Téléchargez vos certifications / diplômes (PDF, JPG, PNG)</label>
                                <input type="file" class="form-control" id="certifications" name="certifications[]" multiple accept=".pdf,.jpg,.jpeg,.png">
                                <small class="form-text text-muted">Vous pouvez télécharger plusieurs fichiers (max 5MB par fichier).</small>
                            </div>
                            <div class="mb-3">
                                <label for="profilePicture" class="form-label">Photo de profil (Optionnel)</label>
                                <input type="file" class="form-control" id="profilePicture" name="profilePicture" accept=".jpg,.jpeg,.png">
                                <small class="form-text text-muted">Taille maximale 2MB.</small>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Mot de passe</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="mb-4">
                                <label for="confirmPassword" class="form-label">Confirmer le mot de passe</label>
                                <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" required>
                            </div>
                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" id="terms" name="terms" <?php echo $terms_accepted ? 'checked' : ''; ?> required>
                                <label class="form-check-label" for="terms">
                                    J'accepte les <a href="#" class="text-decoration-none">Conditions Générales d'Utilisation</a> et la <a href="#" class="text-decoration-none">Politique de Confidentialité</a>.
                                </label>
                            </div>
                            <button type="submit" class="btn btn-success w-100 py-2 mb-3">Finaliser l'Inscription</button>
                            <p class="text-center mb-0">Déjà un compte ? <a href="connexion_technicien_cl.php" class="text-decoration-none">Connectez-vous</a></p>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="footer bg-dark text-white-50 py-5">
        <div class="container">
            <div class="row">
                <div class="col-md-3 mb-4 mb-md-0">
                    <img src="mon logo 3.png" alt="HomeTechnician Logo" style="width: 200px; height: 50px;border-radius: 5px;">
                    <p>La plateforme qui connecte les clients avec des techniciens qualifiés pour tous leurs besoins de service au Cameroun.</p>
                </div>
                <div class="col-md-3 mb-4 mb-md-0">
                    <h3 class="text-white mb-3">Liens Utiles</h3>
                    <ul class="list-unstyled">
                        <li><a href="a_propos_client.php" class="text-white-50 text-decoration-none" >À Propos de Nous</a></li>
                        <li><a href="faq_client.php" class="text-white-50 text-decoration-none">FAQ</a></li>
                        <li><a href="#" class="text-white-50 text-decoration-none">Mentions Légales</a></li>
                        <li><a href="#" class="text-white-50 text-decoration-none">Politique de Confidentialité</a></li>
                        <li><a href="#" class="text-white-50 text-decoration-none">Conditions Générales</a></li>
                    </ul>
                </div>
                <div class="col-md-3 mb-4 mb-md-0">
                    <h3 class="text-white mb-3">Catégories Populaires</h3>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-white-50 text-decoration-none ">Réparation d'Ordinateur</a></li>
                        <li><a href="#" class="text-white-50 text-decoration-none  ">Installation Électrique</a></li>
                        <li><a href="#" class="text-white-50 text-decoration-none ">Dépannage Plomberie</a></li>
                        <li><a href="#" class="text-white-50 text-decoration-none ">Entretien Climatisation</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h3 class="text-white mb-3">Contactez-nous</h3>
                    <a href="mailto:andrelkamdem5@gmail.com " style="text-decoration: none;" class="nav-link"><i class="fas fa-envelope me-2 text-success"></i>andrelkamdem5@gmail.com</a>
                    <p><i class="fas fa-phone me-2 text-success nav-link"></i> +237 654 023 677</p>
                    <div class="social-links mt-3">
                        <a href="#" class="text-white-50 me-3 fs-4"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-white-50 me-3 fs-4"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-white-50 me-3 fs-4"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#" class="text-white-50 fs-4"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom text-center pt-4 mt-4 border-top border-secondary">
                <p class="mb-0">&copy; 2025 VotreSite. Tous droits réservés.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" ></script>
    <script src="js/script.js"></script>
</body>
</html>