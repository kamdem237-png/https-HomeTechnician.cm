<?php
$server_name = "localhost";
$user_name = "root";
$psw = "";
$DB_name = "depanage";
$con = new mysqli($server_name, $user_name, $psw, $DB_name);

if ($con->connect_error) {
    die("Connexion échouée : " . $con->connect_error);
}

$message = "";
$client = null;

// Vérifier si l'ID est présent et valide
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = intval($_GET['id']);

    // Récupérer les données du client
    $stmt = $con->prepare("SELECT * FROM client WHERE id_client = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $client = $result->fetch_assoc();
    } else {
        $message = "Client introuvable.";
    }
    $stmt->close();
}

// Mettre à jour les informations
if (isset($_POST['modifier'])) {
    $nom = trim($_POST['nom']);
    $num_client = trim($_POST['num_client']);
    $email = trim($_POST['email']);
    $zone = trim($_POST['zone']);
    $password = trim($_POST['password']);
    $id_client = intval($_POST['id']);

    // Si un nouveau mot de passe est entré, le hacher, sinon garder l'ancien
    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $query = "UPDATE client SET nom=?, num_client=?, email=?, zone=?, password=? WHERE id_client=?";
        $stmt = $con->prepare($query);
        $stmt->bind_param("sssssi", $nom, $num_client, $email, $zone, $hashed_password, $id_client);
    } else {
        $query = "UPDATE client SET nom=?, num_client=?, email=?, zone=? WHERE id_client=?";
        $stmt = $con->prepare($query);
        $stmt->bind_param("ssssi", $nom, $num_client, $email, $zone, $id_client);
    }

    if ($stmt->execute()) {
        header("Location: gest_client.php");
        exit();
    } else {
        $message = "Erreur lors de la modification : " . $stmt->error;
    }

    $stmt->close();
}

$con->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Modifier un client</title>
    <link rel="icon" type="image/png" href="mon_logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2 class="text-center">Modifier un client</h2>
    <?php if ($message): ?>
        <div class="alert alert-danger"><?= $message ?></div>
    <?php elseif ($client): ?>
        <form method="post">
            <input type="hidden" name="id" value="<?= $client['id_client'] ?>">
            <div class="mb-3">
                <label for="nom" class="form-label">Nom :</label>
                <input type="text" class="form-control" name="nom" value="<?= htmlspecialchars($client['nom']) ?>" required>
            </div>
            <div class="mb-3">
                <label for="num_client" class="form-label">Numéro du client :</label>
                <input type="text" class="form-control" name="num_client" value="<?= htmlspecialchars($client['num_client']) ?>" required>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email :</label>
                <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($client['email']) ?>" required>
            </div>
            <div class="mb-3">
                <label for="zone" class="form-label">Zone :</label>
                <input type="text" class="form-control" name="zone" value="<?= htmlspecialchars($client['zone']) ?>" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Nouveau mot de passe (laisser vide pour garder l'ancien) :</label>
                <input type="password" class="form-control" name="password">
            </div>
            <button type="submit" name="modifier" class="btn btn-warning">Enregistrer les modifications</button>
            <a href="gest_client.php" class="btn btn-secondary">Annuler</a>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
