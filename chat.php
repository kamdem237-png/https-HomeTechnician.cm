<?php
session_start();

$conn = new mysqli("localhost", "root", "", "depanage");

if ($conn->connect_error) {
    error_log("Erreur de connexion à la base de données : " . $conn->connect_error);
    die("Désolé, une erreur technique est survenue. Veuillez réessayer plus tard.");
}

$id_mission = (int)($_GET['id_mission'] ?? 0);
if ($id_mission <= 0) {
    die("ID de mission invalide. Impossible d'ouvrir le chat.");
}

$user = $_SESSION['user'] ?? null;
if (!$user) {
    header("Location: connexion_user.php");
    exit();
}

$role = $_SESSION['role'] ?? '';
$user_id = 0;

if ($role === 'client') {
    $user_id = (int)$user['id_client'];
} elseif ($role === 'technicien') {
    $user_id = (int)$user['id_technicien'];
} else {
    die("Accès refusé. Rôle d'utilisateur non reconnu.");
}

// --- VÉRIFICATION D'ACCÈS ---
$authorized = false;
if ($role === 'client') {
    $stmt = $conn->prepare("SELECT 1 FROM mission WHERE id_mission = ? AND id_client = ?");
    if ($stmt) {
        $stmt->bind_param("ii", $id_mission, $user_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $authorized = true;
        }
        $stmt->close();
    } else {
        error_log("Erreur de préparation de la requête de vérification d'accès (client) : " . $conn->error);
    }
} elseif ($role === 'technicien') {
    $stmt = $conn->prepare("SELECT 1 FROM mission_technicien WHERE id_mission = ? AND id_technicien = ?");
    if ($stmt) {
        $stmt->bind_param("ii", $id_mission, $user_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $authorized = true;
        }
        $stmt->close();
    } else {
        error_log("Erreur de préparation de la requête de vérification d'accès (technicien) : " . $conn->error);
    }
}

if (!$authorized) {
    die("Accès refusé. Vous n'êtes pas autorisé à voir ce chat de mission.");
}

// --- MARQUER les MESSAGES comme LUS ---
$stmt_mark_read = null;
if ($role === 'client') {
    $stmt_mark_read = $conn->prepare("UPDATE chat SET lu = TRUE WHERE id_mission = ? AND id_receiver_client = ? AND id_technicien IS NOT NULL AND lu = FALSE");
    if ($stmt_mark_read) {
        $stmt_mark_read->bind_param("ii", $id_mission, $user_id);
        if (!$stmt_mark_read->execute()) {
            error_log("Erreur d'exécution de la requête pour marquer les messages lus (client) : " . $stmt_mark_read->error);
        }
        $stmt_mark_read->close();
    } else {
        error_log("Erreur de préparation de la requête pour marquer les messages lus (client) : " . $conn->error);
    }
} elseif ($role === 'technicien') {
    $stmt_mark_read = $conn->prepare("UPDATE chat SET lu = TRUE WHERE id_mission = ? AND id_receiver_technicien = ? AND lu = FALSE");
    if ($stmt_mark_read) {
        $stmt_mark_read->bind_param("ii", $id_mission, $user_id);
        if (!$stmt_mark_read->execute()) {
            error_log("Erreur d'exécution de la requête pour marquer les messages lus (technicien) : " . $stmt_mark_read->error);
        }
        $stmt_mark_read->close();
    } else {
        error_log("Erreur de préparation de la requête pour marquer les messages lus (technicien) : " . $conn->error);
    }
}

// --- RÉCUPÉRATION des MESSAGES ---
$sql_msgs = "";
$stmt_msgs = null;

