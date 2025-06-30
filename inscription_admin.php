<?php
session_start();
$conn = new mysqli("localhost", "root", "", "depanage");
if ($conn->connect_error) die("Connexion échouée : " . $conn->connect_error);

$message = "";

if (isset($_POST['valider'])) {
    if (!empty($_POST['nom']) && !empty($_POST['email']) && !empty($_POST['password'])) {
        $nom = htmlspecialchars(trim($_POST['nom']));
        $email = htmlspecialchars(trim($_POST['email']));
        $password = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);

        // Vérifier si l'email existe déjà
        $check = $conn->prepare("SELECT id_admin FROM admin WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $message = "Cet email est déjà utilisé.";
        } else {
            $stmt = $conn->prepare("INSERT INTO admin (nom, email, password) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $nom, $email, $password);

            if ($stmt->execute()) {
                header("Location: connexion_admin.php"); // Redirige vers la connexion
                exit();
            } else {
                $message = "Erreur lors de l'inscription : " . $stmt->error;
            }
            $stmt->close();
        }
        $check->close();
    } else {
        $message = "Tous les champs sont requis.";
    }
}

$conn->close();
?>


<!DOCTYPE html>
<html>

<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="mon_logo.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="c.css">

    <title>agence de reparation des equipements</title>
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
                        <li class="nav-item"><a class="nav-link active" aria-current="page" href="connexion_user.html"><button type="button" class="btn btn-dark"><i class="bi bi-box-arrow-right"></i> Se connecter</button></a></li>
                    </ul>
              </div>
            </div>
        </nav>
    </header>
    <section class="bg-light" style="margin-top: -60px;">
        <div class="container">
            <div class="row mt-5">
                <div class="col-lg-4 bg-white m-auto rounded-top">
                    <h2 class="text-center">Inscription administrateur</h2>
                    <form method="POST" action="">

                        <div class="input-group mb-3">
                            <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                            <input type="text" name="nom" class="form-control" placeholder=" Nom">
                        </div>

                        <div class="input-group mb-3">
                            <span class="input-group-text"><i class="bi bi-envelope"></i></i></span>
                            <input type="email" name="email" class="form-control" placeholder="E-mail">
                        </div>

                        <div class="input-group mb-3">
                            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                            <input type="password" name="password" class="form-control" placeholder="Mot de passe">
                        </div>

                        <div class="d-grid">
                            <button type="submit" name="valider" id="valider" class="btn btn-success">Se connecter</button>
                            <p class="text-center">
                                <i style="color:red">
                                    <?php
                                        if (!empty($message)){
                                            echo $message; 
                                        }
                                    ?>
                                </i>
                            </p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
    <footer></footer>
</body>

</html>
