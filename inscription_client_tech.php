<?php
session_start(); // Toujours démarrer la session au tout début du script

// --- Configuration de la base de données ---
$server_name = "localhost";
$user_name = "root";
$psw = ""; // !!! IMPORTANT : En production, utilisez un mot de passe fort et configurez-le correctement.
$DB_name = "depanage"; // Assurez-vous que cette base de données existe

// Variables pour stocker les messages de succès ou d'erreur à afficher
$message = '';
$message_type = ''; // 'success' ou 'danger'

// Variables pour pré-remplir le formulaire en cas d'erreur
$nom = '';
$email = '';
$telephone = '';
$zone = '';

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

        // Récupération des données du formulaire pour pré-remplir en cas d'erreur
        $nom = trim(htmlspecialchars($_POST['nom'] ?? ''));
        $email = trim(htmlspecialchars($_POST['email'] ?? ''));
        $telephone = trim(htmlspecialchars($_POST['telephone'] ?? ''));
        $zone = trim(htmlspecialchars($_POST['zone'] ?? ''));
        $mot_de_passe = $_POST['mot_de_passe'] ?? '';
        $confirmer_mot_de_passe = $_POST['confirmer_mot_de_passe'] ?? '';

        // 2. Validation des données
        $errors = [];
        if (empty($nom)) {
            $errors[] = "Le nom complet est requis.";
        }
        if (empty($email)) {
            $errors[] = "L'adresse email est requise.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "L'adresse email n'est pas valide.";
        }
        if (empty($telephone)) {
            $errors[] = "Le numéro de téléphone est requis.";
        }
        // Validation pour le format de téléphone au Cameroun (commence par 6, 9 chiffres)
        elseif (!preg_match("/^6[0-9]{8}$/", $telephone)) {
            $errors[] = "Le numéro de téléphone n'est pas valide (doit commencer par 6 et contenir 9 chiffres).";
        }
        if (empty($zone)) {
            $errors[] = "Votre zone (ville ou quartier) est requise.";
        }
        if (empty($mot_de_passe)) {
            $errors[] = "Le mot de passe est requis.";
        } elseif (strlen($mot_de_passe) < 6) {
            $errors[] = "Le mot de passe doit contenir au moins 6 caractères.";
        }
        if ($mot_de_passe !== $confirmer_mot_de_passe) {
            $errors[] = "Les mots de passe ne correspondent pas.";
        }

        if (!empty($errors)) {
            $message = implode("<br>", $errors);
            $message_type = "danger";
        } else {
            // Pas d'erreurs de validation initiales, procéder à la vérification de l'email et à l'insertion

            // 3. Vérifier si l'email existe déjà dans la table `client`
            $checkStmt = $con->prepare("SELECT id_client FROM client WHERE email = ?");
            if ($checkStmt === false) {
                error_log("Erreur de préparation de la requête (vérif email) : " . $con->error);
                $message = "Une erreur est survenue lors de la vérification de l'email.";
                $message_type = "danger";
            } else {
                $checkStmt->bind_param("s", $email);
                $checkStmt->execute();
                $checkStmt->store_result();

                if ($checkStmt->num_rows > 0) {
                    $message = "Cette adresse email est déjà enregistrée. Veuillez vous connecter ou utiliser une autre adresse.";
                    $message_type = "danger";
                } else {
                    // 4. Hachage du mot de passe
                    $password_hashed = password_hash($mot_de_passe, PASSWORD_DEFAULT);

                    // 5. Insertion des données dans la table `client`
                    // --- DÉBUT DE LA MODIFICATION ICI ---
                    // Ajout de 'first_login' dans la liste des colonnes et de sa valeur correspondante dans VALUES
                    $stmt = $con->prepare("INSERT INTO client (password, nom, num_client, email, zone, first_login) VALUES (?, ?, ?, ?, ?, ?)");
                    if ($stmt === false) {
                        error_log("Erreur de préparation de la requête (insertion) : " . $con->error);
                        $message = "Une erreur interne est survenue lors de l'enregistrement de votre compte.";
                        $message_type = "danger";
                    } else {
                        $first_login_value = 2; // La valeur à insérer pour 'first_login'
                        // "sssssi" pour les 6 paramètres: password_hashed (string), nom (string), telephone (string), email (string), zone (string), first_login_value (integer)
                        $stmt->bind_param("sssssi", $password_hashed, $nom, $telephone, $email, $zone, $first_login_value);

                        if ($stmt->execute()) {
                            // Redirection vers connexion_user.php si l'insertion est réussie
                            header("Location: connexion_technicien.php");
                            exit();
                        } else {
                            $message = "Échec de l'inscription. Veuillez réessayer. Erreur: " . $stmt->error;
                            $message_type = "danger";
                        }
                        $stmt->close();
                    }
                    // --- FIN DE LA MODIFICATION ICI ---
                }
                $checkStmt->close();
            }
        }
        $con->close(); // Fermer la connexion à la DB après toutes les opérations
    }
}
// Le code HTML commence ici
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VotreSite - Inscription Client</title>
    <link rel="icon" type="image/png" href="mon_logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="style.css">
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
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavTechnicien" aria-controls="navbarNavTechnicien" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNavTechnicien">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        <li class="nav-item"><a class="nav-link" aria-current="page" href="technicien.php">Accueil</a></li>
                        <li class="nav-item"><a class="nav-link" href="mon_compte_technicien.php">Mon compte</a></li>
                        <li class="nav-item"><a class="nav-link" href="missions_disponibles.php">Mes missions</a></li>
                        <li class="nav-item"><a class="nav-link active" href="connexion_client_tech.php">Je suis un client</a></li>
                    </ul>
                    <ul class="navbar-nav">
                        <li class="nav-item">
                            <a class="nav-link" href="logout_technicien.php">
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
                <div class="col-md-8 col-lg-7">
                    <div class="card shadow p-4">
                        <h2 class="card-title text-center mb-4">Inscription Client</h2>

                        <?php if (!empty($message)): ?>
                            <div class="alert alert-<?php echo $message_type; ?> text-center" role="alert">
                                <?php echo $message; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="inscription_client.php"> <div class="mb-3">
                                <label for="clientName" class="form-label">Nom Complet</label>
                                <input type="text" class="form-control" id="clientName" name="nom" value="<?php echo htmlspecialchars($nom); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="clientEmail" class="form-label">Adresse Email</label>
                                <input type="email" class="form-control" id="clientEmail" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="clientPhone" class="form-label">Numéro de Téléphone</label>
                                <input type="tel" class="form-control" id="clientPhone" name="telephone" placeholder="Ex: 6XXXXXXXX" value="<?php echo htmlspecialchars($telephone); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="clientLocation" class="form-label">Votre Zone (Ville ou Quartier)</label>
                                <input type="text" class="form-control" id="clientLocation" name="zone" placeholder="Ex: Douala, Akwa" value="<?php echo htmlspecialchars($zone); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="clientPassword" class="form-label">Mot de passe</label>
                                <input type="password" class="form-control" id="clientPassword" name="mot_de_passe" required>
                            </div>
                            <div class="mb-3">
                                <label for="confirmPassword" class="form-label">Confirmer le mot de passe</label>
                                <input type="password" class="form-control" id="confirmPassword" name="confirmer_mot_de_passe" required>
                            </div>
                            <div class="d-grid gap-2 mb-3">
                                <button type="submit" class="btn btn-primary" name="valider">S'inscrire</button>
                            </div>
                            <p class="text-center">Déjà un compte ? <a href="connexion_client.php">Connectez-vous ici</a></p>
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
                        <li><a href="a_propos_technicien.php" class="text-white-50 text-decoration-none" >À Propos de Nous</a></li>
                        <li><a href="faq_technicien.php" class="text-white-50 text-decoration-none">FAQ</a></li>
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
    <script src="script.js"></script>
</body>
</html>