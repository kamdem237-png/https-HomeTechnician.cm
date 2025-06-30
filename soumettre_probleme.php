<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'client') {
    header("Location: connexion_user.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "depanage");
if ($conn->connect_error) {
    die("Erreur de connexion : " . $conn->connect_error);
}
$client_id = (int)($_SESSION['user']['id_client'] ?? 0);

$message = "";

// Nettoyage des missions en attente de plus de 30 jours
// Note : Pour une application en production, il est préférable de faire cela via une tâche planifiée (cron job)
// plutôt qu'à chaque chargement de page, pour des raisons de performance.
$conn->query("DELETE FROM mission WHERE statut = 'en_attente' AND date_demande < DATE_SUB(NOW(), INTERVAL 30 DAY)");

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer le titre de la mission depuis le champ 'type_service' du formulaire.
    // Cette variable sera insérée dans la colonne 'titre_probleme' de la DB.
    $titre_mission = trim($_POST['type_service'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $localisation = trim($_POST['localisation'] ?? '');
    $nb = (int)($_POST['nb_techniciens'] ?? 1);

    // Valider tous les champs requis
    if ($titre_mission !== '' && $description !== '' && $localisation !== '' && $client_id > 0) {
        // --- UTILISATION DE REQUÊTES PRÉPARÉES POUR LA SÉCURITÉ ---
        // 1. Préparez votre requête SQL avec des marqueurs de position (?).
        // J'ai mis 'titre_probleme' ici, car cela semble plus logique pour un "titre" de mission.
        // VÉRIFIEZ LE NOM EXACT DE VOTRE COLONNE DANS LA BASE DE DONNÉES POUR LE TITRE DE LA MISSION !
        $stmt = $conn->prepare("INSERT INTO mission
                                (titre_probleme, description, localisation, nb_techniciens_demande, statut, date_demande, id_client)
                                VALUES (?, ?, ?, ?, 'en_attente', NOW(), ?)");

        // Vérifier si la préparation de la requête a échoué
        if ($stmt === false) {
            $message = "Erreur de préparation de la requête : " . $conn->error;
        } else {
            // 2. Liez les paramètres à la requête préparée.
            // 's' pour string (chaîne de caractères), 'i' pour integer (entier).
            // L'ordre des types et des variables doit correspondre à l'ordre des '?' dans la requête.
            $nb_safe = max(1, min(10, $nb)); // S'assurer que la valeur est entre 1 et 10

            // Le premier 's' correspond à $titre_mission qui ira dans 'titre_probleme'
            $stmt->bind_param("sssii", $titre_mission, $description, $localisation, $nb_safe, $client_id);

            // 3. Exécutez la requête préparée
            if ($stmt->execute()) {
                header("Location: missions_clientes.php?success=1");
                exit;
            } else {
                $message = "Erreur lors de l'enregistrement de la mission : " . $stmt->error;
            }

            // 4. Fermez le statement après utilisation
            $stmt->close();
        }
    } else {
        $message = "Veuillez remplir tous les champs requis.";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Soumettre une Mission</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="mon_logo.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Vos styles personnalisés existants */
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
            padding-top: 76px;
        }

        /* Navbar améliorée */
        .navbar {
            background-color: var(--bs-dark) !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1030;
        }

        .navbar-brand {
            padding: 0;
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
            transition: background-color 0.3s ease, border-color 0.3s ease;
        }

        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
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

        /* Formulaire de contact */
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
                        <li class="nav-item"><a class="nav-link active" href="soumettre_probleme.php">Soumettre une Mission</a></li>
                        <li class="nav-item"><a class="nav-link" href="client.php#techniciens">je suis un technicien</a></li>
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

    <section class="bg-light" style="margin-top: -50px;">
        <div class="container mt-5">
            <h3 class="mb-4">Soumettre une nouvelle mission</h3>
            <?php if ($message !== ''): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form action="" method="POST">
                <div class="mb-3">
                    <label for="type_service" class="form-label">Titre de la mission (Ex: Réparation Frigo, Panne Électrique)</label>
                    <input type="text" name="type_service" id="type_service" class="form-control" required value="<?= htmlspecialchars($_POST['type_service'] ?? '') ?>">
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">Description détaillée de la panne/du problème</label>
                    <textarea name="description" id="description" class="form-control" rows="4" required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>

                <div class="mb-3">
                    <label for="localisation" class="form-label">Votre localisation actuelle (Ex: Douala, Akwa)</label>
                    <input type="text" name="localisation" id="localisation" class="form-control" required value="<?= htmlspecialchars($_POST['localisation'] ?? '') ?>">
                </div>

                <div class="mb-3">
                    <label for="nb_techniciens" class="form-label">Nombre de techniciens souhaités</label>
                    <input type="number" name="nb_techniciens" id="nb_techniciens" class="form-control" min="1" max="10" required value="<?= (int)($_POST['nb_techniciens'] ?? 1) ?>">
                </div>

                <button type="submit" class="btn btn-primary">Envoyer la demande</button>
            </form>
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>