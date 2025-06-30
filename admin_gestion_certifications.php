<?php
session_start();

// --- VÉRIFICATION DU RÔLE ADMINISTRATEUR BASÉE SUR LA TABLE 'admin' ---
// Redirection si l'utilisateur n'est pas connecté en tant qu'administrateur
// Ceci est un exemple. Adaptez-le à votre logique d'authentification admin.
// Par exemple, vous pourriez avoir une session['is_admin'] ou vérifier l'ID de l'utilisateur dans une table 'admins'.

// --- Configuration de la base de données ---
$server_name = "localhost";
$user_name = "root";
$psw = ""; // Assurez-vous que c'est bien vide ou le mot de passe de votre base de données
$DB_name = "depanage";

// Connexion à la base de données
$conn = new mysqli($server_name, $user_name, $psw, $DB_name);
if ($conn->connect_error) {
    // Enregistre l'erreur dans les logs du serveur, NE PAS afficher publiquement
    error_log("Erreur de connexion à la base de données : " . $conn->connect_error);
    die("Désolé, une erreur technique est survenue. Veuillez réessayer plus tard.");
}
$conn->set_charset("utf8mb4");

$message = ""; // Variable pour les messages de succès
$error = "";   // Variable pour les messages d'erreur

// --- TRAITEMENT DES ACTIONS D'ADMINISTRATION SUR LES CERTIFICATIONS ---

// 1. Approuver une certification
if (isset($_POST['approve_certification'])) {
    $id_certification = (int)$_POST['id_certification'];

    $stmt = $conn->prepare("UPDATE certifications SET statut = 'approuve' WHERE id_certification = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id_certification);
        if ($stmt->execute()) {
            $message = "Certification approuvée avec succès.";
        } else {
            $error = "Erreur lors de l'approbation de la certification : " . $stmt->error;
        }
        $stmt->close();
    } else {
        $error = "Erreur de préparation de la requête d'approbation.";
    }
}

// 2. Rejeter une certification
if (isset($_POST['reject_certification'])) {
    $id_certification = (int)$_POST['id_certification'];

    $stmt = $conn->prepare("UPDATE certifications SET statut = 'rejete' WHERE id_certification = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id_certification);
        if ($stmt->execute()) {
            $message = "Certification rejetée avec succès.";
        } else {
            $error = "Erreur lors du rejet de la certification : " . $stmt->error;
        }
        $stmt->close();
    } else {
        $error = "Erreur de préparation de la requête de rejet.";
    }
}

// 3. Supprimer une certification (physiquement et en BD)
if (isset($_POST['delete_certification'])) {
    $id_certification = (int)$_POST['id_certification'];

    // Récupérer le chemin du fichier avant de le supprimer de la BD
    $stmt_get_file = $conn->prepare("SELECT chemin_certification FROM certifications WHERE id_certification = ?");
    if ($stmt_get_file) {
        $stmt_get_file->bind_param("i", $id_certification);
        $stmt_get_file->execute();
        $result_file = $stmt_get_file->get_result();
        $file_row = $result_file->fetch_assoc();
        $stmt_get_file->close();

        if ($file_row && !empty($file_row['chemin_certification'])) {
            $file_to_delete = $file_row['chemin_certification'];

            $stmt_delete = $conn->prepare("DELETE FROM certifications WHERE id_certification = ?");
            if ($stmt_delete) {
                $stmt_delete->bind_param("i", $id_certification);
                if ($stmt_delete->execute()) {
                    // Supprimer le fichier réel du serveur
                    if (file_exists($file_to_delete)) {
                        unlink($file_to_delete);
                    }
                    $message = "Certification supprimée avec succès (fichier inclus).";
                } else {
                    $error = "Erreur lors de la suppression de la certification : " . $stmt_delete->error;
                }
                $stmt_delete->close();
            } else {
                $error = "Erreur de préparation pour la suppression de certification.";
            }
        } else {
            $error = "Fichier de certification non trouvé ou déjà supprimé pour cette ID.";
        }
    } else {
        $error = "Erreur de préparation pour récupérer le chemin du fichier de certification.";
    }
}


