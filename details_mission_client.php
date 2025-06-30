<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'client') {
    header("Location: connexion_user.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "depanage");
if ($conn->connect_error) {
    die("Erreur de connexion : " . $conn->connect_error);
}

$mission = null;
if (isset($_GET['id_mission']) && is_numeric($_GET['id_mission'])) {
    $id_mission = (int)$_GET['id_mission'];
    $id_client = (int)$_SESSION['user']['id_client'];

    // Récupérer les détails de la mission pour le client connecté
    $stmt = $conn->prepare("SELECT m.*, 
                                (SELECT COUNT(*) FROM mission_technicien mt WHERE mt.id_mission = m.id_mission) AS techniciens_affectes
                            FROM mission m 
                            WHERE m.id_mission = ? AND m.id_client = ?");
    if ($stmt) {
        $stmt->bind_param("ii", $id_mission, $id_client);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $mission = $result->fetch_assoc();
        }
        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="mon_logo.png">
    <title>Détails de la Mission</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Styles similaires à vos autres pages pour la cohérence */
        body { padding-top: 76px; }
        .navbar { background-color: #343a40 !important; box-shadow: 0 4px 12px rgba(0,0,0,0.1); position: fixed; top: 0; width: 100%; z-index: 1030; }
        .navbar-brand img { max-width: 200px; height: auto; border-radius: 8px; object-fit: contain; }
        .navbar-nav .nav-link { color: rgba(255,255,255,0.75) !important; font-weight: 500; margin-right: 15px; transition: color 0.3s ease; }
        .navbar-nav .nav-link:hover, .navbar-nav .nav-link.active { color: #007bff !important; }
        .btn-primary { background-color: #007bff; border-color: #007bff; }
        .btn-primary:hover { background-color: #0056b3; border-color: #0056b3; }
        .container { margin-top: 30px; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
        .card-header { background-color: #007bff; color: white; font-weight: bold; border-top-left-radius: 12px; border-top-right-radius: 12px; }
        .footer { background-color: #343a40 !important; color: rgba(255,255,255,0.7); padding: 2rem 0; margin-top: 30px;}
        .footer a { color: rgba(255,255,255,0.6); }
        .footer a:hover { color: white; text-decoration: underline; }
    </style>
</head>
<body>
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container-fluid">
                <a class="navbar-brand" href="site de mise en relation techniciens&clients.html">
                    <img src="mon logo 3.png" alt="HomeTechnician Logo">
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavClient" aria-controls="navbarNavClient" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNavClient">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        <li class="nav-item"><a class="nav-link" aria-current="page" href="client.php">Accueil</a></li>
                        <li class="nav-item"><a class="nav-link" href="mon_compte_client.php">Mon compte</a></li>
                        <li class="nav-item"><a class="nav-link active" href="missions_clientes.php">Missions</a></li>
                        <li class="nav-item"><a class="nav-link" href="soumettre_probleme.php">Soumettre une mission</a></li>
                        <li class="nav-item"><a class="nav-link" href="client.php#techniciens">Je suis un technicien</a></li>
                    </ul>
                    <ul class="navbar-nav">
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">
                                <button type="button" class="btn btn-primary"><i class="fas fa-sign-out-alt me-1"></i> Déconnexion</button>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <div class="container mt-5">
        <?php if ($mission): ?>
            <div class="card">
                <div class="card-header">
                    Détails de la mission : <?= htmlspecialchars($mission['titre_probleme']) ?>
                </div>
                <div class="card-body">
                    <p><strong>Description:</strong> <?= htmlspecialchars($mission['description']) ?></p>
                    <p><strong>Localisation:</strong> <?= htmlspecialchars($mission['localisation']) ?></p>
                    <p><strong>Techniciens demandés:</strong> <?= (int)$mission['nb_techniciens_demande'] ?></p>
                    <p><strong>Techniciens affectés:</strong> <?= (int)$mission['techniciens_affectes'] ?></p>
                    <p><strong>Statut:</strong> 
                        <?php
                        $statut_display = '';
                        $badge_class = 'bg-secondary';
                        if ($mission['statut'] === 'en_attente') {
                            $statut_display = 'En attente';
                            $badge_class = 'bg-warning text-dark';
                        } elseif ($mission['statut'] === 'en_cours') {
                            $statut_display = 'En cours';
                            $badge_class = 'bg-info';
                        } elseif ($mission['statut'] === 'terminee') {
                            $statut_display = 'Terminée';
                            $badge_class = 'bg-success';
                        } elseif ($mission['statut'] === 'annulee') {
                            $statut_display = 'Annulée';
                            $badge_class = 'bg-danger';
                        }
                        ?>
                        <span class="badge <?= $badge_class ?>"><?= $statut_display ?></span>
                    </p>
                    <p><strong>Date de demande:</strong> <?= htmlspecialchars($mission['date_demande']) ?></p>
                    <?php if ($mission['statut'] === 'terminee' && $mission['date_terminee']): ?>
                        <p><strong>Date de fin:</strong> <?= htmlspecialchars($mission['date_terminee']) ?></p>
                    <?php endif; ?>
                    <a href="missions_clientes.php" class="btn btn-secondary mt-3">Retour à mes missions</a>
                    <?php if ($mission['statut'] === 'en_cours' && (int)$mission['techniciens_affectes'] >= (int)$mission['nb_techniciens_demande']): ?>
                        <a href="chat.php?id_mission=<?= (int)$mission['id_mission'] ?>" class="btn btn-info mt-3 ms-2"><i class="bi bi-chat-dots-fill"></i> Accéder au Chat</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-warning text-center" role="alert">
                Mission non trouvée ou vous n'avez pas l'autorisation d'y accéder.
            </div>
            <a href="missions_clientes.php" class="btn btn-secondary mt-3">Retour à mes missions</a>
        <?php endif; ?>
    </div>

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
                        <li><a href="a_propos_client.php" class="text-white-50 text-decoration-none" >À Propos de Nous</a></li>
                        <li><a href="faq_client.php" class="text-white-50 text-decoration-none">FAQ</a></li>
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>