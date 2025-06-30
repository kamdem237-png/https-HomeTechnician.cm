<?php

// Définir des paramètres de cookie de session plus stricts pour la sécurité
if (PHP_VERSION_ID < 70300) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
} else {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

session_start();

// Redirection si l'utilisateur n'est pas connecté ou n'est pas un technicien
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'technicien') {
    header("Location: connexion_tehnicien.php");
    exit();
}

// Connexion à la base de données
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'depanage');

$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

if ($conn->connect_error) {
    error_log("Erreur de connexion à la base de données : " . $conn->connect_error);
    die("Désolé, une erreur technique est survenue. Veuillez réessayer plus tard.");
}

$technicien = $_SESSION['user'];
$id_technicien = (int)$technicien['id_technicien'];

$search_ville = trim($_GET['ville'] ?? '');

$sql = "SELECT m.id_mission, m.titre_probleme, m.description, m.localisation, m.statut, m.nb_techniciens_demande, m.date_terminee,
                 COUNT(mt.id_technicien) AS techniciens_disponible,
                 MAX(CASE WHEN mt.id_technicien = ? THEN 1 ELSE 0 END) AS deja_affecte,
                 (SELECT COUNT(c2.id_chat) FROM chat c2
                  WHERE c2.id_mission = m.id_mission
                    AND c2.lu = FALSE
                    AND c2.id_receiver_technicien = ?  -- Message spécifiquement pour *ce* technicien, peu importe l'expéditeur
                 ) AS unread_messages_count
        FROM mission m
        LEFT JOIN mission_technicien mt ON m.id_mission = mt.id_mission
        WHERE (m.statut != 'terminee' OR (m.statut = 'terminee' AND m.date_terminee >= NOW() - INTERVAL 15 DAY))";

if (!empty($search_ville)) {
    $sql .= " AND m.localisation LIKE ?";
}

$sql .= " GROUP BY m.id_mission ORDER BY m.date_demande DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("Erreur de préparation de la requête (missions_disponibles) : " . $conn->error);
    die("Désolé, une erreur technique est survenue.");
}

// Correction du binding des paramètres :
if (!empty($search_ville)) {
    $search_param = '%' . $search_ville . '%';
    // Les types doivent correspondre aux paramètres : deux entiers ('ii') et une chaîne ('s')
    $stmt->bind_param("iis", $id_technicien, $id_technicien, $search_param);
} else {
    // Les types doivent correspondre aux paramètres : deux entiers ('ii')
    $stmt->bind_param("ii", $id_technicien, $id_technicien);
}

