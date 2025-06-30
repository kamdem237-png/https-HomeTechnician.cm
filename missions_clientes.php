<?php

// Set stricter session cookie parameters for security
// This ensures cookies are more secure, especially on modern PHP versions.
if (PHP_VERSION_ID < 70300) {
    session_set_cookie_params([
        'lifetime' => 0, // Cookie expires when the browser is closed
        'path' => '/',
        'domain' => '', // Your domain (e.g., 'your-site.com') - leave empty for the current domain if not specified
        'secure' => true, // Send cookie only over HTTPS
        'httponly' => true, // Prevent JavaScript access to the cookie
        'samesite' => 'Lax' // Or 'Strict' for stronger CSRF protection
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

session_start(); // Start the session after setting parameters

// Redirect if the user is not logged in or is not a client
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'client') {
    header("Location: connexion_user.php");
    exit();
}

// Database connection
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', ''); // Consider using a password, even in local environments
define('DB_NAME', 'depanage');

$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

if ($conn->connect_error) {
    error_log("Erreur de connexion à la base de données : " . $conn->connect_error);
    die("Désolé, une erreur technique est survenue. Veuillez réessayer plus tard.");
}

$client = $_SESSION['user'];
$id_client = (int)$client['id_client'];

// Fetch missions for the current client
$sql = "SELECT m.id_mission, m.titre_probleme, m.description, m.localisation, m.statut, m.nb_techniciens_demande,
                (SELECT COUNT(*) FROM mission_technicien mt WHERE mt.id_mission = m.id_mission) AS techniciens_disponible,
                (SELECT COUNT(c2.id_chat) FROM chat c2 WHERE c2.id_mission = m.id_mission AND c2.lu = FALSE AND c2.id_receiver_client = m.id_client AND c2.id_technicien IS NOT NULL) AS unread_messages_count
        FROM mission m
        WHERE m.id_client = ?
        ORDER BY m.date_demande DESC"; // Order by most recent missions first

$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("Erreur de préparation de la requête (missions_clientes) : " . $conn->error);
    die("Désolé, une erreur technique est survenue.");
}

$stmt->bind_param("i", $id_client);
$stmt->execute();
$result = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <title>Mes missions</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="icon" type="image/png" href="mon_logo.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Your existing custom styles */
        :root {
            --bs-primary: #007bff; /* Default Bootstrap Blue */
            --bs-secondary: #6c757d; /* Secondary Gray */
            --bs-success: #28a745;
            --bs-dark: #343a40;
            --bs-light: #f8f9fa;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
            background-color: var(--bs-light);
            padding-top: 76px; /* Space for fixed navbar */
        }

        /* Improved Navbar */
        .navbar {
            background-color: var(--bs-dark) !important; /* Dark background */
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            position: fixed; /* Makes the navbar fixed at the top */
            top: 0;
            width: 100%;
            z-index: 1030;
        }

        .navbar-brand {
            padding: 0; /* Remove default padding for image */
        }

        .navbar-brand img {
            max-width: 200px; /* More reasonable size for a logo */
            height: auto;
            border-radius: 8px; /* Soften corners */
            object-fit: contain; /* Ensure the entire logo is visible */
        }

        .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.75) !important; /* Light text color */
            font-weight: 500;
            margin-right: 15px; /* Spacing between links */
            transition: color 0.3s ease;
        }

        .navbar-nav .nav-link:hover,
        .navbar-nav .nav-link.active {
            color: var(--bs-primary) !important; /* Primary color on hover/active */
        }

        .btn-primary { /* Renamed from btn-dark to be consistent with soumettre_probleme */
            background-color: var(--bs-primary); /* Primary button for connecting */
            border-color: var(--bs-primary);
            transition: background-color 0.3s ease, border-color 0.3s ease;
        }

        .btn-primary:hover {
            background-color: #0056b3; /* Darker shade on hover */
            border-color: #0056b3;
        }

        /* General content sections */
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
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08); /* More pronounced shadow */
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

        /* Footer */
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
            color: var(--bs-success); /* Green for social icons on hover */
        }

        .notification-badge {
            position: absolute;
            top: -5px; /* Adjust as per design */
            right: -5px; /* Adjust as per design */
            padding: .25em .6em;
            border-radius: 1rem;
            font-size: .75em;
            background-color: red;
            color: white;
            z-index: 10;
        }
        .btn-chat-relative {
            position: relative;
            display: inline-flex; /* Allows the badge to be positioned relative to the button */
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
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavClient" aria-controls="navbarNavClient" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNavClient">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        <li class="nav-item"><a class="nav-link" aria-current="page" href="client.php">Accueil</a></li>
                        <li class="nav-item"><a class="nav-link" href="mon_compte_client.php">Mon compte</a></li>
                        <li class="nav-item"><a class="nav-link active" href="missions_clientes.php">Missions</a></li>
                        <li class="nav-item"><a class="nav-link" href="soumettre_probleme.php">Soumettre une Mission</a></li>
                        <li class="nav-item"><a class="nav-link" href="client.php#techniciens">je suis un technicien</a></li>
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
        <h3 class="mb-4">Mes missions</h3>

        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>Titre du Problème</th>
                    <th>Localisation</th>
                    <th>Techniciens Affectés</th>
                    <th>Statut</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()):
                        $nb_tech_dispo = (int)$row['techniciens_disponible'];
                        $nb_tech_demande = (int)$row['nb_techniciens_demande'];
                        $unread_count = (int)$row['unread_messages_count'];
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($row['titre_probleme']) ?></td>
                            <td><?= htmlspecialchars($row['localisation']) ?></td>
                            <td><?= $nb_tech_dispo ?> / <?= $nb_tech_demande ?></td>
                            <td>
                                <?php
                                $statut_display = '';
                                $badge_class = 'bg-secondary'; // Default
                                if ($row['statut'] === 'en_attente') {
                                    $statut_display = 'En attente';
                                    $badge_class = 'bg-warning text-dark';
                                } elseif ($row['statut'] === 'en_cours') {
                                    $statut_display = 'En cours';
                                    $badge_class = 'bg-info';
                                } elseif ($row['statut'] === 'terminee') {
                                    $statut_display = 'Terminée';
                                    $badge_class = 'bg-success';
                                } elseif ($row['statut'] === 'annulee') {
                                    $statut_display = 'Annulée';
                                    $badge_class = 'bg-danger';
                                }
                                ?>
                                <span class="badge <?= $badge_class ?>"><?= $statut_display ?></span>
                            </td>
                            <td>
                                <?php if ($row['statut'] === 'en_attente'): ?>
                                    <?php if ($nb_tech_dispo == 0): ?>
                                        <button class="btn btn-warning btn-sm" disabled>En attente d'un technicien</button>
                                    <?php else: ?>
                                        <button class="btn btn-info btn-sm" disabled>Techniciens en cours d'affectation</button>
                                    <?php endif; ?>
                                    <form method="POST" action="annuler_mission.php" class="d-inline">
                                        <input type="hidden" name="id_mission" value="<?= (int)$row['id_mission'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm mt-1" onclick="return confirm('Êtes-vous sûr de vouloir annuler cette mission ?')">Annuler</button>
                                    </form>

                                <?php elseif ($row['statut'] === 'en_cours'): ?>
                                    <?php if ($nb_tech_dispo >= $nb_tech_demande): ?>
                                        <a href="chat.php?id_mission=<?= (int)$row['id_mission'] ?>" class="btn btn-info btn-sm btn-chat-relative">
                                            <i class="bi bi-chat-dots-fill"></i> Chat Technicien
                                            <span class="badge notification-badge" id="badge_mission_<?= $row['id_mission'] ?>" style="<?= ($unread_count > 0) ? 'display:inline-block;' : 'display:none;' ?>">
                                                <?= $unread_count ?>
                                            </span>
                                        </a>
                                        <form method="POST" action="cloturer_mission.php" class="d-inline">
                                            <input type="hidden" name="id_mission" value="<?= (int)$row['id_mission'] ?>">
                                            <button type="submit" class="btn btn-success btn-sm mt-1" onclick="return confirm('Confirmez-vous que cette mission est terminée ?')">Mission terminée</button>
                                        </form>
                                    <?php else: ?>
                                        <button class="btn btn-info btn-sm" disabled>En attente de techniciens supplémentaires</button>
                                    <?php endif; ?>

                                <?php elseif ($row['statut'] === 'terminee'): ?>
                                    <button class="btn btn-outline-success btn-sm" disabled>Mission terminée</button>
                                    <button class="btn btn-outline-secondary btn-sm mt-1" disabled><i class="bi bi-chat-dots-fill"></i> Chat clôturé</button>
                                    
                                <?php elseif ($row['statut'] === 'annulee'): ?>
                                    <button class="btn btn-outline-danger btn-sm" disabled>Mission annulée</button>
                                    <button class="btn btn-outline-secondary btn-sm mt-1" disabled><i class="bi bi-chat-dots-fill"></i> Chat clôturé</button>

                                <?php endif; ?>
                                <form method="GET" action="details_mission_client.php" class="mt-1">
                                    <input type="hidden" name="id_mission" value="<?= (int)$row['id_mission'] ?>">
                                    <button type="submit" class="btn btn-secondary btn-sm">
                                        <i class="fas fa-book-open me-2"></i> Voir les détails
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="text-center">Aucune mission trouvée.</td></tr>
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
    <script>
        function fetchUnreadMessagesCountClient() {
            fetch('get_unread_messages_client.php') // Make sure this file exists and works correctly
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        console.error('Server error fetching unread messages:', data.error);
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
                .catch(error => console.error('Network error fetching unread messages:', error));
        }

        document.addEventListener('DOMContentLoaded', fetchUnreadMessagesCountClient);
        setInterval(fetchUnreadMessagesCountClient, 10000); // Update every 10 seconds
    </script>
    <?php $conn->close(); ?>
</body>
</html>