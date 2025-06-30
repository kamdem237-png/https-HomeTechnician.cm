<?php
session_start();

// --- Configuration de la base de données ---
$server_name = "localhost";
$user_name = "root";
$psw = ""; // Ton mot de passe MySQL (laisse-le vide si tu n'en as pas)
$DB_name = "depanage"; // Le nom de ta base de données

// Connexion à la base de données
$con = new mysqli($server_name, $user_name, $psw, $DB_name);

// Vérifier la connexion
if ($con->connect_error) {
    error_log("Erreur de connexion à la base de données : " . $con->connect_error);
    die("Désolé, une erreur technique est survenue. Veuillez réessayer plus tard.");
}

// Définir l'encodage des caractères pour éviter les problèmes d'accents
$con->set_charset("utf8mb4");

// --- Vérification de l'authentification du technicien ---
if (!isset($_SESSION['user']) || !is_array($_SESSION['user']) || $_SESSION['role'] !== 'technicien' || !isset($_SESSION['user']['id_technicien'])) {
    header("Location: connexion_technicien.php");
    exit();
}

$technicien_id = $_SESSION['user']['id_technicien'];
$mission = null;
$error = '';
$message = ''; // Pour afficher les messages de succès
$message_type = ''; // Initialiser le type de message pour les alertes Bootstrap

// Gérer les messages de succès/erreur de session (après une redirection par exemple)
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    $message_type = 'success'; // Définir le type pour les messages de succès
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error = $_SESSION['error_message'];
    $message_type = 'danger'; // Définir le type pour les messages d'erreur
    unset($_SESSION['error_message']);
}

