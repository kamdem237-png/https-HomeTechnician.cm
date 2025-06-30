<?php
session_start();

// Ensure the temporary user data for dual roles is present
// These should have been set in connexion_user.php
if (!isset($_SESSION['client_data']) || !isset($_SESSION['technicien_data'])) {
    // If not, redirect them back to the login page
    header('Location: connexion_user.php');
    exit();
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_role'])) {
    $selected_role = $_POST['role_selection'] ?? '';

    // Important: Determine which data to use based on the selected role
    $user_data_for_session = null;
    $redirect_to = '';

    if ($selected_role === 'client') {
        $user_data_for_session = $_SESSION['client_data'];
        $redirect_to = 'client.php';
    } elseif ($selected_role === 'technicien') {
        $user_data_for_session = $_SESSION['technicien_data'];
        $redirect_to = 'technicien.php';
    } else {
        $message = "Veuillez sélectionner un rôle valide.";
    }

    if ($user_data_for_session) {
        // Set the definitive session variables
        $_SESSION['user'] = $user_data_for_session;
        $_SESSION['role'] = $selected_role;

        // Clean up the temporary session variables
        unset($_SESSION['client_data']);
        unset($_SESSION['technicien_data']);

        // Redirect to the appropriate dashboard
        header('Location: ' . $redirect_to);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choisir votre rôle</title>
    <link rel="icon" type="image/png" href="mon_logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header text-center">
                        <h3>Choisir votre rôle</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-danger" role="alert">
                                <?= htmlspecialchars($message) ?>
                            </div>
                        <?php endif; ?>
                        <p class="text-center">Il semble que votre compte soit associé à plusieurs rôles. Veuillez choisir comment vous souhaitez vous connecter :</p>
                        <form method="POST">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="role_selection" id="roleClient" value="client" required>
                                    <label class="form-check-label" for="roleClient">
                                        Se connecter en tant que Client
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="role_selection" id="roleTechnicien" value="technicien" required>
                                    <label class="form-check-label" for="roleTechnicien">
                                        Se connecter en tant que Technicien
                                    </label>
                                </div>
                            </div>
                            <div class="d-grid">
                                <button type="submit" name="select_role" class="btn btn-primary">Continuer</button>
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