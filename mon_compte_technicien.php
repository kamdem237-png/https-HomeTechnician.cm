<?php
session_start();

// Redirection si l'utilisateur n'est pas connecté ou n'est pas un technicien
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'technicien') {
    header("Location: connexion_user.php");
    exit();
}

// --- Configuration de la base de données ---
$server_name = "localhost";
$user_name = "root";
$psw = ""; // Assurez-vous que c'est bien vide ou le mot de passe de votre base de données
$DB_name = "depanage";

// Connexion à la base de données
$conn = new mysqli($server_name, $user_name, $psw, $DB_name);
if ($conn->connect_error) {
    error_log("Erreur de connexion à la base de données : " . $conn->connect_error);
    die("Désolé, une erreur technique est survenue. Veuillez réessayer plus tard.");
}
$conn->set_charset("utf8mb4");

// Assurez-vous que $id_technicien est toujours défini à partir de la session
$id_technicien = (int)$_SESSION['user']['id_technicien'];
$message = "";
$error = "";

// Récupérer les informations actuelles du technicien, y compris le mot de passe hashé
// Utilisation de 'password' pour la cohérence avec le reste du code PHP
$stmt = $conn->prepare("SELECT prenom, nom, num_technicien, email, zone, specialite, photo_profil_path, description, annees_experience, password FROM technicien WHERE id_technicien = ?");
if ($stmt) {
    $stmt->bind_param("i", $id_technicien);
    $stmt->execute();
    $result = $stmt->get_result();
    $technicien_data = $result->fetch_assoc();
    $stmt->close();
} else {
    error_log("Erreur de préparation de la requête de récupération des données du technicien : " . $conn->error);
    $technicien_data = null; // En cas d'erreur de préparation
}

if (!$technicien_data) {
    // Si pour une raison quelconque le technicien n'est pas trouvé, déconnecter
    header("Location: logout_technicien.php"); // Assuming a logout_technicien.php exists
    exit();
}

// Valeurs par défaut pour le formulaire si aucune donnée n'est encore chargée ou en cas d'erreur
$current_prenom = htmlspecialchars($technicien_data['prenom'] ?? '');
$current_nom = htmlspecialchars($technicien_data['nom'] ?? '');
$current_num_technicien = htmlspecialchars($technicien_data['num_technicien'] ?? '');
$current_email = htmlspecialchars($technicien_data['email'] ?? '');
$current_zone = htmlspecialchars($technicien_data['zone'] ?? '');
$current_specialite = htmlspecialchars($technicien_data['specialite'] ?? '');
$current_photo_profil_path = htmlspecialchars($technicien_data['photo_profil_path'] ?? 'uploads/default_profile.png');
$current_description = htmlspecialchars($technicien_data['description'] ?? '');
$current_annees_experience = htmlspecialchars($technicien_data['annees_experience'] ?? 0);
$hashed_password_from_db = $technicien_data['password']; // Utilisation de 'password'

