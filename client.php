<?php
session_start(); // Démarre la session en début de script

// --- Configuration de la base de données ---
$server_name = "localhost";
$user_name = "root";
$psw = ""; // Ton mot de passe MySQL (laisse-le vide si tu n'en as pas)
$DB_name = "depanage"; // Le nom de ta base de données

// Connexion à la base de données
$con = new mysqli($server_name, $user_name, $psw, $DB_name);

// Vérifier la connexion
if ($con->connect_error) {
    // Il est crucial de loguer les erreurs de connexion et d'afficher un message générique à l'utilisateur
    error_log("Erreur de connexion à la base de données : " . $con->connect_error);
    die("Désolé, une erreur technique est survenue. Veuillez réessayer plus tard.");
}

// Définir l'encodage pour éviter les problèmes d'accents et de caractères spéciaux
$con->set_charset("utf8mb4");

// --- Récupération des annonces visibles par le client ---
$announcements = []; // Initialise un tableau vide pour stocker les annonces

// Prépare la requête pour sélectionner les annonces actives et visibles par les clients, triées par date de publication décroissante
$stmt_annonces = $con->prepare("SELECT titre, contenu, date_publication, date_expiration FROM annonces WHERE statut_actif = 1 AND visible_client = 1 ORDER BY date_publication DESC");

if ($stmt_annonces) {
    $stmt_annonces->execute(); // Exécute la requête
    $result_annonces = $stmt_annonces->get_result(); // Récupère le jeu de résultats

    // Parcourt les résultats et stocke chaque annonce dans le tableau $announcements
    while ($row = $result_annonces->fetch_assoc()) {
        $announcements[] = $row;
    }
    $stmt_annonces->close(); // Ferme l'instruction préparée
} else {
    // Gérer l'erreur si la préparation de la requête échoue
    error_log("Erreur lors de la préparation de la requête des annonces : " . $con->error);
    // En production, ne pas afficher l'erreur SQL à l'utilisateur, juste un message générique.
}

// Pour le bloc "Annonces de l'Administrateur", on peut réutiliser $announcements si elles sont les mêmes.
// Si les annonces de l'admin proviennent d'une logique différente (ex: visible_admin = 1), il faudrait une requête séparée.
// Pour cet exemple, nous considérons qu'elles sont les mêmes ou que vous n'avez qu'un type d'annonce pour le client.
$annonces = $announcements; // Rend les annonces disponibles pour la section "annonces-admin"

// --- Vérification de l'authentification du client ---
// Redirige si l'utilisateur n'est pas connecté ou n'est pas un client
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'client') {
    header("Location: connexion_client.php"); // Assurez-vous que ce chemin est correct
    exit();
}

