<?php
session_start(); // DOIT ÊTRE LA PREMIÈRE LIGNE

// Vérification de la session administrateur


$conn = new mysqli("localhost", "root", "", "depanage");
if ($conn->connect_error) {
    die("Erreur de connexion : " . $conn->connect_error);
}

$id_mission = (int)($_GET['id_mission'] ?? 0);

if ($id_mission <= 0) {
    die("ID de mission invalide. Veuillez sélectionner une mission depuis la page de gestion.");
}

// Récupérer les détails de la mission
$mission_details = null;
$stmt_mission = $conn->prepare("SELECT description, localisation FROM mission WHERE id_mission = ?");
if ($stmt_mission) {
    $stmt_mission->bind_param("i", $id_mission);
    $stmt_mission->execute();
    $result_mission = $stmt_mission->get_result();
    if ($result_mission->num_rows > 0) {
        $mission_details = $result_mission->fetch_assoc();
    }
    $stmt_mission->close();
}

// Récupérer tous les messages pour cette mission, en joignant les noms des expéditeurs
$messages = [];
$sql_msgs = "SELECT c.*,
             COALESCE(t.nom, cl.nom) AS sender_nom,
             CASE
                 WHEN c.id_technicien IS NOT NULL THEN 'Technicien'
                 WHEN c.id_client IS NOT NULL THEN 'Client'
                 ELSE 'Inconnu'
             END AS sender_type
             FROM chat c
             LEFT JOIN technicien t ON c.id_technicien = t.id_technicien
             LEFT JOIN client cl ON c.id_client = cl.id_client
             WHERE c.id_mission = ?
             ORDER BY c.date ASC"; // Assurez-vous que 'date' est le nom correct de votre colonne de temps

$stmt_msgs = $conn->prepare($sql_msgs);
if (!$stmt_msgs) {
    die("Erreur de préparation des messages : " . $conn->error);
}
$stmt_msgs->bind_param("i", $id_mission);
$stmt_msgs->execute();
$result_msgs = $stmt_msgs->get_result();

if ($result_msgs) {
    while ($row = $result_msgs->fetch_assoc()) {
        $messages[] = $row;
    }
}
$stmt_msgs->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Administration - Messagerie Mission #<?= htmlspecialchars($id_mission) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="mon_logo.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .container {
            max-width: 900px;
            margin-top: 50px;
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        #chat-box {
            height: 500px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
            background-color: #e9ecef;
        }
        .message-bubble {
            margin-bottom: 15px;
            padding: 10px 15px;
            border-radius: 18px;
            max-width: 85%; /* Plus large pour l'admin */
            word-wrap: break-word;
            clear: both;
        }
        .message-bubble.client-message {
            background-color: #d1e7dd; /* Vert clair pour client */
            color: #212529; /* Texte sombre */
            float: left;
            border-bottom-left-radius: 2px;
        }
        .message-bubble.technicien-message {
            background-color: #f8d7da; /* Rouge clair pour technicien */
            color: #212529; /* Texte sombre */
            float: right;
            border-bottom-right-radius: 2px;
        }
        .message-info {
            font-size: 0.75em;
            margin-top: 5px;
            color: #6c757d;
        }
        .message-bubble .sender-name {
            font-weight: bold;
            margin-bottom: 3px;
        }
    </style>
</head>
<body>
    <header>
 <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container-fluid">
                <a class="navbar-brand" href="admin.php">Tableau de Bord Admin</a>
                <div class="collapse navbar-collapse">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        <li class="nav-item"><a class="nav-link" href="admin.php">Accueil Admin</a></li>
                        <li class="nav-item"><a class="nav-link" aria-current="page" href="gest_technicien.php">Gestion Techniciens</a></li>
                        <li class="nav-item"><a propos class="nav-link" href="gest_client.php">Gestion Clients</a></li>
                        <li class="nav-item"><a class="nav-link" href="gest_annonce.php">Gestion Annonces </a></li>
                        <li class="nav-item"><a class="nav-link" href="admin_message.php">Gestion Messages</a></li>
                    </ul>
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php"><button class="btn btn-outline-light">Déconnexion</button></a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <div class="container">
        <h2 class="mb-4 text-center">Messagerie Mission #<?= htmlspecialchars($id_mission) ?></h2>
        <?php if ($mission_details): ?>
            <p class="text-muted text-center">Description: **<?= htmlspecialchars($mission_details['description']) ?>** | Localisation: **<?= htmlspecialchars($mission_details['localisation']) ?>**</p>
            <hr>
        <?php endif; ?>

        <div id="chat-box">
            <?php if (!empty($messages)): ?>
                <?php foreach ($messages as $msg): ?>
                    <?php
                    $message_class = '';
                    if ($msg['sender_type'] === 'Client') {
                        $message_class = 'client-message';
                    } elseif ($msg['sender_type'] === 'Technicien') {
                        $message_class = 'technicien-message';
                    }
                    ?>
                    <div class="message-bubble <?= $message_class ?>">
                        <div class="sender-name">
                            <?= htmlspecialchars($msg['sender_nom']) ?> (<?= htmlspecialchars($msg['sender_type']) ?>)
                        </div>
                        <p class="mb-0"><?= nl2br(htmlspecialchars($msg['message'])) ?></p>
                        <div class="message-info">
                            <?= date('d/m/Y H:i', strtotime($msg['date'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-center text-muted">Aucun message pour cette mission.</p>
            <?php endif; ?>
        </div>

        <div class="text-center mt-4">
            <a href="admin_message.php" class="btn btn-secondary btn-lg">
                <i class="bi bi-arrow-left"></i> Retour à la Liste des Missions
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Faire défiler la boîte de chat vers le bas au chargement
        var chatBox = document.getElementById("chat-box");
        chatBox.scrollTop = chatBox.scrollHeight;
    </script>
</body>
</html>