// --- Traitement de la modification des informations (texte) ---
if (isset($_POST['update_profile'])) {
    $nom = trim($_POST['nom'] ?? '');
    $num_technicien = trim($_POST['num_technicien'] ?? '');
    $zone = trim($_POST['zone'] ?? '');
    $specialite = trim($_POST['specialite'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $annees_experience = intval($_POST['annees_experience'] ?? 0);

    // Validation des données
    $errors = [];
    if (empty($nom)) { $errors[] = "Le nom est requis."; }
    if (empty($num_technicien)) { $errors[] = "Le numéro de téléphone est requis."; }
    // Validation spécifique pour les numéros de téléphone camerounais (commence par 6, 9 chiffres)
    elseif (!preg_match("/^(\+237|00237)?(6[5-9]\d{7})$/", $num_technicien)) {
        $errors[] = "Le numéro de téléphone n'est pas valide (doit commencer par 6 et contenir 9 chiffres après l'indicatif).";
    }
    if (empty($zone)) { $errors[] = "La zone est requise."; }
    if (empty($specialite)) { $errors[] = "La spécialité est requise."; }
    if (empty($description)) { $errors[] = "La description de votre expérience est requise."; }
    elseif (strlen($description) < 50) { $errors[] = "La description doit contenir au moins 50 caractères."; }
    if ($annees_experience < 0 || $annees_experience > 50) { $errors[] = "Le nombre d'années d'expérience n'est pas valide."; }

    if (!empty($errors)) {
        $error = implode("<br>", $errors);
    } else {
        // Vérifier si le numéro de technicien est déjà utilisé par un AUTRE technicien
        $checkStmt = $conn->prepare("SELECT id_technicien FROM technicien WHERE num_technicien = ? AND id_technicien != ?");
        if ($checkStmt) {
            $checkStmt->bind_param("si", $num_technicien, $id_technicien);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            if ($checkResult->num_rows > 0) {
                $error = "Ce numéro de téléphone est déjà utilisé par un autre compte.";
            }
            $checkStmt->close();
        } else {
            $error = "Erreur lors de la préparation de la vérification du numéro de téléphone.";
        }

        if (empty($error)) {
            $stmt_update = $conn->prepare("UPDATE technicien SET nom = ?, num_technicien = ?, zone = ?, specialite = ?, description = ?, annees_experience = ? WHERE id_technicien = ?");
            if ($stmt_update) {
                $stmt_update->bind_param("sssssii", $nom, $num_technicien, $zone, $specialite, $description, $annees_experience, $id_technicien);

                if ($stmt_update->execute()) {
                    $message = "Vos informations ont été mises à jour avec succès.";
                    // Mettre à jour les données de session et recharger les données du technicien
                    $_SESSION['user']['nom'] = $nom;
                    $_SESSION['user']['num_technicien'] = $num_technicien;
                    $_SESSION['user']['zone'] = $zone;
                    $_SESSION['user']['specialite'] = $specialite;
                    $_SESSION['user']['description'] = $description;
                    $_SESSION['user']['annees_experience'] = $annees_experience;

                    // Re-fetch des données complètes pour l'affichage, y compris la photo_profil_path
                    // Utilisation de 'password' pour la re-lecture
                    $stmt_re_fetch = $conn->prepare("SELECT prenom, nom, num_technicien, email, zone, specialite, photo_profil_path, description, annees_experience, password FROM technicien WHERE id_technicien = ?");
                    if ($stmt_re_fetch) {
                        $stmt_re_fetch->bind_param("i", $id_technicien);
                        $stmt_re_fetch->execute();
                        $result_re_fetch = $stmt_re_fetch->get_result();
                        $technicien_data = $result_re_fetch->fetch_assoc();
                        $stmt_re_fetch->close();

                        // Mettre à jour les variables d'affichage
                        $current_prenom = htmlspecialchars($technicien_data['prenom'] ?? '');
                        $current_nom = htmlspecialchars($technicien_data['nom'] ?? '');
                        $current_num_technicien = htmlspecialchars($technicien_data['num_technicien'] ?? '');
                        $current_zone = htmlspecialchars($technicien_data['zone'] ?? '');
                        $current_specialite = htmlspecialchars($technicien_data['specialite'] ?? '');
                        $current_description = htmlspecialchars($technicien_data['description'] ?? '');
                        $current_annees_experience = htmlspecialchars($technicien_data['annees_experience'] ?? 0);
                        $hashed_password_from_db = $technicien_data['password']; // Utilisation de 'password'
                    } else {
                        $error = "Erreur lors de la préparation de la re-lecture des données.";
                    }
                } else {
                    $error = "Erreur lors de la mise à jour : " . $stmt_update->error;
                }
                $stmt_update->close();
            } else {
                $error = "Erreur de préparation de la requête de mise à jour.";
            }
        }
    }
}

// --- Traitement de l'upload de la photo de profil ---
if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == UPLOAD_ERR_OK) {
    $target_dir = "uploads/techniciens/"; // Dossier spécifique pour les photos de techniciens

    // Assurez-vous que le dossier existe et est accessible en écriture
    if (!is_dir($target_dir)) {
        if (!mkdir($target_dir, 0755, true)) {
            $error = "Impossible de créer le dossier d'upload. Contactez l'administrateur.";
        }
    }

    if (empty($error)) { // Continuer seulement si le dossier a été créé/existe
        $imageFileType = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = array("jpg", "jpeg", "png", "gif");

        if (!in_array($imageFileType, $allowed_extensions)) {
            $error = "Seuls les fichiers JPG, JPEG, PNG et GIF sont autorisés pour la photo de profil.";
        } elseif ($_FILES['profile_picture']['size'] > 5000000) { // 5MB max
            $error = "La taille de l'image est trop grande (max 5MB).";
        } else {
            // Supprimer l'ancienne photo si elle existe et n'est pas la photo par défaut
            if (!empty($current_photo_profil_path) && $current_photo_profil_path !== 'uploads/default_profile.png' && file_exists($current_photo_profil_path)) {
                unlink($current_photo_profil_path);
            }

            // Renommer le fichier pour éviter les conflits et le stocker
            $new_filename = uniqid('profile_tech_') . '.' . $imageFileType;
            $target_file = $target_dir . $new_filename;

            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
                $stmt_photo = $conn->prepare("UPDATE technicien SET photo_profil_path = ? WHERE id_technicien = ?");
                if ($stmt_photo) {
                    $stmt_photo->bind_param("si", $target_file, $id_technicien);
                    if ($stmt_photo->execute()) {
                        $message = "Photo de profil mise à jour avec succès.";
                        $current_photo_profil_path = $target_file; // Mettre à jour pour affichage immédiat
                    } else {
                        $error = "Erreur lors de l'enregistrement du chemin de la photo en BD : " . $stmt_photo->error;
                        unlink($target_file); // Supprimer le fichier si l'enregistrement BD échoue
                    }
                    $stmt_photo->close();
                } else {
                    $error = "Erreur de préparation de la requête d'upload de photo.";
                }
            } else {
                $error = "Erreur lors du téléchargement de votre photo.";
            }
        }
    }
}

