<?php
session_start();

// Inclure le fichier d'utilitaires qui contient la fonction logUserAction
require_once 'utils.php';

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
    header('location: connexion_admin.php'); // Redirect to admin login if not authenticated
    exit();
}

$message = '';
$message_type = '';

// Handle announcement deletion
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_annonce_to_delete = (int)$_GET['id'];
    $annonce_info = null;

    // --- IMPORTANT: Get announcement info BEFORE deletion for logging ---
    $stmt_get_annonce = $con->prepare("SELECT titre FROM annonces WHERE id_annonce = ?");
    if ($stmt_get_annonce) {
        $stmt_get_annonce->bind_param("i", $id_annonce_to_delete);
        $stmt_get_annonce->execute();
        $result_get_annonce = $stmt_get_annonce->get_result();
        $annonce_info = $result_get_annonce->fetch_assoc();
        $stmt_get_annonce->close();
    }

    $stmt = $con->prepare("DELETE FROM annonces WHERE id_annonce = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id_annonce_to_delete);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Annonce supprimée avec succès.";
            $_SESSION['message_type'] = "success";

            // --- Log the deletion action ---
            $admin_id = $_SESSION['id_admin'] ?? null;
            $annonce_title = $annonce_info['titre'] ?? 'Annonce inconnue'; // Fallback title
            logUserAction(
                'Suppression Annonce',
                $id_annonce_to_delete,
                'annonce',
                'Titre: ' . $annonce_title, // Use retrieved title for identifier
                "Annonce '{$annonce_title}' (ID: {$id_annonce_to_delete}) supprimée par l'administrateur (ID: {$admin_id})",
                $admin_id
            );

        } else {
            $_SESSION['message'] = "Erreur lors de la suppression de l'annonce : " . $stmt->error;
            $_SESSION['message_type'] = "danger";
        }
        $stmt->close();
    } else {
        $_SESSION['message'] = "Erreur de préparation de la requête de suppression : " . $con->error;
        $_SESSION['message_type'] = "danger";
    }
    header('location: gest_annonce.php'); // Redirect to prevent re-submission
    exit();
}

