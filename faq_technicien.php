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




?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQ - Foire Aux Questions</title>
    <link rel="icon" type="image/png" href="mon_logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
   <style>
     body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background-color: #f8f9fa; /* Light background color */
        }
        .navbar-custom {
            background-color: #343a40; /* Dark color for the navigation bar */
        }
        .navbar-custom .navbar-brand,
        .navbar-custom .nav-link {
            color: #ffffff;
        }
        .navbar-custom .nav-link:hover {
            color: #cccccc;
        }
        .footer-custom {
            background-color: #343a40; /* Dark color for the footer */
            color: #ffffff;
            padding: 30px 0;
            margin-top: auto; /* Pushes the footer to the bottom */
        }
        .footer-custom a {
            color: #ffffff;
            text-decoration: none;
        }
        .footer-custom a:hover {
            color: #cccccc;
        }
        .faq-section {
            padding: 60px 0;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .faq-title {
            color: #007bff; /* Bootstrap primary color */
            margin-bottom: 40px;
        }
        .accordion-item {
            border: 1px solid #dee2e6;
            margin-bottom: 10px;
            border-radius: 5px;
        }
        .accordion-button {
            background-color: #f0f0f0;
            color: #333;
            font-weight: bold;
            text-align: left;
        }
        .accordion-button:not(.collapsed) {
            background-color: #e9ecef;
            color: #007bff;
        }
        .accordion-body {
            background-color: #ffffff;
        }
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
                        <li class="nav-item"><a class="nav-link" aria-current="page" href="technicien.php">Accueil</a></li>
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

    <div class="container my-5 flex-grow-1">
        <div class="row justify-content-center">
            <div class="col-md-10 col-lg-8">
                <div class="faq-section p-4">
                    <h1 class="text-center faq-title">Foire Aux Questions (FAQ)</h1>
                    <p class="text-center text-muted mb-5">
                        Vous trouverez ici les réponses aux questions les plus fréquemment posées sur notre plateforme de dépannage.
                    </p>

                    <div class="accordion" id="faqAccordion">

                        <h4 class="mt-4 mb-3">Questions Générales</h4>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingGeneralOne">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseGeneralOne" aria-expanded="false" aria-controls="collapseGeneralOne">
                                    Qu'est-ce que "HomeTechnician" ?
                                </button>
                            </h2>
                            <div id="collapseGeneralOne" class="accordion-collapse collapse" aria-labelledby="headingGeneralOne" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    "HomeTechnician" est une plateforme qui met en relation des clients ayant des besoins de dépannage (plomberie, électricité, informatique, etc.) avec des techniciens qualifiés et disponibles dans leur zone géographique. Notre objectif est de faciliter un dépannage rapide et efficace avec des techniciens certifiés où que vous soyez.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingGeneralTwo">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseGeneralTwo" aria-expanded="false" aria-controls="collapseGeneralTwo">
                                    Comment fonctionne la plateforme ?
                                </button>
                            </h2>
                            <div id="collapseGeneralTwo" class="accordion-collapse collapse" aria-labelledby="headingGeneralTwo" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Les clients soumettent une demande de dépannage en précisant leur problème, leur localisation et leur spécialité requise. Les techniciens disponibles peuvent consulter ces demandes et choisir d'intervenir. Une fois qu'un technicien est sélectionné, un chat s'ouvre pour faciliter la communication.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingGeneralThree">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseGeneralThree" aria-expanded="false" aria-controls="collapseGeneralThree">
                                    La création de compte est-elle gratuite ?
                                </button>
                            </h2>
                            <div id="collapseGeneralThree" class="accordion-collapse collapse" aria-labelledby="headingGeneralThree" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Oui, la création d'un compte client ou technicien est entièrement gratuite.
                                </div>
                            </div>
                        </div>

                        <h4 class="mt-5 mb-3">Pour les Clients</h4>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingClientOne">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseClientOne" aria-expanded="false" aria-controls="collapseClientOne">
                                    Comment soumettre une demande de dépannage ?
                                </button>
                            </h2>
                            <div id="collapseClientOne" class="accordion-collapse collapse" aria-labelledby="headingClientOne" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Après vous être connecté à votre compte client, cliquez sur "Soumettre un problème" ou "Nouvelle Mission". Remplissez le formulaire avec les détails de votre problème, votre localisation et la spécialité requise.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingClientTwo">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseClientTwo" aria-expanded="false" aria-controls="collapseClientTwo">
                                    Comment choisir un technicien ?
                                </button>
                            </h2>
                            <div id="collapseClientTwo" class="accordion-collapse collapse" aria-labelledby="headingClientTwo" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Une fois votre demande soumise, les techniciens disponibles dans votre zone et spécialité pourront se proposer. Vous verrez une liste de ces techniciens et pourrez choisir celui qui vous convient le mieux en fonction de son profil ou de ses évaluations.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingClientThree">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseClientThree" aria-expanded="false" aria-controls="collapseClientThree">
                                    Puis-je annuler une mission ?
                                </button>
                            </h2>
                            <div id="collapseClientThree" class="accordion-collapse collapse" aria-labelledby="headingClientThree" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Oui, vous pouvez annuler une mission tant qu'elle est en statut "en attente" (avant qu'un technicien ne l'ait acceptée ou si le technicien désigné ne s'est pas encore déplacé). Rendez-vous dans "Mes Missions" et cherchez l'option d'annulation.
                                </div>
                            </div>
                        </div>

                        <h4 class="mt-5 mb-3">Pour les Techniciens</h4>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingTechnicianOne">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTechnicianOne" aria-expanded="false" aria-controls="collapseTechnicianOne">
                                    Comment accepter une mission ?
                                </button>
                            </h2>
                            <div id="collapseTechnicianOne" class="accordion-collapse collapse" aria-labelledby="headingTechnicianOne" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Connectez-vous à votre compte technicien et accédez à "Missions Disponibles". Vous y verrez les demandes de dépannage correspondant à vos spécialités et votre zone. Cliquez sur une mission pour voir les détails et l'accepter.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingTechnicianTwo">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTechnicianTwo" aria-expanded="false" aria-controls="collapseTechnicianTwo">
                                    Que faire après avoir accepté une mission ?
                                </button>
                            </h2>
                            <div id="collapseTechnicianTwo" class="accordion-collapse collapse" aria-labelledby="headingTechnicianTwo" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Après avoir accepté une mission, utilisez la fonction de chat pour communiquer avec le client. Coordonnez les détails de l'intervention, l'heure d'arrivée et tout autre détail nécessaire.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingTechnicianThree">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTechnicianThree" aria-expanded="false" aria-controls="collapseTechnicianThree">
                                    Comment marquer une mission comme terminée ?
                                </button>
                            </h2>
                            <div id="collapseTechnicianThree" class="accordion-collapse collapse" aria-labelledby="headingTechnicianThree" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Une fois l'intervention terminée, accédez aux détails de la mission dans votre tableau de bord et utilisez l'option pour la marquer comme "terminée". Cela informera le client et finalisera la mission sur la plateforme.
                                </div>
                            </div>
                        </div>

                        <h4 class="mt-5 mb-3">Questions Fréquentes sur l'Utilisation</h4>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingUsageOne">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseUsageOne" aria-expanded="false" aria-controls="collapseUsageOne">
                                    Comment faire en cas de problème de manipulation du site ?
                                </button>
                            </h2>
                            <div id="collapseUsageOne" class="accordion-collapse collapse" aria-labelledby="headingUsageOne" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Veuillez remplir le formulaire de contact sur la page d'accueil pour tout problème lié à la manipulation du site.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingUsageTwo">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseUsageTwo" aria-expanded="false" aria-controls="collapseUsageTwo">
                                    Et pour tout problème vis-à-vis du personnel ?
                                </button>
                            </h2>
                            <div id="collapseUsageTwo" class="accordion-collapse collapse" aria-labelledby="headingUsageTwo" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Veuillez remplir le formulaire "Signaler un problème à l'administration" sur la page "Mon Compte" de vos différents comptes (client ou technicien).
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

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
</body>
</html>

