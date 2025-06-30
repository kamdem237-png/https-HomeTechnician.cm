<?php
session_start();

// Assurez-vous que le chemin vers utils.php est correct
// C'est nécessaire si vous utilisez logUserAction() ici.
require_once 'utils.php'; 

// Paramètres de connexion à la base de données
$server_name = "localhost";
$user_name = "root";
$psw = "";
$DB_name = "depanage";

// Connexion à la base de données
$conn = new mysqli($server_name, $user_name, $psw, $DB_name);

// Vérification de la connexion
if ($conn->connect_error) {
    error_log("Erreur de connexion à la base de données : " . $conn->connect_error);
    // Afficher un message d'erreur générique à l'utilisateur
    $_SESSION['message'] = "Désolé, une erreur technique est survenue. Veuillez réessayer plus tard.";
    $_SESSION['message_type'] = "danger";
    header('Location: index.php'); // Ou la page de connexion elle-même
    exit();
}
$conn->set_charset("utf8mb4");

$message = ""; // Message d'erreur ou de succès
$message_type = ""; // Type du message (e.g., "success", "danger", "info", "warning")

// Traitement du formulaire de connexion si la requête est POST
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['valider'])) {
    $email = trim($_POST['email'] ?? '');
    $password_input = $_POST['password'] ?? '';

    // Vérification que les champs ne sont pas vides
    if (empty($email) || empty($password_input)) {
        $message = "Veuillez remplir tous les champs.";
        $message_type = "danger";
    } else {
        // --- Vérifier si l'utilisateur est un client ---
        // MODIFICATION ICI : Ajout de 'is_active' et 'is_banned' dans la sélection
        $stmtClient = $conn->prepare("SELECT id_client, nom, email, password, first_login, en_quarantaine, is_active, is_banned FROM client WHERE email = ?");
        if ($stmtClient) {
            $stmtClient->bind_param("s", $email);
            $stmtClient->execute();
            $resClient = $stmtClient->get_result();
            $client_data = $resClient->fetch_assoc();

            if ($client_data && password_verify($password_input, $client_data['password'])) {
                // --- NOUVELLES VÉRIFICATIONS : Statut du compte (actif, banni) ---
                if ($client_data['is_active'] == 0) {
                    $message = "Votre compte est désactivé. Veuillez contacter l'administration.";
                    $message_type = "danger";
                    // Enregistrer la tentative de connexion d'un compte désactivé
                    logUserAction('Tentative de connexion', $client_data['id_client'], 'client', $client_data['email'], "Compte désactivé.");
                } elseif ($client_data['is_banned'] == 1) {
                    $message = "Votre compte a été banni. Vous ne pouvez plus vous connecter.";
                    $message_type = "danger";
                    // Enregistrer la tentative de connexion d'un compte banni
                    logUserAction('Tentative de connexion', $client_data['id_client'], 'client', $client_data['email'], "Compte banni.");
                } elseif ($client_data['en_quarantaine'] == 1) {
                    $message = "Votre compte est actuellement mis en quarantaine. Veuillez contacter l'administrateur.";
                    $message_type = "danger";
                    // Enregistrer la tentative de connexion d'un compte en quarantaine
                    logUserAction('Tentative de connexion', $client_data['id_client'], 'client', $client_data['email'], "Compte en quarantaine.");
                } else {
                    // Si toutes les vérifications de statut sont passées, l'utilisateur peut se connecter
                    $_SESSION['user'] = $client_data;
                    $_SESSION['role'] = 'client';
                    $_SESSION['logged_in'] = true; // Ajout d'un flag général de connexion

                    // Vérifier la première connexion
                    if ($client_data['first_login'] == 1) {
                        $_SESSION['change_pwd_user'] = [
                            'role' => 'client',
                            'id' => $client_data['id_client']
                        ];
                        header("Location: changer_mdp.php");
                        exit();
                    }
                    header("Location: client.php"); // Rediriger vers le tableau de bord client
                    exit();
                }
            } else {
                // Email ou mot de passe incorrect
                $message = "Email ou mot de passe incorrect.";
                $message_type = "danger";
            }
            $stmtClient->close();
        } else {
            // Erreur de préparation de la requête SQL
            error_log("Erreur de préparation de la requête client : " . $conn->error);
            $message = "Une erreur interne est survenue. Veuillez réessayer plus tard.";
            $message_type = "danger";
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Client - VotreSite</title>
    <link rel="icon" type="image/png" href="mon_logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
                        <li class="nav-item"><a class="nav-link" aria-current="page" href="index.html">Accueil</a></li>
                        <li class="nav-item"><a class="nav-link" href="a_propos.html">À propos de nous</a></li>
                        <li class="nav-item"><a class="nav-link" href="index.html#nos_services">Nos services</a></li>
                        <li class="nav-item"><a class="nav-link" href="index.html#techniciens">je suis un technicien</a></li>
                        <li class="nav-item"><a class="nav-link" href="index.html#contact-section">Contact</a></li>
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
    <section class="d-flex flex-column min-vh-100 bg-light">
        <div class="container my-auto">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-5">
                    <div class="card shadow-sm p-4">
                        <h2 class="card-title text-center mb-4">Connexion Client</h2>
                        <?php if ($message): // Affiche le message s'il existe ?>
                            <div class="alert alert-<?php echo $message_type === 'danger' ? 'danger' : 'success'; ?>" role="alert">
                                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php endif; ?>
                        <form action="connexion_client.php" method="POST">
                            <div class="mb-3">
                                <label for="email" class="form-label">Adresse Email</label>
                                <input type="email" class="form-control" id="email" name="email" required value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>">
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Mot de Passe</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" name="valider" class="btn btn-primary btn-lg">Se Connecter</button>
                            </div>
                            <p class="text-center mt-3">Pas encore de compte ? <a href="inscription_client.php">Inscrivez-vous</a></p>
                            <p class="text-center mt-2"><a href="site de mise en relation techniciens&clients.html">Retour à l'accueil</a></p>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

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
</body>
</html>