// Retrieve session messages after a redirect
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Retrieve active announcements for display in the management table
// We fetch all announcements here to allow management, not just visible ones
$sql = "SELECT id_annonce, titre, contenu, date_publication, visible_client, visible_technicien, visible_admin, statut_actif FROM annonces ORDER BY date_publication DESC";
$result = $con->query($sql);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Annonces - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="mon_logo.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="c.css">
    <style>
        /* Styles communs à vos pages d'administration */
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

        /* Footer styles */
        .footer { background-color: #343a40 !important; color: rgba(255, 255, 255, 0.7); padding: 50px 0; }
        .footer h3 { color: white; font-weight: 600; margin-bottom: 15px; }
        .footer a { color: rgba(255, 255, 255, 0.6); text-decoration: none; transition: color 0.3s ease; }
        .footer a:hover { color: white; text-decoration: underline; }
        .footer .social-links a { color: rgba(255, 255, 255, 0.7); font-size: 1.5rem; margin-right: 15px; transition: color 0.3s ease; }
        .footer .social-links a:hover { color: #28a745; } /* Vert pour les icônes sociales au survol */
    </style>
</head>
<body>
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container-fluid" style="margin-top:8px;">
                <a class="navbar-brand" href="admin.php" style="margin-top:5px;">Tableau de Bord Admin</a>

                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavAdmin" aria-controls="navbarNavAdmin" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarNavAdmin">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        <li class="nav-item"><a class="nav-link" href="admin.php">Accueil Admin</a></li>
                        <li class="nav-item"><a class="nav-link" href="gestion_users.php">Gestion des Utilisateurs</a></li>
                        <li class="nav-item"><a class="nav-link active" href="gest_annonce.php">Gestion Annonces</a></li>
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

<section>
    <div class="container mt-5">
        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="bg-white p-4 rounded shadow">
            <h2 class="text-center mb-4">Gestion des Annonces Actuelles</h2>
            <div class="text-end mb-3">
                <a href="ajouter_annonce.php" class="btn btn-success me-2">
                    <i class="bi bi-megaphone-fill"></i> Ajouter une annonce
                </a>
                <a href="historique_annonces.php" class="btn btn-info">
                    <i class="bi bi-clock-history"></i> Voir Historique Annonces
                </a>
            </div>

            <?php if ($result && $result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead>
                            <tr>
                                <th>Titre</th>
                                <th>Contenu</th>
                                <th>Date Publication</th>
                                <th>Actif</th>
                                <th>Clients</th>
                                <th>Techniciens</th>
                                <th>Admin</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['titre']) ?></td>
                                    <td><?= nl2br(htmlspecialchars(mb_strimwidth($row['contenu'], 0, 100, "..."))) ?></td>
                                    <td>
                                        <?php
                                        // Correction : Assurez-vous que la date n'est pas nulle avant de tenter de la formater
                                        if (isset($row['date_publication']) && $row['date_publication'] !== null) {
                                            echo (new DateTime($row['date_publication']))->format('d/m/Y H:i');
                                        } else {
                                            echo 'Date inconnue'; // Fallback si la date est null
                                        }
                                        ?>
                                    </td>
                                    <td><?= $row['statut_actif'] ? '<span class="badge bg-success">Oui</span>' : '<span class="badge bg-secondary">Non</span>' ?></td>
                                    <td><?= $row['visible_client'] ? '<span class="badge bg-success">Oui</span>' : '<span class="badge bg-danger">Non</span>' ?></td>
                                    <td><?= $row['visible_technicien'] ? '<span class="badge bg-success">Oui</span>' : '<span class="badge bg-danger">Non</span>' ?></td>
                                    <td><?= $row['visible_admin'] ? '<span class="badge bg-success">Oui</span>' : '<span class="badge bg-danger">Non</span>' ?></td>
                                    <td>
                                        <a href="modifier_annonce.php?id=<?= $row['id_annonce'] ?>" class="btn btn-warning btn-sm me-2" title="Modifier">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <a href="gest_annonce.php?action=delete&id=<?= $row['id_annonce'] ?>" class="btn btn-danger btn-sm" title="Supprimer"
                                            onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette annonce ?');">
                                            <i class="bi bi-trash"></i>
                                        </a>

                                        <button class="btn btn-info btn-sm mt-2" type="button" data-bs-toggle="collapse" data-bs-target="#logsAnnonce<?= $row['id_annonce'] ?>" aria-expanded="false" aria-controls="logsAnnonce<?= $row['id_annonce'] ?>">
                                            <i class="bi bi-info-circle"></i> Voir Logs
                                        </button>

                                        <div class="collapse mt-2" id="logsAnnonce<?= $row['id_annonce'] ?>">
                                            <div class="card card-body p-2 bg-light">
                                                <h6>Historique des actions sur cette annonce:</h6>
                                                <?php
                                                // Retrieve logs related to this specific announcement
                                                $stmt_logs = $con->prepare("SELECT action_time, action_type, description, target_identifier, admin_id FROM action_logs WHERE target_role = 'annonce' AND target_id = ? ORDER BY action_time DESC LIMIT 5");
                                                if ($stmt_logs) {
                                                    $stmt_logs->bind_param("i", $row['id_annonce']);
                                                    $stmt_logs->execute();
                                                    $result_logs = $stmt_logs->get_result();

                                                    if ($result_logs->num_rows > 0) {
                                                        echo "<ul class='list-group list-group-flush'>";
                                                        while($log = $result_logs->fetch_assoc()) {
                                                            $log_admin_id = $log['admin_id'] ?? 'N/A';
                                                            echo "<li class='list-group-item p-1 border-0'><small>";
                                                            echo "[" . (new DateTime($log['action_time']))->format('d/m/Y H:i') . "] ";
                                                            echo "<strong>" . htmlspecialchars($log['action_type']) . "</strong> : ";
                                                            echo htmlspecialchars($log['description']);
                                                            echo "</small></li>";
                                                        }
                                                        echo "</ul>";
                                                    } else {
                                                        echo "<small class='text-muted'>Aucun log d'action trouvé directement pour cette annonce.</small>";
                                                    }
                                                    $stmt_logs->close();
                                                } else {
                                                    error_log("Erreur de préparation de la requête de logs (annonce): " . $con->error);
                                                    echo "<small class='text-danger'>Erreur de chargement des logs.</small>";
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-center text-muted p-3">Aucune annonce active trouvée. <a href="ajouter_annonce.php">Ajoutez-en une nouvelle.</a></p>
            <?php endif; ?>
        </div>
    </div>
</section>

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
                    <li><a href="a_propos.php" class="text-white-50 text-decoration-none">À Propos de Nous</a></li>
                    <li><a href="#" class="text-white-50 text-decoration-none">FAQ Admin</a></li>
                    <li><a href="#" class="text-white-50 text-decoration-none">Mentions Légales</a></li>
                    <li><a href="#" class="text-white-50 text-decoration-none">Politique de Confidentialité</a></li>
                    <li><a href="#" class="text-white-50 text-decoration-none">Conditions Générales</a></li>
                </ul>
            </div>
            <div class="col-md-3 mb-4 mb-md-0">
                <h3 class="text-white mb-3">Services Populaires</h3>
                <ul class="list-unstyled">
                    <li><a href="#" class="text-white-50 text-decoration-none">Réparation d'Ordinateur</a></li>
                    <li><a href="#" class="text-white-50 text-decoration-none">Installation Électrique</a></li>
                    <li><a href="#" class="text-white-50 text-decoration-none">Dépannage Plomberie</a></li>
                    <li><a href="#" class="text-white-50 text-decoration-none">Entretien Climatisation</a></li>
                </ul>
            </div>
            <div class="col-md-3">
                <h3 class="text-white mb-3">Contactez-nous</h3>
                <p><i class="fas fa-envelope me-2 text-success"></i> contact@votresite.com</p>
                <p><i class="fas fa-phone me-2 text-success"></i> +237 6XX XXX XXX</p>
                <div class="social-links mt-3">
                    <a href="#" class="text-white-50 me-3 fs-4"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="text-white-50 me-3 fs-4"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="text-50 fs-4"><i class="fab fa-linkedin-in"></i></a>
                    <a href="#" class="text-white-50 fs-4"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
        </div>
        <div class="footer-bottom text-center pt-4 mt-4 border-top border-secondary">
            <p class="mb-0">&copy; 2025 VotreSite. Tous droits réservés.</p>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php $con->close(); ?>