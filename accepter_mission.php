<?php
session_start();
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

$server_name = "localhost";
$user_name = "root";
$psw = "";
$DB_name = "depanage";

$con = new mysqli($server_name, $user_name, $psw, $DB_name);
if ($con->connect_error) {
    die("Connexion échouée : " . $con->connect_error);
}

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'technicien') {
    header("Location: connexion_user.php");
    exit();
}

// Initialisation de $message pour éviter les erreurs si aucune action n'est effectuée
$message = "";

if (isset($_POST['id_mission'])) {
    $id_technicien = (int)$_SESSION['user']['id_technicien'];
    $id_mission = (int)$_POST['id_mission'];

    if ($id_mission <= 0) {
        $_SESSION['error_message'] = "ID mission invalide.";
        header("Location: missions_disponibles.php"); // Rediriger vers la liste
        exit();
    } else {
        // Démarrez une transaction pour garantir l'atomicité des opérations
        $con->begin_transaction();

        try {
            // 1. Vérifier si le technicien est déjà affecté à cette mission
            $stmtVerif = $con->prepare("SELECT 1 FROM mission_technicien WHERE id_mission = ? AND id_technicien = ?");
            $stmtVerif->bind_param("ii", $id_mission, $id_technicien);
            $stmtVerif->execute();
            $stmtVerif->store_result();

            if ($stmtVerif->num_rows > 0) {
                $_SESSION['error_message'] = "Vous êtes déjà affecté à cette mission.";
                $con->rollback(); // Annuler la transaction
                header("Location: detail_mission.php?id_mission=" . $id_mission); // Rediriger vers les détails
                exit();
            }
            $stmtVerif->close();

            // 2. Récupérer l'état actuel de la mission avant l'insertion pour des vérifications
            $stmtCurrentMission = $con->prepare("SELECT statut, nb_techniciens_demande FROM mission WHERE id_mission = ? FOR UPDATE"); // Verrouiller la ligne
            $stmtCurrentMission->bind_param("i", $id_mission);
            $stmtCurrentMission->execute();
            $resultCurrentMission = $stmtCurrentMission->get_result();
            $current_mission_data = $resultCurrentMission->fetch_assoc();
            $stmtCurrentMission->close();

            if (!$current_mission_data) {
                $_SESSION['error_message'] = "Mission introuvable.";
                $con->rollback();
                header("Location: missions_disponibles.php");
                exit();
            }

            // Compter les techniciens déjà affectés pour cette mission
            $stmtCountAffected = $con->prepare("SELECT COUNT(*) FROM mission_technicien WHERE id_mission = ?");
            $stmtCountAffected->bind_param("i", $id_mission);
            $stmtCountAffected->execute();
            $stmtCountAffected->bind_result($already_affected_count);
            $stmtCountAffected->fetch();
            $stmtCountAffected->close();

            // Vérifier si la mission est toujours 'en_attente' et si le nombre max de techniciens n'est pas atteint
            if ($current_mission_data['statut'] !== 'en_attente' && $current_mission_data['statut'] !== 'en_cours') { // Permet de prendre si 'en_cours' mais pas complet
                $_SESSION['error_message'] = "Impossible de prendre cette mission. Son statut n'est pas 'en attente' ou 'en cours'.";
                $con->rollback();
                header("Location: detail_mission.php?id_mission=" . $id_mission);
                exit();
            }

            if ($already_affected_count >= $current_mission_data['nb_techniciens_demande']) {
                $_SESSION['error_message'] = "Impossible de prendre cette mission. Le nombre maximal de techniciens a déjà été atteint.";
                $con->rollback();
                header("Location: detail_mission.php?id_mission=" . $id_mission);
                exit();
            }


            // 3. Insérer l'affectation du technicien
            $stmtInsert = $con->prepare("INSERT INTO mission_technicien (id_mission, id_technicien) VALUES (?, ?)");
            $stmtInsert->bind_param("ii", $id_mission, $id_technicien);
            if (!$stmtInsert->execute()) {
                throw new Exception("Erreur lors de l'affectation à la mission : " . $stmtInsert->error);
            }
            $stmtInsert->close();

            // 4. Mettre à jour le statut de la mission si le nombre de techniciens demandés est atteint
            $updateSql = "
                UPDATE mission m
                SET m.statut = 'en_cours', m.date_debut_mission = NOW()
                WHERE m.id_mission = ?
                AND (
                    SELECT COUNT(*) FROM mission_technicien mt WHERE mt.id_mission = m.id_mission
                ) >= m.nb_techniciens_demande
            ";
            $stmtUpdate = $con->prepare($updateSql);
            $stmtUpdate->bind_param("i", $id_mission);
            if (!$stmtUpdate->execute()) {
                throw new Exception("Erreur lors de la mise à jour du statut de la mission : " . $stmtUpdate->error);
            }
            $stmtUpdate->close();

            // 5. Récupérer les informations pour l'e-mail
            $sqlMail = "SELECT m.description, m.localisation, c.email AS client_email,
                                t.nom AS nom, t.email AS email
                                FROM mission m
                                JOIN client c ON m.id_client = c.id_client
                                JOIN technicien t ON t.id_technicien = ?
                                WHERE m.id_mission = ?";
            $stmtMail = $con->prepare($sqlMail);
            $stmtMail->bind_param("ii", $id_technicien, $id_mission);
            $stmtMail->execute();
            $resultMail = $stmtMail->get_result();
            $mission_mail_data = $resultMail->fetch_assoc();
            $stmtMail->close();

            if ($mission_mail_data) {
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host = 'sandbox.smtp.mailtrap.io';
                    $mail->SMTPAuth = true;
                    $mail->Username = '4c3235ea25bf72'; // Remplace par ton Mailtrap Username
                    $mail->Password = 'd8c84603dd3ae8'; // Remplace par ton Mailtrap Password
                    $mail->Port = 587;
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

                    $mail->SMTPOptions = [
                        'ssl' => [
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                            'allow_self_signed' => true
                        ]
                    ];

                    $mail->setFrom('noreply@depannage.com', 'Service Dépannage');
                    $mail->addAddress($mission_mail_data['client_email']);

                    $mail->isHTML(true);
                    $mail->CharSet = 'UTF-8';
                    $mail->Subject = 'Un technicien a accepté votre mission !';
                    $mail->Body = '
                        <html>
                        <head>
                            <meta charset="UTF-8">
                            <title>Mission Acceptée</title>
                        </head>
                        <body>
                            <h4>Bonjour,</h4>
                            <p>Un technicien a accepté votre mission :</p>
                            <ul>
                                <li><strong>Description :</strong> ' . htmlspecialchars($mission_mail_data['description']) . '</li>
                                <li><strong>Localisation :</strong> ' . htmlspecialchars($mission_mail_data['localisation']) . '</li>
                                <li><strong>Technicien :</strong> ' . htmlspecialchars($mission_mail_data['nom']) . '</li>
                                <li><strong>Email du technicien :</strong> ' . htmlspecialchars($mission_mail_data['email']) . '</li>
                            </ul>
                            <p>Votre mission est désormais <strong>en cours</strong> si le nombre de techniciens est atteint.</p>
                        </body>
                        </html>
                    ';
                    $mail->AltBody = "Un technicien a accepté votre mission : {$mission_mail_data['description']} à {$mission_mail_data['localisation']}.\nTechnicien: {$mission_mail_data['nom']} ({$mission_mail_data['email']})";

                    $mail->send();
                } catch (Exception $e) {
                    // Ne pas bloquer le processus si l'e-mail échoue, juste logguer l'erreur
                    error_log("Erreur lors de l'envoi de l'e-mail pour mission {$id_mission}: " . $mail->ErrorInfo);
                    // Vous pouvez stocker ce message dans une session si vous voulez l'afficher
                    $_SESSION['error_message'] = "Mission prise en charge, mais l'envoi de l'e-mail au client a échoué.";
                }
            } else {
                $_SESSION['error_message'] = "Mission prise en charge, mais les détails pour l'e-mail sont introuvables.";
            }

            $con->commit(); // Confirmer la transaction
            $_SESSION['success_message'] = "Mission prise en charge avec succès ! Vous pouvez la retrouver dans 'Mes missions'.";
            header("Location: missions_disponibles.php"); // Rediriger le technicien vers "Mes missions"
            exit();

        } catch (Exception $e) {
            $con->rollback(); // Annuler la transaction en cas d'erreur
            error_log("Erreur critique lors de la prise en charge de la mission: " . $e->getMessage());
            $_SESSION['error_message'] = "Une erreur est survenue lors de la prise en charge de la mission. Veuillez réessayer.";
            header("Location: details_mission.php?id_mission=" . $id_mission); // Retourner aux détails avec l'erreur
            exit();
        }
    }
} else {
    $_SESSION['error_message'] = "ID mission manquant pour l'acceptation.";
    header("Location: missions_disponibles.php"); // Rediriger si pas d'ID de mission
    exit();
}

$con->close();
?>