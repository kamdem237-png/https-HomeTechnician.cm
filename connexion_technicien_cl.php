<?php
session_start(); // Démarre la session en début de script

// --- Configuration de la base de données ---
$server_name = "localhost";
$user_name = "root";
$psw = ""; // Ton mot de passe MySQL (laisse-le vide si tu n'en as pas)
$DB_name = "depanage"; // Le nom de ta base de données

// Affichage des erreurs pour le développement - à supprimer ou modifier pour la production
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Connexion à la base de données
$conn = new mysqli($server_name, $user_name, $psw, $DB_name);

// Vérifier la connexion
if ($conn->connect_error) {
    error_log("Erreur de connexion à la base de données : " . $conn->connect_error);
    die("Désolé, une erreur technique est survenue. Veuillez réessayer plus tard.");
}

// Définir l'encodage pour éviter les problèmes d'accents et de caractères spéciaux
$conn->set_charset("utf8mb4");

// --- Initialisation des variables pour les messages ---
$message = "";
$message_type = ""; // Peut être 'success', 'error', 'info'

// Effacer les messages de session existants après affichage
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// --- Traitement du formulaire de connexion si soumis ---
if (isset($_POST['valider'])) {
    // Nettoyage et validation des entrées utilisateur
    $email = trim($_POST['email'] ?? '');
    $password_input = trim($_POST['password'] ?? '');

    // Utilisation de htmlspecialchars pour prévenir les attaques XSS lors de l'affichage de l'email
    $email_safe = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');

    if (empty($email) || empty($password_input)) {
        $message = "Veuillez remplir tous les champs.";
        $message_type = "error";
    } else {
        // --- Vérifier l'authentification en tant que technicien ---
        // Ajout de 'is_active' à la sélection
        $stmtTech = $conn->prepare("SELECT id_technicien, nom, prenom, email, num_technicien, specialite, zone, password, first_login, en_quarantaine, fin_quarantaine, is_active FROM technicien WHERE email = ?");
        
        if ($stmtTech) {
            $stmtTech->bind_param("s", $email);
            $stmtTech->execute();
            $resTech = $stmtTech->get_result();
            
            if ($resTech->num_rows > 0) {
                $tech = $resTech->fetch_assoc();

                // --- NOUVEAU : Vérification du statut 'is_active' ---
                if (isset($tech['is_active']) && $tech['is_active'] == 0) {
                    $message = "Votre compte technicien a été désactivé. Veuillez contacter l'administration.";
                    $message_type = "error";
                    $stmtTech->close();
                    goto end_process; // Sortir du script
                }
                // --- FIN NOUVEAU ---

                // --- Vérification de la quarantaine ---
                if (isset($tech['en_quarantaine']) && $tech['en_quarantaine'] == 1) {
                    $fin_quarantaine_timestamp = strtotime($tech['fin_quarantaine']);
                    $current_timestamp = time();

                    if ($current_timestamp < $fin_quarantaine_timestamp) {
                        // Le technicien est toujours en quarantaine
                        $message = "Votre compte est en quarantaine jusqu'au " . date('d/m/Y H:i', $fin_quarantaine_timestamp) . ". Veuillez contacter l'administration.";
                        $message_type = "error";
                        $stmtTech->close();
                        goto end_process; // Sortir du script
                    } else {
                        // La période de quarantaine est terminée, lever automatiquement la quarantaine
                        $stmt_auto_unquarantine = $conn->prepare("UPDATE technicien SET en_quarantaine = 0, fin_quarantaine = NULL WHERE id_technicien = ?");
                        if ($stmt_auto_unquarantine) {
                            $stmt_auto_unquarantine->bind_param("i", $tech['id_technicien']);
                            $stmt_auto_unquarantine->execute();
                            $stmt_auto_unquarantine->close();
                        } else {
                            error_log("Erreur de préparation pour auto-lever la quarantaine: " . $conn->error);
                        }
                    }
                }
                // --- FIN Vérification de la quarantaine ---

                // Si le compte est actif et pas en quarantaine, vérifier le mot de passe
                if (password_verify($password_input, $tech['password'])) {
                    // Authentification technicien réussie
                    $_SESSION['user'] = $tech; // Stocke toutes les données du technicien
                    $_SESSION['role'] = 'technicien'; // Définit le rôle

                    // Stocke aussi les variables individuelles pour faciliter l'accès si nécessaire
                    $_SESSION['id_technicien'] = $tech['id_technicien'];
                    $_SESSION['email'] = $tech['email'];
                    $_SESSION['nom_complet'] = $tech['prenom'] . ' ' . $tech['nom'];
                    $_SESSION['specialite'] = $tech['specialite'];
                    $_SESSION['zone'] = $tech['zone'];

                    // Vérifier si c'est la première connexion pour forcer le changement de mot de passe
                    if (isset($tech['first_login']) && $tech['first_login'] == 1) {
                        $_SESSION['change_pwd_user'] = [
                            'role' => 'technicien',
                            'id' => $tech['id_technicien']
                        ];
                        header("Location: changer_mot_de_passe.php"); // Page pour changer le mot de passe initial
                        exit();
                    }
                    header("Location: technicien.php"); // Redirige vers le tableau de bord technicien
                    exit();
                } else {
                    $message = "Email ou mot de passe incorrect.";
                    $message_type = "error";
                }
            } else {
                $message = "Aucun compte technicien trouvé avec cet email.";
                $message_type = "error";
            }
            $stmtTech->close();
        } else {
            error_log("Erreur de préparation de la requête technicien: " . $conn->error);
            $message = "Une erreur est survenue lors de la vérification de votre compte.";
            $message_type = "error";
        }
    }
}

end_process: // Label pour le goto
// Ferme la connexion à la base de données
$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Technicien - VotreSite</title>
    <link rel="icon" type="image/png" href="mon_logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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

        /* Custom styles for announcement cards to ensure consistency */
        .annonce-card {
            border: none;
            border-radius: 12px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            background-color: white; /* Ensure a white background */
            padding: 20px; /* Add some padding */
        }
    </style>

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
                        <li class="nav-item"><a class="nav-link active" href="#techniciens">je suis un technicien</a></li>
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

    <section class="d-flex flex-column min-vh-100 bg-light">
        <div class="container my-auto">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-5">
                    <div class="card shadow-sm p-4">
                        <h2 class="card-title text-center mb-4">Connexion Technicien</h2>
                        <?php if ($message): // Affiche le message s'il existe ?>
                            <div class="alert alert-<?php echo $message_type === 'error' ? 'danger' : 'success'; ?>" role="alert">
                                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php endif; ?>
                        <form action="connexion_technicien_cl.php" method="POST">
                            <div class="mb-3">
                                <label for="email" class="form-label">Adresse Email</label>
                                <input type="email" class="form-control" id="email" name="email" required value="<?php echo isset($email_safe) ? $email_safe : ''; ?>">
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Mot de Passe</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" name="valider" class="btn btn-primary btn-lg">Se Connecter</button>
                            </div>
                            <p class="text-center mt-3">Pas encore de compte ? <a href="inscription_technicien.php">Inscrivez-vous (Technicien)</a></p>
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>