if ($role === 'client') {
    $sql_msgs = "SELECT c.*,
                 COALESCE(t.nom, cl.nom) AS sender_nom,
                 CASE
                     WHEN c.id_technicien IS NOT NULL THEN 'technicien'
                     WHEN c.id_client IS NOT NULL THEN 'client'
                     ELSE 'inconnu'
                 END AS sender_role
                 FROM chat c
                 LEFT JOIN technicien t ON c.id_technicien = t.id_technicien
                 LEFT JOIN client cl ON c.id_client = cl.id_client
                 WHERE c.id_mission = ?
                 AND (
                     -- Messages envoyés par CE CLIENT (une seule instance par message logique)
                     (c.id_client = ? AND c.id_chat IN (
                         SELECT MIN(c_sub.id_chat)
                         FROM chat c_sub
                         WHERE c_sub.id_mission = c.id_mission
                           AND c_sub.id_client = ?   -- L'expéditeur est le client actuel
                           AND c_sub.message = c.message
                           AND c_sub.date = c.date
                         GROUP BY c_sub.id_mission, c_sub.id_client, c_sub.message, c_sub.date
                     ))
                     OR
                     -- Messages envoyés par un TECHNICIEN à CE CLIENT
                     (c.id_technicien IS NOT NULL AND c.id_receiver_client = ?)
                 )
                 ORDER BY c.date ASC";
    $stmt_msgs = $conn->prepare($sql_msgs);
    if ($stmt_msgs) {
        $stmt_msgs->bind_param("iiii", $id_mission, $user_id, $user_id, $user_id);
    }
} elseif ($role === 'technicien') {
    $sql_msgs = "SELECT c.*,
                 COALESCE(t_sender.nom, cl_sender.nom) AS sender_nom,
                 CASE
                     WHEN c.id_technicien IS NOT NULL THEN 'technicien'
                     WHEN c.id_client IS NOT NULL THEN 'client'
                     ELSE 'inconnu'
                 END AS sender_role
                 FROM chat c
                 LEFT JOIN technicien t_sender ON c.id_technicien = t_sender.id_technicien
                 LEFT JOIN client cl_sender ON c.id_client = cl_sender.id_client
                 WHERE c.id_mission = ?
                 AND (
                     -- Messages que CE TECHNICIEN a ENVOYÉS (une seule instance par message logique)
                     (c.id_technicien = ? AND c.id_chat IN (
                         SELECT MIN(c_sub.id_chat)
                         FROM chat c_sub
                         WHERE c_sub.id_mission = c.id_mission
                           AND c_sub.id_technicien = ?
                           AND c_sub.message = c.message
                           AND c_sub.date = c.date
                         GROUP BY c_sub.id_mission, c_sub.id_technicien, c_sub.message, c_sub.date
                     ))
                     OR
                     -- Messages que CE TECHNICIEN a REÇUS du CLIENT
                     (c.id_client IS NOT NULL AND c.id_receiver_technicien = ?)
                     OR
                     -- Messages que CE TECHNICIEN a REÇUS d'un AUTRE TECHNICIEN
                     (c.id_technicien IS NOT NULL AND c.id_technicien != ? AND c.id_receiver_technicien = ?)
                 )
                 ORDER BY c.date ASC";
    $stmt_msgs = $conn->prepare($sql_msgs);
    if ($stmt_msgs) {
        $stmt_msgs->bind_param("iiiiii", $id_mission, $user_id, $user_id, $user_id, $user_id, $user_id);
    }
}

$messages = [];
if ($stmt_msgs) {
    if (!$stmt_msgs->execute()) {
        error_log("Erreur d'exécution de la requête de messages : " . $stmt_msgs->error);
    } else {
        $result_msgs = $stmt_msgs->get_result();
        while ($row = $result_msgs->fetch_assoc()) {
            $messages[] = $row;
        }
    }
    $stmt_msgs->close();
} else {
    error_log("Erreur de préparation de la requête de messages : " . $conn->error);
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Mission</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8ff9fa; }
        .chat-container { max-width: 800px; margin: 30px auto; background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .messages-box { max-height: 400px; overflow-y: auto; border: 1px solid #dee2e6; padding: 15px; border-radius: 5px; background-color: #e9ecef; }
        .message { margin-bottom: 10px; padding: 8px 12px; border-radius: 15px; max-width: 70%; position: relative; }
        .message.sent { background-color: #007bff; color: #fff; margin-left: auto; text-align: right; }
        .message.received { background-color: #6c757d; color: #fff; margin-right: auto; text-align: left; }
        .message-sender { font-size: 0.8em; font-weight: bold; margin-bottom: 3px; }
        .message-text { word-wrap: break-word; }
        .message-info { font-size: 0.7em; opacity: 0.8; margin-top: 5px; }
        .message.sent .message-info { text-align: right; }
        .message.received .message-info { text-align: left; }
        .message-form .input-group { align-items: center; }
        .message-form textarea { resize: none; }
    </style>
</head>
<body>
<div class="container chat-container">
    <h2 class="mb-4 text-center">Chat Mission #<?= htmlspecialchars($id_mission) ?></h2>

    <div class="messages-box">
        <?php if (!empty($messages)): ?>
            <?php foreach ($messages as $msg): ?>
                <?php
                $is_sent = false;
                if ($role === 'client' && $msg['id_client'] == $user_id) {
                    $is_sent = true;
                } elseif ($role === 'technicien' && $msg['id_technicien'] == $user_id) {
                    $is_sent = true;
                }
                ?>
                <div class="message <?= $is_sent ? 'sent' : 'received' ?>">
                    <div class="message-sender">
                        <?= htmlspecialchars($msg['sender_nom']) ?> (<?= htmlspecialchars($msg['sender_role']) ?>)
                    </div>
                    <div class="message-text">
                        <?= nl2br(htmlspecialchars($msg['message'])) ?>
                    </div>
                    <div class="message-info">
                        <?= date('d/m/Y H:i', strtotime($msg['date'])) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-center text-muted">Soyez le premier à envoyer un message pour cette mission !</p>
        <?php endif; ?>
    </div>

    <form method="POST" action="enregistrer_message.php" class="mt-3 message-form">
        <input type="hidden" name="id_mission" value="<?= htmlspecialchars($id_mission) ?>">
        <div class="input-group">
            <textarea name="message" class="form-control" rows="2" required placeholder="Écrire un message..." aria-label="Écrire un message"></textarea>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-send-fill"></i> Envoyer
            </button>
        </div>
    </form>

    <div class="text-center mt-4">
        <a href="<?= ($role === 'technicien') ? 'missions_disponibles.php' : 'missions_clientes.php' ?>" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Retour aux missions
        </a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>