// --- Récupération des détails de la mission (GET) ---
// L'ID de la mission doit venir via l'URL (GET) pour une page de détails
if (isset($_GET['id_mission'])) { // C'est ici que nous nous attendons à 'id_mission'
    $mission_id = filter_var($_GET['id_mission'], FILTER_VALIDATE_INT);

    if ($mission_id) {
        // Jointure avec la table 'client'
        // AJOUT de 'm.titre_probleme' dans le SELECT
        $stmt = $con->prepare("SELECT m.*, c.nom AS client_nom, c.email AS client_email, c.num_client AS client_num_client, c.zone AS client_zone
                               FROM mission m
                               JOIN client c ON m.id_client = c.id_client
                               WHERE m.id_mission = ?");
        $stmt->bind_param("i", $mission_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $mission = $result->fetch_assoc();
        } else {
            $error = "Mission introuvable ou vous n'avez pas l'autorisation d'y accéder.";
            $message_type = 'danger';
        }
        $stmt->close();
    } else {
        $error = "ID de mission invalide.";
        $message_type = 'danger';
    }
} else {
    $error = "Aucun ID de mission spécifié.";
    $message_type = 'danger';
}

// Vérifier si le technicien est déjà affecté à cette mission (pour le bouton)
$is_affected = false;
if ($mission && $mission['statut'] !== 'terminee' && $mission['statut'] !== 'annulee') {
    $stmt_check_affectation = $con->prepare("SELECT COUNT(*) FROM mission_technicien WHERE id_mission = ? AND id_technicien = ?");
    $stmt_check_affectation->bind_param("ii", $mission['id_mission'], $technicien_id);
    $stmt_check_affectation->execute();
    $stmt_check_affectation->bind_result($count_affectation);
    $stmt_check_affectation->fetch();
    $stmt_check_affectation->close();
    if ($count_affectation > 0) {
        $is_affected = true;
    }
}

$con->close(); // Fermer la connexion à la base de données
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails Mission - VotreSite</title>
    <link rel="icon" type="image/png" href="mon_logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/forms.css">

    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f8f9fa; padding-top: 76px; }
        .navbar { background-color: #343a40 !important; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); position: fixed; top: 0; width: 100%; z-index: 1030; }
        .navbar-brand img { max-width: 200px; height: auto; border-radius: 8px; object-fit: contain; }
        .navbar-nav .nav-link { color: rgba(255, 255, 255, 0.75) !important; font-weight: 500; margin-right: 15px; transition: color 0.3s ease; }
        .navbar-nav .nav-link:hover, .navbar-nav .nav-link.active { color: #007bff !important; }
        .btn-primary { background-color: #007bff; border-color: #007bff; }
        .btn-primary:hover { background-color: #0056b3; border-color: #0056b3; }
        .container { margin-top: 30px; margin-bottom: 50px; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08); margin-bottom: 30px; }
        .card-header { background-color: #007bff; color: white; border-top-left-radius: 12px; border-top-right-radius: 12px; font-size: 1.5rem; font-weight: bold; padding: 1.5rem; }
        .card-body { padding: 2.5rem; }
        .card-body p { margin-bottom: 1rem; line-height: 1.8; font-size: 1.05rem; color: #555; }
        .card-body strong { color: #333; }
        .alert { margin-top: 20px; }
        .footer { background-color: #343a40 !important; color: rgba(255, 255, 255, 0.7); }
        .footer h3 { color: white; }
        .footer a { color: rgba(255, 255, 255, 0.6); text-decoration: none; }
        .footer a:hover { color: white; }
        .footer .social-links a { color: rgba(255, 255, 255, 0.7); font-size: 1.5rem; transition: color 0.3s ease; }
        .footer .social-links a:hover { color: #28a745; }
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
                        <li class="nav-item"><a class="nav-link active" href="missions_disponibles.php">Mes missions</a></li>
                        <li class="nav-item"><a class="nav-link" href="contact_admin.php">Contact Admin</a></li>
                        <li class="nav-item"><a class="nav-link" href="client.php">Je suis un client</a></li>
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
        <div class="container">
            <?php if (!empty($message)): ?>
                <div class="alert alert-success mt-4" role="alert">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger mt-4" role="alert">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($mission): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        Détails de la Mission #<?= htmlspecialchars($mission['id_mission']) ?>
                    </div>
                    <div class="card-body">
                        <p><strong>Titre du problème :</strong> <?= htmlspecialchars($mission['titre_probleme']) ?></p>
                        <p><strong>Type de service :</strong> <?= htmlspecialchars($mission['type_service']) ?></p>
                        <p><strong>Description :</strong> <?= nl2br(htmlspecialchars($mission['description'])) ?></p>
                        <p><strong>Localisation :</strong> <?= htmlspecialchars($mission['localisation']) ?></p>
                        <p><strong>Date de demande :</strong> <?= (new DateTime($mission['date_demande']))->format('d/m/Y à H:i') ?></p>
                        <p>
                            <strong>Statut :</strong>
                            <?php
                            $badge_class = '';
                            switch ($mission['statut']) {
                                case 'en_attente': $badge_class = 'bg-info'; break;
                                case 'en_cours': $badge_class = 'bg-warning text-dark'; break;
                                case 'terminee': $badge_class = 'bg-success'; break;
                                case 'annulee': $badge_class = 'bg-danger'; break;
                                default: $badge_class = 'bg-secondary'; break;
                            }
                            ?>
                            <span class="badge <?= $badge_class ?>"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $mission['statut']))) ?></span>
                        </p>
                        <p><strong>Nombre de techniciens demandé :</strong> <?= htmlspecialchars($mission['nb_techniciens_demande']) ?></p>

                        <hr>
                        <h5>Informations Client</h5>
                        <p><strong>Nom du client :</strong> <?= htmlspecialchars($mission['client_nom']) ?></p>
                        <p><strong>Email du client :</strong> <a href="mailto:<?= htmlspecialchars($mission['client_email']) ?>"><?= htmlspecialchars($mission['client_email']) ?></a></p>
                        <p><strong>Téléphone du client :</strong> <?= htmlspecialchars($mission['client_num_client']) ?></p>
                        <p><strong>Zone du client :</strong> <?= htmlspecialchars($mission['client_zone']) ?></p>

                        <div class="mt-4 text-center">
                            <a href="missions_disponibles.php" class="btn btn-secondary me-2"><i class="fas fa-arrow-left me-2"></i> Retour aux missions</a>
                            
                            <?php if ($is_affected): ?>
                                <button class="btn btn-success" disabled>
                                    <i class="fas fa-check-circle me-2"></i> Vous êtes déjà affecté à cette mission
                                </button>
                                <?php if ($mission['statut'] === 'en_cours'): // Seulement si la mission est en cours ?>
                                    <a href="chat.php?id_mission=<?= (int)$mission['id_mission'] ?>" class="btn btn-info ms-2">
                                        <i class="bi bi-send-fill me-2"></i> Accéder au chat
                                    </a>
                                <?php endif; ?>
                            <?php elseif ($mission['statut'] === 'terminee' || $mission['statut'] === 'annulee'): ?>
                                <button class="btn btn-outline-secondary" disabled>
                                    <i class="fas fa-ban me-2"></i> Mission <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $mission['statut']))) ?>
                                </button>
                            <?php else: ?>
                                <form method="POST" action="accepter_mission.php" class="d-inline-block" onsubmit="return confirm('Confirmez-vous vouloir accepter cette mission ?');">
                                    <input type="hidden" name="id_mission" value="<?= (int)$mission['id_mission'] ?>">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-handshake me-2"></i> Accepter la Mission
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>