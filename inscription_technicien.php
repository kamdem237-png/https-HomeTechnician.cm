<?php
session_start(); // Démarre la session en début de script

// --- Configuration de la base de données ---
$server_name = "localhost";
$user_name = "root";
$psw = ""; // Ton mot de passe MySQL (laisse-le vide si tu n'en as pas)
$DB_name = "depanage"; // Le nom de ta base de données

// Variables pour stocker les messages de succès ou d'erreur à afficher
$message = '';
$message_type = '';

// Variables pour pré-remplir le formulaire en cas d'erreur
$firstName = '';
$lastName = '';
$email = '';
$numTechnicien = ''; 
$zone = ''; 
$specialty = '';
$description = '';
$experienceYears = '';
$terms_accepted = false; 

// Dossier d'upload et constantes pour les fichiers
define('UPLOAD_DIR', 'uploads/techniciens/');
define('MAX_CERT_SIZE', 5 * 1024 * 1024); // 5 Mo
define('MAX_PROFILE_PIC_SIZE', 2 * 1024 * 1024); // 2 Mo
define('ALLOWED_CERT_EXTS', ['pdf', 'jpg', 'jpeg', 'png']);
define('ALLOWED_PROFILE_PIC_EXTS', ['jpg', 'jpeg', 'png']);

