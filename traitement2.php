<?php
session_start(); // Assurez-vous que la session est démarrée au tout début

$server_name = "localhost";
$user_name = "root";
$psw = "";
$DB_name = "depanage";

$conn = new mysqli($server_name, $user_name, $psw, $DB_name);

if ($conn->connect_error) {
    die("Échec de la connexion : " . $conn->connect_error);
}

// Récupérer les critères de recherche
$zone = $_POST['zone'] ?? '';
$specialite = $_POST['specialty'] ?? '';

// ************ NOUVEAU : Stocker les critères de recherche dans la session ************
$_SESSION['last_search_zone'] = $zone;
$_SESSION['last_search_specialty'] = $specialite;
// ***********************************************************************************

$sql = "SELECT * FROM technicien
        WHERE LOWER(TRIM(zone)) = LOWER(TRIM(?))
        AND LOWER(TRIM(specialite)) = LOWER(TRIM(?))";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $zone, $specialite);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Techniciens disponibles</title>
    <link rel="icon" type="image/png" href="mon_logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body>
<div class="container mt-5">
    <h2 class="mb-4">Techniciens disponibles</h2>

    <?php if ($result && $result->num_rows > 0): ?>
        <div class="row">
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($row['prenom']) ?> <?= htmlspecialchars($row['nom']) ?></h5>
                            <p class="card-text">
                                <strong>Téléphone :</strong> <?= htmlspecialchars($row['num_technicien']) ?><br>
                                <strong>Email :</strong> <?= htmlspecialchars($row['email']) ?><br>
                                <strong>Zone :</strong> <?= htmlspecialchars($row['zone']) ?><br>
                                <strong>Spécialité :</strong> <?= htmlspecialchars($row['specialite']) ?>
                            </p>
                            <form action="technicien_details.php" method="GET">
                                <input type="hidden" name="id_technicien" value="<?= (int)$row['id_technicien'] ?>">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle"></i> Sélectionner ce technicien
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
        <form action="traitement2.php" method="POST" class="d-inline">
            <input type="hidden" name="zone" value="<?= htmlspecialchars($zone) ?>">
            <input type="hidden" name="specialty" value="<?= htmlspecialchars($specialite) ?>">
            <button type="submit" class="btn btn-secondary mt-3">Retour</button>
        </form>
        <?php else: ?>
        <div class="alert alert-warning mt-4" role="alert">
            Aucun technicien trouvé pour la zone <strong><?= htmlspecialchars($zone) ?></strong> et la spécialité <strong><?= htmlspecialchars($specialite) ?></strong>.
        </div>
        <center>
            <a href="soumettre_probleme.php" class="btn btn-primary mt-3">Trouver une autre solution</a>
            <form action="traitement2.php" method="POST" class="d-inline">
                <input type="hidden" name="zone" value="<?= htmlspecialchars($zone) ?>">
                <input type="hidden" name="specialty" value="<?= htmlspecialchars($specialite) ?>">
                <button type="submit" class="btn btn-secondary mt-3">Retour à la recherche</button>
            </form>
            </center>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php $conn->close(); ?>