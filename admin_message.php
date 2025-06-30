<?php
session_start(); // DOIT ÊTRE LA PREMIÈRE LIGNE

// Vérification de la session administrateur
// This check was missing in your original snippet, but it's crucial for security.
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || ($_SESSION['role'] ?? '') !== 'admin') {
    header('location: connexion_admin.php');
    exit();
}

$conn = new mysqli("localhost", "root", "", "depanage");
if ($conn->connect_error) {
    die("Erreur de connexion : " . $conn->connect_error);
}

$missions = [];
// Récupérer toutes les missions pour le menu déroulant
// Inclure une brève description du problème pour aider l'admin à identifier la mission
$sql_missions = "SELECT id_mission, description, localisation FROM mission ORDER BY id_mission DESC";
$result_missions = $conn->query($sql_missions);

if ($result_missions && $result_missions->num_rows > 0) {
    while ($row = $result_missions->fetch_assoc()) {
        $missions[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Administration - Gestion des Messages</title>
    <link rel="icon" type="image/png" href="mon_logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .container {
            max-width: 900px;
            margin-top: 50px;
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="admin.php">Tableau de Bord Admin</a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavAdminMessage" aria-controls="navbarNavAdminMessage" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNavAdminMessage">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="admin.php">Accueil Admin</a></li>
                    <li class="nav-item"><a class="nav-link" href="gest_client.php">Gestion des Clients</a></li>
                    <li class="nav-item"><a class="nav-link" href="gest_technicien.php">Gestion des Techniciens</a></li>
                    <li class="nav-item"><a class="nav-link active" href="admin_message.php">Gestion des Messages</a></li>
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
    </header>

    <div class="container">
        <h2 class="mb-4 text-center">Consulter les Messageries des Missions</h2>

        <form action="admin_view_chat.php" method="GET" class="mb-5 p-4 border rounded">
            <div class="mb-3">
                <label for="mission_select" class="form-label">Sélectionner une Mission :</label>
                <select class="form-select" id="mission_select" name="id_mission" required>
                    <option value="">-- Choisir une mission --</option>
                    <?php if (!empty($missions)): ?>
                        <?php foreach ($missions as $mission): ?>
                            <option value="<?= htmlspecialchars($mission['id_mission']) ?>">
                                Mission #<?= htmlspecialchars($mission['id_mission']) ?>: <?= htmlspecialchars(substr($mission['description'], 0, 50)) ?>... (<?= htmlspecialchars($mission['localisation']) ?>)
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="" disabled>Aucune mission disponible.</option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="text-center">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-chat-dots"></i> Consulter la Messagerie
                </button>
            </div>
        </form>

        <p class="text-center text-muted">Sélectionnez une mission dans la liste pour voir l'historique complet des discussions entre le client et les techniciens.</p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>