$client_name = isset($_SESSION['user']['nom']) ? $_SESSION['user']['nom'] : 'Utilisateur';

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VotreSite - Réparation et Services Techniques</title>
    <link rel="icon" type="image/png" href="mon_logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/forms.css">

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
                        <li class="nav-item"><a class="nav-link active" aria-current="page" href="client.php">Accueil</a></li>
                        <li class="nav-item"><a class="nav-link" href="mon_compte_client.php">Mon compte</a></li>
                        <li class="nav-item"><a class="nav-link" href="missions_clientes.php">Missions</a></li>
                        <li class="nav-item"><a class="nav-link" href="soumettre_probleme.php">Soumettre une Mission</a></li>
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
        
        <div id="content-wrapper">
            <section id="hero-section" class="hero-section text-white text-center">
                <div class="container">
                    <h1 class="display-4 fw-bold mb-3">Trouvez le technicien qu'il vous faut, près de chez vous.</h1>
                    <p class="lead mb-4">Votre solution rapide et fiable pour tous vos besoins techniques au Cameroun.</p>
                    <div class="div2">
                        <form method="post" action="traitement2.php" class="d-flex flex-column flex-md-row align-items-center justify-content-center">
                            <div class="mb-3 me-md-2 mb-md-0"> <label for="specialty" class="form-label visually-hidden">Choisissez une spécialité</label> <select class="form-select" id="specialty" name="specialty" required>
                                    <option value="">Choisissez une spécialité...</option>
                                    <option value="electricite" <?php echo (isset($specialty) && $specialty == 'electricite') ? 'selected' : ''; ?>>Électricité</option>
                                    <option value="plomberie" <?php echo (isset($specialty) && $specialty == 'plomberie') ? 'selected' : ''; ?>>Plomberie</option>
                                    <option value="informatique" <?php echo (isset($specialty) && $specialty == 'informatique') ? 'selected' : ''; ?>>Informatique</option>
                                    <option value="electromenager" <?php echo (isset($specialty) && $specialty == 'electromenager') ? 'selected' : ''; ?>>Électroménager</option>
                                    <option value="automobile" <?php echo (isset($specialty) && $specialty == 'automobile') ? 'selected' : ''; ?>>Automobile</option>
                                    <option value="bricolage" <?php echo (isset($specialty) && $specialty == 'bricolage') ? 'selected' : ''; ?>>Bricolage / Général</option>
                                    <option value="autres" <?php echo (isset($specialty) && $specialty == 'autres') ? 'selected' : ''; ?>>Autres</option>
                                </select>
                            </div>
                            <div class="mb-3 me-md-2 mb-md-0"> <label for="zone" class="form-label visually-hidden">Votre zone</label> <input type="text" name="zone" placeholder="Votre zone (Ex: Douala, Akwa)" id="zone" required class="form-control">
                            </div>
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-search me-2"></i>Rechercher un technicien
                            </button>
                        </form>
                    </div>
                </div>
            </section>

            <section id="annonces-admin" class="py-5 bg-light">
                <div class="container">
                    <h2 class="text-center mb-5">Annonces de l'Administrateur</h2>
                    <?php if (!empty($annonces)): ?>
                        <div class="row">
                            <?php foreach ($annonces as $annonce): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="annonce-card h-100">
                                        <div class="card-body">
                                            <h5 class="card-title"><?= htmlspecialchars($annonce['titre']) ?></h5>
                                            <p class="card-text"><?= nl2br(htmlspecialchars($annonce['contenu'])) ?></p>
                                            <p class="text-muted"><small>Publié le
                                                <?php
                                                // Ensure 'date_publication' is a string and not null for announcements
                                                if (isset($annonce['date_publication']) && $annonce['date_publication'] !== null) {
                                                    echo (new DateTime($annonce['date_publication']))->format('d/m/Y à H:i');
                                                } else {
                                                    echo 'Date inconnue'; // Fallback if 'date_publication' is null
                                                }
                                                ?>
                                            </small></p>
                                            <?php if (isset($annonce['date_expiration']) && $annonce['date_expiration'] !== null && $annonce['date_expiration'] != '0000-00-00 00:00:00'): ?>
                                                <p class="text-muted"><small>Expire le: <?= (new DateTime($annonce['date_expiration']))->format('d/m/Y') ?></small></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-center lead text-muted">Aucune annonce de l'administrateur pour le moment.</p>
                    <?php endif; ?>
                </div>
            </section>
        </div>

            <section id="clients" class="py-5 bg-light">
                <div class="container">
                    <h2 class="text-center mb-4">Vous avez besoin d'un technicien ?</h2>
                    <div class="row g-4">
                        <div class="col-md-6 col-lg-4">
                            <div class="card shadow-sm h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-bolt fa-3x text-primary mb-3"></i>
                                    <h5 class="card-title">Service Rapide</h5>
                                    <p class="card-text">Obtenez une intervention rapide pour vos pannes ou installations urgentes.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <div class="card shadow-sm h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-medal fa-3x text-primary mb-3"></i>
                                    <h5 class="card-title">Techniciens Qualifiés</h5>
                                    <p class="card-text">Nous mettons à votre disposition des professionnels vérifiés et expérimentés.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <div class="card shadow-sm h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-hand-holding-usd fa-3x text-primary mb-3"></i>
                                    <h5 class="card-title">Devis Gratuits</h5>
                                    <p class="card-text">Demandez plusieurs devis et choisissez l'offre qui vous convient le mieux.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="text-center mt-5">
                        <a href="soumettre_probleme.php" class="btn btn-primary btn-lg px-5 py-3">Trouver un technicien maintenant</a>
                    </div>
                </div>
            </section>

            <section id="techniciens" class="py-5">
                <div class="container">
                    <h2 class="text-center mb-4">Vous êtes technicien ? Rejoignez-nous !</h2>
                    <div class="row g-4">
                        <div class="col-md-6 col-lg-4">
                            <div class="card shadow-sm h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-chart-line fa-3x text-primary mb-3"></i>
                                    <h5 class="card-title">Nouvelles Opportunités</h5>
                                    <p class="card-text">Accédez à une clientèle prête à vous engager pour vos services.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <div class="card shadow-sm h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-cogs fa-3x text-primary mb-3"></i>
                                    <h5 class="card-title">Gestion Simplifiée</h5>
                                    <p class="card-text">Gérez vos demandes, devis et plannings directement depuis notre plateforme.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <div class="card shadow-sm h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-bullhorn fa-3x text-primary mb-3"></i>
                                    <h5 class="card-title">Visibilité Accrue</h5>
                                    <p class="card-text">Mettez en avant votre expertise et développez votre activité.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="text-center mt-5">
                        <a href="inscription_technicien_cl.php" class="btn btn-outline-primary btn-lg px-5 py-3">Inscrivez-vous comme technicien</a>
                    </div>
                </div>
            </section>

            <section id="how-it-works" class="py-5 bg-light">
                <div class="container">
                    <h2 class="text-center mb-5">Comment ça marche ?</h2>
                    <div class="row text-center">
                        <div class="col-md-4 mb-4">
                            <div class="icon-circle p-4 mb-3 rounded-circle mx-auto d-flex align-items-center justify-content-center" style="width: 100px; height: 100px;">
                                <i class="fas fa-search text-primary fs-1"></i>
                            </div>
                            <h4 class="text-primary">1. Décrivez votre besoin</h4>
                            <p>Expliquez la nature de votre problème ou service requis via notre formulaire simple.</p>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="icon-circle p-4 mb-3 rounded-circle mx-auto d-flex align-items-center justify-content-center" style="width: 100px; height: 100px;">
                                <i class="fas fa-file-invoice-dollar text-primary fs-1"></i>
                            </div>
                            <h4 class="text-primary">2. Recevez des devis</h4>
                            <p>Les techniciens qualifiés et disponibles vous envoient rapidement leurs propositions et tarifs.</p>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="icon-circle p-4 mb-3 rounded-circle mx-auto d-flex align-items-center justify-content-center" style="width: 100px; height: 100px;">
                                <i class="fas fa-user-check text-primary fs-1"></i>
                            </div>
                            <h4 class="text-primary">3. Choisissez votre technicien</h4>
                            <p>Comparez les profils, les avis des clients, et les prix pour faire le meilleur choix en toute confiance.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="categories-section py-5 bg-light">
                <div class="container py-5" id="nos_services">
                    <h2 class="text-center display-5 fw-bold mb-5">Nos Domaines d'Expertise</h2>
                    <div class="row row-cols-2 row-cols-md-3 row-cols-lg-6 g-4 text-center">
                        <div class="col">
                            <a href="#" class="card h-100 shadow-sm border-0 category-card d-flex flex-column justify-content-center align-items-center p-3">
                                <i class="fas fa-plug fa-2x text-primary mb-2"></i>
                                <span class="fw-bold">Électricité</span>
                            </a>
                        </div>
                        <div class="col">
                            <a href="#" class="card h-100 shadow-sm border-0 category-card d-flex flex-column justify-content-center align-items-center p-3">
                                <i class="fas fa-faucet fa-2x text-primary mb-2"></i>
                                <span class="fw-bold">Plomberie</span>
                            </a>
                        </div>
                        <div class="col">
                            <a href="#" class="card h-100 shadow-sm border-0 category-card d-flex flex-column justify-content-center align-items-center p-3">
                                <i class="fas fa-desktop fa-2x text-primary mb-2"></i>
                                <span class="fw-bold">Informatique</span>
                            </a>
                        </div>
                        <div class="col">
                            <a href="#" class="card h-100 shadow-sm border-0 category-card d-flex flex-column justify-content-center align-items-center p-3">
                                <i class="fas fa-refrigerator fa-2x text-primary mb-2"></i>
                                <span class="fw-bold">Électroménager</span>
                            </a>
                        </div>
                        <div class="col">
                            <a href="#" class="card h-100 shadow-sm border-0 category-card d-flex flex-column justify-content-center align-items-center p-3">
                                <i class="fas fa-car-battery fa-2x text-primary mb-2"></i>
                                <span class="fw-bold">Automobile</span>
                            </a>
                        </div>
                        <div class="col">
                            <a href="#" class="card h-100 shadow-sm border-0 category-card d-flex flex-column justify-content-center align-items-center p-3">
                                <i class="fas fa-paint-roller fa-2x text-primary mb-2"></i>
                                <span class="fw-bold">Bricolage</span>
                            </a>
                        </div>
                    </div>
                </div>
            </section>

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

<?php
// C'est le bon endroit pour fermer la connexion à la base de données.
// Elle sera fermée après que tout le contenu HTML ait été envoyé au navigateur.
$con->close();
?>