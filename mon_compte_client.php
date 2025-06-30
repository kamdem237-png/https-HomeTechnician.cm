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

$id_client = (int)$_SESSION['user']['id_client'];
$message = "";
$error = "";

// Récupérer les informations actuelles du client
// J'ai inclus 'num_client' et 'zone' qui sont dans votre table
$stmt = $conn->prepare("SELECT id_client, nom, num_client, email, zone, photo_profil_path FROM client WHERE id_client = ?");
$stmt->bind_param("i", $id_client);
$stmt->execute();
$result = $stmt->get_result();
$client_data = $result->fetch_assoc();
$stmt->close();

if (!$client_data) {
    // Si pour une raison quelconque le client n'est pas trouvé
    header("Location: logout.php");
    exit();
}

// --- Traitement de la modification des informations ---
if (isset($_POST['update_profile'])) {
    $nom = trim($_POST['nom'] ?? '');
    $num_client = trim($_POST['num_client'] ?? ''); // Utilisation de num_client au lieu de prenom
    $zone = trim($_POST['zone'] ?? '');             // Utilisation de zone au lieu de telephone et adresse

    if (empty($nom) || empty($num_client) || empty($zone)) {
        $error = "Tous les champs (Nom, Numéro Client, Zone) sont requis.";
    } else {
        // Mise à jour de nom, num_client et zone
        $stmt_update = $conn->prepare("UPDATE client SET nom = ?, num_client = ?, zone = ? WHERE id_client = ?");
        $stmt_update->bind_param("sssi", $nom, $num_client, $zone, $id_client);

        if ($stmt_update->execute()) {
            $message = "Vos informations ont été mises à jour avec succès.";
            // Mettre à jour les données de session si nécessaire (si elles sont utilisées ailleurs)
            $_SESSION['user']['nom'] = $nom;
            $_SESSION['user']['num_client'] = $num_client;
            $_SESSION['user']['zone'] = $zone;
            // Recharger les données du client pour afficher les dernières mises à jour
            $stmt = $conn->prepare("SELECT id_client, nom, num_client, email, zone, photo_profil_path FROM client WHERE id_client = ?");
            $stmt->bind_param("i", $id_client);
            $stmt->execute();
            $result = $stmt->get_result();
            $client_data = $result->fetch_assoc(); // Re-fetch data
            $stmt->close();
        } else {
            $error = "Erreur lors de la mise à jour : " . $conn->error;
        }
        $stmt_update->close();
    }
}

// --- Traitement de l'upload de la photo de profil ---
if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == UPLOAD_ERR_OK) {
    $target_dir = "uploads/";
    $imageFileType = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
    $allowed_extensions = array("jpg", "jpeg", "png", "gif");

    if (!in_array($imageFileType, $allowed_extensions)) {
        $error = "Seuls les fichiers JPG, JPEG, PNG et GIF sont autorisés.";
    } elseif ($_FILES['profile_picture']['size'] > 5000000) { // 5MB
        $error = "La taille de l'image est trop grande (max 5MB).";
    } else {
        // Supprimer l'ancienne photo si elle n'est pas la photo par défaut
        // Assurez-vous que 'photo_profil_path' est bien dans $client_data
        if (isset($client_data['photo_profil_path']) && $client_data['photo_profil_path'] && $client_data['photo_profil_path'] !== 'uploads/default_profile.png' && file_exists($client_data['photo_profil_path'])) {
            unlink($client_data['photo_profil_path']);
        }

        // Renommer le fichier pour éviter les conflits et le stocker
        $new_filename = uniqid('profile_') . '.' . $imageFileType;
        $target_file = $target_dir . $new_filename;

        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
            $stmt_photo = $conn->prepare("UPDATE client SET photo_profil_path = ? WHERE id_client = ?");
            $stmt_photo->bind_param("si", $target_file, $id_client);
            if ($stmt_photo->execute()) {
                $message = "Photo de profil mise à jour avec succès.";
                $client_data['photo_profil_path'] = $target_file; // Mettre à jour pour affichage immédiat
            } else {
                $error = "Erreur lors de l'enregistrement du chemin de la photo en BD : " . $conn->error;
                unlink($target_file); // Supprimer le fichier si l'enregistrement BD échoue
            }
            $stmt_photo->close();
        } else {
            $error = "Erreur lors du téléchargement de votre photo.";
        }
    }
}

