<?php
session_start();

// Inclure le système de log pour enregistrer les actions importantes
require_once 'utils.php'; 

// Vérification de l'authentification de l'administrateur
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || ($_SESSION['role'] ?? '') !== 'admin') {
    header('location: connexion_admin.php');
    exit();
}

// Paramètres de connexion à la base de données
$server_name = "localhost";
$user_name = "root";
$psw = ""; // Assurez-vous que c'est le bon mot de passe pour votre utilisateur 'root'
$DB_name = "depanage";

// Connexion à la base de données
$con = new mysqli($server_name, $user_name, $psw, $DB_name);

// Vérification de la connexion
if ($con->connect_error) {
    error_log("Erreur de connexion à la base de données : " . $con->connect_error);
    die("Désolé, une erreur technique est survenue. Veuillez réessayer plus tard.");
}

// Définir l'encodage
$con->set_charset("utf8mb4");

// --- Gestion des actions de quarantaine (MISE À JOUR) ---
if (isset($_GET['action']) && isset($_GET['id'])) {
    $technicien_id = (int)$_GET['id'];
    $action_type = $_GET['action'];
    $admin_id = $_SESSION['id_admin'] ?? 'N/A'; // Récupère l'ID de l'admin pour le log

    // Commencez une transaction pour assurer la cohérence des données
    $con->begin_transaction();

    try {
        // Récupérer les informations du technicien pour le log et vérification
        $stmt_get_tech = $con->prepare("SELECT email, nom, prenom FROM technicien WHERE id_technicien = ?");
        if (!$stmt_get_tech) {
            throw new Exception("Erreur de préparation pour récupérer le technicien : " . $con->error);
        }
        $stmt_get_tech->bind_param("i", $technicien_id);
        $stmt_get_tech->execute();
        $result_get_tech = $stmt_get_tech->get_result();
        $tech_info = $result_get_tech->fetch_assoc();
        $stmt_get_tech->close();

        if (!$tech_info) {
            throw new Exception("Technicien introuvable avec l'ID spécifié.");
        }

        $email_technicien = $tech_info['email'];
        $nom_complet_technicien = $tech_info['prenom'] . ' ' . $tech_info['nom'];

        if ($action_type === 'quarantine') {
            // Mettre en quarantaine pour 15 jours
            // Un technicien en quarantaine ne doit pas être actif.
            $fin_quarantaine = date('Y-m-d H:i:s', strtotime('+15 days'));
            $stmt_quarantine = $con->prepare("UPDATE technicien SET en_quarantaine = 1, fin_quarantaine = ?, is_active = 0 WHERE id_technicien = ?");
            
            if (!$stmt_quarantine) {
                throw new Exception("Erreur de préparation de la requête de quarantaine : " . $con->error);
            }
            $stmt_quarantine->bind_param("si", $fin_quarantaine, $technicien_id);
            
            if ($stmt_quarantine->execute()) {
                $_SESSION['message'] = "Le technicien **{$nom_complet_technicien}** (ID: {$technicien_id}) a été mis en quarantaine pour 15 jours et son compte est temporairement désactivé.";
                $_SESSION['message_type'] = "success";
                logUserAction(
                    'Mise en Quarantaine Technicien', 
                    $technicien_id, 
                    'technicien', 
                    $nom_complet_technicien . ' (' . $email_technicien . ')',
                    "Mis en quarantaine jusqu'au {$fin_quarantaine} par l'administrateur (ID: {$admin_id})"
                );
            } else {
                throw new Exception("Erreur lors de la mise en quarantaine du technicien : " . $stmt_quarantine->error);
            }
            $stmt_quarantine->close();

        } elseif ($action_type === 'unquarantine') {
            // Lever la quarantaine
            // Lors de la levée de quarantaine, il est généralement bon de le rendre actif à nouveau.
            $stmt_unquarantine = $con->prepare("UPDATE technicien SET en_quarantaine = 0, fin_quarantaine = NULL, is_active = 1 WHERE id_technicien = ?");
            
            if (!$stmt_unquarantine) {
                throw new Exception("Erreur de préparation de la requête de levée de quarantaine : " . $con->error);
            }
            $stmt_unquarantine->bind_param("i", $technicien_id);
            
            if ($stmt_unquarantine->execute()) {
                $_SESSION['message'] = "La quarantaine du technicien **{$nom_complet_technicien}** (ID: {$technicien_id}) a été levée et son compte est réactivé.";
                $_SESSION['message_type'] = "success";
                logUserAction(
                    'Levée Quarantaine Technicien', 
                    $technicien_id, 
                    'technicien', 
                    $nom_complet_technicien . ' (' . $email_technicien . ')',
                    "Quarantaine levée par l'administrateur (ID: {$admin_id})"
                );
            } else {
                throw new Exception("Erreur lors de la levée de la quarantaine du technicien : " . $stmt_unquarantine->error);
            }
            $stmt_unquarantine->close();
        }
        
        $con->commit(); // Confirmer la transaction si tout s'est bien passé

    } catch (Exception $e) {
        $con->rollback(); // Annuler la transaction en cas d'erreur
        error_log("Erreur lors de l'action de quarantaine/désquarantaine du technicien {$technicien_id}: " . $e->getMessage());
        $_SESSION['message'] = "Erreur: " . $e->getMessage();
        $_SESSION['message_type'] = "danger";
    }
    
    // Rediriger pour éviter la soumission multiple et nettoyer l'URL
    header('location: gest_technicien.php');
    exit();
}
// --- FIN Gestion des actions de quarantaine ---


