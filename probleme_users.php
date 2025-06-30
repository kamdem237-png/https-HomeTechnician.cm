<?php
session_start();

// Vérification de l'authentification de l'administrateur
// Added a security check for admin login, assuming you have an admin session setup.
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || ($_SESSION['role'] ?? '') !== 'admin') {
    header('location: connexion_admin.php');
    exit();
}

$conn = new mysqli("localhost", "root", "", "depanage");
if ($conn->connect_error) {
    die("Erreur de connexion : " . $conn->connect_error);
}

$message = "";
$error = "";

// --- Traitement de la mise à jour du statut d'un problème ---
if (isset($_POST['update_status'])) {
    $id_signalement = (int)$_POST['id_signalement'];
    $new_status = trim($_POST['new_status'] ?? '');

    if (empty($id_signalement) || !in_array($new_status, ['ouvert', 'en_traitement', 'ferme'])) {
        $error = "Statut invalide ou ID de signalement manquant.";
    } else {
        $stmt_update = $conn->prepare("UPDATE signalements SET statut = ? WHERE id_signalement = ?");
        $stmt_update->bind_param("si", $new_status, $id_signalement);

        if ($stmt_update->execute()) {
            $message = "Statut du signalement #$id_signalement mis à jour avec succès.";
        } else {
            $error = "Erreur lors de la mise à jour du statut : " . $conn->error;
        }
        $stmt_update->close();
    }
}

// --- Récupération des signalements avec les informations de l'utilisateur ---
// On utilise LEFT JOIN pour récupérer les noms des clients/techniciens même si l'ID est NULL
$query = "
    SELECT
        s.id_signalement,
        s.sujet,
        s.description,
        s.date_signalement,
        s.statut,
        c.nom AS client_nom,
        t.nom AS technicien_nom
    FROM
        signalements s
    LEFT JOIN
        client c ON s.id_client = c.id_client
    LEFT JOIN
        technicien t ON s.id_technicien = t.id_technicien
    ORDER BY
        s.date_signalement DESC;
";

$result_signalements = $conn->query($query);

$signalements = [];
if ($result_signalements->num_rows > 0) {
    while ($row = $result_signalements->fetch_assoc()) {
        $signalements[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des Problèmes Utilisateurs - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
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
                        <li class="nav-item"><a class="nav-link active" href="probleme_users.php">Problèmes Utilisateurs</a></li>
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

<div class="container mt-5">
    <h3 class="mb-4">Problèmes Signalés par les Utilisateurs</h3>

    <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (empty($signalements)): ?>
        <div class="alert alert-info">Aucun problème n'a été signalé pour le moment.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Signalé par</th>
                        <th>Sujet</th>
                        <th>Description</th>
                        <th>Date</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($signalements as $signalement): ?>
                        <tr>
                            <td><?= htmlspecialchars($signalement['id_signalement']) ?></td>
                            <td>
                                <?php
                                if ($signalement['client_nom']) {
                                    echo "Client: " . htmlspecialchars($signalement['client_nom']);
                                } elseif ($signalement['technicien_nom']) {
                                    echo "Technicien: " . htmlspecialchars($signalement['technicien_nom']);
                                } else {
                                    echo "Inconnu"; // Au cas où l'ID ne correspondrait à aucun user
                                }
                                ?>
                            </td>
                            <td><?= htmlspecialchars($signalement['sujet']) ?></td>
                            <td><?= nl2br(htmlspecialchars($signalement['description'])) ?></td>
                            <td><?= htmlspecialchars($signalement['date_signalement']) ?></td>
                            <td>
                                <span class="badge
                                    <?php
                                        if ($signalement['statut'] == 'ouvert') echo 'bg-danger';
                                        elseif ($signalement['statut'] == 'en_traitement') echo 'bg-warning text-dark';
                                        else echo 'bg-success';
                                    ?>
                                ">
                                    <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $signalement['statut']))) ?>
                                </span>
                            </td>
                            <td>
                                <form action="" method="POST" class="d-flex">
                                    <input type="hidden" name="id_signalement" value="<?= htmlspecialchars($signalement['id_signalement']) ?>">
                                    <select name="new_status" class="form-select form-select-sm me-2">
                                        <option value="ouvert" <?= ($signalement['statut'] == 'ouvert') ? 'selected' : '' ?>>Ouvert</option>
                                        <option value="en_traitement" <?= ($signalement['statut'] == 'en_traitement') ? 'selected' : '' ?>>En traitement</option>
                                        <option value="ferme" <?= ($signalement['statut'] == 'ferme') ? 'selected' : '' ?>>Fermé</option>
                                    </select>
                                    <button type="submit" name="update_status" class="btn btn-sm btn-primary">
                                        <i class="bi bi-arrow-repeat"></i> Maj
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>