<?php
session_start();

// Database connection parameters
$server_name = "localhost";
$user_name = "root";
$psw = ""; // Ensure this is the correct password for your 'root' user
$DB_name = "depanage";

// Establish database connection
$con = new mysqli($server_name, $user_name, $psw, $DB_name);

// Check connection
if ($con->connect_error) {
    error_log("Erreur de connexion à la base de données : " . $con->connect_error);
    die("Désolé, une erreur technique est survenue. Veuillez réessayer plus tard.");
}

// Admin authentication check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || ($_SESSION['role'] ?? '') !== 'admin') {
    header('location: connexion_admin.php');
    exit();
}

// Include the utility function to get announcements
// Make sure utils.php is in the same directory or adjust the path accordingly.
include_once 'utils.php';


$announcements = get_announcements('admin');

?>

<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="mon_logo.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="c.css">
    <title>Tableau de Bord Admin</title>
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
        .card-header { background-color: #007bff; color: white; border-top-left-radius: 12px; border-top-right-radius: 12px; font-size: 1.8rem; font-weight: bold; padding: 1.5rem; text-align: center;}
        .card-body { padding: 2.5rem; }
        .alert { margin-top: 20px; }
        .table { margin-top: 20px; }
        .table th, .table td { vertical-align: middle; }
        .table .badge { font-size: 0.8em; padding: 0.5em 0.8em; }
        .message-unread { font-weight: bold; }
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
            <div class="container-fluid" style="margin-top:10px;">
                <a class="navbar-brand" href="admin.php" style="margin-top:0px;">Tableau de Bord Admin</a>

                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavAdmin" aria-controls="navbarNavAdmin" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarNavAdmin">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        <li class="nav-item"><a class="nav-link active" href="admin.php">Accueil Admin</a></li>
                        <li class="nav-item"><a class="nav-link" href="gestion_users.php">Gestion des Utilisateurs</a></li>
                        <li class="nav-item"><a class="nav-link" href="gest_annonce.php">Gestion Annonces</a></li>
                        <li class="nav-item"><a class="nav-link" href="probleme_users.php">Problèmes Utilisateurs</a></li>
                        <li class="nav-item"><a class="nav-link" href="admin_gestion_certifications.php">Certifications Techniciens</a></li>
                        <li class="nav-item"><a class="nav-link" aria-current="page" href="admin_messages.php">Messages Contact</a></li>
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

    <main class="container mt-4">
        <center>
            <div class="bg-white p-4 rounded shadow">
                <h2 class="text-center mb-4">Bienvenue dans le compte administrateur</h2>
                <p>C'est ici que vous gérez tous les aspects de votre plateforme.</p>
                <a href="gest_client.php" class="btn btn-success mt-3">Gérer les Clients</a>
                <a href="gest_technicien.php" class="btn btn-success mt-3 ms-2">Gérer les Techniciens</a>
                <a href="gest_annonce.php" class="btn btn-info mt-3 ms-2">Gérer les Annonces</a>
                <a href="probleme_users.php" class="btn btn-warning mt-3 ms-2">Voir Problèmes Utilisateurs</a>
                <a href="admin_message.php" class="btn btn-primary mt-3 ms-2">Messages Missions</a>
            </div>
        </center>

        <div class="mt-5 p-4 bg-light border rounded">
            <h3 class="mb-3"><i class="bi bi-megaphone"></i> Annonces récentes pour l'Admin :</h3>
            <?php if (!empty($announcements)): ?>
                <?php foreach ($announcements as $annonce): ?>
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($annonce['titre']) ?></h5>
                            <h6 class="card-subtitle mb-2 text-muted">Publié le <?= (new DateTime($annonce['date_publication']))->format('d/m/Y à H:i') ?></h6>
                            <p class="card-text"><?= nl2br(htmlspecialchars($annonce['contenu'])) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted">Aucune annonce disponible pour le moment.</p>
            <?php endif; ?>
        </div>
    </main>

    <footer>
    </footer>
</body>
</html>

<?php $con->close(); ?>
