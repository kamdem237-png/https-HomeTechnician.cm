<?php
session_start();

// Vérification de l'authentification de l'administrateur
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || ($_SESSION['role'] ?? '') !== 'admin') {
    header('location: connexion_admin.php');
    exit();
}

$server_name = "localhost";
$user_name = "root";
$psw = "";
$DB_name = "depanage";
$con = new mysqli($server_name, $user_name, $psw, $DB_name);

if ($con->connect_error) {
    error_log("Erreur de connexion à la base de données : " . $con->connect_error);
    die("Désolé, une erreur technique est survenue.");
}

$message = '';
$message_type = '';
$annonce = null;
$annonce_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Récupérer les détails de l'annonce à modifier
if ($annonce_id > 0) {
    // FIX: Added statut_actif to the SELECT query
    $stmt = $con->prepare("SELECT id_annonce, titre, contenu, visible_client, visible_technicien, visible_admin, statut_actif FROM annonces WHERE id_annonce = ?");
    if ($stmt) {
        $stmt->bind_param("i", $annonce_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $annonce = $result->fetch_assoc();
        } else {
            $_SESSION['message'] = "Annonce non trouvée.";
            $_SESSION['message_type'] = "danger";
            header('location: gest_annonce.php');
            exit();
        }
        $stmt->close();
    } else {
        error_log("Erreur de préparation de la requête de sélection : " . $con->error);
        $_SESSION['message'] = "Erreur interne lors de la récupération de l'annonce.";
        $_SESSION['message_type'] = "danger";
        header('location: gest_annonce.php');
        exit();
    }
} else {
    $_SESSION['message'] = "ID d'annonce non spécifié.";
    $_SESSION['message_type'] = "danger";
    header('location: gest_annonce.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $statut_actif = isset($_POST['statut_actif']) ? 1 : 0;
    $titre = trim($_POST['titre'] ?? '');
    $contenu = trim($_POST['contenu'] ?? '');
    $visible_client = isset($_POST['visible_client']) ? 1 : 0;
    $visible_technicien = isset($_POST['visible_technicien']) ? 1 : 0;
    $visible_admin = isset($_POST['visible_admin']) ? 1 : 0;

    if (empty($titre) || empty($contenu)) {
        $message = "Le titre et le contenu de l'annonce sont obligatoires.";
        $message_type = "danger";
    } else {
        $stmt = $con->prepare("UPDATE annonces SET titre = ?, contenu = ?, visible_client = ?, visible_technicien = ?, visible_admin = ?, statut_actif = ? WHERE id_annonce = ?");
        if ($stmt) {
            $stmt->bind_param("ssiiiii", $titre, $contenu, $visible_client, $visible_technicien, $visible_admin, $statut_actif, $annonce_id);
            if ($stmt->execute()) {
                $_SESSION['message'] = "Annonce modifiée avec succès.";
                $_SESSION['message_type'] = "success";
                header('location: gest_annonce.php');
                exit();
            } else {
                $message = "Erreur lors de la modification de l'annonce : " . $stmt->error;
                $message_type = "danger";
            }
            $stmt->close();
        } else {
            $message = "Erreur de préparation de la requête : " . $con->error;
            $message_type = "danger";
        }
    }
}
$con->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="mon_logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="c.css">
    <title>Modifier une Annonce</title>
</head>
<body>
<header>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid" style="margin-top:20px;">
            <a class="navbar-brand" href="admin.php" style="margin-top:-10px;">Tableau de Bord Admin</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavAdmin" aria-controls="navbarNavAdmin" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNavAdmin"> <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="admin.php">Accueil Admin</a></li>
                    <li class="nav-item"><a class="nav-link" href="gestion_clients.php">Gestion des Clients</a></li>
                    <li class="nav-item"><a class="nav-link" href="gestion_techniciens.php">Gestion des Techniciens</a></li>
                    <li class="nav-item"><a class="nav-link active" href="gest_annonce.php">Gestion Annonces</a></li>
                    <li class="nav-item"><a class="nav-link" href="probleme_users.php">Problèmes Utilisateurs</a></li>
                    <li class="nav-item"><a class="nav-link" href="admin_gestion_messages.php">Gestion des Messages</a></li>
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
            <h2 class="text-center mb-4">Modifier l'Annonce</h2>
            <?php if ($message): ?>
                <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <form method="POST">
                <div class="mb-3">
                    <label for="titre" class="form-label">Titre de l'annonce :</label>
                    <input type="text" class="form-control" id="titre" name="titre" required value="<?= htmlspecialchars($annonce['titre']) ?>">
                </div>
                <div class="mb-3">
                    <label for="contenu" class="form-label">Contenu de l'annonce :</label>
                    <textarea class="form-control" id="contenu" name="contenu" rows="5" required><?= htmlspecialchars($annonce['contenu']) ?></textarea>
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="visible_client" name="visible_client" value="1" <?= $annonce['visible_client'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="visible_client">Visible pour les Clients</label>
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="visible_technicien" name="visible_technicien" value="1" <?= $annonce['visible_technicien'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="visible_technicien">Visible pour les Techniciens</label>
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="visible_admin" name="visible_admin" value="1" <?= $annonce['visible_admin'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="visible_admin">Visible pour l'Admin</label>
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="statut_actif" name="statut_actif" value="1" <?= ($annonce['statut_actif'] ?? 0) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="statut_actif">Annonce active (visible sur les pages d'accueil)</label>
                </div>
                <div class="d-grid gap-2">
                    <button type="submit" name="valider" class="btn btn-primary">Modifier l'Annonce</button>
                    <a href="gest_annonce.php" class="btn btn-secondary">Annuler</a>
                </div>
            </form>
        </div>
    </div>
</section>
</body>
</html>