// --- Traitement du changement de mot de passe ---
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_new_password = $_POST['confirm_new_password'] ?? '';

    $pw_errors = [];

    // 1. Vérifier que le mot de passe actuel est correct
    if (!password_verify($current_password, $hashed_password_from_db)) {
        $pw_errors[] = "Le mot de passe actuel est incorrect.";
    }

    // 2. Valider la longueur du nouveau mot de passe (minimum 8 caractères, ou plus selon vos règles)
    if (strlen($new_password) < 8) {
        $pw_errors[] = "Le nouveau mot de passe doit contenir au moins 8 caractères.";
    }

    // 3. Vérifier que les nouveaux mots de passe correspondent
    if ($new_password !== $confirm_new_password) {
        $pw_errors[] = "Le nouveau mot de passe et sa confirmation ne correspondent pas.";
    }

    if (!empty($pw_errors)) {
        $error = implode("<br>", $pw_errors);
    } else {
        // Hacher le nouveau mot de passe avant de le stocker
        $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Utilisation de 'password' pour la mise à jour
        $stmt_update_pw = $conn->prepare("UPDATE technicien SET password = ? WHERE id_technicien = ?");
        if ($stmt_update_pw) {
            $stmt_update_pw->bind_param("si", $hashed_new_password, $id_technicien);

            if ($stmt_update_pw->execute()) {
                $message = "Votre mot de passe a été mis à jour avec succès.";
                // Mettre à jour le hash en mémoire pour éviter le re-fetch immédiat pour ce cas
                $hashed_password_from_db = $hashed_new_password;
            } else {
                $error = "Erreur lors de la mise à jour du mot de passe : " . $stmt_update_pw->error;
            }
            $stmt_update_pw->close();
        } else {
            $error = "Erreur de préparation de la requête de mise à jour du mot de passe.";
        }
    }
}

