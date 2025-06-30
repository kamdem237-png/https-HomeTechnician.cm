
<?php
session_start();

$server_name = "localhost";
$user_name = "root";
$psw = "";
$DB_name = "depanage";

$conn = new mysqli($server_name, $user_name, $psw, $DB_name);
if ($conn->connect_error) {
    die("Connexion échouée : " . $conn->connect_error);
}

$message = "";
$showChoiceModal = false;

if (isset($_POST['valider'])) {
    if (!empty($_POST['email']) && !empty($_POST['password'])) {
        $email = htmlspecialchars(trim($_POST['email']));
        $password = $_POST['password'];

        // Vérifier technicien
        $stmt = $conn->prepare("SELECT * FROM techniciens WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $resTech = $stmt->get_result();
        $tech = $resTech->fetch_assoc();
        $stmt->close();

        // Vérifier client
        $stmt = $conn->prepare("SELECT * FROM clients WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $resClient = $stmt->get_result();
        $client = $resClient->fetch_assoc();
        $stmt->close();

        $isTech = $tech && password_verify($password, $tech['password']);
        $isClient = $client && password_verify($password, $client['password']);

        if ($isTech && $isClient) {
            $_SESSION['client'] = $client;
            $_SESSION['technicien'] = $tech;
            $showChoiceModal = true;
        } elseif ($isTech) {
            $_SESSION['user'] = $tech;
            header("Location: technicien.html");
            exit;
        } elseif ($isClient) {
            $_SESSION['user'] = $client;
            header("Location: client.html");
            exit;
        } else {
            $message = "Compte introuvable ou mot de passe incorrect.";
        }
    } else {
        $message = "Veuillez remplir tous les champs.";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</head>

<body>
        <header>
        <nav class="navbar navbar-expand-lg navbar-default bg-default">
            <div class="container-fluid">
                <a class="navbar-brand" href="agence de reparation des equipements.html"><img src="mon logo 4.png" width="250px" height="50px" style="border-radius: 50px;" class="img1"></a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav">
                        <li class="nav-item"><a class="nav-link" aria-current="page" href="agence de reparation des equipements.html">Accueil</a></li>
                        <li class="nav-item"><a class="nav-link" href="À propos.html">À propos de nous</a></li>
                        <li class="nav-item"><a class="nav-link" href="Nos services.html">Nos services</a></li>
                        <li class="nav-item"><a class="nav-link" href="#">Contact</a></li>
                    </ul>
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item"><a class="nav-link active" aria-current="page" href="connexion.html"><button type="button" class="btn btn-dark"><i class="bi bi-box-arrow-right"></i> Se connecter</button></a></li>
                    </ul>
              </div>
            </div>
        </nav>
    </header>
    <section class="bg-light">
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-md-5 bg-white rounded p-4 shadow">
                    <h3 class="text-center mb-4">Se connecter</h3>
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="email" class="form-label">E-mail</label>
                            <input type="email" name="email" class="form-control" placeholder="Entrez votre e-mail" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Mot de passe</label>
                            <input type="password" name="password" class="form-control" placeholder="Mot de passe" required>
                        </div>
                        <button type="submit" name="valider" class="btn btn-success w-100">Connexion</button>
                        <?php if (!empty($message)) : ?>
                            <div class="alert alert-danger mt-3"><?php echo $message; ?></div>
                        <?php endif; ?>
                    </form>
                    <div class="mt-3 text-center">
                        <p>Vous n'avez pas de compte ? <a href="inscription.html">Inscription</a></p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- MODAL CHOIX DU ROLE -->
    <?php if ($showChoiceModal): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var myModal = new bootstrap.Modal(document.getElementById('roleChoiceModal'));
            myModal.show();
        });
    </script>

    <div class="modal fade" id="roleChoiceModal" tabindex="-1" aria-labelledby="roleChoiceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center">
                <div class="modal-header">
                    <h5 class="modal-title" id="roleChoiceModalLabel">Choisissez votre rôle</h5>
                </div>
                <div class="modal-body">
                    <p>Ce compte est associé à un client et à un technicien.</p>
                    <p>Où souhaitez-vous aller ?</p>
                    <div class="d-grid gap-2 col-8 mx-auto">
                        <a href="client.html" class="btn btn-primary">Espace Client</a>
                        <a href="technicien.html" class="btn btn-secondary">Espace Technicien</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</body>
</html>
