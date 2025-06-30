<?php
session_start();

// --- Configuration de la base de données ---
$server_name = "localhost";
$user_name = "root";
$psw = ""; // Remplacez par votre mot de passe MySQL
$DB_name = "depanage"; // Le nom de votre base de données

// Connexion à la base de données
$con = new mysqli($server_name, $user_name, $psw, $DB_name);

// Vérifier la connexion
if ($con->connect_error) {
    error_log("Erreur de connexion à la base de données : " . $con->connect_error);
    die("Désolé, une erreur technique est survenue. Veuillez réessayer plus tard.");
}

$con->set_charset("utf8mb4");

// --- Vérification de l'authentification de l'administrateur ---
// Assurez-vous que seule une personne avec le rôle 'admin' peut accéder à cette page
if (!isset($_SESSION['user']) || !is_array($_SESSION['user']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: connexion_admin.php"); // Rediriger vers la page de connexion admin
    exit();
}

$messages_de_contact = [];
$error = '';
$message = '';

// Récupérer les messages de contact depuis la base de données
$stmt = $con->prepare("SELECT id_message, nom_expediteur, email_expediteur, sujet, date_envoi, lu FROM messages_contact ORDER BY date_envoi DESC");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $messages_de_contact[] = $row;
    }
} else {
    $message = "Aucun message de contact trouvé pour le moment.";
}
$stmt->close();

