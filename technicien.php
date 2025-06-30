<?php
session_start();

// --- Configuration de la base de données ---
$server_name = "localhost";
$user_name = "root";
$psw = ""; // Your MySQL password (leave empty if none)
$DB_name = "depanage"; // Your database name

// Establish database connection
$con = new mysqli($server_name, $user_name, $psw, $DB_name);

// Check connection
if ($con->connect_error) {
    // Log the error for debugging, but show a user-friendly message
    error_log("Database connection error: " . $con->connect_error);
    die("Désolé, une erreur technique est survenue. Veuillez réessayer plus tard.");
}

// Set character set to avoid encoding issues
$con->set_charset("utf8mb4");

// --- Technician Authentication Check ---
// Redirect if the user is not logged in or is not a technician
if (!isset($_SESSION['user']) || ($_SESSION['role'] ?? '') !== 'technicien') {
    header("Location: connexion_technicien.php"); // Ensure this path is correct
    exit();
}

// Retrieve logged-in technician's information
$technicien_id = $_SESSION['user']['id_technicien'];
// Ensure technician's first name is stored in session if available in 'techniciens' table
$technicien_prenom = isset($_SESSION['user']['prenom']) ? htmlspecialchars($_SESSION['user']['prenom']) : 'Technicien';

// --- Fetch Available Missions ---
$missions_disponibles = [];
// Query to get "en_attente" missions that are not yet assigned to a technician.
// Added m.type_service to the SELECT statement.
$sql_missions = "SELECT m.id_mission, m.type_service, m.description, m.localisation, m.date_demande, m.statut, c.nom AS client_nom
                 FROM mission m
                 JOIN client c ON m.id_client = c.id_client
                 WHERE m.statut IN ('en_attente') AND m.id_technicien IS NULL
                 ORDER BY m.date_demande DESC";

$result_missions = $con->query($sql_missions);

if ($result_missions) {
    while ($row = $result_missions->fetch_assoc()) {
        $missions_disponibles[] = $row;
    }
} else {
    error_log("Error retrieving missions: " . $con->error);
}

