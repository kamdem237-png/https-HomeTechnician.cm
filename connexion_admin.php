<?php
// connexion_admin.php

session_start();

// Configuration de la base de données
$server_name = "localhost";
$user_name = "root";
$psw = ""; // Assurez-vous que c'est le bon mot de passe pour votre utilisateur 'root'
$DB_name = "depanage";

// Connexion à la base de données
$conn = new mysqli($server_name, $user_name, $psw, $DB_name);

// Vérification de la connexion
if ($conn->connect_error) {
    // Enregistrez l'erreur pour le débogage, mais ne l'affichez pas à l'utilisateur final
    error_log("Erreur de connexion à la base de données : " . $conn->connect_error);
    die("Désolé, une erreur technique est survenue. Veuillez réessayer plus tard.");
}

$message = ""; // Message d'erreur ou de succès

// Traitement du formulaire de connexion si la requête est POST
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['valider'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Vérification que les champs ne sont pas vides
    if (empty($email) || empty($password)) {
        $message = "Veuillez remplir tous les champs.";
    } else {
        // Préparation de la requête pour éviter les injections SQL
        $stmt = $conn->prepare("SELECT id_admin, nom, email, password FROM admin WHERE email = ?");
        if (!$stmt) {
            error_log("Erreur de préparation de la requête admin : " . $conn->error);
            $message = "Une erreur interne est survenue.";
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc(); // Récupère la ligne de l'utilisateur

            // Vérification du mot de passe
            // password_verify() est crucial pour les mots de passe hachés
            if ($user && password_verify($password, $user['password'])) {
                // Connexion réussie : stocke les informations importantes en session
                $_SESSION['user'] = [
                    'id_admin' => $user['id_admin'],
                    'nom' => $user['nom'],
                    'email' => $user['email']
                ];
                $_SESSION['role'] = 'admin'; // Définissez le rôle de l'utilisateur comme 'admin'
                $_SESSION['admin_logged_in'] = true; // Un simple drapeau pour la connexion admin

                // Redirection vers le tableau de bord admin
                header("Location: admin.php");
                exit(); // Toujours exit() après une redirection
            } else {
                // Email ou mot de passe incorrect
                $message = "Email ou mot de passe incorrect.";
            }
            $stmt->close();
        }
    }
}

$conn->close(); // Fermez la connexion à la base de données
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="mon_logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="c.css"> <title>Connexion Administrateur</title>
    <style>
        /* Styles CSS pour centrer le formulaire */
        body {
            background-color: #f8f9fa; /* Couleur de fond légère */
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh; /* Prend toute la hauteur de la fenêtre */
            margin: 0;
        }
        .login-container {
            max-width: 400px; /* Largeur maximale du conteneur */
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1); /* Légère ombre */
            background-color: #fff; /* Fond blanc pour le formulaire */
        }
        .form-control:focus {
            box-shadow: none; /* Enlève le shadow par default de Bootstrap au focus */
            border-color: #86b7fe; /* Change la couleur du bord au focus */
        }
    </style>
</head>

<body class="bg-light">

    <section>
        <div class="container">
            <div class="row">
                <div class="col-lg-12 m-auto">
                    <div class="login-container">
                        <h2 class="text-center mb-4">Compte Administrateur</h2>
                        <form method="POST" action="connexion_admin.php">

                            <div class="input-group mb-3">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input type="email" name="email" class="form-control" placeholder="E-mail" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                            </div>

                            <div class="input-group mb-3">
                                <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                                <input type="password" name="password" class="form-control" placeholder="Mot de passe" required>
                            </div>

                            <div class="d-grid mb-3">
                                <button type="submit" name="valider" class="btn btn-success">Se connecter</button>
                            </div>

                            <?php if (!empty($message)): ?>
                                <div class="alert alert-danger text-center" role="alert">
                                    <?= htmlspecialchars($message) ?>
                                </div>
                            <?php endif; ?>

                            <p class="text-center mt-3">
                                Vous n'avez pas de compte ? <a href="inscription_admin.php" style="color: rgb(0, 47, 255);">Inscription</a>
                            </p>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

</body>
</html>