$stmt->execute();
$result = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <title>Missions disponibles</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="icon" type="image/png" href="mon_logo.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --bs-primary: #007bff;
            --bs-secondary: #6c757d;
            --bs-success: #28a745;
            --bs-dark: #343a40;
            --bs-light: #f8f9fa;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
            background-color: var(--bs-light);
            padding-top: 76px;
        }

        .navbar {
            background-color: var(--bs-dark) !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1030;
        }

        .navbar-brand {
            padding: 0;
        }

        .navbar-brand img {
            max-width: 200px;
            height: auto;
            border-radius: 8px;
            object-fit: contain;
        }

        .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.75) !important;
            font-weight: 500;
            margin-right: 15px;
            transition: color 0.3s ease;
        }

        .navbar-nav .nav-link:hover,
        .navbar-nav .nav-link.active {
            color: var(--bs-primary) !important;
        }

        .btn-dark {
            background-color: var(--bs-primary);
            border-color: var(--bs-primary);
            transition: background-color 0.3s ease, border-color 0.3s ease;
        }

        .btn-dark:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }

        .technician-hero {
            background: linear-gradient(rgba(0, 123, 255, 0.8), rgba(0, 86, 179, 0.8)), url('path/to/your/technician-bg.jpg') no-repeat center center/cover;
            min-height: 250px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            color: white;
            padding: 4rem 0;
        }

        .technician-hero h1 {
            font-size: 2.8rem;
            margin-bottom: 1rem;
        }

        section {
            padding: 4rem 0;
        }

        section h2 {
            font-weight: 700;
            color: var(--bs-dark);
            margin-bottom: 3rem;
            position: relative;
            padding-bottom: 10px;
        }

        section h2::after {
            content: '';
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            bottom: 0;
            width: 80px;
            height: 4px;
            background-color: var(--bs-primary);
            border-radius: 2px;
        }

        .card {
            border: none;
            border-radius: 12px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .card-title {
            color: var(--bs-primary);
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .mission-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            margin-bottom: 20px;
            background-color: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .mission-card .card-header {
            background-color: var(--bs-primary);
            color: white;
            font-weight: bold;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }

        .mission-card .card-body {
            padding: 20px;
        }

        .mission-card .btn {
            margin-top: 15px;
        }

        .annonce-card {
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .annonce-card h5 {
            color: #007bff;
            margin-bottom: 10px;
        }
        .annonce-card p {
            font-size: 0.95rem;
            color: #555;
        }
        .annonce-card .text-muted {
            font-size: 0.85rem;
        }

        .footer {
            background-color: var(--bs-dark) !important;
            color: rgba(255, 255, 255, 0.7);
        }

        .footer h3 {
            color: white;
            font-weight: 600;
        }

        .footer a {
            color: rgba(255, 255, 255, 0.6);
            transition: color 0.3s ease;
        }

        .footer a:hover {
            color: white;
            text-decoration: underline;
        }

        .footer .social-links a {
            color: rgba(255, 255, 255, 0.7);
            font-size: 1.5rem;
            margin-right: 15px;
            transition: color 0.3s ease;
        }

        .footer .social-links a:hover {
            color: var(--bs-success);
        }
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            padding: .25em .6em;
            border-radius: 1rem;
            font-size: .75em;
            background-color: red;
            color: white;
            z-index: 10;
        }
        .btn-chat-relative {
            position: relative;
            display: inline-flex;
            align-items: center;
        }

    </style>
</head>
<body>
<header>
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container-fluid">
                <a class="navbar-brand" href="site de mise en relation techniciens&clients.html">
                    <img src="mon logo 3.png" alt="HomeTechnician Logo">
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavTechnicien" aria-controls="navbarNavTechnicien" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNavTechnicien">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        <li class="nav-item"><a class="nav-link" aria-current="page" href="technicien.php">Accueil</a></li>
                        <li class="nav-item"><a class="nav-link" href="mon_compte_technicien.php">Mon compte</a></li>
                        <li class="nav-item"><a class="nav-link active" href="missions_disponibles.php">Mes missions</a></li>
                        <li class="nav-item"><a class="nav-link" href="connexion_client_tech.php">Je suis un client</a></li>
                    </ul>
                    <ul class="navbar-nav">
                        <li class="nav-item">
                            <a class="nav-link" href="logout_technicien.php">
                                <button type="button" class="btn btn-primary"><i class="fas fa-sign-out-alt me-1"></i> Déconnexion</button>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

<div class="container mt-5">
    <h3>Missions Disponibles</h3>

    <form method="GET" class="mb-4">
        <div class="input-group">
            <input type="text" class="form-control" name="ville" placeholder="Rechercher par ville..." value="<?= htmlspecialchars($search_ville) ?>">
            <button class="btn btn-outline-primary" type="submit">Rechercher</button>
        </div>
    </form>

    <table class="table table-striped table-hover">
        <thead>
            <tr>
                <th>Titre du Problème</th>
                <th>Localisation</th>
                <th>Techniciens</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()):
                $nb_tech = (int)$row['techniciens_disponible'];
                $nb_demande = (int)$row['nb_techniciens_demande'];
                $deja_affecte = (bool)$row['deja_affecte'];
                $statut = $row['statut'];
                $unread_count = (int)$row['unread_messages_count']; // Nombre de messages non lus
            ?>
                <tr>
                    <td><?= htmlspecialchars($row['titre_probleme']) ?></td>
                    <td><?= htmlspecialchars($row['localisation']) ?></td>
                    <td><?= $nb_tech ?> / <?= $nb_demande ?></td>
                    <td>
                        <?php if ($statut === 'terminee'): ?>
                            <button class="btn btn-outline-success btn-sm" disabled>Mission terminée</button><br>
                            <small class="text-muted">Clôturée récemment</small><br>
                            <button class="btn btn-outline-secondary btn-sm mt-1" disabled>
                                <i class="bi bi-send-fill"></i> Chat clôturé
                            </button>

                        <?php elseif ($deja_affecte): ?>
                            <button class="btn btn-success btn-sm" disabled>Affecté</button><br>
                            <?php if ($nb_tech >= $nb_demande && $statut === 'en_cours'): // Chat disponible uniquement si la mission est en_cours et qu'il y a assez de techniciens affectés ?>
                                <a href="chat.php?id_mission=<?= (int)$row['id_mission'] ?>" class="btn btn-info btn-sm mt-1 btn-chat-relative">
                                    <i class="bi bi-send-fill"></i> Chat client
                                    <span class="badge notification-badge" id="badge_mission_<?= $row['id_mission'] ?>" style="<?= ($unread_count > 0) ? 'display:inline-block;' : 'display:none;' ?>">
                                        <?= $unread_count ?>
                                    </span>
                                </a>
                            <?php else: ?>
                                <button class="btn btn-outline-secondary btn-sm mt-1" disabled>
                                    <i class="bi bi-send-fill"></i> Chat indisponible
                                </button>
                            <?php endif; ?>
                            <form method="GET" action="details_mission.php" class="mt-1">
                                <input type="hidden" name="id_mission" value="<?= (int)$row['id_mission'] ?>">
                                <button type="submit" class="btn btn-secondary btn-sm">
                                    <i class="fas fa-book-open me-2"></i> Voir les détails
                                </button>
                            </form>

                        <?php elseif ($nb_tech < $nb_demande): ?>
                            <form method="GET" action="details_mission.php">
                                <input type="hidden" name="id_mission" value="<?= (int)$row['id_mission'] ?>">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="fas fa-book-open me-2"></i> Voir plus de détails
                                </button>
                            </form>

                        <?php else: // $nb_tech >= $nb_demande ET !deja_affecte ?>
                            <button class="btn btn-secondary btn-sm" disabled>Complet</button><br>
                            <button class="btn btn-outline-secondary btn-sm mt-1" disabled>
                                <i class="bi bi-send-fill"></i> Chat indisponible
                            </button>
                            <form method="GET" action="details_mission.php" class="mt-1">
                                <input type="hidden" name="id_mission" value="<?= (int)$row['id_mission'] ?>">
                                <button type="submit" class="btn btn-secondary btn-sm">
                                    <i class="fas fa-book-open me-2"></i> Voir les détails
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="5">Aucune mission disponible.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
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
                        <li><a href="a_propos_technicien.php" class="text-white-50 text-decoration-none" >À Propos de Nous</a></li>
                        <li><a href="faq_technicien.php" class="text-white-50 text-decoration-none">FAQ</a></li>
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
<script>
    function fetchUnreadMessagesCountTechnician() {
        fetch('get_unread_messages_count.php')
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    console.error('Erreur du serveur lors de la récupération des non-lus:', data.error);
                    return;
                }
                document.querySelectorAll('.notification-badge').forEach(badge => {
                    const missionId = badge.id.replace('badge_mission_', '');
                    const unreadCount = data[missionId] || 0;

                    if (unreadCount > 0) {
                        badge.textContent = unreadCount;
                        badge.style.display = 'inline-block';
                    } else {
                        badge.style.display = 'none';
                    }
                });
            })
            .catch(error => console.error('Erreur réseau lors de la récupération des messages non lus:', error));
    }

    document.addEventListener('DOMContentLoaded', fetchUnreadMessagesCountTechnician);
    setInterval(fetchUnreadMessagesCountTechnician, 10000);
</script>
<?php $conn->close(); ?>
</body>
</html>