// --- Traitement du signalement de problème à l'administration ---
if (isset($_POST['report_problem'])) {
    $sujet = trim($_POST['sujet'] ?? '');
    $description_probleme = trim($_POST['description_probleme'] ?? '');

    if (empty($sujet) || empty($description_probleme)) {
        $error = "Veuillez remplir le sujet et la description du problème.";
    } else {
        // Enregistrer le problème dans une table 'signalements'
        // Vous devrez créer une table `signalements` avec des colonnes comme `id_client`, `sujet`, `description`, `date_signalement`, `statut`
        $stmt_report = $conn->prepare("INSERT INTO signalements (id_client, sujet, description, date_signalement, statut) VALUES (?, ?, ?, NOW(), 'ouvert')");
        $stmt_report->bind_param("iss", $id_client, $sujet, $description_probleme);

        if ($stmt_report->execute()) {
            $message = "Votre problème a été signalé à l'administration.";
        } else {
            $error = "Erreur lors du signalement du problème : " . $conn->error;
        }
        $stmt_report->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mon compte client</title>
    <link rel="icon" type="image/png" href="mon_logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .profile-img-container {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            overflow: hidden;
            margin: 0 auto 20px auto;
            border: 3px solid #007bff;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .profile-img-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
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
                        <li class="nav-item"><a class="nav-link active" href="mon_compte_client.php">Mon compte</a></li>
                        <li class="nav-item"><a class="nav-link" href="missions_clientes.php">Missions</a></li>
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
<style>
        /* Styles personnalisés pour cette page */
        :root {
            --bs-primary: #007bff; /* Bleu Bootstrap par défaut */
            --bs-secondary: #6c757d; /* Gris secondaire */
            --bs-success: #28a745;
            --bs-dark: #343a40;
            --bs-light: #f8f9fa;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
            background-color: var(--bs-light);
            padding-top: 76px; /* Espace pour la navbar fixe */
        }

        /* Navbar améliorée */
        .navbar {
            background-color: var(--bs-dark) !important; /* Fond sombre */
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            position: fixed; /* Rend la navbar fixe en haut */
            top: 0;
            width: 100%;
            z-index: 1030;
        }

        .navbar-brand {
            padding: 0; /* Supprime le padding par défaut pour l'image */
        }

        .navbar-brand img {
            max-width: 200px; /* Taille plus raisonnable pour un logo */
            height: auto;
            border-radius: 8px; /* Adoucissement des coins */
            object-fit: contain; /* Assure que le logo entier est visible */
        }

        .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.75) !important; /* Couleur de texte claire */
            font-weight: 500;
            margin-right: 15px; /* Espacement entre les liens */
            transition: color 0.3s ease;
        }

        .navbar-nav .nav-link:hover,
        .navbar-nav .nav-link.active {
            color: var(--bs-primary) !important; /* Couleur primaire au survol/actif */
        }

        .btn-dark {
            background-color: var(--bs-primary); /* Bouton primaire pour se connecter */
            border-color: var(--bs-primary);
            transition: background-color 0.3s ease, border-color 0.3s ease;
        }

        .btn-dark:hover {
            background-color: #0056b3; /* Teinte plus foncée au survol */
            border-color: #0056b3;
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(rgba(0, 123, 255, 0.8), rgba(0, 86, 179, 0.8)), url('path/to/your/hero-background.jpg') no-repeat center center/cover; /* Ajoutez une image de fond si vous en avez une */
            min-height: 450px; /* Hauteur minimale pour la section */
            display: flex;
            align-items: center;
            justify-content: center;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            color: white;
            padding: 6rem 0; /* Plus de padding pour l'esthétique */
        }

        .hero-section h1 {
            font-size: 3.5rem;
            margin-bottom: 1.5rem;
        }

        .hero-section p.lead {
            font-size: 1.25rem;
            margin-bottom: 2.5rem;
        }

        .hero-section .div2 {
            background-color: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            display: inline-block; /* Pour que la div s'adapte au contenu */
        }

        .hero-section .div2 input[type="text"] {
            width: 280px; /* Taille fixe pour les inputs */
            height: 48px;
            padding: 0 15px;
            margin: 10px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 1rem;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .hero-section .div2 input[type="submit"] {
            height: 48px;
            padding: 0 30px;
            font-size: 1.1rem;
            border-radius: 8px;
            margin-top: 20px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        /* Sections de contenu générales */
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
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08); /* Ombre plus prononcée */
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

   /* How It Works Section */
  #how-it-works .icon-circle {
    background-color: var(--bs-light);
    border: 2px solid var(--bs-primary) !important;
    color: var(--bs-primary);
    font-size: 3rem;
    transition: background-color 0.3s ease, color 0.3s ease;
  }

  #how-it-works .icon-circle:hover {
    background-color: var(--bs-primary);
    color: white;
  }

  #how-it-works h4 {
    color: var(--bs-primary);
    font-weight: 600;
    margin-top: 15px;
  }

    /* Contact Form */
  #contact .form-control,
  #contact .form-control:focus {
    border-radius: 8px;
    border-color: #ddd;
    box-shadow: none;
  }
  #contact .form-control:focus {
    border-color: var(--bs-primary);
    box-shadow: 0 0 0 0.25rem rgba(0, 123, 255, 0.25);
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
    color: var(--bs-success); /* Vert pour les icônes sociales au survol */
  }