// --- Traitement de l'upload de certifications ---
if (isset($_POST['upload_certification'])) {
    $nom_certification = trim($_POST['nom_certification']);
    // Dossier où les certifications seront stockées. Assurez-vous qu'il existe et est inscriptible.
    $upload_dir = "uploads/certifications/";

    // Assurez-vous que le dossier d'upload existe
    if (!is_dir($upload_dir)) {
        // Tentative de créer le dossier. Si cela échoue, une erreur est définie.
        if (!mkdir($upload_dir, 0777, true)) {
            $error = "Impossible de créer le dossier d'upload pour les certifications. Contactez l'administrateur.";
        }
    }

    $file_name = uniqid() . "_" . basename($_FILES["fichier_certification"]["name"]);
    $target_file = $upload_dir . $file_name;
    $uploadOk = 1;
    $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

    // Vérifier si le champ de fichier n'est pas vide
    if (empty($_FILES["fichier_certification"]["name"])) {
        $error = "Veuillez sélectionner un fichier à télécharger.";
        $uploadOk = 0;
    } else {
        // Vérifier si le fichier est un PDF, JPG, JPEG ou PNG
        if($file_type != "pdf" && $file_type != "jpg" && $file_type != "png" && $file_type != "jpeg") {
            $error = "Seuls les fichiers PDF, JPG, JPEG & PNG sont autorisés.";
            $uploadOk = 0;
        }

        // Vérifier la taille du fichier (exemple: max 5MB)
        if ($_FILES["fichier_certification"]["size"] > 5000000) {
            $error = "Désolé, votre fichier est trop volumineux (max 5MB).";
            $uploadOk = 0;
        }
    }

    // Si tout est bon, tenter l'upload et l'insertion en BD
    if ($uploadOk == 1 && empty($error)) { // Ajout de empty($error) pour s'assurer que le mkdir n'a pas échoué
        if (move_uploaded_file($_FILES["fichier_certification"]["tmp_name"], $target_file)) {
            // Insérer dans la base de données avec le statut 'en_attente'
            $stmt = $conn->prepare("INSERT INTO certifications (id_technicien, nom_certification, chemin_certification, statut) VALUES (?, ?, ?, 'en_attente')");
            if ($stmt) {
                // CORRECTION ICI: Utilisation de $id_technicien au lieu de $id_technicien_connecte
                $stmt->bind_param("iss", $id_technicien, $nom_certification, $target_file);
                if ($stmt->execute()) {
                    $message = "Certification ajoutée avec succès et en attente d'approbation par l'administrateur.";
                } else {
                    $error = "Erreur lors de l'enregistrement de la certification en base de données : " . $stmt->error;
                    unlink($target_file); // Supprimer le fichier si l'insertion échoue
                }
                $stmt->close();
            } else {
                $error = "Erreur de préparation de la requête d'ajout de certification.";
                unlink($target_file); // Supprimer le fichier même si la préparation échoue
            }
        } else {
            $error = "Désolé, une erreur est survenue lors du téléchargement de votre fichier.";
        }
    }
}

// --- TRAITEMENT DE LA SUPPRESSION D'UNE CERTIFICATION PAR LE TECHNICIEN ---
if (isset($_POST['delete_own_certification'])) {
    $id_certification = (int)$_POST['id_certification'];

    // Sécurité: Vérifiez que le technicien est bien le propriétaire de la certification
    $stmt_check_owner = $conn->prepare("SELECT chemin_certification FROM certifications WHERE id_certification = ? AND id_technicien = ?");
    if ($stmt_check_owner) {
        // CORRECTION ICI: Utilisation de $id_technicien au lieu de $id_technicien_connecte
        $stmt_check_owner->bind_param("ii", $id_certification, $id_technicien);
        $stmt_check_owner->execute();
        $result_owner = $stmt_check_owner->get_result();
        $file_row = $result_owner->fetch_assoc();
        $stmt_check_owner->close();

        if ($file_row) {
            $file_to_delete = $file_row['chemin_certification'];

            $stmt_delete = $conn->prepare("DELETE FROM certifications WHERE id_certification = ? AND id_technicien = ?");
            if ($stmt_delete) {
                // CORRECTION ICI: Utilisation de $id_technicien au lieu de $id_technicien_connecte
                $stmt_delete->bind_param("ii", $id_certification, $id_technicien);
                if ($stmt_delete->execute()) {
                    // Supprimer le fichier réel du serveur
                    if (file_exists($file_to_delete) && !empty($file_to_delete)) {
                        unlink($file_to_delete);
                    }
                    $message = "Certification supprimée avec succès.";
                } else {
                    $error = "Erreur lors de la suppression de la certification : " . $stmt_delete->error;
                }
                $stmt_delete->close();
            } else {
                $error = "Erreur de préparation pour la suppression de certification.";
            }
        } else {
            $error = "Certification non trouvée ou vous n'êtes pas autorisé à la supprimer.";
        }
    } else {
        $error = "Erreur de préparation pour vérifier le propriétaire de la certification.";
    }
}