$specialite = isset($_POST['specialite']) ? trim($_POST['specialite']) : "";
$zone = isset($_POST['zone']) ? trim($_POST['zone']) : "";

// Initialisation de la requête SQL
// Ajout de 'en_quarantaine' et 'fin_quarantaine' à la sélection (MODIFIÉ)
// Ajout de 'is_active' et 'is_banned' pour la logique des boutons bannir/réactiver
$sql = "SELECT id_technicien, nom, num_technicien, email, zone, specialite, en_quarantaine, fin_quarantaine, is_active, is_banned FROM technicien WHERE 1 ";
$params = []; // Tableau pour stocker les paramètres pour la requête préparée
$types = ""; // Chaîne pour les types de paramètres ('s' pour string)

if (!empty($specialite)) {
    $sql .= " AND specialite LIKE ?";
    $params[] = "%" . $specialite . "%";
    $types .= "s";
}
if (!empty($zone)) {
    $sql .= " AND zone LIKE ?";
    $params[] = "%" . $zone . "%";
    $types .= "s";
}

$sql .= " ORDER BY nom ASC"; // Tri par nom par défaut

$stmt = $con->prepare($sql);

if (!$stmt) {
    error_log("Erreur de préparation de la requête techniciens : " . $con->error);
    $result = null; // En cas d'erreur de préparation, on n'a pas de résultat
} else {
    if (!empty($params)) {
        // Appeler bind_param dynamiquement
        $stmt->bind_param($types, ...$params); // Utilisation du splat operator (...) pour PHP 5.6+
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
}

// Message de succès/erreur après une action (suppression, ajout, modification)
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
    <link rel="stylesheet" href="c.css"> <title>Gestion des Techniciens</title>
    <style>
        /* Styles CSS supplémentaires pour les éléments spécifiques du formulaire */
        .div2 {
            padding: 20px;
            margin-top: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        #specialite, #zone {
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

        /* Nouveaux styles pour les boutons de quarantaine */
        .btn-quarantine {
            background-color: #dc3545; /* Rouge pour mettre en quarantaine */
            color: white;
            border: none;
        }
        .btn-quarantine:hover {
            background-color: #c82333;
        }
        .btn-unquarantine {
            background-color: #ffc107; /* Jaune/orange pour lever la quarantaine */
            color: #212529; /* Texte sombre */
            border: none;
        }
        .btn-unquarantine:hover {
            background-color: #e0a800;
        }
        /* Styles pour les badges de statut */
        .badge-active {
            background-color: #28a745; /* Vert */
            color: white;
        }
        .badge-inactive {
            background-color: #6c757d; /* Gris */
            color: white;
        }
        .badge-banned {
            background-color: #0d6efd; /* Bleu */
            color: white;
        }
        .badge-quarantine {
            background-color: #dc3545; /* Rouge */
            color: white;
        }
    </style>
</head>
<body>
<header>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid" style="margin-top:20px;">
            <a class="navbar-brand" href="admin.php" style="margin-top:-10px;">Tableau de Bord Admin</a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavGestTech" aria-controls="navbarNavGestTech" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNavGestTech">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="admin.php">Accueil Admin</a></li>
                    <li class="nav-item"><a class="nav-link" href="gest_client.php">Gestion des Clients</a></li>
                    <li class="nav-item"><a class="nav-link active" href="gest_technicien.php">Gestion des Techniciens</a></li>
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
                <input style="margin-right: 25px; width: 300px; height: 40px; border-radius: 5px;"
                        type="text" name="specialite" placeholder=" Spécialité du technicien"
                        value="<?= htmlspecialchars($specialite) ?>">
                <input style="width: 300px; height: 40px; border-radius: 5px; margin-right: 10px;"
                        type="text" name="zone" placeholder=" Zone"
                        value="<?= htmlspecialchars($zone) ?>">
                <input type="submit" value="Rechercher">
            </form>
        </div><br><br>
    </center>
</header>

<section>
    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                <?= $message ?> <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="bg-white m-auto rounded-top p-4 shadow">
            <h2 class="text-center mb-4">Techniciens membres du site :</h2>
            <div class="text-end mb-3">
                <a href="ajouter_technicien.php" class="btn btn-success">
                    <i class="bi bi-person-plus-fill"></i> Ajouter un technicien
                </a>
            </div>

            <?php if ($result && $result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Numéro du technicien</th>
                                <th>E-mail</th>
                                <th>Zone</th>
                                <th>Spécialité</th>
                                <th>Statut Compte</th> <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['nom']) ?></td>
                                    <td><?= htmlspecialchars($row['num_technicien']) ?></td>
                                    <td><?= htmlspecialchars($row['email']) ?></td>
                                    <td><?= htmlspecialchars($row['zone']) ?></td>
                                    <td><?= htmlspecialchars($row['specialite'])?></td>
                                    <td>
                                        <?php if ($row['is_banned']): ?>
                                            <span class="badge badge-banned">Banni</span>
                                        <?php elseif ($row['en_quarantaine']): ?>
                                            <span class="badge badge-quarantine">En Quarantaine</span><br>
                                            <small class="text-muted">Jusqu'à: <?= date('d/m/Y H:i', strtotime($row['fin_quarantaine'])) ?></small>
                                        <?php elseif ($row['is_active']): ?>
                                            <span class="badge badge-active">Actif</span>
                                        <?php else: ?>
                                            <span class="badge badge-inactive">Inactif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="modifier_technicien.php?id=<?= $row['id_technicien'] ?>" class="btn btn-warning btn-sm me-2">
                                            <i class="bi bi-pencil-square"></i> Modifier
                                        </a>
                                        
                                        <?php if ($row['is_banned'] == 1): ?>
                                            <a href="reactiver_technicien.php?id=<?= $row['id_technicien'] ?>" class="btn btn-primary btn-sm me-2 mt-1" onclick="return confirm('Êtes-vous sûr de vouloir réactiver ce technicien ?');">
                                                <i class="bi bi-person-check"></i> Réactiver Compte
                                            </a>
                                        <?php else: ?>
                                            <a href="bannir_technicien.php?id=<?= $row['id_technicien'] ?>" class="btn btn-info btn-sm me-2 mt-1" onclick="return confirm('Êtes-vous sûr de vouloir bannir ce technicien ? Cette action le désactivera et le rendra irrécupérable sans action manuelle.');">
                                                <i class="bi bi-person-x"></i> Bannir Compte
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($row['en_quarantaine']): ?>
                                            <a href="gest_technicien.php?action=unquarantine&id=<?= $row['id_technicien'] ?>" class="btn btn-unquarantine btn-sm mt-1"
                                                onclick="return confirm('Êtes-vous sûr de vouloir lever la quarantaine de ce technicien ?');">
                                                <i class="bi bi-unlock"></i> Lever Quarantaine
                                            </a>
                                        <?php else: ?>
                                            <a href="gest_technicien.php?action=quarantine&id=<?= $row['id_technicien'] ?>" class="btn btn-quarantine btn-sm mt-1"
                                                onclick="return confirm('Êtes-vous sûr de vouloir mettre ce technicien en quarantaine pour 15 jours ? Le compte sera désactivé pendant cette période.');">
                                                <i class="bi bi-lock"></i> Mettre en Quarantaine
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-center text-muted p-3">Aucun technicien trouvé pour les critères de recherche.</p>
            <?php endif; ?>
        </div>
    </div>
</section>
</body>
</html>

<?php $con->close(); ?>