</style>

<div class="container mt-5">
    <h3 class="mb-4">Mon Compte Client</h3>

    <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            Informations de Profil
        </div>
        <div class="card-body">
            <div class="text-center mb-3">
                <div class="profile-img-container">
                    <img src="<?= htmlspecialchars($client_data['photo_profil_path'] ?: 'uploads/default_profile.png') ?>" alt="Photo de profil">
                </div>
                <h5><?= htmlspecialchars($client_data['nom']) ?></h5>
            </div>

            <form action="" method="POST" enctype="multipart/form-data" class="mb-4 p-3 border rounded">
                <h6 class="mb-3">Changer votre photo de profil</h6>
                <div class="mb-3">
                    <label for="profile_picture" class="form-label">Sélectionner une nouvelle photo</label>
                    <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept="image/*">
                </div>
                <button type="submit" class="btn btn-secondary"><i class="bi bi-upload"></i> Uploader la photo</button>
            </form>

            <form action="" method="POST" class="p-3 border rounded">
                <h6 class="mb-3">Modifier vos informations</h6>
                <div class="mb-3">
                    <label for="nom" class="form-label">Nom</label>
                    <input type="text" class="form-control" id="nom" name="nom" value="<?= htmlspecialchars($client_data['nom']) ?>" required>
                </div>
                <div class="mb-3">
                    <label for="num_client" class="form-label">Numéro Client</label>
                    <input type="text" class="form-control" id="num_client" name="num_client" value="<?= htmlspecialchars($client_data['num_client']) ?>" required>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email (non modifiable)</label>
                    <input type="email" class="form-control" id="email" value="<?= htmlspecialchars($client_data['email']) ?>" disabled>
                </div>
                <div class="mb-3">
                    <label for="zone" class="form-label">Zone</label>
                    <input type="text" class="form-control" id="zone" name="zone" value="<?= htmlspecialchars($client_data['zone']) ?>" required>
                </div>
                <button type="submit" name="update_profile" class="btn btn-primary"><i class="bi bi-pencil-square"></i> Mettre à jour</button>
            </form>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-danger text-white">
            Signaler un problème à l'administration
        </div>
        <div class="card-body">
            <p>Si vous rencontrez un problème avec l'application ou une mission, veuillez nous le signaler.</p>
            <form action="" method="POST">
                <div class="mb-3">
                    <label for="sujet_probleme" class="form-label">Sujet du problème</label>
                    <input type="text" class="form-control" id="sujet_probleme" name="sujet" required>
                </div>
                <div class="mb-3">
                    <label for="description_probleme" class="form-label">Description détaillée du problème</label>
                    <textarea class="form-control" id="description_probleme" name="description_probleme" rows="4" required></textarea>
                </div>
                <button type="submit" name="report_problem" class="btn btn-danger"><i class="bi bi-exclamation-triangle"></i> envoyer</button>
            </form>
        </div>
    </div>
    

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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>