// --- RÉCUPÉRATION DES CERTIFICATIONS POUR AFFICHAGE ---
$certifications = [];
$filter_status = $_GET['status'] ?? 'all'; // Par défaut, afficher toutes les certifications

$sql = "SELECT c.id_certification, c.nom_certification, c.chemin_certification, c.statut, c.date_ajout, t.prenom, t.nom, t.id_technicien
        FROM certifications c
        JOIN technicien t ON c.id_technicien = t.id_technicien";

if ($filter_status !== 'all') {
    $sql .= " WHERE c.statut = ?";
}
$sql .= " ORDER BY c.date_ajout DESC";

$stmt_fetch = $conn->prepare($sql);

if ($stmt_fetch) {
    if ($filter_status !== 'all') {
        $stmt_fetch->bind_param("s", $filter_status);
    }
    $stmt_fetch->execute();
    $result = $stmt_fetch->get_result();
    while ($row = $result->fetch_assoc()) {
        $certifications[] = $row;
    }
    $stmt_fetch->close();
} else {
    $error = "Erreur de préparation de la requête de récupération des certifications : " . $conn->error;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Gestion des Certifications</title>
    <link rel="icon" type="image/png" href="mon_logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
                        <li class="nav-item"><a class="nav-link" href="admin.php">Accueil Admin</a></li>
                        <li class="nav-item"><a class="nav-link" href="gestion_users.php">Gestion des Utilisateurs</a></li>
                        <li class="nav-item"><a class="nav-link" href="gest_annonce.php">Gestion Annonces</a></li>
                        <li class="nav-item"><a class="nav-link" href="probleme_users.php">Problèmes Utilisateurs</a></li>
                        <li class="nav-item"><a class="nav-link active" href="admin_gestion_certifications.php">Certifications Techniciens</a></li>
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


    <main class="container mt-5 py-4">
        <h3 class="mb-4 text-center">Gestion des Certifications des Techniciens</h3>

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm mb-4">
            <div class="card-header text-center py-3">
                <h5 class="mb-0"><i class="fas fa-certificate me-2"></i> Liste des Certifications</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="statusFilter" class="form-label">Filtrer par statut :</label>
                    <select class="form-select" id="statusFilter" onchange="window.location.href='admin_gestion_certifications.php?status=' + this.value;">
                        <option value="all" <?= ($filter_status == 'all') ? 'selected' : '' ?>>Toutes</option>
                        <option value="en_attente" <?= ($filter_status == 'en_attente') ? 'selected' : '' ?>>En Attente</option>
                        <option value="approuve" <?= ($filter_status == 'approuve') ? 'selected' : '' ?>>Approuvées</option>
                        <option value="rejete" <?= ($filter_status == 'rejete') ? 'selected' : '' ?>>Rejetées</option>
                    </select>
                </div>

                <?php if (!empty($certifications)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead>
                                <tr>
                                    <th>ID Cert.</th>
                                    <th>Technicien</th>
                                    <th>Certification</th>
                                    <th>Fichier</th>
                                    <th>Date d'ajout</th>
                                    <th>Statut</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($certifications as $cert): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($cert['id_certification']) ?></td>
                                        <td><a href="admin_voir_technicien.php?id=<?= $cert['id_technicien'] ?>"><?= htmlspecialchars($cert['prenom'] . ' ' . $cert['nom']) ?></a></td>
                                        <td><?= htmlspecialchars($cert['nom_certification']) ?></td>
                                        <td>
                                            <?php if (!empty($cert['chemin_certification']) && file_exists($cert['chemin_certification'])): ?>
                                                <a href="<?= htmlspecialchars($cert['chemin_certification']) ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Voir le fichier">
                                                    <i class="fas fa-file-alt"></i> Voir
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">Fichier introuvable</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('d/m/Y H:i', strtotime($cert['date_ajout'])) ?></td>
                                        <td>
                                            <?php
                                                $badge_class = 'badge ';
                                                switch ($cert['statut']) {
                                                    case 'en_attente': $badge_class .= 'bg-warning text-dark badge-status-en_attente'; break;
                                                    case 'approuve': $badge_class .= 'bg-success badge-status-approuve'; break;
                                                    case 'rejete': $badge_class .= 'bg-danger badge-status-rejete'; break;
                                                    default: $badge_class .= 'bg-secondary badge-status-unknown'; break;
                                                }
                                            ?>
                                            <span class="<?= $badge_class ?>"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $cert['statut']))) ?></span>
                                        </td>
                                        <td class="text-center">
                                            <form action="" method="POST" class="d-inline-block me-1">
                                                <input type="hidden" name="id_certification" value="<?= $cert['id_certification'] ?>">
                                                <?php if ($cert['statut'] === 'en_attente' || $cert['statut'] === 'rejete'): ?>
                                                    <button type="submit" name="approve_certification" class="btn btn-sm btn-success" title="Approuver">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($cert['statut'] === 'en_attente' || $cert['statut'] === 'approuve'): ?>
                                                    <button type="submit" name="reject_certification" class="btn btn-sm btn-warning text-dark" title="Rejeter">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </form>
                                            <form action="" method="POST" class="d-inline-block">
                                                <input type="hidden" name="id_certification" value="<?= $cert['id_certification'] ?>">
                                                <button type="submit" name="delete_certification" class="btn btn-sm btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette certification et son fichier ? Cette action est irréversible.');" title="Supprimer">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-center text-muted">Aucune certification trouvée pour le statut "<?= htmlspecialchars(ucfirst(str_replace('_', ' ', $filter_status))) ?>".</p>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <footer class="footer bg-dark text-white-50 py-5 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-3 mb-4 mb-md-0">
                    <h3 class="text-white mb-3">Admin Panel</h3>
                    <p>Gestion centrale de la plateforme HomeTechnician.</p>
                </div>
                <div class="col-md-3 mb-4 mb-md-0">
                    <h3 class="text-white mb-3">Navigation</h3>
                    <ul class="list-unstyled">
                        <li><a href="admin_dashboard.php" class="text-white-50 text-decoration-none">Tableau de Bord</a></li>
                        <li><a href="admin_gestion_techniciens.php" class="text-white-50 text-decoration-none">Gérer Techniciens</a></li>
                        <li><a href="admin_gestion_clients.php" class="text-white-50 text-decoration-none">Gérer Clients</a></li>
                        <li><a href="admin_gestion_certifications.php" class="text-white-50 text-decoration-none">Certifications</a></li>
                        <li><a href="admin_gestion_signalements.php" class="text-white-50 text-decoration-none">Signalements</a></li>
                    </ul>
                </div>
                <div class="col-md-3 mb-4 mb-md-0">
                    <h3 class="text-white mb-3">Ressources</h3>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-white-50 text-decoration-none">Documentation</a></li>
                        <li><a href="#" class="text-white-50 text-decoration-none">Journal des Erreurs</a></li>
                        <li><a href="#" class="text-white-50 text-decoration-none">Statistiques du Site</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h3 class="text-white mb-3">Support</h3>
                    <p><i class="fas fa-envelope me-2 text-success"></i> admin@votresite.com</p>
                    <p><i class="fas fa-phone me-2 text-success"></i> +237 XXX XXX XXX</p>
                    <div class="social-links mt-3">
                        <a href="#" class="text-white-50 me-3 fs-4"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-white-50 me-3 fs-4"><i class="fab fa-twitter"></i></a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom text-center pt-4 mt-4 border-top border-secondary">
                <p class="mb-0">&copy; 2025 HomeTechnician Admin. Tous droits réservés.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>