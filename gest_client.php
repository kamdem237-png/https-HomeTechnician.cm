<?php
// gest_client.php

session_start();

// Standardized Verification for Admin Authentication
// We check for 'admin_logged_in' flag AND 'role' being 'admin'
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('location: connexion_admin.php');
    exit(); // Always exit() after a redirection
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
    // Enregistrement de l'erreur au lieu de l'afficher directement à l'utilisateur
    error_log("Erreur de connexion à la base de données : " . $con->connect_error);
    die("Désolé, une erreur technique est survenue. Veuillez réessayer plus tard.");
}

$zone_recherchee = "";
$result = null;

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['zone'])) {
    $zone_recherchee = trim($_POST['zone']);

    if ($zone_recherchee !== "") {
        // Requête préparée pour éviter les injections SQL
        // MODIFICATION ICI : Ajout de is_active et is_banned
        $stmt = $con->prepare("SELECT id_client, nom, num_client, email, zone, en_quarantaine, is_active, is_banned FROM client WHERE zone LIKE ?");
        $like_zone = "%" . $zone_recherchee . "%"; // Utilisation correcte du wildcard
        $stmt->bind_param("s", $like_zone);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
    }
}

// Si aucun filtre n'est appliqué ou si la recherche est vide, afficher tous les clients
if ($result === null || ($result && $result->num_rows === 0 && !empty($zone_recherchee))) {
    // This condition ensures that if a search was performed and yielded no results,
    // we still show "No clients found for..." and don't fall back to all clients.
    // If $result is null (initial load) or search was empty, we fetch all.
    if ($result === null || empty($zone_recherchee)) {
        // MODIFICATION ICI : Ajout de is_active et is_banned
        $result = $con->query("SELECT id_client, nom, num_client, email, zone, en_quarantaine, is_active, is_banned FROM client ORDER BY nom ASC");
        if (!$result) {
            error_log("Erreur lors de la récupération de tous les clients : " . $con->error);
            die("Erreur lors du chargement des clients.");
        }
    }
}

// Message de succès/erreur après une action (suppression, ajout, modification, ban/unban, quarantaine)
$message = '';
$message_type = ''; // 'success' ou 'danger'

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']); // Nettoyer le message après affichage
    unset($_SESSION['message_type']);
}
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
    <link rel="stylesheet" href="c.css"> <title>Gestion des Clients</title>
    <style>
        /* Styles CSS supplémentaires pour les éléments spécifiques du formulaire */
        .div2 {
            padding: 20px;
            margin-top: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        #zone {
            padding: 8px 12px;
            border: 1px solid #ced4da;
        }
        input[type="submit"] {
            padding: 8px 20px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        input[type="submit"]:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
<header>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid" style="margin-top:20px;">
            <a class="navbar-brand" href="admin.php" style="margin-top:-10px;">Tableau de Bord Admin</a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavGestClient" aria-controls="navbarNavGestClient" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNavGestClient">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="admin.php">Accueil Admin</a></li>
                    <li class="nav-item"><a class="nav-link active" href="gest_client.php">Gestion des Clients</a></li>
                    <li class="nav-item"><a class="nav-link" href="gest_technicien.php">Gestion des Techniciens</a></li>
                    <li class="nav-item"><a class="nav-link" href="admin_message.php">Gestion des Messages</a></li>
                    <li class="nav-item"><a class="nav-link" href="gest_annonce.php">Gestion Annonces</a></li>
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

    <center>
        <div class="container div2" style="background-color: rgba(97, 176, 255, 0.959);">
            <form method="post" action="">
                <input type="text" name="zone" placeholder=" zone d'habitation" id="zone"
                    style="margin-right: 25px; width: 300px; height: 40px; border-radius: 5px;"
                    value="<?= htmlspecialchars($zone_recherchee) ?>">
                <input type="submit" value="Rechercher">
            </form>
        </div><br><br>
    </center>
</header>

<section>
    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="bg-white m-auto rounded-top p-4 shadow">
            <h2 class="text-center mb-4">Clients membres du site :</h2>
            <div class="text-end mb-3">
                <a href="ajouter_client.php" class="btn btn-primary">
                    <i class="bi bi-person-plus-fill"></i> Ajouter un client
                </a>
            </div>

            <?php if ($result && $result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Numéro du client</th>
                                <th>E-mail</th>
                                <th>Zone</th>
                                <th>Statut Quarantaine</th>
                                <th>Statut Compte</th> <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['nom']) ?></td>
                                    <td><?= htmlspecialchars($row['num_client']) ?></td>
                                    <td><?= htmlspecialchars($row['email']) ?></td>
                                    <td><?= htmlspecialchars($row['zone']) ?></td>
                                    <td>
                                        <?php if ($row['en_quarantaine']): ?>
                                            <span class="badge bg-danger">En Quarantaine</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Non Quarantaine</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($row['is_banned'] == 1): ?>
                                            <span class="badge bg-dark">Banni</span>
                                        <?php elseif ($row['is_active'] == 0): ?>
                                            <span class="badge bg-secondary">Désactivé</span>
                                        <?php else: ?>
                                            <span class="badge bg-primary">Actif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="modifier_client.php?id=<?= $row['id_client'] ?>" class="btn btn-warning btn-sm me-2" title="Modifier le client">
                                            <i class="bi bi-pencil-square"></i> Modifier
                                        </a>
                                        
                                        <?php if ($row['en_quarantaine']): ?>
                                            <a href="quarantaine_client.php?id=<?= $row['id_client'] ?>&action=unquarantine" class="btn btn-success btn-sm me-2" title="Retirer de la quarantaine"
                                                onclick="return confirm('Êtes-vous sûr de vouloir retirer ce client de la quarantaine ?');">
                                                <i class="bi bi-person-check-fill"></i> Lever Quarantaine
                                            </a>
                                        <?php else: ?>
                                            <a href="quarantaine_client.php?id=<?= $row['id_client'] ?>&action=quarantine" class="btn btn-info btn-sm me-2" title="Mettre en quarantaine"
                                                onclick="return confirm('Êtes-vous sûr de vouloir mettre ce client en quarantaine ? Cela restreindra potentiellement ses accès.');">
                                                <i class="bi bi-person-badge-fill"></i> Mettre en Quarantaine
                                            </a>
                                        <?php endif; ?>

                                        <a href="toggle_client_status.php?id=<?= $row['id_client'] ?>"
                                            onclick="return confirm('Êtes-vous sûr de vouloir <?= ($row['is_banned'] == 1 ? 'DÉBANNNIR' : 'BANNNIR') ?> le client <?= htmlspecialchars($row['nom']) ?> (<?= htmlspecialchars($row['email']) ?>) ?');"
                                            class="btn <?= ($row['is_banned'] == 1 ? 'btn-outline-primary' : 'btn-outline-danger') ?> btn-sm"
                                            title="<?= ($row['is_banned'] == 1 ? 'Débannir ce client' : 'Bannir ce client') ?>">
                                            <i class="bi bi-person-slash-fill"></i> <?= ($row['is_banned'] == 1 ? 'Débannir' : 'Bannir') ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-center text-muted p-3">Aucun client trouvé pour la zone "<strong><?= htmlspecialchars($zone_recherchee) ?></strong>" ou aucun client enregistré.</p>
            <?php endif; ?>
        </div>
    </div>
</section>
</body>
</html>

<?php $con->close(); ?>