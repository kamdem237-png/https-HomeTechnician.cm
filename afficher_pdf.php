<?php
// Incluez votre connexion à la base de données si nécessaire pour récupérer le chemin du fichier
// Exemple simplifié, suppose que le 'chemin_fichier' est passé en paramètre URL
// Dans un cas réel, vous vérifieriez l'ID de la certification et récupéreriez le chemin depuis la DB
// pour des raisons de sécurité et pour éviter les accès directs à des fichiers arbitraires.

if (isset($_GET['fichier'])) {
    $chemin_relatif_db = $_GET['fichier']; // Exemple: 'uploads/certifications_techniciens/cert_tech_ssc1920efcad.pdf'

    // CONSTRUIRE LE CHEMIN PHYSIQUE COMPLET SUR LE SERVEUR
    // Assurez-vous que c'est le chemin réel et sécurisé vers votre dossier 'www' de Wamp
    $document_root = $_SERVER['DOCUMENT_ROOT']; // Obtient le DocumentRoot de votre Virtual Host (ex: c:/wamp64/www/projet)
    $chemin_physique_fichier = $document_root . '/' . $chemin_relatif_db;

    // Assurez-vous que le fichier existe et que le chemin est valide
    if (file_exists($chemin_physique_fichier) && is_file($chemin_physique_fichier)) {
        $nom_fichier = basename($chemin_physique_fichier); // Obtient juste le nom du fichier (ex: cert_tech_ssc1920efcad.pdf)

        // Définir les en-têtes HTTP
        header('Content-type: application/pdf');
        // C'est la ligne CRUCIALE : "inline" force la visualisation dans le navigateur si possible
        // "attachment" force le téléchargement
        header('Content-Disposition: inline; filename="' . $nom_fichier . '"');
        header('Content-Transfer-Encoding: binary');
        header('Accept-Ranges: bytes');
        @readfile($chemin_physique_fichier); // Lire et envoyer le contenu du fichier
        exit();
    } else {
        // Fichier non trouvé
        header("HTTP/1.0 404 Not Found");
        echo "Fichier PDF introuvable.";
        exit();
    }
} else {
    // Paramètre 'fichier' manquant
    header("HTTP/1.0 400 Bad Request");
    echo "Paramètre de fichier manquant.";
    exit();
}
?>