// Fonction pour supprimer les fichiers téléchargés en cas d'échec
function deleteUploadedFiles($filePaths) {
    foreach ($filePaths as $path) {
        if (file_exists($path) && $path !== UPLOAD_DIR . 'default_profile.png') {
            unlink($path);
        }
    }
}

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
        $numTechnicien = trim(htmlspecialchars($_POST['phone'] ?? '')); 
        $zone = trim(htmlspecialchars($_POST['zone'] ?? '')); 
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
        if (empty($numTechnicien)) { $errors[] = "Le numéro de téléphone est requis."; }
        // Validation spécifique pour les numéros de téléphone camerounais (commence par 6, 9 chiffres)
        elseif (!preg_match("/^(\+237|00237)?(6[5-9]\d{7})$/", $numTechnicien)) {
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

        // Initialisation des chemins de fichiers pour la suppression en cas d'erreur
        $uploaded_file_paths = [];
        $profile_picture_path_db = UPLOAD_DIR . 'default_profile.png'; // Valeur par défaut de la DB

        // Créer le dossier d'uploads s'il n'existe pas
        if (!is_dir(UPLOAD_DIR)) {
            if (!mkdir(UPLOAD_DIR, 0777, true)) {
                $errors[] = "Impossible de créer le dossier d'upload. Contactez l'administrateur.";
            }
        }
        
        // Tentative d'upload des fichiers seulement si aucune erreur de création de répertoire
        if (!in_array("Impossible de créer le dossier d'upload. Contactez l'administrateur.", $errors)) {
            // Téléchargement des certifications
            if (isset($_FILES['certifications']) && !empty($_FILES['certifications']['name'][0])) {
                foreach ($_FILES['certifications']['name'] as $key => $name) {
                    $file_tmp = $_FILES['certifications']['tmp_name'][$key];
                    $file_size = $_FILES['certifications']['size'][$key];
                    $file_error = $_FILES['certifications']['error'][$key];

                    if ($file_error === UPLOAD_ERR_OK) {
                        $file_ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                        
                        if (in_array($file_ext, ALLOWED_CERT_EXTS) && $file_size <= MAX_CERT_SIZE) {
                            $new_file_name = uniqid('cert_') . '.' . $file_ext;
                            $upload_file = UPLOAD_DIR . $new_file_name;
                            if (move_uploaded_file($file_tmp, $upload_file)) {
                                $uploaded_file_paths[] = $upload_file; // Ajout au tableau pour la DB
                            } else {
                                $errors[] = "Erreur lors du téléchargement du fichier de certification '{$name}'.";
                            }
                        } else {
                            $errors[] = "Fichier de certification '{$name}' non valide (taille max " . (MAX_CERT_SIZE / 1024 / 1024) . "MB, formats: " . implode(', ', ALLOWED_CERT_EXTS) . ").";
                        }
                    } elseif ($file_error !== UPLOAD_ERR_NO_FILE) {
                                $errors[] = "Erreur de téléchargement pour le fichier '{$name}'. Code: " . $file_error;
                    }
                }
            }

            // Téléchargement de la photo de profil
            if (isset($_FILES['profilePicture']) && $_FILES['profilePicture']['error'] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['profilePicture']['tmp_name'];
                $file_size = $_FILES['profilePicture']['size'];
                $file_ext = strtolower(pathinfo($_FILES['profilePicture']['name'], PATHINFO_EXTENSION));
                
                if (in_array($file_ext, ALLOWED_PROFILE_PIC_EXTS) && $file_size <= MAX_PROFILE_PIC_SIZE) {
                    $new_file_name = uniqid('profile_') . '.' . $file_ext;
                    $upload_file = UPLOAD_DIR . $new_file_name;
                    if (move_uploaded_file($file_tmp, $upload_file)) {
                        $profile_picture_path_db = $upload_file; // Met à jour le chemin si l'upload est réussi
                        $uploaded_file_paths[] = $profile_picture_path_db; // Ajout au tableau des fichiers à supprimer
                    } else {
                        $errors[] = "Erreur lors du téléchargement de la photo de profil.";
                    }
                } else {
                    $errors[] = "Photo de profil non valide (taille max " . (MAX_PROFILE_PIC_SIZE / 1024 / 1024) . "MB, formats: " . implode(', ', ALLOWED_PROFILE_PIC_EXTS) . ").";
                }
            }
        }

        if (!empty($errors)) {
            $message = implode("<br>", $errors);
            $message_type = "danger";
            // Supprimer les fichiers téléchargés si la validation échoue
            deleteUploadedFiles($uploaded_file_paths);
        } else {
            // --- Vérifier si l'email ou le numéro de technicien existe déjà (actif ou inactif) ---
            $checkStmt = $con->prepare("SELECT id_technicien FROM technicien WHERE email = ? OR num_technicien = ?");
            if ($checkStmt === false) {
                error_log("Erreur de préparation de la requête (vérif email/num_technicien technicien) : " . $con->error);
                $message = "Une erreur est survenue lors de la vérification de l'email ou du numéro de technicien.";
                $message_type = "danger";
                deleteUploadedFiles($uploaded_file_paths); // Supprimer les fichiers en cas d'erreur de préparation
            } else {
                $checkStmt->bind_param("ss", $email, $numTechnicien);
                $checkStmt->execute();
                $checkStmt->store_result();

                if ($checkStmt->num_rows > 0) {
                    // Si l'e-mail ou le numéro de téléphone existe, empêcher la réinscription.
                    $message = "Cette adresse email ou ce numéro de téléphone est déjà enregistré. Si vous pensez qu'il s'agit d'une erreur ou si votre compte a été désactivé, veuillez contacter l'administration.";
                    $message_type = "danger";
                    deleteUploadedFiles($uploaded_file_paths); // Supprimer les fichiers
                } else {
                    // --- Début de la transaction ---
                    $con->begin_transaction();
                    $success = true;

                    // 4. Hachage du mot de passe
                    $password_hashed = password_hash($password_plain, PASSWORD_DEFAULT);
                    
                    // 5. Insertion des données dans la table 'technicien'
                    // Le champ 'chemin_certifications' est retiré de cette table
                    $stmt = $con->prepare("INSERT INTO technicien (prenom, nom, password, num_technicien, email, zone, specialite, description, annees_experience, photo_profil_path, first_login, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    if ($stmt === false) {
                        error_log("Erreur de préparation de la requête (insertion technicien) : " . $con->error);
                        $message = "Une erreur interne est survenue lors de l'enregistrement de votre compte.";
                        $message_type = "danger";
                        $success = false;
                    } else {
                        $firstLogin = 2; // Mettez à 1 si vous voulez forcer le changement de mot de passe à la première connexion
                        $isActive = 1;   // Nouveau technicien est actif par défaut

                        $stmt->bind_param("sssssssssiis", 
                            $firstName, $lastName, $password_hashed, $numTechnicien, $email, $zone,
                            $specialty, $description, $experienceYears,
                            $profile_picture_path_db, $firstLogin, $isActive
                        );

                        if (!$stmt->execute()) {
                            $message = "Échec de l'inscription du technicien. Veuillez réessayer. Erreur: " . $stmt->error;
                            $message_type = "danger";
                            $success = false;
                        } else {
                            $technicien_id = $con->insert_id; // Récupère l'ID du technicien nouvellement inséré

                            // Insertion des certifications dans la table 'certifications'
                            if (!empty($uploaded_file_paths)) {
                                $certStmt = $con->prepare("INSERT INTO certifications (id_technicien, chemin_certification) VALUES (?, ?)");
                                if ($certStmt === false) {
                                    error_log("Erreur de préparation de la requête (insertion certifications) : " . $con->error);
                                    $message = "Une erreur interne est survenue lors de l'enregistrement de vos certifications.";
                                    $message_type = "danger";
                                    $success = false;
                                } else {
                                    foreach ($uploaded_file_paths as $cert_path) {
                                        // Assurez-vous que seul le chemin des certifications est lié ici, pas la photo de profil
                                        if ($cert_path !== $profile_picture_path_db || $profile_picture_path_db === UPLOAD_DIR . 'default_profile.png') { // Empêche la photo de profil d'être insérée comme une certification
                                            $certStmt->bind_param("is", $technicien_id, $cert_path);
                                            if (!$certStmt->execute()) {
                                                $message = "Échec de l'insertion d'une certification. Erreur: " . $certStmt->error;
                                                $message_type = "danger";
                                                $success = false;
                                                break; // Sortir de la boucle si une insertion échoue
                                            }
                                        }
                                    }
                                    $certStmt->close();
                                }
                            }
                        }
                        $stmt->close();
                    }

                    if ($success) {
                        $con->commit(); // Valider toutes les opérations
                        $_SESSION['message'] = "Votre inscription a été soumise avec succès ! Vous pouvez maintenant vous connecter.";
                        $_SESSION['message_type'] = "success";
                        header("Location: connexion_technicien_cl.php"); // Redirection vers la page de connexion du technicien
                        exit(); // TRÈS IMPORTANT : Arrête l'exécution du script après la redirection
                    } else {
                        $con->rollback(); // Annuler toutes les opérations si une erreur est survenue
                        deleteUploadedFiles($uploaded_file_paths); // Supprimer les fichiers téléchargés
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
        /* Styles personnalisés pour cette page (copiés de accueil.html et ajustés) */
        :root {
            --bs-primary: #007bff; /* Bleu Bootstrap par défaut */
            --bs-secondary: #6c757d; /* Gris secondaire */
            --bs-success: #28a745;
            --bs-info: #17a2b8; /* Ajouté pour l'exemple Innovation */
            --bs-warning: #ffc107; /* Ajouté pour l'exemple Communauté */
            --bs-danger: #dc3545; /* Ajouté pour l'exemple Excellence */
            --bs-dark: #343a40;
            --bs-light: #f8f9fa;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
            background-color: var(--bs-light);
            /* Ajout du padding-top pour compenser la navbar fixe, comme dans accueil.html */
            padding-top: 76px; 
        }

        /* Navbar améliorée (copiée de accueil.html) */
        .navbar {
            background-color: var(--bs-dark) !important; /* Fond sombre */
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            position: fixed; /* Rend la navbar fixe en haut */
            top: 0;
            width: 100%;
            z-index: 1030;
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
            color: var(--bs-primary) !important; /* Couleur primaire au survol/actif (bleu) */
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

        /* Styles généraux des sections et titres (conservés de a_propos.html, ajustés si nécessaire) */
        section {
            padding: 4rem 0;
        }

        h2.display-5 { /* Style pour les titres H2 des sections */
            font-weight: 700;
            color: var(--bs-dark); /* Couleur par défaut pour les titres de section */
            margin-bottom: 3rem;
            position: relative;
            padding-bottom: 10px;
            text-align: center; /* Centrer tous les titres de section */
        }

        h2.display-5::after {
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

        /* Pour les titres spécifiques qui ont déjà une couleur (Mission, Vision) */
        h2.display-5.text-primary::after,
        h2.display-5.text-success::after {
            background-color: currentColor; /* Utilisez la couleur du texte */
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

        /* Spécifique à la section équipe */
        .team-member img {
            width: 150px; 
            height: 150px;
            object-fit: cover;
            border: 5px solid var(--bs-light); 
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        .team-member .social-links a {
            font-size: 1.25rem;
            transition: color 0.3s ease;
        }

        .team-member .social-links a:hover {
            color: var(--bs-dark) !important;
        }

        /* Footer (ajusté pour la couleur des icônes sociales au survol) */
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
            color: var(--bs-primary); /* BLEU pour les icônes sociales au survol, cohérent avec la navbar */
        }

    </style>
</head>
<body>

    <header>
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark py-3 shadow-sm fixed-top">
            <div class="container-fluid">
                <a class="navbar-brand" href="site de mise en relation techniciens&clients.html">
                    <img src="mon logo 3.png" alt="HomeTechnician Logo">
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        <li class="nav-item"><a class="nav-link" aria-current="page" href="site de mise en relation techniciens&clients.html">Accueil</a></li>
                        <li class="nav-item"><a class="nav-link" href="a_propos.html">À propos de nous</a></li>
                        <li class="nav-item"><a class="nav-link" href="site de mise en relation techniciens&clients.html#nos_services">Nos services</a></li>
                        <li class="nav-item"><a class="nav-link active" href="inscription_Technicien.php">je suis un technicien</a></li>
                        <li class="nav-item"><a class="nav-link" href="site de mise en relation techniciens&clients.html#contact-section">Contact</a></li>
                    </ul>
                    <div class="d-flex ms-lg-3">
                        <div class="d-flex ms-lg-3">
                        <div class="dropdown me-6">
                            <a href="connexion_client.php" class="btn btn-outline-primary">
                                Connexion
                            </a>
                            
                            <a href="inscription_client.php" class="btn btn-primary">
                                Inscription
                            </a>
                        </div>
                        </div>
                    </div>
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

                        <form action="inscription_Technicien.php" method="POST" enctype="multipart/form-data">
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label for="firstName" class="form-label">Prénom</label>
                                    <input type="text" class="form-control" id="firstName" name="firstName" value="<?php echo htmlspecialchars($firstName ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="lastName" class="form-label">Nom</label>
                                    <input type="text" class="form-control" id="lastName" name="lastName" value="<?php echo htmlspecialchars($lastName ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Adresse Email</label>
                                <input type="email" class="form-control" id="email" name="email" placeholder="votre@email.com" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="phone" class="form-label">Numéro de téléphone</label>
                                <input type="tel" class="form-control" id="phone" name="phone" placeholder="+237 6XXXXXXXX" value="<?php echo htmlspecialchars($numTechnicien ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="zone" class="form-label">Votre Zone d'Intervention (Ville/Région)</label>
                                <input type="text" class="form-control" id="zone" name="zone" placeholder="Ex: Douala, Yaoundé, Littoral" value="<?php echo htmlspecialchars($zone ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="specialty" class="form-label">Votre Spécialité Principale</label>
                                <select class="form-select" id="specialty" name="specialty" required>
                                    <option value="">Choisissez une spécialité...</option>
                                    <option value="electricite" <?php echo (($specialty ?? '') == 'electricite') ? 'selected' : ''; ?>>Électricité</option>
                                    <option value="plomberie" <?php echo (($specialty ?? '') == 'plomberie') ? 'selected' : ''; ?>>Plomberie</option>
                                    <option value="informatique" <?php echo (($specialty ?? '') == 'informatique') ? 'selected' : ''; ?>>Informatique</option>
                                    <option value="electromenager" <?php echo (($specialty ?? '') == 'electromenager') ? 'selected' : ''; ?>>Électroménager</option>
                                    <option value="automobile" <?php echo (($specialty ?? '') == 'automobile') ? 'selected' : ''; ?>>Automobile</option>
                                    <option value="bricolage" <?php echo (($specialty ?? '') == 'bricolage') ? 'selected' : ''; ?>>Bricolage / Général</option>
                                    <option value="autres" <?php echo (($specialty ?? '') == 'autres') ? 'selected' : ''; ?>>Autres</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Décrivez votre expérience et vos services (min. 50 caractères)</label>
                                <textarea class="form-control" id="description" name="description" rows="4" minlength="50" required><?php echo htmlspecialchars($description ?? ''); ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="experienceYears" class="form-label">Années d'expérience</label>
                                <input type="number" class="form-control" id="experienceYears" name="experienceYears" min="0" max="50" value="<?php echo htmlspecialchars($experienceYears ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="certifications" class="form-label">Téléchargez vos certifications / diplômes (PDF, JPG, PNG)</label>
                                <input type="file" class="form-control" id="certifications" name="certifications[]" multiple accept=".pdf,.jpg,.jpeg,.png">
                                <small class="form-text text-muted">Vous pouvez télécharger plusieurs fichiers (max <?php echo MAX_CERT_SIZE / 1024 / 1024; ?>MB par fichier).</small>
                            </div>
                            <div class="mb-3">
                                <label for="profilePicture" class="form-label">Photo de profil (Optionnel)</label>
                                <input type="file" class="form-control" id="profilePicture" name="profilePicture" accept=".jpg,.jpeg,.png">
                                <small class="form-text text-muted">Taille maximale <?php echo MAX_PROFILE_PIC_SIZE / 1024 / 1024; ?>MB.</small>
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
                            <p class="text-center mb-0">Déjà un compte ? <a href="connexion_technicien.php" class="text-decoration-none">Connectez-vous</a></p>
                            <p class="text-center mt-2 mb-0">Vous êtes client ? <a href="inscription_client.php" class="text-decoration-none">Inscrivez-vous ici</a></p>
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
                        <li><a href="a_propos.html" class="text-white-50 text-decoration-none" >À Propos de Nous</a></li>
                        <li><a href="faq.html" class="text-white-50 text-decoration-none">FAQ</a></li>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
</body>
</html>