// --- RÉCUPÉRATION DES CERTIFICATIONS DU TECHNICIEN CONNECTÉ POUR AFFICHAGE ---
$mes_certifications = [];
// Récupère toutes les certifications soumises par le technicien connecté
$stmt_fetch = $conn->prepare("SELECT id_certification, nom_certification, chemin_certification, statut, date_ajout FROM certifications WHERE id_technicien = ? ORDER BY date_ajout DESC");

if ($stmt_fetch) {
    // CORRECTION ICI: Utilisation de $id_technicien au lieu de $id_technicien_connecte
    $stmt_fetch->bind_param("i", $id_technicien);
    $stmt_fetch->execute();
    $result = $stmt_fetch->get_result();
    while ($row = $result->fetch_assoc()) {
        $mes_certifications[] = $row;
    }
    $stmt_fetch->close();
} else {
    $error = "Erreur de préparation de la requête de récupération de vos certifications : " . $conn->error;
}


// --- Traitement de la suppression de certification (potentiellement doublon avec delete_own_certification) ---
// Ce bloc semble être un doublon partiel de `delete_own_certification`.
// Je le laisse pour l'instant mais vous pourriez vouloir le fusionner.
if (isset($_POST['delete_certification'])) {
    $cert_id_to_delete = (int)$_POST['delete_certification'];

    // Récupérer le chemin du fichier avant de le supprimer de la BD
    $stmt_get_file = $conn->prepare("SELECT chemin_certification FROM certifications WHERE id_certification = ? AND id_technicien = ?");
    if ($stmt_get_file) {
        $stmt_get_file->bind_param("ii", $cert_id_to_delete, $id_technicien);
        $stmt_get_file->execute();
        $result_file = $stmt_get_file->get_result();
        $file_row = $result_file->fetch_assoc();
        $stmt_get_file->close();

        if ($file_row && !empty($file_row['chemin_certification'])) { // Correction: chemin_fichier -> chemin_certification
            $file_to_delete = $file_row['chemin_certification']; // Correction: chemin_fichier -> chemin_certification

            $stmt_delete = $conn->prepare("DELETE FROM certifications WHERE id_certification = ? AND id_technicien = ?");
            if ($stmt_delete) {
                $stmt_delete->bind_param("ii", $cert_id_to_delete, $id_technicien);
                if ($stmt_delete->execute()) {
                    // Supprimer le fichier réel du serveur
                    if (file_exists($file_to_delete)) {
                        unlink($file_to_delete);
                    }
                    $message = "Certification supprimée avec succès.";
                } else {
                    $error = "Erreur lors de la suppression de la certification : " . $stmt_delete->error;
                }
                $stmt_delete->close();
            } else {
                $error = "Erreur de préparation pour la suppression de certification.";
            }
        } else {
            $error = "Certification non trouvée ou vous n'avez pas la permission de la supprimer.";
        }
    } else {
        $error = "Erreur de préparation pour récupérer le chemin du fichier de certification.";
    }
}

// --- PHP pour afficher les certifications existantes (Ce bloc est correct) ---
$existing_certifications = [];
$stmt_fetch_certs = $conn->prepare("SELECT id_certification, nom_certification, chemin_certification, statut FROM certifications WHERE id_technicien = ? ORDER BY date_ajout DESC");
if ($stmt_fetch_certs) {
    $stmt_fetch_certs->bind_param("i", $id_technicien);
    $stmt_fetch_certs->execute();
    $result_certs = $stmt_fetch_certs->get_result();
    while ($row = $result_certs->fetch_assoc()) {
        $existing_certifications[] = $row;
    }
    $stmt_fetch_certs->close();
} else {
    error_log("Erreur de préparation pour récupérer les certifications : " . $conn->error);
}

