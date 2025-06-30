<?php
session_start();

// Vérification de l'authentification de l'administrateur
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || ($_SESSION['role'] ?? '') !== 'admin') {
    header('location: connexion_admin.php');
    exit();
}

// Paramètres de connexion à la base de données
$server_name = "localhost";
$user_name = "root";
$psw = "";
$DB_name = "depanage";

// Connexion à la base de données
$con = new mysqli($server_name, $user_name, $psw, $DB_name);

// Vérification de la connexion
if ($con->connect_error) {
    error_log("Erreur de connexion à la base de données : " . $con->connect_error);
    die("Désolé, une erreur technique est survenue. Veuillez réessayer plus tard.");
}

// Inclure la fonction utilitaire pour récupérer les annonces
// You need to make sure 'utils.php' actually exists and contains the get_announcements function.
// For demonstration purposes, I'll define a dummy one if it's not provided.
if (!function_exists('get_announcements')) {
    function get_announcements($role, $include_inactive = false) {
        global $con; // Use the global connection variable
        $sql = "SELECT id_annonce, titre, contenu, date_publication, visible_client, visible_technicien, visible_admin, statut_actif FROM annonces";
        if (!$include_inactive) {
            $sql .= " WHERE statut_actif = 1";
        }
        $sql .= " ORDER BY date_publication DESC";
        $result = $con->query($sql);
        $announcements = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $announcements[] = $row;
            }
        }
        return $announcements;
    }
}


// Récupérer TOUTES les annonces pour l'historique (actives et inactives)
$announcements_history = get_announcements('admin', true); // Le second paramètre à 'true' est crucial ici

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="mon_logo.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="c.css">
    <title>Historique des Annonces</title>
</head>
<body>
<header>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="admin.php">Tableau de Bord Admin</a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavHistAnnonce" aria-controls="navbarNavHistAnnonce" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNavHistAnnonce">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="admin.php">Accueil Admin</a></li>
                    <li class="nav-item"><a class="nav-link" href="gestion_users.php">Gestion des Utilisateurs</a></li>
                    <li class="nav-item"><a class="nav-link active" href="gest_annonce.php">Gestion Annonces </a></li>
                    <li class="nav-item"><a class="nav-link" href="probleme_users.php">Problèmes Utilisateurs</a></li>
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
        <div class="bg-white p-4 rounded shadow">
            <h2 class="text-center mb-4">Historique de Toutes les Annonces</h2>
            <div class="text-end mb-3">
                <a href="gest_annonce.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left-circle"></i> Retour à la gestion des annonces
                </a>
            </div>

            <?php if (!empty($announcements_history)): ?>
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
                            <?php foreach ($announcements_history as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['titre']) ?></td>
                                    <td><?= nl2br(htmlspecialchars(mb_strimwidth($row['contenu'], 0, 100, "..."))) ?></td>
                                    <td><?= (new DateTime($row['date_publication']))->format('d/m/Y H:i') ?></td>
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
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-center text-muted p-3">Aucune annonce historique trouvée.</p>
            <?php endif; ?>
        </div>
    </div>
</section>
</body>
</html>

<?php $con->close(); ?>