<?php
session_start(); // Toujours démarrer la session au tout début du script

// Vérifier si l'utilisateur est connecté et est un client
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'client') {
    // Rediriger vers la page de connexion si non connecté ou non client
    header("Location: connexion_client.php");
    exit();
}

// Récupérer les informations du client connecté depuis la session
// IMPORTANT: Assurez-vous que ces variables de session sont définies lors de la connexion du client
$client_id = $_SESSION['user']['id_client'] ?? null; // Correction: utilisez $_SESSION['user']['id_client'] si c'est ainsi que vous stockez l'ID
$client_email = $_SESSION['user']['email'] ?? null; // Correction: utilisez $_SESSION['user']['email']
$client_nom_complet = $_SESSION['user']['nom'] ?? null; // Correction: utilisez $_SESSION['user']['nom']
$client_telephone = $_SESSION['user']['num_client'] ?? null; // Correction: utilisez $_SESSION['user']['num_client']
$client_zone = $_SESSION['user']['zone'] ?? null; // Correction: utilisez $_SESSION['user']['zone'] pour pré-remplir la localisation

// --- Configuration de la base de données ---
$server_name = "localhost";
$user_name = "root";
$psw = "";
$DB_name = "depanage";

// Connexion à la base de données
$conn = new mysqli($server_name, $user_name, $psw, $DB_name);

// Vérifier la connexion
if ($conn->connect_error) {
    error_log("Échec de la connexion à la base de données : " . $conn->connect_error);
    header("Location: client.php?error=db_connect_failed");
    exit();
}
$conn->set_charset("utf8mb4"); // S'assurer de l'encodage UTF-8

$technicien_id = $_GET['id_technicien'] ?? null;

$technicien_data = null;
$certifications = []; // Initialise un tableau vide pour stocker les détails de certification
$missions_history = []; // Initialise un tableau vide pour l'historique des missions

if ($technicien_id) {
    // 1. Récupérer les informations détaillées du technicien
    $sql_technicien = "SELECT id_technicien, nom, prenom, email, num_technicien, zone, specialite, description, annees_experience, photo_profil_path
                               FROM technicien
                               WHERE id_technicien = ?";
    $stmt_technicien = $conn->prepare($sql_technicien);

    if ($stmt_technicien === false) {
        error_log("Erreur de préparation de la requête technicien : " . $conn->error);
        header("Location: client.php?error=db_prepare_error_tech");
        exit();
    }
    
    $stmt_technicien->bind_param("i", $technicien_id);
    $stmt_technicien->execute();
    $result_technicien = $stmt_technicien->get_result();

    if ($result_technicien->num_rows > 0) {
        $technicien_data = $result_technicien->fetch_assoc();
    }
    $stmt_technicien->close();

    // Rediriger si le technicien n'est pas trouvé
    if (!$technicien_data) {
        header("Location: client.php?error=technician_not_found");
        exit();
    }

    // 2. Récupérer les certifications du technicien depuis la nouvelle table 'certifications'
    $sql_certifications = "SELECT nom_certification, chemin_certification, date_ajout
                           FROM certifications
                           WHERE id_technicien = ? 
                           AND statut = 'approuve'
                           ORDER BY date_ajout DESC, nom_certification ASC"; // Ordre pour une meilleure visibilité
    $stmt_certifications = $conn->prepare($sql_certifications);

    if ($stmt_certifications === false) {
        error_log("Erreur de préparation de la requête certifications : " . $conn->error);
    } else {
        $stmt_certifications->bind_param("i", $technicien_id);
        $stmt_certifications->execute();
        $result_certifications = $stmt_certifications->get_result();

        while ($row = $result_certifications->fetch_assoc()) {
            $certifications[] = $row;
        }
        $stmt_certifications->close();
    }

    // 3. Récupérer l'historique des missions du technicien
    // Note: 'localisation' dans la table mission pourrait ne pas correspondre à la zone du technicien
    $sql_missions = "SELECT description, statut, date_demande, date_debut_mission, date_terminee, localisation
                     FROM mission
                     WHERE id_technicien = ?
                     ORDER BY date_demande DESC";
    $stmt_missions = $conn->prepare($sql_missions);

    if ($stmt_missions === false) {
        error_log("Erreur de préparation de la requête missions : " . $conn->error);
    } else {
        $stmt_missions->bind_param("i", $technicien_id);
        $stmt_missions->execute();
        $result_missions = $stmt_missions->get_result();

        while ($row = $result_missions->fetch_assoc()) {
            $missions_history[] = $row; // Correction de la variable
        }
        $stmt_missions->close();
    }
    $last_search_zone = $_SESSION['last_search_zone'] ?? '';
    $last_search_specialty = $_SESSION['last_search_specialty'] ?? '';
} else {
    // Redirection si l’ID du technicien est manquant
    header("Location: client.php?error=no_technician_id");
    exit();
}

