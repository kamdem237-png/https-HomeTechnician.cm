<?php
session_start();

// Ensure the user is supposed to be here for a password change
if (!isset($_SESSION['change_pwd_user']) || !isset($_SESSION['change_pwd_user']['role']) || !isset($_SESSION['change_pwd_user']['id'])) {
    header('Location: connexion_user.php'); // Redirect if not properly set up for password change
    exit();
}

$user_role = $_SESSION['change_pwd_user']['role'];
$user_id = $_SESSION['change_pwd_user']['id'];
$message = '';

// Database connection (copy from connexion_user.php)
$server_name = "localhost";
$user_name = "root";
$psw = "";
$DB_name = "depanage";
$conn = new mysqli($server_name, $user_name, $psw, $DB_name);

if ($conn->connect_error) {
    error_log("Erreur de connexion à la base de données : " . $conn->connect_error);
    die("Désolé, une erreur technique est survenue.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($new_password) || empty($confirm_password)) {
        $message = "Veuillez remplir tous les champs.";
    } elseif ($new_password !== $confirm_password) {
        $message = "Les mots de passe ne correspondent pas.";
    } elseif (strlen($new_password) < 6) { // Example: minimum password length
        $message = "Le nouveau mot de passe doit contenir au moins 6 caractères.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $table = '';
        $id_column = '';
        $redirect_page = '';

        if ($user_role === 'client') {
            $table = 'client';
            $id_column = 'id_client';
            $redirect_page = 'client.php';
        } elseif ($user_role === 'technicien') {
            $table = 'technicien';
            $id_column = 'id_technicien';
            $redirect_page = 'technicien.php';
        }

        if ($table) {
            // Crucial: Update the password AND set first_login to 0
            $stmt = $conn->prepare("UPDATE $table SET password = ?, first_login = 0 WHERE $id_column = ?");
            if ($stmt) {
                $stmt->bind_param("si", $hashed_password, $user_id);
                if ($stmt->execute()) {
                    // Password changed successfully, clear first_login session variable
                    unset($_SESSION['change_pwd_user']);

                    // Re-fetch user data to populate $_SESSION['user'] correctly
                    // This is important because 'first_login' is now 0 in DB
                    $select_stmt = $conn->prepare("SELECT * FROM $table WHERE $id_column = ?");
                    if ($select_stmt) {
                        $select_stmt->bind_param("i", $user_id);
                        $select_stmt->execute();
                        $_SESSION['user'] = $select_stmt->get_result()->fetch_assoc();
                        $_SESSION['role'] = $user_role; // Set the role definitively
                        $select_stmt->close();
                    }

                    header("Location: " . $redirect_page);
                    exit();
                } else {
                    $message = "Erreur lors de la mise à jour du mot de passe.";
                }
                $stmt->close();
            } else {
                error_log("Erreur de préparation de la requête de mise à jour du mot de passe : " . $conn->error);
                $message = "Une erreur interne est survenue.";
            }
        } else {
            $message = "Rôle d'utilisateur invalide.";
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Changer le mot de passe</title>
    <link rel="icon" type="image/png" href="mon_logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header text-center">
                        <h3>Changer votre mot de passe</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-danger" role="alert">
                                <?= htmlspecialchars($message) ?>
                            </div>
                        <?php endif; ?>
                        <p>C'est votre première connexion ou votre mot de passe a expiré. Veuillez le changer.</p>
                        <form method="POST">
                            <div class="mb-3">
                                <label for="new_password" class="form-label">Nouveau mot de passe</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirmer le nouveau mot de passe</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" name="change_password" class="btn btn-primary">Changer le mot de passe</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>