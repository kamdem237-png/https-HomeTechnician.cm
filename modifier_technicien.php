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
$technicien = null;

// Vérifier si l'ID est présent et valide
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = intval($_GET['id']);

    // Récupérer les données du technicien
    $stmt = $con->prepare("SELECT * FROM techniciens WHERE id_techniciens = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $technicien = $result->fetch_assoc();
    } else {
        $message = "technicien introuvable.";
    }
    $stmt->close();
}

// Mettre à jour les informations
if (isset($_POST['modifier'])) {
    $nom = trim($_POST['nom']);
    $num_technicien = trim($_POST['num_technicien']);
    $email = trim($_POST['email']);
    $zone = trim($_POST['zone']);
    $password = trim($_POST['password']);
    $specialite = trim($_POST['specialite']);
    $id_technicien = intval($_POST['id']);

    // Si un nouveau mot de passe est entré, le hacher, sinon garder l'ancien
    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $query = "UPDATE techniciens SET nom=?, num_techniciens=?, email=?, zone=?, password=? WHERE id_techniciens=?";
        $stmt = $con->prepare($query);
        $stmt->bind_param("sssssi", $nom, $num_technicien, $email, $zone, $hashed_password, $id_technicien);
    } else {
        $query = "UPDATE techniciens SET nom=?, num_techniciens=?, email=?, zone=?, password=? WHERE id_techniciens=?";
        $stmt = $con->prepare($query);
        $stmt->bind_param("sssssi", $nom, $num_technicien, $email, $zone, $specialite, $id_technicien);
    }

    if ($stmt->execute()) {
        header("Location: gest_technicien.php");
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
    <title>Modifier un technicien</title>
    <link rel="icon" type="image/png" href="mon_logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2 class="text-center">Modifier un technicien</h2>
    <?php if ($message): ?>
        <div class="alert alert-danger"><?= $message ?></div>
    <?php elseif ($technicien): ?>
        <form method="post">
            <input type="hidden" name="id" value="<?= $technicien['id_techniciens'] ?>">
            <div class="mb-3">
                <label for="nom" class="form-label">Nom :</label>
                <input type="text" class="form-control" name="nom" value="<?= htmlspecialchars($technicien['nom']) ?>" required>
            </div>
            <div class="mb-3">
                <label for="num_technicien" class="form-label">Numéro du technicien :</label>
                <input type="text" class="form-control" name="num_technicien" value="<?= htmlspecialchars($technicien['num_techniciens']) ?>" required>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email :</label>
                <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($technicien['email']) ?>" required>
            </div>
            <div class="mb-3">
                <label for="zone" class="form-label">Zone :</label>
                <input type="text" class="form-control" name="zone" value="<?= htmlspecialchars($technicien['zone']) ?>" required>
            </div>
            <div class="mb-3">
                <label for="specialite" class="form-label">Specialite :</label>
                <input type="text" class="form-control" name="specialite" value="<?= htmlspecialchars($technicien['specialite']) ?>" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Nouveau mot de passe (laisser vide pour garder l'ancien) :</label>
                <input type="password" class="form-control" name="password">
            </div>
            <button type="submit" name="modifier" class="btn btn-warning">Enregistrer les modifications</button>
            <a href="gest_technicien.php" class="btn btn-secondary">Annuler</a>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