// --- Fetch Admin Announcements via utils.php ---
// Ensure utils.php is included and the get_announcements function exists.
include_once 'utils.php';
// Get announcements visible to technicians (limited to 3 for the homepage)
$announcements = get_announcements('technicien', true, 3); // role 'technicien', **active only (true)**, limit 3

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace Technicien - VotreSite</title>
    <link rel="icon" type="image/png" href="mon_logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/forms.css">

    <style>
        /* Custom styles for this page */
        :root {
            --bs-primary: #007bff; /* Default Bootstrap Blue */
            --bs-secondary: #6c757d; /* Secondary Gray */
            --bs-success: #28a745;
            --bs-dark: #343a40;
            --bs-light: #f8f9fa;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
            background-color: var(--bs-light);
            padding-top: 76px; /* Space for fixed navbar */
        }

        /* Improved Navbar */
        .navbar {
            background-color: var(--bs-dark) !important; /* Dark background */
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            position: fixed; /* Make navbar fixed at the top */
            top: 0;
            width: 100%;
            z-index: 1030;
        }

        .navbar-brand {
            padding: 0; /* Remove default padding for image */
        }

        .navbar-brand img {
            max-width: 200px; /* More reasonable size for a logo */
            height: auto;
            border-radius: 8px; /* Soften corners */
            object-fit: contain; /* Ensure the entire logo is visible */
        }

        .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.75) !important; /* Light text color */
            font-weight: 500;
            margin-right: 15px; /* Spacing between links */
            transition: color 0.3s ease;
        }

        .navbar-nav .nav-link:hover,
        .navbar-nav .nav-link.active {
            color: var(--bs-primary) !important; /* Primary color on hover/active */
        }

        .btn-primary { /* Renamed to match your HTML usage */
            background-color: var(--bs-primary); /* Primary button for logging in */
            border-color: var(--bs-primary);
            transition: background-color 0.3s ease, border-color 0.3s ease;
        }

        .btn-primary:hover { /* Renamed to match your HTML usage */
            background-color: #0056b3; /* Darker shade on hover */
            border-color: #0056b3;
        }

        /* Hero Section (or equivalent for technician) */
        .technician-hero {
            background: linear-gradient(rgba(0, 123, 255, 0.8), rgba(0, 86, 179, 0.8)), url('path/to/your/technician-bg.jpg') no-repeat center center/cover; /* Different background image if you want */
            min-height: 250px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            color: white;
            padding: 4rem 0;
        }

        .technician-hero h1 {
            font-size: 2.8rem;
            margin-bottom: 1rem;
        }

        /* General content sections */
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
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08); /* More pronounced shadow */
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

        .mission-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            margin-bottom: 20px;
            background-color: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .mission-card .card-header {
            background-color: var(--bs-primary);
            color: white;
            font-weight: bold;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }

        .mission-card .card-body {
            padding: 20px;
        }

        .mission-card .btn {
            margin-top: 15px;
        }

        /* Announcement Card Specific Styles */
        .annonce-card {
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .annonce-card h5 {
            color: #007bff;
            margin-bottom: 10px;
        }
        .annonce-card p {
            font-size: 0.95rem;
            color: #555;
        }
        .annonce-card .text-muted {
            font-size: 0.85rem;
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
            color: var(--bs-success); /* Green for social icons on hover */
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
                        <li class="nav-item"><a class="nav-link active" aria-current="page" href="technicien.php">Accueil</a></li>
                        <li class="nav-item"><a class="nav-link" href="mon_compte_technicien.php">Mon compte</a></li>
                        <li class="nav-item"><a class="nav-link" href="missions_disponibles.php">Mes missions</a></li>
                        <li class="nav-item"><a class="nav-link" href="connexion_client_tech.php">Je suis un client</a></li>
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

    <main>
        <div id="content-wrapper">
            <section class="technician-hero text-white text-center">
                <div class="container">
                    <h1 class="display-4 fw-bold mb-3">Bonjour, <?= $technicien_prenom ?> !</h1>
                    <p class="lead mb-0">Découvrez les nouvelles missions disponibles et développez votre activité.</p>
                </div>
            </section>

            <section id="annonces-admin" class="py-5 bg-light">
                <div class="container">
                    <h2 class="text-center mb-5">Annonces de l'Administrateur</h2>
                    <?php if (!empty($announcements)): ?>
                        <div class="row">
                            <?php foreach ($announcements as $annonce): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="annonce-card h-100">
                                        <h5><?= htmlspecialchars($annonce['titre']) ?></h5>
                                        <p><?= nl2br(htmlspecialchars($annonce['contenu'])) ?></p>
                                        <p class="text-muted"><small>Publié le
                                                <?php
                                                // Ensure 'date_publication' is set and not null
                                                if (isset($annonce['date_publication']) && $annonce['date_publication'] !== null) {
                                                    echo (new DateTime($annonce['date_publication']))->format('d/m/Y à H:i');
                                                } else {
                                                    echo 'Date inconnue'; // Fallback if 'date_publication' is null
                                                }
                                                ?>
                                            </small></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-center lead text-muted">Aucune annonce de l'administrateur pour le moment.</p>
                    <?php endif; ?>
                </div>
            </section>

            <section id="missions-disponibles" class="py-5">
                <div class="container">
                    <h2 class="text-center mb-5">Missions disponibles</h2>
                    <?php if (!empty($missions_disponibles)): ?>
                        <div class="row">
                            <?php foreach ($missions_disponibles as $mission): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="card mission-card h-100">
                                        <div class="card-header">
                                            Mission : <strong><?= htmlspecialchars($mission['type_service'] ?? 'Non spécifié') ?></strong>
                                        </div>
                                        <div class="card-body">
                                            <h5 class="card-title text-primary">Client : <?= htmlspecialchars($mission['client_nom']) ?></h5>
                                            <p class="card-text">
                                                <strong>Localisation :</strong> <?= htmlspecialchars($mission['localisation']) ?><br>
                                                <strong>Date de publication :</strong>
                                                <?php
                                                // Check if 'date_demande' is set and not null for missions
                                                if (isset($mission['date_demande']) && $mission['date_demande'] !== null) {
                                                    echo (new DateTime($mission['date_demande']))->format('d/m/Y à H:i');
                                                } else {
                                                    echo 'Date inconnue'; // Fallback if 'date_demande' is null
                                                }
                                                ?><br>
                                                <strong>Statut :</strong> <span class="badge bg-info"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $mission['statut']))) ?></span>
                                            </p>
                                            <a href="details_mission.php?id_mission=<?= $mission['id_mission'] ?>" class="btn btn-primary btn-sm">Voir les détails et proposer un devis</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-center lead text-muted">Aucune nouvelle mission disponible pour le moment.</p>
                        <div class="text-center mt-4">
                            <a href="mon_compte_technicien.php" class="btn btn-outline-primary">Mettez à jour votre profil et vos préférences !</a>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section id="features-technician" class="py-5 bg-light">
                <div class="container">
                    <h2 class="text-center mb-5">Nos avantages pour les techniciens</h2>
                    <div class="row text-center">
                        <div class="col-md-4 mb-4">
                            <div class="card shadow-sm h-100 p-4">
                                <i class="fas fa-chart-line fa-3x text-primary mb-3"></i>
                                <h5 class="card-title">Accédez à de nouvelles opportunités</h5>
                                <p class="card-text">Recevez des demandes de service ciblées dans votre spécialité et votre zone.</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="card shadow-sm h-100 p-4">
                                <i class="fas fa-cogs fa-3x text-primary mb-3"></i>
                                <h5 class="card-title">Gérez vos missions facilement</h5>
                                <p class="card-text">Suivez vos devis, missions en cours et complétées depuis un tableau de bord intuitif.</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="card shadow-sm h-100 p-4">
                                <i class="fas fa-star fa-3x text-primary mb-3"></i>
                                <h5 class="card-title">Construisez votre réputation</h5>
                                <p class="card-text">Obtenez des évaluations de clients satisfaits et augmentez votre visibilité.</p>
                            </div>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eJBnK1e8p5x9YfE" crossorigin="anonymous"></script>
    <script src="js/script.js"></script>
</body>
</html>

<?php
// Close the database connection
$con->close();
?>