// --- Traitement du signalement de problème à l'administration ---
if (isset($_POST['report_problem'])) {
    $sujet = trim($_POST['sujet'] ?? '');
    $description_probleme = trim($_POST['description_probleme'] ?? '');

    // CORRECTION ICI: Utilisation de empty() à la place de vide() et des guillemets corrects
    if (empty($sujet) || empty($description_probleme)) {
        $error = "Veuillez remplir le sujet et la description du problème.";
    } else {
        // Enregistrer le problème dans la table 'signalements'
        // Assurez-vous que la table 'signalements' existe et a les colonnes nécessaires (id_technicien, sujet, description, date_signalement, statut)
        $stmt_report = $conn->prepare("INSERT INTO signalements (id_technicien, sujet, description, date_signalement, statut) VALUES (?, ?, ?, NOW(), 'ouvert')");
        if ($stmt_report) {
            $stmt_report->bind_param("iss", $id_technicien, $sujet, $description_probleme);

            if ($stmt_report->execute()) {
                $message = "Votre problème a été signalé à l'administration.";
            } else {
                $error = "Erreur lors du signalement du problème : " . $stmt_report->error;
            }
            $stmt_report->close();
        } else {
            $error = "Erreur de préparation de la requête de signalement.";
        }
    }
}

// CORRECTION ICI: Utilisation de $conn->close()
$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Compte Technicien - HomeTechnician</title>
    <link rel="icon" type="image/png" href="mon_logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
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

        /* Hero Section (ou équivalent pour technicien) */
        .technician-hero {
            background: linear-gradient(rgba(0, 123, 255, 0.8), rgba(0, 86, 179, 0.8)), url('path/to/your/technician-bg.jpg') no-repeat center center/cover; /* Image de fond différente si vous voulez */
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

        /* Annonce Card Specific Styles */
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
            background-color: #f8f9fa; /* Ajout d'un fond léger */
        }
        .profile-img-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
         .card-header {
            background-color: #28a745; /* Couleur primaire pour l'en-tête de carte */
            color: white;
            font-weight: 600;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
        }

        .table-responsive {
            margin-top: 20px;
        }

        .table th, .table td {
            vertical-align: middle;
        }

        /* Badges de statut pour le technicien */
        .badge-status-en_attente {
            background-color: #ffc107; /* Jaune warning */
            color: #212529; /* Texte noir */
        }
        .badge-status-approuve {
            background-color: #28a745; /* Vert success */
            color: white;
        }
        .badge-status-rejete {
            background-color: #dc3545; /* Rouge danger */
            color: white;
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
                        <li class="nav-item"><a class="nav-link active" href="mon_compte_technicien.php">Mon compte</a></li>
                        <li class="nav-item"><a class="nav-link" href="missions_disponibles.php">Mes missions</a></li>
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

    <main class="container mt-5 py-4">
        <h3 class="mb-4 text-center">Gestion de Mon Compte Technicien</h3>

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row justify-content-center">
            <div class="col-md-10 col-lg-12">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white text-center py-3">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i> Informations de Profil</h5>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <div class="profile-img-container">
                                <img src="<?= $current_photo_profil_path ?>" alt="Photo de profil">
                            </div>
                            <h4><?= $current_prenom . ' ' . $current_nom ?></h4>
                            <p class="text-muted mb-1"><?= $current_specialite ?></p>
                            <p class="text-muted"><?= $current_zone ?></p>
                        </div>

                        <form action="" method="POST" enctype="multipart/form-data" class="mb-5 p-4 border rounded bg-light">
                            <h6 class="mb-3 text-primary"><i class="fas fa-camera me-2"></i> Changer votre photo de profil</h6>
                            <div class="mb-3">
                                <label for="profile_picture" class="form-label">Sélectionner une nouvelle photo</label>
                                <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept="image/jpeg,image/png,image/gif">
                                <small class="form-text text-muted">Formats acceptés : JPG, JPEG, PNG, GIF (max 5MB).</small>
                            </div>
                            <button type="submit" class="btn btn-secondary"><i class="fas fa-upload me-2"></i> Uploader la photo</button>
                        </form>

                        <form action="" method="POST" class="p-4 border rounded">
                            <h6 class="mb-3 text-primary"><i class="fas fa-edit me-2"></i> Modifier vos informations personnelles</h6>
                            <div class="mb-3">
                                <label for="nom" class="form-label">Nom</label>
                                <input type="text" class="form-control" id="nom" name="nom" value="<?= $current_nom ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="num_technicien" class="form-label">Numéro de téléphone</label>
                                <input type="tel" class="form-control" id="num_technicien" name="num_technicien" placeholder="+237 6XXXXXXXX" value="<?= $current_num_technicien ?>" required>
                                <small class="form-text text-muted">Format: +237 6XXXXXXXX ou 6XXXXXXXX (9 chiffres après le 6).</small>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Adresse Email</label>
                                <input type="email" class="form-control" id="email" value="<?= $current_email ?>" readonly>
                                <small class="form-text text-muted">L'adresse email ne peut pas être modifiée ici.</small>
                            </div>
                            <div class="mb-3">
                                <label for="zone" class="form-label">Zone de service (Ville, Quartier)</label>
                                <input type="text" class="form-control" id="zone" name="zone" value="<?= $current_zone ?>" required>
                                <small class="form-text text-muted">Ex: Douala, Akwa</small>
                            </div>
                            <div class="mb-3">
                                <label for="specialite" class="form-label">Votre Spécialité Principale</label>
                                <select class="form-select" id="specialite" name="specialite" required>
                                    <option value="">Choisissez une spécialité...</option>
                                    <option value="electricite" <?= ($current_specialite == 'electricite') ? 'selected' : '' ?>>Électricité</option>
                                    <option value="plomberie" <?= ($current_specialite == 'plomberie') ? 'selected' : '' ?>>Plomberie</option>
                                    <option value="informatique" <?= ($current_specialite == 'informatique') ? 'selected' : '' ?>>Informatique</option>
                                    <option value="electromenager" <?= ($current_specialite == 'electromenager') ? 'selected' : '' ?>>Électroménager</option>
                                    <option value="automobile" <?= ($current_specialite == 'automobile') ? 'selected' : '' ?>>Automobile</option>
                                    <option value="bricolage" <?= ($current_specialite == 'bricolage') ? 'selected' : '' ?>>Bricolage / Général</option>
                                    <option value="autres" <?= ($current_specialite == 'autres') ? 'selected' : '' ?>>Autres</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Décrivez votre expérience et vos services</label>
                                <textarea class="form-control" id="description" name="description" rows="4" minlength="50" required><?= $current_description ?></textarea>
                                <small class="form-text text-muted">Minimum 50 caractères pour une description complète.</small>
                            </div>
                            <div class="mb-4">
                                <label for="annees_experience" class="form-label">Années d'expérience</label>
                                <input type="number" class="form-control" id="annees_experience" name="annees_experience" min="0" max="50" value="<?= $current_annees_experience ?>" required>
                            </div>
                            <button type="submit" name="update_profile" class="btn btn-primary w-100"><i class="fas fa-save me-2"></i> Enregistrer les modifications</button>
                        </form>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-info text-white text-center py-3">
                        <h5 class="mb-0"><i class="fas fa-key me-2"></i> Changer votre mot de passe</h5>
                    </div>
                    <div class="card-body">
                        <form action="" method="POST" class="p-4 border rounded bg-light">
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Mot de passe actuel</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>
                            <div class="mb-3">
                                <label for="new_password" class="form-label">Nouveau mot de passe</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                <small class="form-text text-muted">Minimum 8 caractères.</small>
                            </div>
                            <div class="mb-4">
                                <label for="confirm_new_password" class="form-label">Confirmer le nouveau mot de passe</label>
                                <input type="password" class="form-control" id="confirm_new_password" name="confirm_new_password" required>
                            </div>
                            <button type="submit" name="change_password" class="btn btn-info w-100"><i class="fas fa-lock me-2"></i> Changer le mot de passe</button>
                        </form>
                    </div>
                </div>

                 <div class="card shadow-sm mb-4">
            <div class="card-header text-center py-3">
                <h5 class="mb-0"><i class="fas fa-upload me-2"></i> Télécharger une nouvelle certification</h5>
            </div>
            <div class="card-body">
                <form action="" method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="nom_certification" class="form-label">Nom de la certification :</label>
                        <input type="text" class="form-control" id="nom_certification" name="nom_certification" required>
                    </div>
                    <div class="mb-3">
                        <label for="fichier_certification" class="form-label">Fichier de certification (PDF, JPG, PNG) :</label>
                        <input type="file" class="form-control" id="fichier_certification" name="fichier_certification" accept=".pdf,.jpg,.jpeg,.png" required>
                        <div class="form-text">Taille maximale : 5MB. Formats acceptés : PDF, JPG, JPEG, PNG.</div>
                    </div>
                    <button type="submit" name="upload_certification" class="btn btn-success"><i class="fas fa-cloud-upload-alt me-2"></i> Télécharger</button>
                </form>
            </div>
        </div>

        <div class="card shadow-sm mt-5" style="margin-bottom:50px">
            <div class="card-header text-center py-3">
                <h5 class="mb-0"><i class="fas fa-list-alt me-2"></i> Mes certifications soumises</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($mes_certifications)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead>
                                <tr>
                                    <th>Certification</th>
                                    <th>Fichier</th>
                                    <th>Date d'ajout</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($mes_certifications as $cert): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($cert['nom_certification']) ?></td>
                                        <td>
                                            <?php if (!empty($cert['chemin_certification']) && file_exists($cert['chemin_certification'])): ?>
                                                <a href="<?= htmlspecialchars($cert['chemin_certification']) ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Voir le fichier">
                                                    <i class="fas fa-file-alt"></i> Voir
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">Fichier introuvable</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('d/m/Y H:i', strtotime($cert['date_ajout'])) ?></td>
                                        <td>
                                            <?php
                                                $badge_class = 'badge ';
                                                switch ($cert['statut']) {
                                                    case 'en_attente': $badge_class .= 'bg-warning text-dark badge-status-en_attente'; break;
                                                    case 'approuve': $badge_class .= 'bg-success badge-status-approuve'; break;
                                                    case 'rejete': $badge_class .= 'bg-danger badge-status-rejete'; break;
                                                    default: $badge_class .= 'bg-secondary'; break;
                                                }
                                            ?>
                                            <span class="<?= $badge_class ?>"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $cert['statut']))) ?></span>
                                        </td>
                                        <td>
                                            <?php if ($cert['statut'] === 'rejete'): ?>
                                                <form action="" method="POST" class="d-inline-block">
                                                    <input type="hidden" name="id_certification" value="<?= $cert['id_certification'] ?>">
                                                    <button type="submit" name="delete_own_certification" class="btn btn-sm btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette certification ?');" title="Supprimer">
                                                        <i class="fas fa-trash-alt"></i> Supprimer
                                                    </button>
                                                </form>
                                            <?php elseif ($cert['statut'] === 'en_attente'): ?>
                                                <span class="text-muted">En attente d'examen</span>
                                            <?php elseif ($cert['statut'] === 'approuve'): ?>
                                                <span class="text-success">Validée</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-center text-muted">Vous n'avez pas encore soumis de certifications.</p>
                <?php endif; ?>
            </div>
        </div>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-danger text-white text-center py-3">
                        <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i> Signaler un Problème à l'Administration</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-center text-muted">Si vous rencontrez un problème avec l'application ou une mission spécifique, veuillez utiliser ce formulaire pour nous le signaler.</p>
                        <form action="" method="POST" class="p-4 border rounded bg-light">
                            <div class="mb-3">
                                <label for="sujet_probleme" class="form-label">Sujet du problème</label>
                                <input type="text" class="form-control" id="sujet_probleme" name="sujet" required>
                            </div>
                            <div class="mb-3">
                                <label for="description_probleme" class="form-label">Description détaillée du problème</label>
                                <textarea class="form-control" id="description_probleme" name="description_probleme" rows="4" required></textarea>
                            </div>
                            <button type="submit" name="report_problem" class="btn btn-danger w-100"><i class="fas fa-paper-plane me-2"></i> Envoyer</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