// Gérer les messages de succès/erreur de session (par exemple, après une action de lecture)
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages Admin - VotreSite</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="icon" type="image/png" href="mon_logo.png">
    <style>
        /* Styles généraux pour le corps et la navigation */
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f8f9fa; padding-top: 76px; }
        .navbar { background-color: #343a40 !important; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); position: fixed; top: 0; width: 100%; z-index: 1030; }
        .navbar-brand img { max-width: 200px; height: auto; border-radius: 8px; object-fit: contain; }
        .navbar-nav .nav-link { color: rgba(255, 255, 255, 0.75) !important; font-weight: 500; margin-right: 15px; transition: color 0.3s ease; }
        .navbar-nav .nav-link:hover, .navbar-nav .nav-link.active { color: #007bff !important; }
        .btn-primary { background-color: #007bff; border-color: #007bff; }
        .btn-primary:hover { background-color: #0056b3; border-color: #0056b3; }
        
        /* Styles pour le contenu principal (carte des messages) */
        .container { margin-top: 30px; margin-bottom: 50px; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08); margin-bottom: 30px; }
        .card-header { background-color: #007bff; color: white; border-top-left-radius: 12px; border-top-right-radius: 12px; font-size: 1.8rem; font-weight: bold; padding: 1.5rem; text-align: center;}
        .card-body { padding: 2.5rem; }
        .alert { margin-top: 20px; }
        
        /* Styles pour le tableau des messages */
        .table { margin-top: 20px; }
        .table th, .table td { vertical-align: middle; }
        .table .badge { font-size: 0.8em; padding: 0.5em 0.8em; }
        .message-unread { font-weight: bold; } /* Met en gras les messages non lus */
        
        /* Styles pour le pied de page */
        .footer { background-color: #343a40 !important; color: rgba(255, 255, 255, 0.7); padding: 3rem 0; }
        .footer h3 { color: white; font-weight: 600; }
        .footer a { color: rgba(255, 255, 255, 0.6); transition: color 0.3s ease; text-decoration: none; }
        .footer a:hover { color: white; text-decoration: underline; }
        .footer .social-links a { color: rgba(255, 255, 255, 0.7); font-size: 1.5rem; margin-right: 15px; transition: color 0.3s ease; }
        .footer .social-links a:hover { color: #28a745; }
    </style>
</head>
<body>
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container-fluid" style="margin-top:-10px;">
                <a class="navbar-brand" href="admin.php" style="margin-top:10px;">Tableau de Bord Admin</a>

                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavAdmin" aria-controls="navbarNavAdmin" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarNavAdmin">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        <li class="nav-item"><a class="nav-link" href="admin.php">Accueil Admin</a></li>
                        <li class="nav-item"><a class="nav-link" href="gestion_users.php">Gestion des Utilisateurs</a></li>
                        <li class="nav-item"><a class="nav-link" href="gest_annonce.php">Gestion Annonces</a></li>
                        <li class="nav-item"><a class="nav-link" href="probleme_users.php">Problèmes Utilisateurs</a></li>
                        <li class="nav-item"><a class="nav-link active" aria-current="page" href="admin_messages.php">Messages Contact</a></li>
                        <li class="nav-item"><a class="nav-link" href="admin_missions_chat.php">Messageries Missions</a></li>
                    </ul>
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="logout_admin.php"><button class="btn btn-outline-light">Déconnexion</button></a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <main>
        <div class="container">
            <div class="card mt-4">
                <div class="card-header">
                    <i class="fas fa-inbox me-2"></i> Messages de Contact
                </div>
                <div class="card-body">
                    <?php if (!empty($message)): ?>
                        <div class="alert alert-info" role="alert">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (count($messages_de_contact) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Expéditeur</th>
                                        <th>Email</th>
                                        <th>Sujet</th>
                                        <th>Date</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($messages_de_contact as $msg): ?>
                                        <tr class="<?= $msg['lu'] ? '' : 'message-unread' ?>">
                                            <td><?= htmlspecialchars($msg['id_message']) ?></td>
                                            <td><?= htmlspecialchars($msg['nom_expediteur']) ?></td>
                                            <td><?= htmlspecialchars($msg['email_expediteur']) ?></td>
                                            <td><?= htmlspecialchars($msg['sujet']) ?></td>
                                            <td><?= (new DateTime($msg['date_envoi']))->format('d/m/Y H:i') ?></td>
                                            <td>
                                                <span class="badge <?= $msg['lu'] ? 'bg-secondary' : 'bg-success' ?>">
                                                    <?= $msg['lu'] ? 'Lu' : 'Non lu' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="admin_lire_message.php?id=<?= htmlspecialchars($msg['id_message']) ?>" class="btn btn-sm btn-info" title="Lire le message">
                                                    <i class="fas fa-eye"></i> Lire
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-light text-center" role="alert">
                            Aucun message de contact à afficher.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <footer class="footer bg-dark text-white-50 py-5">
        <div class="container">
            <div class="row">
                <div class="col-md-3 mb-4 mb-md-0">
                    <h3 class="text-white mb-3">VotreSite</h3>
                    <p>La plateforme qui connecte les clients avec des techniciens qualifiés pour tous leurs besoins de service au Cameroun.</p>
                </div>
                <div class="col-md-3 mb-4 mb-md-0">
                    <h3 class="text-white mb-3">Liens Utiles</h3>
                    <ul class="list-unstyled">
                        <li><a href="a_propos.php">À Propos de Nous</a></li>
                        <li><a href="#">FAQ Techniciens</a></li>
                        <li><a href="#">Mentions Légales</a></li>
                        <li><a href="#">Politique de Confidentialité</a></li>
                        <li><a href="#">Conditions Générales</a></li>
                    </ul>
                </div>
                <div class="col-md-3 mb-4 mb-md-0">
                    <h3 class="text-white mb-3">Services Populaires</h3>
                    <ul class="list-unstyled">
                        <li><a href="#">Réparation d'Ordinateur</a></li>
                        <li><a href="#">Installation Électrique</a></li>
                        <li><a href="#">Dépannage Plomberie</a></li>
                        <li><a href="#">Entretien Climatisation</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h3 class="text-white mb-3">Contactez-nous</h3>
                    <p><i class="fas fa-envelope me-2 text-success"></i> contact@votresite.com</p>
                    <p><i class="fas fa-phone me-2 text-success"></i> +237 6XX XXX XXX</p>
                    <div class="social-links mt-3">
                        <a href="#" class="me-3 fs-4"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="me-3 fs-4"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="me-3 fs-4"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#" class="fs-4"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom text-center pt-4 mt-4 border-top border-secondary">
                <p class="mb-0">&copy; 2025 VotreSite. Tous droits réservés.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eJBnK1e8p5x9YfE" crossorigin="anonymous"></script>
</body>
</html>

<?php
$con->close();
?>