$conn->close(); // Correction de la fonction de fermeture

// Déterminez la base_url de votre projet pour les chemins absolus des assets.
$base_url = '/projet/'; // ADAPTEZ CETTE LIGNE À VOTRE CONFIGURATION !

// Assurez-vous que le chemin de base se termine par un slash si ce n’est pas déjà le cas
if (substr($base_url, -1) !== '/') {
    $base_url .= '/';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="mon_logo.png">
    <title>Détails du Technicien - <?= htmlspecialchars($technicien_data['prenom'] . ' ' . $technicien_data['nom']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f7f6;
        }
        .header {
            background-color: #28a745;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .nav-links a {
            color: white;
            text-decoration: none;
            margin-left: 20px;
            font-weight: bold;
            transition: color 0.3s ease;
        }
        .nav-links a:hover {
            color: #e2e6ea;
        }
        .container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            margin-top: 30px;
        }
        .profile-img-container {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            overflow: hidden;
            border: 4px solid #28a745;
            margin: 0 auto 20px auto;
            background-color: #e9ecef;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .profile-img-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .info-section h4 {
            color: #28a745;
            margin-top: 25px;
            margin-bottom: 15px;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 5px;
        }
        .list-group-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 15px;
            border-color: #f0f0f0;
        }
        .list-group-item strong {
            color: #333;
        }
        .list-group-item span {
            color: #555;
        }
        .description-box {
            background-color: #f8f9fa;
            border-left: 5px solid #28a745;
            padding: 15px;
            border-radius: 5px;
            color: #495057;
            line-height: 1.6;
        }
        .certification-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0; /* Augmenter le padding vertical */
            border-bottom: 1px solid #e9ecef; /* Ligne de séparation plus claire */
            font-size: 0.95em; /* Légèrement plus petit */
        }
        .certification-item:last-child {
            border-bottom: none;
        }
        .certification-item span {
            flex-grow: 1; /* Permet au nom de prendre l'espace disponible */
            margin-right: 10px; /* Espace entre le nom et le bouton */
            color: #495057;
        }
        .certification-item a.btn {
            padding: 5px 12px; /* Ajuster la taille du bouton */
            font-size: 0.85em;
        }

        /* Styles pour le tableau des missions */
        .table-missions {
            width: 100%;
            margin-top: 20px;
            border-collapse: collapse; /* Pour des bordures nettes */
        }
        .table-missions th,
        .table-missions td {
            padding: 12px 15px; /* Padding généreux */
            text-align: left;
            border-bottom: 1px solid #dee2e6; /* Ligne de séparation */
            vertical-align: top; /* Alignement en haut pour le contenu long */
        }
        .table-missions th {
            background-color: #e9ecef;
            color: #495057;
            font-weight: bold;
            font-size: 0.9em;
            text-transform: uppercase;
        }
        .table-missions tbody tr:hover {
            background-color: #f2f2f2;
        }
        .table-missions td:first-child {
            font-weight: 500; /* Description en gras */
        }
        .table-missions .status-badge {
            padding: 5px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: bold;
            color: white;
        }
        .status-en_attente { background-color: #ffc107; color: #333 !important; } /* Jaune */
        .status-acceptée { background-color: #28a745; } /* Vert */
        .status-refusée { background-color: #dc3545; } /* Rouge */
        .status-terminée { background-color: #6c757d; } /* Gris */

        /* Rendre le tableau responsive */
        @media (max-width: 768px) {
            .table-missions, .table-missions tbody, .table-missions tr, .table-missions td, .table-missions th {
                display: block; /* Empile les éléments */
                width: 100%;
            }
            .table-missions thead {
                display: none; /* Cache l'en-tête original */
            }
            .table-missions tr {
                margin-bottom: 15px;
                border: 1px solid #dee2e6;
                border-radius: 5px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            }
            .table-missions td {
                text-align: right; /* Aligne la valeur à droite */
                padding-left: 50%; /* Crée de l'espace pour le pseudo-élément */
                position: relative;
                border: none; /* Supprime les bordures internes de td */
            }
            .table-missions td::before {
                content: attr(data-label); /* Utilise l'attribut data-label pour l'étiquette */
                position: absolute;
                left: 15px;
                width: calc(50% - 30px); /* Ajuster la largeur de l'étiquette */
                padding-right: 10px;
                white-space: nowrap;
                font-weight: bold;
                text-align: left;
                color: #495057;
            }
        }
        .demand-form-container {
            display: none; /* Masqué par défaut */
            margin-top: 30px;
            padding: 25px;
            background-color: #f8f9fa;
            border-radius: 8px;
            box-shadow: inset 0 1px 5px rgba(0, 0, 0, 0.05);
        }
        .demand-form-container.active {
            display: block; /* Affiché quand actif */
        }
        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
        }
        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }
        .btn-success {
            background-color: #28a745;
            border-color: #28a745;
        }
        .btn-success:hover {
            background-color: #218838;
            border-color: #218838;
        }
    </style>
</head>
<body>
    <div class="container my-5">
        <?php
        // Affichage des messages de session (succès ou erreur) - Tel que défini précédemment
        if (isset($_SESSION['form_message'])) {
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">';
            echo htmlspecialchars($_SESSION['form_message']);
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
            echo '</div>';
            unset($_SESSION['form_message']);
        }

        if (isset($_SESSION['form_error'])) {
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
            echo htmlspecialchars($_SESSION['form_error']);
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
            echo '</div>';
            unset($_SESSION['form_error']);
        }
        ?>

        <?php if ($technicien_data): ?>
            <div class="text-center mt-4">
                <form action="traitement2.php" method="POST" class="d-inline">
                    <input type="hidden" name="zone" value="<?= htmlspecialchars($last_search_zone) ?>">
                    <input type="hidden" name="specialty" value="<?= htmlspecialchars($last_search_specialty) ?>">
                    <button type="submit" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i> Retour à la liste</button>
                </form>
            </div>
            <?php else: ?>
            <div class="alert alert-warning text-center" role="alert">
                Technicien introuvable. Veuillez vérifier l'ID.
            </div>
            <div class="text-center mt-4">
                <form action="traitement2.php" method="POST" class="d-inline">
                    <input type="hidden" name="zone" value="<?= htmlspecialchars($last_search_zone) ?>">
                    <input type="hidden" name="specialty" value="<?= htmlspecialchars($last_search_specialty) ?>">
                    <button type="submit" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i> Retour à la recherche</button>
                </form>
                </div>
        <?php endif; ?>
    </div>

    <div class="container my-5">
        <?php if ($technicien_data): ?>
            <div class="row">
                <div class="col-md-4 text-center">
                    <div class="profile-img-container">
                        <img src="<?= htmlspecialchars($technicien_data['photo_profil_path'] ?? $base_url . 'uploads/default_profile.png') ?>" alt="Photo de profil">
                    </div>
                    <h3><?= htmlspecialchars($technicien_data['prenom'] . ' ' . $technicien_data['nom']) ?></h3>
                    <p class="text-muted"><?= htmlspecialchars($technicien_data['specialite']) ?></p>
                    <p class="text-muted">Zone : <?= htmlspecialchars($technicien_data['zone']) ?></p>
                </div>
                <div class="col-md-8">
                    <div class="info-section">
                        <h4><i class="fas fa-info-circle me-2"></i> Informations de contact</h4>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item"><strong>Email :</strong> <span><?= htmlspecialchars($technicien_data['email']) ?></span></li>
                            <li class="list-group-item"><strong>Téléphone :</strong> <span><?= htmlspecialchars($technicien_data['num_technicien']) ?></span></li>
                        </ul>

                        <h4><i class="fas fa-briefcase me-2"></i> Expérience et Spécialité</h4>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item"><strong>Spécialité :</strong> <span><?= htmlspecialchars($technicien_data['specialite']) ?></span></li>
                            <li class="list-group-item"><strong>Années d'expérience :</strong> <span><?= htmlspecialchars($technicien_data['annees_experience']) ?> ans</span></li>
                            <li class="list-group-item"><strong>Description :</strong>
                                <p class="description-box mt-2"><?= nl2br(htmlspecialchars($technicien_data['description'])) ?></p>
                            </li>
                        </ul>
                    </div>

                    <div class="info-section mt-4">
                        <h4><i class="fas fa-certificate me-2"></i> Certifications Approuvées</h4>
                        <?php if (!empty($certifications)): ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($certifications as $cert): ?>
                                    <li class="list-group-item certification-item">
                                        <span>
                                            <strong><?= htmlspecialchars($cert['nom_certification']) ?></strong> (Ajoutée le <?= date('d/m/Y', strtotime($cert['date_ajout'])) ?>)
                                        </span>
                                        <?php if (!empty($cert['chemin_certification'])): ?>
                                            <a href="<?= htmlspecialchars($cert['chemin_certification']) ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Voir la certification">
                                                <i class="fas fa-file-alt me-1"></i> Voir
                                            </a>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-muted">Aucune certification approuvée pour ce technicien pour le moment.</p>
                        <?php endif; ?>
                    </div>

                    <div class="info-section mt-4">
                        <h4><i class="fas fa-history me-2"></i> Historique des Missions</h4>
                        <?php if (!empty($missions_history)): ?>
                            <div class="table-responsive">
                                <table class="table table-missions table-striped">
                                    <thead>
                                        <tr>
                                            <th>Description</th>
                                            <th>Statut</th>
                                            <th>Localisation</th>
                                            <th>Date Demande</th>
                                            <th>Début Mission</th>
                                            <th>Fin Mission</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($missions_history as $mission): ?>
                                            <tr>
                                                <td data-label="Description"><?= nl2br(htmlspecialchars($mission['description'])) ?></td>
                                                <td data-label="Statut">
                                                    <?php
                                                    $status_class = '';
                                                    switch ($mission['statut']) {
                                                        case 'en_attente':
                                                            $status_class = 'status-en_attente';
                                                            break;
                                                        case 'acceptée':
                                                            $status_class = 'status-acceptée';
                                                            break;
                                                        case 'refusée':
                                                            $status_class = 'status-refusée';
                                                            break;
                                                        case 'terminée':
                                                            $status_class = 'status-terminée';
                                                            break;
                                                        default:
                                                            $status_class = 'bg-secondary'; // Cas par défaut si statut inconnu
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge <?= $status_class ?> status-badge"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $mission['statut']))) ?></span>
                                                </td>
                                                <td data-label="Localisation"><?= htmlspecialchars($mission['localisation']) ?></td>
                                                <td data-label="Date Demande"><?= date('d/m/Y H:i', strtotime($mission['date_demande'])) ?></td>
                                                <td data-label="Début Mission">
                                                    <?= $mission['date_debut_mission'] ? date('d/m/Y H:i', strtotime($mission['date_debut_mission'])) : 'N/A' ?>
                                                </td>
                                                <td data-label="Fin Mission">
                                                    <?= $mission['date_terminee'] ? date('d/m/Y H:i', strtotime($mission['date_terminee'])) : 'N/A' ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">Aucun historique de mission disponible pour ce technicien.</p>
                        <?php endif; ?>
                    </div>

                    <div class="text-center mt-4">
                        <a href="client.php" class="btn btn-secondary me-2"><i class="fas fa-arrow-left me-2"></i> Retour à l'Accueil</a>
                        
                        <button type="button" id="showDemandFormBtn" class="btn btn-success">
                            <i class="fas fa-wrench me-2"></i> Demander un service à ce technicien
                        </button>

                        <div id="demandFormContainer" class="demand-form-container">
                            <h5 class="mb-3 text-primary">Décrivez votre problème et envoyez la demande</h5>
                            <form method="POST" action="<?= $base_url ?>envoyer_demande_service.php">
                                <input type="hidden" name="id_technicien" value="<?= (int)$technicien_data['id_technicien'] ?>">
                                <input type="hidden" name="technicien_email" value="<?= htmlspecialchars($technicien_data['email']) ?>">
                                <input type="hidden" name="technicien_nom" value="<?= htmlspecialchars($technicien_data['prenom'] . ' ' . $technicien_data['nom']) ?>">
                                <input type="hidden" name="client_id" value="<?= htmlspecialchars($client_id ?? '') ?>">
                                <input type="hidden" name="client_email" value="<?= htmlspecialchars($client_email ?? '') ?>">
                                <input type="hidden" name="client_nom" value="<?= htmlspecialchars($client_nom_complet ?? '') ?>">
                                <input type="hidden" name="client_telephone" value="<?= htmlspecialchars($client_telephone ?? '') ?>">

                                <div class="mb-3 text-start">
                                    <label for="problemDescription" class="form-label">Description détaillée de votre problème :</label>
                                    <textarea class="form-control" id="problemDescription" name="description_probleme" rows="5" required
                                                placeholder="Ex: Mon ordinateur ne s'allume plus après une coupure de courant..."></textarea>
                                </div>
                                <div class="mb-3 text-start">
                                    <label for="missionLocation" class="form-label">Lieu de l'intervention (ville ou quartier) :</label>
                                    <input type="text" class="form-control" id="missionLocation" name="localisation_mission" value="<?= htmlspecialchars($client_zone ?? '') ?>" required
                                                placeholder="Ex: Douala, Bonamoussadi">
                                </div>
                                <button type="submit" class="btn btn-primary mt-3"><i class="fas fa-paper-plane me-2"></i> Envoyer la demande</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-warning text-center" role="alert">
                Technicien introuvable. Veuillez vérifier l'ID.
            </div>
            <div class="text-center mt-4">
                <a href="client.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i> Retour à l'Accueil</a>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('showDemandFormBtn').addEventListener('click', function() {
            var formContainer = document.getElementById('demandFormContainer');
            if (formContainer.classList.contains('active')) {
                formContainer.classList.remove('active');
            } else {
                formContainer.classList.add('active');
                formContainer.scrollIntoView({ behavior: 'smooth' });
            }
        });
    </script>
</body>
</html>