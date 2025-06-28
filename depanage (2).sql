-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : ven. 27 juin 2025 à 15:38
-- Version du serveur : 9.1.0
-- Version de PHP : 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `depanage`
--

-- --------------------------------------------------------

--
-- Structure de la table `action_logs`
--

DROP TABLE IF EXISTS `action_logs`;
CREATE TABLE IF NOT EXISTS `action_logs` (
  `id_action_logs` int NOT NULL AUTO_INCREMENT,
  `action_time` datetime DEFAULT CURRENT_TIMESTAMP,
  `action_type` varchar(50) NOT NULL,
  `target_role` varchar(50) DEFAULT NULL,
  `target_id` int DEFAULT NULL,
  `target_identifier` varchar(255) DEFAULT NULL,
  `admin_id` int DEFAULT NULL,
  `description` text,
  PRIMARY KEY (`id_action_logs`),
  KEY `target_id` (`target_id`),
  KEY `target_role` (`target_role`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `action_logs`
--

INSERT INTO `action_logs` (`id_action_logs`, `action_time`, `action_type`, `target_role`, `target_id`, `target_identifier`, `admin_id`, `description`) VALUES
(1, '2025-06-25 14:02:42', 'Bannissement Technicien', 'technicien', 6, '0', NULL, 'Banni par l\'administrateur (ID: N/A)'),
(2, '2025-06-25 14:06:29', 'Réactivation Technicien', 'technicien', 6, '0', NULL, 'Réactivé et débanni par l\'administrateur (ID: N/A)'),
(3, '2025-06-25 14:07:59', 'Bannissement Technicien', 'technicien', 6, '0', NULL, 'Banni par l\'administrateur (ID: N/A)'),
(4, '2025-06-25 14:08:02', 'Réactivation Technicien', 'technicien', 6, '0', NULL, 'Réactivé et débanni par l\'administrateur (ID: N/A)'),
(5, '2025-06-25 14:08:17', 'Bannissement Client', 'client', 6, '0', NULL, 'Statut désactivé et banni par l\'administrateur (ID: N/A)'),
(6, '2025-06-25 14:08:54', 'Débannissement Client', 'client', 6, '0', NULL, 'Statut réactivé (débanni) par l\'administrateur (ID: N/A)');

-- --------------------------------------------------------

--
-- Structure de la table `admin`
--

DROP TABLE IF EXISTS `admin`;
CREATE TABLE IF NOT EXISTS `admin` (
  `id_admin` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_admin`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `admin`
--

INSERT INTO `admin` (`id_admin`, `password`, `nom`, `email`, `created_at`) VALUES
(5, '$2y$10$SC2gByVo0AP/NobcfCSrVeSEK06Ilaq1o4zTwF55tp/7EO.v0RgBi', 'kamdem wabo andrel dela werta', 'andrelkamdem5@gmail.com', '2025-06-10 13:03:03');

-- --------------------------------------------------------

--
-- Structure de la table `annonces`
--

DROP TABLE IF EXISTS `annonces`;
CREATE TABLE IF NOT EXISTS `annonces` (
  `id_annonce` int NOT NULL AUTO_INCREMENT,
  `titre` varchar(255) NOT NULL,
  `contenu` text NOT NULL,
  `date_publication` datetime DEFAULT CURRENT_TIMESTAMP,
  `visible_client` tinyint(1) DEFAULT '1',
  `visible_technicien` tinyint(1) DEFAULT '1',
  `visible_admin` tinyint(1) DEFAULT '1',
  `statut_actif` tinyint(1) DEFAULT '1',
  `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
  `date_expiration` datetime DEFAULT NULL,
  PRIMARY KEY (`id_annonce`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `annonces`
--

INSERT INTO `annonces` (`id_annonce`, `titre`, `contenu`, `date_publication`, `visible_client`, `visible_technicien`, `visible_admin`, `statut_actif`, `date_creation`, `date_expiration`) VALUES
(2, 'BIENVENUE AUX NOUVEAUX', 'faites comme chez vous!', '2025-06-24 10:53:14', 1, 1, 1, 1, '2025-06-24 10:53:14', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `certifications`
--

DROP TABLE IF EXISTS `certifications`;
CREATE TABLE IF NOT EXISTS `certifications` (
  `id_certification` int NOT NULL AUTO_INCREMENT,
  `id_technicien` int NOT NULL,
  `nom_certification` varchar(255) NOT NULL,
  `chemin_certification` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_ajout` datetime DEFAULT CURRENT_TIMESTAMP,
  `statut` enum('en_attente','approuve','rejete') DEFAULT 'en_attente',
  PRIMARY KEY (`id_certification`),
  KEY `id_technicien` (`id_technicien`)
) ENGINE=MyISAM AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `certifications`
--

INSERT INTO `certifications` (`id_certification`, `id_technicien`, `nom_certification`, `chemin_certification`, `date_ajout`, `statut`) VALUES
(4, 6, 'maintenance des ordinateurs', 'uploads/certifications_techniciens/cert_tech_685c2741e53af.pdf', '2025-06-25 17:43:45', 'approuve'),
(17, 6, 'fichier', 'uploads/certifications/685d0b9542799_Cours Architechture des ordinateurs chapitre 1.pdf', '2025-06-26 09:57:57', 'en_attente');

-- --------------------------------------------------------

--
-- Structure de la table `chat`
--

DROP TABLE IF EXISTS `chat`;
CREATE TABLE IF NOT EXISTS `chat` (
  `id_chat` int NOT NULL AUTO_INCREMENT,
  `id_mission` int NOT NULL,
  `id_technicien` int DEFAULT NULL,
  `id_client` int DEFAULT NULL,
  `message` text NOT NULL,
  `date` datetime NOT NULL,
  `lu` tinyint(1) DEFAULT '0',
  `id_receiver_technicien` int DEFAULT NULL,
  `id_receiver_client` int DEFAULT NULL,
  PRIMARY KEY (`id_chat`),
  KEY `id_mission` (`id_mission`),
  KEY `id_technicien` (`id_technicien`),
  KEY `id_client` (`id_client`)
) ENGINE=MyISAM AUTO_INCREMENT=62 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `chat`
--

INSERT INTO `chat` (`id_chat`, `id_mission`, `id_technicien`, `id_client`, `message`, `date`, `lu`, `id_receiver_technicien`, `id_receiver_client`) VALUES
(1, 19, NULL, 2, 'salut', '2025-06-10 11:04:28', 1, NULL, NULL),
(2, 19, 2, NULL, 'salut a vous!', '2025-06-10 11:05:11', 1, NULL, NULL),
(3, 19, NULL, 2, 'comment vous allez?', '2025-06-10 11:12:31', 1, NULL, NULL),
(4, 19, 2, NULL, 'bien merci et vous?', '2025-06-10 11:18:28', 1, NULL, NULL),
(5, 19, NULL, 2, 'ca va', '2025-06-10 12:15:50', 1, NULL, NULL),
(6, 19, 2, NULL, 'bien a ce propos...', '2025-06-10 12:16:33', 1, NULL, NULL),
(7, 19, 2, NULL, 'decrivez moi votre probleme que je puisse analyser la panne', '2025-06-10 12:17:05', 1, NULL, NULL),
(8, 19, 2, NULL, 'et en ce qui concerne les prix, on en discutera', '2025-06-10 12:17:29', 1, NULL, NULL),
(9, 20, 2, NULL, 'bonjour Mr', '2025-06-10 12:24:59', 1, NULL, NULL),
(10, 20, NULL, 2, 'salut comment vous allez', '2025-06-10 12:26:26', 1, NULL, NULL),
(11, 20, 2, NULL, 'd\'accord', '2025-06-10 12:50:35', 1, NULL, NULL),
(12, 20, NULL, 2, 'a ce propos...', '2025-06-10 13:42:18', 1, NULL, NULL),
(13, 20, NULL, 2, '🙂', '2025-06-10 13:43:00', 1, NULL, NULL),
(14, 23, 2, NULL, 'hello', '2025-06-10 14:11:20', 1, NULL, NULL),
(15, 24, NULL, 2, 'salut', '2025-06-10 14:24:12', 1, NULL, NULL),
(16, 26, 2, NULL, 'heo', '2025-06-10 14:29:03', 1, NULL, NULL),
(17, 23, 2, NULL, 'salut', '2025-06-10 18:14:29', 1, NULL, NULL),
(18, 26, NULL, 2, 'salut', '2025-06-10 18:15:00', 1, NULL, NULL),
(19, 23, 2, NULL, 'salut Mr', '2025-06-11 12:49:52', 1, NULL, NULL),
(20, 23, NULL, 2, 'salut...', '2025-06-11 12:50:53', 1, NULL, NULL),
(21, 24, 5, NULL, 'salut a vous', '2025-06-24 09:52:59', 1, NULL, NULL),
(22, 30, NULL, 2, 'salut', '2025-06-24 14:40:24', 0, NULL, NULL),
(23, 40, 8, NULL, 'salut', '2025-06-26 12:42:11', 1, NULL, NULL),
(24, 40, 6, NULL, 'salut', '2025-06-26 12:42:37', 1, NULL, NULL),
(25, 40, NULL, 2, 'salut', '2025-06-26 12:43:52', 1, NULL, NULL),
(26, 40, NULL, 2, 'salut', '2025-06-27 12:57:58', 1, 6, NULL),
(27, 40, NULL, 2, 'salut', '2025-06-27 12:57:58', 1, 8, NULL),
(28, 40, 6, NULL, 'salut', '2025-06-27 12:58:34', 1, NULL, 2),
(29, 40, 8, NULL, 'salut', '2025-06-27 12:59:06', 1, NULL, 2),
(30, 40, NULL, 2, 'salut', '2025-06-27 13:07:44', 1, 6, NULL),
(31, 40, NULL, 2, 'salut', '2025-06-27 13:07:44', 1, 8, NULL),
(32, 40, 6, NULL, 'salut', '2025-06-27 13:16:55', 1, NULL, 2),
(33, 40, 8, NULL, 'es4ecdd5fvtgtbnh8j8u', '2025-06-27 13:17:28', 1, NULL, 2),
(34, 40, 6, NULL, 'eawirnewainruewnfc', '2025-06-27 13:28:18', 1, NULL, 2),
(35, 40, 6, NULL, 'eawirnewainruewnfc', '2025-06-27 13:28:18', 1, 8, NULL),
(36, 40, 6, NULL, 'hjbnbiuniunininijniniun', '2025-06-27 13:28:30', 1, NULL, 2),
(37, 40, 6, NULL, 'hjbnbiuniunininijniniun', '2025-06-27 13:28:30', 1, 8, NULL),
(38, 40, 8, NULL, 'okoioioiooiokpopolplplp', '2025-06-27 13:29:07', 1, NULL, 2),
(39, 40, 8, NULL, 'okoioioiooiokpopolplplp', '2025-06-27 13:29:07', 1, 6, NULL),
(40, 40, 8, NULL, 'kmomoimoim', '2025-06-27 13:29:15', 1, NULL, 2),
(41, 40, 8, NULL, 'kmomoimoim', '2025-06-27 13:29:15', 1, 6, NULL),
(42, 40, NULL, 2, 'ok', '2025-06-27 13:30:48', 0, 6, NULL),
(43, 40, NULL, 2, 'ok', '2025-06-27 13:30:48', 0, 8, NULL),
(44, 41, 8, NULL, 'salut boss', '2025-06-27 13:33:13', 1, NULL, 2),
(45, 41, 8, NULL, 'salut boss', '2025-06-27 13:33:13', 1, 6, NULL),
(46, 41, 8, NULL, 'salut', '2025-06-27 13:34:04', 1, NULL, 2),
(47, 41, 8, NULL, 'salut', '2025-06-27 13:34:04', 1, 6, NULL),
(48, 41, 6, NULL, 'yo', '2025-06-27 13:34:32', 1, NULL, 2),
(49, 41, 6, NULL, 'yo', '2025-06-27 13:34:32', 1, 8, NULL),
(50, 41, NULL, 2, 'salut', '2025-06-27 13:35:13', 1, 6, NULL),
(51, 41, NULL, 2, 'salut', '2025-06-27 13:35:13', 1, 8, NULL),
(52, 41, 8, NULL, 'salut a tous', '2025-06-27 13:43:58', 1, NULL, 2),
(53, 41, 8, NULL, 'salut a tous', '2025-06-27 13:43:58', 1, 6, NULL),
(54, 41, NULL, 2, 'salutvoues', '2025-06-27 13:45:24', 1, 6, NULL),
(55, 41, NULL, 2, 'salutvoues', '2025-06-27 13:45:24', 1, 8, NULL),
(56, 41, 8, NULL, 'ici john le technicien', '2025-06-27 13:47:03', 1, NULL, 2),
(57, 41, 8, NULL, 'ici john le technicien', '2025-06-27 13:47:03', 1, 6, NULL),
(58, 41, 6, NULL, 'ici andrel le technicien', '2025-06-27 13:47:33', 1, NULL, 2),
(59, 41, 6, NULL, 'ici andrel le technicien', '2025-06-27 13:47:33', 1, 8, NULL),
(60, 41, NULL, 2, 'et moi le client', '2025-06-27 13:48:49', 1, 6, NULL),
(61, 41, NULL, 2, 'et moi le client', '2025-06-27 13:48:49', 1, 8, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `client`
--

DROP TABLE IF EXISTS `client`;
CREATE TABLE IF NOT EXISTS `client` (
  `id_client` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fin_quarantaine` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `num_client` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telephone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `zone` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_login` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `photo_profil_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'uploads/default_profile.png',
  `en_quarantaine` tinyint(1) DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_banned` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_client`),
  UNIQUE KEY `num_client` (`num_client`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_email_active_banned` (`email`,`is_active`,`is_banned`),
  KEY `idx_num_client_active_banned` (`num_client`,`is_active`,`is_banned`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `client`
--

INSERT INTO `client` (`id_client`, `password`, `nom`, `fin_quarantaine`, `num_client`, `email`, `telephone`, `zone`, `first_login`, `created_at`, `photo_profil_path`, `en_quarantaine`, `is_active`, `is_banned`) VALUES
(2, '$2y$10$wrpx3xEypuCSCBky2cDarOBIfSTSGCaMYG80Tho6qc5dQuh1DFsaC', 'kamdem wabo andrel dela werta', NULL, '90124376', 'andrelkamdem5@gmail.com', '', 'douala', 0, '2025-06-06 13:51:18', 'uploads/profile_6848371a4de55.jpg', 0, 1, 0),
(6, '$2y$10$fuSBDJuIxUvjA4q4lmb4ZejdhcFvGNGUioZUI.fro8F4EE/dHYJla', 'jemima', NULL, '56874390', 'johnw@gmail.com', '', 'Douala', 0, '2025-06-06 18:01:06', 'uploads/default_profile.png', 0, 1, 0),
(9, '$2y$10$fAEWTqRUIOUIbNV1F0g43Or2MkTbB5HExgXqx.g6.UQU8VavXlsKm', 'kamdem wabo andrel dela werta', NULL, '690124376', 'salui@gmail.com', '', 'Lysbonne', 1, '2025-06-25 14:09:58', 'uploads/default_profile.png', 0, 1, 0);

-- --------------------------------------------------------

--
-- Structure de la table `commentaires_mission`
--

DROP TABLE IF EXISTS `commentaires_mission`;
CREATE TABLE IF NOT EXISTS `commentaires_mission` (
  `id_commentaire` int NOT NULL AUTO_INCREMENT,
  `id_mission` int NOT NULL,
  `id_client` int NOT NULL,
  `commentaire` text NOT NULL,
  `date_commentaire` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_commentaire`),
  KEY `id_mission` (`id_mission`),
  KEY `id_client` (`id_client`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `commentaires_mission`
--

INSERT INTO `commentaires_mission` (`id_commentaire`, `id_mission`, `id_client`, `commentaire`, `date_commentaire`) VALUES
(6, 5, 2, 'kkkk', '2025-06-10 16:45:45');

-- --------------------------------------------------------

--
-- Structure de la table `historique_mission`
--

DROP TABLE IF EXISTS `historique_mission`;
CREATE TABLE IF NOT EXISTS `historique_mission` (
  `id_historique` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_mission` int UNSIGNED NOT NULL,
  `date_fin` datetime NOT NULL,
  `commentaire` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id_historique`),
  KEY `id_mission` (`id_mission`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `messages`
--

DROP TABLE IF EXISTS `messages`;
CREATE TABLE IF NOT EXISTS `messages` (
  `id_message` int NOT NULL AUTO_INCREMENT,
  `id_mission` int NOT NULL,
  `id_auteur` int NOT NULL,
  `type_auteur` enum('client','technicien') NOT NULL,
  `contenu` text NOT NULL,
  `date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `id_conversation` int NOT NULL,
  `id_sender` int NOT NULL,
  `message_text` text NOT NULL,
  `timestamp` datetime DEFAULT CURRENT_TIMESTAMP,
  `is_read` tinyint(1) DEFAULT '0',
  `read_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id_message`),
  KEY `id_conversation` (`id_conversation`),
  KEY `id_sender` (`id_sender`),
  KEY `fk_messages_mission` (`id_mission`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `messages`
--

INSERT INTO `messages` (`id_message`, `id_mission`, `id_auteur`, `type_auteur`, `contenu`, `date`, `id_conversation`, `id_sender`, `message_text`, `timestamp`, `is_read`, `read_at`) VALUES
(1, 40, 8, 'technicien', 'jkhkh', '2025-06-26 13:13:35', 0, 0, '', '2025-06-26 13:13:35', 0, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `messages_contact`
--

DROP TABLE IF EXISTS `messages_contact`;
CREATE TABLE IF NOT EXISTS `messages_contact` (
  `id_message` int NOT NULL AUTO_INCREMENT,
  `nom_expediteur` varchar(200) NOT NULL,
  `email_expediteur` varchar(200) NOT NULL,
  `sujet` varchar(200) NOT NULL,
  `message_corps` text NOT NULL,
  `date_envoi` datetime DEFAULT CURRENT_TIMESTAMP,
  `lu` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id_message`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `message_lu`
--

DROP TABLE IF EXISTS `message_lu`;
CREATE TABLE IF NOT EXISTS `message_lu` (
  `id_message` int NOT NULL,
  `id_utilisateur` int NOT NULL,
  `type_utilisateur` enum('client','technicien') NOT NULL,
  `date_lecture` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_message`,`id_utilisateur`,`type_utilisateur`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `message_lu`
--

INSERT INTO `message_lu` (`id_message`, `id_utilisateur`, `type_utilisateur`, `date_lecture`) VALUES
(1, 8, 'technicien', '2025-06-26 13:13:35'),
(1, 6, 'technicien', '2025-06-26 13:21:16'),
(1, 2, 'client', '2025-06-26 13:26:44');

-- --------------------------------------------------------

--
-- Structure de la table `mission`
--

DROP TABLE IF EXISTS `mission`;
CREATE TABLE IF NOT EXISTS `mission` (
  `id_mission` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `type_service` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `titre_probleme` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Problème non spécifié',
  `localisation` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nb_techniciens_demande` tinyint UNSIGNED NOT NULL DEFAULT '1',
  `nb_techniciens_disponibles` int NOT NULL,
  `statut` enum('en_attente','en_cours','terminee') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en_attente',
  `date_demande` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `id_client` int UNSIGNED NOT NULL,
  `date_terminee` datetime DEFAULT NULL,
  `last_read_message_id_client` int DEFAULT '0',
  `last_read_message_id_technicien` int DEFAULT '0',
  `date_debut_mission` datetime DEFAULT NULL,
  `date_creation` datetime DEFAULT NULL,
  `id_technicien` int UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id_mission`),
  KEY `id_clients` (`id_client`),
  KEY `fk_mission_technicien` (`id_technicien`)
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `mission`
--

INSERT INTO `mission` (`id_mission`, `description`, `type_service`, `titre_probleme`, `localisation`, `nb_techniciens_demande`, `nb_techniciens_disponibles`, `statut`, `date_demande`, `id_client`, `date_terminee`, `last_read_message_id_client`, `last_read_message_id_technicien`, `date_debut_mission`, `date_creation`, `id_technicien`) VALUES
(4, 'tuyau', '', 'Problème non spécifié', 'douala', 1, 0, 'terminee', '2025-06-06 16:53:00', 2, NULL, 0, 0, NULL, NULL, NULL),
(5, 'machine', '', 'Problème non spécifié', 'lysbonne', 2, 0, 'terminee', '2025-06-06 16:54:17', 2, NULL, 0, 0, NULL, NULL, NULL),
(6, 'voiture', '', 'Problème non spécifié', 'douala', 1, 0, 'terminee', '2025-06-06 17:16:29', 2, '2025-06-06 17:17:09', 0, 0, NULL, NULL, NULL),
(7, 'telephone', '', 'Problème non spécifié', 'douala', 1, 0, 'terminee', '2025-06-06 17:30:00', 2, '2025-06-06 18:16:16', 0, 0, NULL, NULL, NULL),
(10, 'besoin de reparation d\'un telephone', '', 'Problème non spécifié', 'lysbonne', 1, 0, 'terminee', '2025-06-06 22:36:50', 2, '2025-06-06 22:55:14', 0, 0, NULL, NULL, NULL),
(11, 'becanne', '', 'Problème non spécifié', 'lysbonne', 1, 0, 'terminee', '2025-06-06 23:21:59', 2, '2025-06-07 09:13:15', 0, 0, NULL, NULL, NULL),
(12, 'pannau solaire endomage', '', 'Problème non spécifié', 'douala', 1, 0, 'terminee', '2025-06-07 10:47:33', 2, '2025-06-07 11:06:52', 0, 0, NULL, NULL, NULL),
(13, 'voiture', '', 'Problème non spécifié', 'douala', 1, 0, 'terminee', '2025-06-07 11:04:33', 2, '2025-06-07 11:10:11', 0, 0, NULL, NULL, NULL),
(15, 'iop', '', 'Problème non spécifié', 'xcvnm,', 1, 0, 'terminee', '2025-06-07 14:38:01', 2, '2025-06-07 14:38:51', 0, 0, NULL, NULL, NULL),
(19, 'tuyau', '', 'Problème non spécifié', 'douala', 1, 0, 'terminee', '2025-06-10 09:26:59', 2, '2025-06-10 12:20:03', 0, 0, NULL, NULL, NULL),
(20, 'machine', '', 'Problème non spécifié', 'douala', 1, 0, 'terminee', '2025-06-10 12:24:08', 2, '2025-06-10 14:27:17', 0, 0, NULL, NULL, NULL),
(22, 'tuyau', '', 'Problème non spécifié', 'Localisation non précisée', 1, 0, 'terminee', '2025-06-10 13:54:27', 2, '2025-06-10 14:28:26', 0, 0, '2025-06-10 14:21:54', NULL, NULL),
(23, 'tuyau', '', 'Problème non spécifié', 'lysbonne', 1, 0, 'terminee', '2025-06-10 14:01:11', 2, '2025-06-24 11:50:02', 0, 0, NULL, NULL, NULL),
(24, 'besoin d\'un reparateur de tuyaterie', '', 'Problème non spécifié', 'Localisation non précisée', 1, 0, 'terminee', '2025-06-10 14:22:51', 2, '2025-06-24 11:50:04', 0, 0, '2025-06-10 14:24:01', NULL, NULL),
(26, 'voiture endomage', '', 'Problème non spécifié', 'Localisation non précisée', 1, 0, 'terminee', '2025-06-10 14:26:11', 2, '2025-06-24 11:50:00', 0, 0, '2025-06-10 14:26:49', NULL, NULL),
(27, 'tuyau', '', 'Problème non spécifié', 'lysbonne', 1, 0, 'terminee', '2025-06-11 12:51:41', 2, '2025-06-24 11:49:57', 0, 0, NULL, NULL, NULL),
(28, 'besoin d\'un technicien.', '', 'Problème non spécifié', 'Localisation non précisée', 1, 0, 'terminee', '2025-06-13 19:46:17', 2, '2025-06-13 19:47:59', 0, 0, '2025-06-13 19:47:54', NULL, NULL),
(29, 'fzxtcyvnomkl', '', 'Problème non spécifié', 'Localisation non précisée', 1, 0, 'terminee', '2025-06-19 10:54:29', 2, '2025-06-24 11:50:07', 0, 0, NULL, NULL, NULL),
(30, 'j\'aurai besoin d\'un technicien', '', 'Problème non spécifié', 'douala', 1, 0, 'terminee', '2025-06-24 11:50:44', 2, '2025-06-24 14:40:36', 0, 0, '2025-06-24 12:03:42', NULL, NULL),
(32, 'description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme', '', 'reparation', 'douala', 1, 0, 'terminee', '2025-06-24 15:04:27', 2, '2025-06-24 15:22:21', 0, 0, '2025-06-24 15:05:03', NULL, NULL),
(40, 'description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme description_probleme', '', 'reparation', 'douala', 2, 0, 'terminee', '2025-06-26 12:32:06', 2, '2025-06-27 13:30:56', 0, 0, '2025-06-26 12:41:51', NULL, NULL),
(41, 'kmom', '', 'reparation', 'douala', 2, 0, 'en_cours', '2025-06-27 13:31:10', 2, NULL, 0, 0, '2025-06-27 13:32:59', NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `mission_technicien`
--

DROP TABLE IF EXISTS `mission_technicien`;
CREATE TABLE IF NOT EXISTS `mission_technicien` (
  `id_technicien_mission` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_technicien` int UNSIGNED NOT NULL,
  `id_mission` int UNSIGNED NOT NULL,
  `date_affectation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_technicien_mission`),
  UNIQUE KEY `unique_technicien_mission` (`id_technicien`,`id_mission`),
  KEY `id_mission` (`id_mission`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `mission_technicien`
--

INSERT INTO `mission_technicien` (`id_technicien_mission`, `id_technicien`, `id_mission`, `date_affectation`) VALUES
(36, 6, 32, '2025-06-24 15:05:03'),
(37, 6, 40, '2025-06-26 12:37:13'),
(38, 8, 40, '2025-06-26 12:41:51'),
(39, 6, 41, '2025-06-27 13:31:32'),
(40, 8, 41, '2025-06-27 13:32:59');

-- --------------------------------------------------------

--
-- Structure de la table `nombres`
--

DROP TABLE IF EXISTS `nombres`;
CREATE TABLE IF NOT EXISTS `nombres` (
  `id` int NOT NULL AUTO_INCREMENT,
  `valeur` int NOT NULL,
  `est_pair` tinyint(1) DEFAULT NULL,
  `est_premier` tinyint(1) DEFAULT NULL,
  `est_carre_parfait` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=200 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `nombres`
--

INSERT INTO `nombres` (`id`, `valeur`, `est_pair`, `est_premier`, `est_carre_parfait`) VALUES
(1, 7, NULL, NULL, NULL),
(2, 617, NULL, NULL, NULL),
(3, 558, NULL, NULL, NULL),
(4, 941, NULL, NULL, NULL),
(5, 31, NULL, NULL, NULL),
(6, 331, NULL, NULL, NULL),
(7, 563, NULL, NULL, NULL),
(8, 822, NULL, NULL, NULL),
(9, 421, NULL, NULL, NULL),
(10, 642, NULL, NULL, NULL),
(11, 946, NULL, NULL, NULL),
(12, 804, NULL, NULL, NULL),
(13, 183, NULL, NULL, NULL),
(14, 503, NULL, NULL, NULL),
(15, 967, NULL, NULL, NULL),
(16, 328, NULL, NULL, NULL),
(17, 740, NULL, NULL, NULL),
(18, 715, NULL, NULL, NULL),
(19, 354, NULL, NULL, NULL),
(20, 626, NULL, NULL, NULL),
(21, 71, NULL, NULL, NULL),
(22, 476, NULL, NULL, NULL),
(23, 169, NULL, NULL, NULL),
(24, 418, NULL, NULL, NULL),
(25, 585, NULL, NULL, NULL),
(26, 668, NULL, NULL, NULL),
(27, 586, NULL, NULL, NULL),
(28, 926, NULL, NULL, NULL),
(29, 873, NULL, NULL, NULL),
(30, 587, NULL, NULL, NULL),
(31, 319, NULL, NULL, NULL),
(32, 832, NULL, NULL, NULL),
(33, 205, NULL, NULL, NULL),
(34, 530, NULL, NULL, NULL),
(35, 34, NULL, NULL, NULL),
(36, 582, NULL, NULL, NULL),
(37, 809, NULL, NULL, NULL),
(38, 297, NULL, NULL, NULL),
(39, 59, NULL, NULL, NULL),
(40, 406, NULL, NULL, NULL),
(41, 855, NULL, NULL, NULL),
(42, 54, NULL, NULL, NULL),
(43, 709, NULL, NULL, NULL),
(44, 0, NULL, NULL, NULL),
(45, 782, NULL, NULL, NULL),
(46, 764, NULL, NULL, NULL),
(47, 476, NULL, NULL, NULL),
(48, 87, NULL, NULL, NULL),
(49, 7, NULL, NULL, NULL),
(50, 775, NULL, NULL, NULL),
(51, 853, NULL, NULL, NULL),
(52, 940, NULL, NULL, NULL),
(53, 145, NULL, NULL, NULL),
(54, 903, NULL, NULL, NULL),
(55, 81, NULL, NULL, NULL),
(56, 697, NULL, NULL, NULL),
(57, 242, NULL, NULL, NULL),
(58, 121, NULL, NULL, NULL),
(59, 878, NULL, NULL, NULL),
(60, 29, NULL, NULL, NULL),
(61, 512, NULL, NULL, NULL),
(62, 472, NULL, NULL, NULL),
(63, 824, NULL, NULL, NULL),
(64, 705, NULL, NULL, NULL),
(65, 52, NULL, NULL, NULL),
(66, 145, NULL, NULL, NULL),
(67, 569, NULL, NULL, NULL),
(68, 414, NULL, NULL, NULL),
(69, 361, NULL, NULL, NULL),
(70, 562, NULL, NULL, NULL),
(71, 731, NULL, NULL, NULL),
(72, 968, NULL, NULL, NULL),
(73, 645, NULL, NULL, NULL),
(74, 325, NULL, NULL, NULL),
(75, 690, NULL, NULL, NULL),
(76, 476, NULL, NULL, NULL),
(77, 309, NULL, NULL, NULL),
(78, 116, NULL, NULL, NULL),
(79, 655, NULL, NULL, NULL),
(80, 926, NULL, NULL, NULL),
(81, 667, NULL, NULL, NULL),
(82, 555, NULL, NULL, NULL),
(83, 777, NULL, NULL, NULL),
(84, 219, NULL, NULL, NULL),
(85, 765, NULL, NULL, NULL),
(86, 168, NULL, NULL, NULL),
(87, 545, NULL, NULL, NULL),
(88, 223, NULL, NULL, NULL),
(89, 481, NULL, NULL, NULL),
(90, 737, NULL, NULL, NULL),
(91, 240, NULL, NULL, NULL),
(92, 990, NULL, NULL, NULL),
(93, 231, NULL, NULL, NULL),
(94, 187, NULL, NULL, NULL),
(95, 240, NULL, NULL, NULL),
(96, 641, NULL, NULL, NULL),
(97, 485, NULL, NULL, NULL),
(98, 501, NULL, NULL, NULL),
(99, 50, NULL, NULL, NULL),
(100, 750, NULL, NULL, NULL),
(101, 601, NULL, NULL, NULL),
(102, 755, NULL, NULL, NULL),
(103, 11, NULL, NULL, NULL),
(104, 595, NULL, NULL, NULL),
(105, 58, NULL, NULL, NULL),
(106, 507, NULL, NULL, NULL),
(107, 361, NULL, NULL, NULL),
(108, 283, NULL, NULL, NULL),
(109, 333, NULL, NULL, NULL),
(110, 816, NULL, NULL, NULL),
(111, 81, NULL, NULL, NULL),
(112, 957, NULL, NULL, NULL),
(113, 543, NULL, NULL, NULL),
(114, 846, NULL, NULL, NULL),
(115, 599, NULL, NULL, NULL),
(116, 459, NULL, NULL, NULL),
(117, 500, NULL, NULL, NULL),
(118, 123, NULL, NULL, NULL),
(119, 116, NULL, NULL, NULL),
(120, 211, NULL, NULL, NULL),
(121, 706, NULL, NULL, NULL),
(122, 898, NULL, NULL, NULL),
(123, 374, NULL, NULL, NULL),
(124, 176, NULL, NULL, NULL),
(125, 756, NULL, NULL, NULL),
(126, 255, NULL, NULL, NULL),
(127, 7, NULL, NULL, NULL),
(128, 270, NULL, NULL, NULL),
(129, 331, NULL, NULL, NULL),
(130, 845, NULL, NULL, NULL),
(131, 230, NULL, NULL, NULL),
(132, 618, NULL, NULL, NULL),
(133, 400, NULL, NULL, NULL),
(134, 144, NULL, NULL, NULL),
(135, 524, NULL, NULL, NULL),
(136, 185, NULL, NULL, NULL),
(137, 357, NULL, NULL, NULL),
(138, 227, NULL, NULL, NULL),
(139, 67, NULL, NULL, NULL),
(140, 655, NULL, NULL, NULL),
(141, 72, NULL, NULL, NULL),
(142, 398, NULL, NULL, NULL),
(143, 772, NULL, NULL, NULL),
(144, 666, NULL, NULL, NULL),
(145, 17, NULL, NULL, NULL),
(146, 86, NULL, NULL, NULL),
(147, 381, NULL, NULL, NULL),
(148, 648, NULL, NULL, NULL),
(149, 97, NULL, NULL, NULL),
(150, 541, NULL, NULL, NULL),
(151, 416, NULL, NULL, NULL),
(152, 457, NULL, NULL, NULL),
(153, 37, NULL, NULL, NULL),
(154, 816, NULL, NULL, NULL),
(155, 968, NULL, NULL, NULL),
(156, 394, NULL, NULL, NULL),
(157, 68, NULL, NULL, NULL),
(158, 155, NULL, NULL, NULL),
(159, 574, NULL, NULL, NULL),
(160, 407, NULL, NULL, NULL),
(161, 310, NULL, NULL, NULL),
(162, 332, NULL, NULL, NULL),
(163, 732, NULL, NULL, NULL),
(164, 662, NULL, NULL, NULL),
(165, 116, NULL, NULL, NULL),
(166, 592, NULL, NULL, NULL),
(167, 0, NULL, NULL, NULL),
(168, 299, NULL, NULL, NULL),
(169, 651, NULL, NULL, NULL),
(170, 358, NULL, NULL, NULL),
(171, 836, NULL, NULL, NULL),
(172, 106, NULL, NULL, NULL),
(173, 24, NULL, NULL, NULL),
(174, 801, NULL, NULL, NULL),
(175, 934, NULL, NULL, NULL),
(176, 268, NULL, NULL, NULL),
(177, 537, NULL, NULL, NULL),
(178, 16, NULL, NULL, NULL),
(179, 794, NULL, NULL, NULL),
(180, 330, NULL, NULL, NULL),
(181, 266, NULL, NULL, NULL),
(182, 341, NULL, NULL, NULL),
(183, 906, NULL, NULL, NULL),
(184, 509, NULL, NULL, NULL),
(185, 829, NULL, NULL, NULL),
(186, 617, NULL, NULL, NULL),
(187, 600, NULL, NULL, NULL),
(188, 150, NULL, NULL, NULL),
(189, 1, NULL, NULL, NULL),
(190, 293, NULL, NULL, NULL),
(191, 621, NULL, NULL, NULL),
(192, 228, NULL, NULL, NULL),
(193, 276, NULL, NULL, NULL),
(194, 698, NULL, NULL, NULL),
(195, 662, NULL, NULL, NULL),
(196, 217, NULL, NULL, NULL),
(197, 101, NULL, NULL, NULL),
(198, 852, NULL, NULL, NULL),
(199, 25, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `signalements`
--

DROP TABLE IF EXISTS `signalements`;
CREATE TABLE IF NOT EXISTS `signalements` (
  `id_signalement` int NOT NULL AUTO_INCREMENT,
  `id_client` int DEFAULT NULL,
  `id_technicien` int DEFAULT NULL,
  `sujet` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `date_signalement` datetime DEFAULT CURRENT_TIMESTAMP,
  `statut` varchar(50) DEFAULT 'ouvert',
  PRIMARY KEY (`id_signalement`),
  KEY `id_client` (`id_client`),
  KEY `id_technicien` (`id_technicien`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `signalements`
--

INSERT INTO `signalements` (`id_signalement`, `id_client`, `id_technicien`, `sujet`, `description`, `date_signalement`, `statut`) VALUES
(1, 2, NULL, 'plainte sur un technicien', 'bnm,;;iugv cdsncodsincokmdscds', '2025-06-10 18:07:29', 'ferme');

-- --------------------------------------------------------

--
-- Structure de la table `technicien`
--

DROP TABLE IF EXISTS `technicien`;
CREATE TABLE IF NOT EXISTS `technicien` (
  `id_technicien` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `prenom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `num_technicien` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `zone` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `specialite` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `en_quarantaine` tinyint(1) DEFAULT '0',
  `fin_quarantaine` datetime DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `annees_experience` int DEFAULT NULL,
  `chemin_certifications` text COLLATE utf8mb4_unicode_ci,
  `chemin_photo_profil` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_login` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `photo_profil_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'uploads/default_profile.png',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_banned` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_technicien`),
  UNIQUE KEY `num_client` (`num_technicien`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `technicien`
--

INSERT INTO `technicien` (`id_technicien`, `prenom`, `password`, `nom`, `num_technicien`, `email`, `zone`, `specialite`, `en_quarantaine`, `fin_quarantaine`, `description`, `annees_experience`, `chemin_certifications`, `chemin_photo_profil`, `first_login`, `created_at`, `photo_profil_path`, `is_active`, `is_banned`) VALUES
(6, 'andrel dela werta', '$2y$10$Sds8WQIKyUVdeVP6T1DOLeXMvywxSIhulMG2Rvq3XA6CfMS5X5OgS', 'kamdem wabo', '654023677', 'andrelkamdem5@gmail.com', 'Douala', 'informatique', 0, NULL, 'qwertyuiop[asdfghjkl;&#039;\r\nzxcvbnm,./wertyuiop[qazwsxrdctgvyhniujmoik,pol[p;/[]&#039;]\r\n&#039;\\]', 5, '0', NULL, 2, '2025-06-24 13:13:31', 'uploads/techniciens/profile_tech_685c962675a8f.jpg', 1, 0),
(8, 'andrel dela werta', '$2y$10$wtEdomAZpWoJPxtW8l8pj.gUA7HLSYAhdy8MxE/OnOutwBQ2AMbym', 'kamdem wabo', '699885500', 'johnw@gmail.com', 'Douala', 'electricite', 0, NULL, 'description description description description description description description description description description description description description description description description description description description description description description description description description description description description description description description description description description description description description description description description description description description description description description description description description description description description description description description description description description description description description description description description description', 2, NULL, NULL, 0, '2025-06-25 14:39:41', 'uploads/default_profile.png', 1, 0),
(9, 'Emeryc', '$2y$10$GdiagrMXHE39TmWTESuAluvdo.YBgi04qoRMhxWPOsjXtiPWowW3u', 'Feudje Djomo', '672170259', 'emerycdjomo@gmail.com', 'Los Angeles', 'informatique', 0, NULL, '/lsa,dkmhwokjhtagcsfrnmW-IFOTXFSANMDoIWYSCYF4WGIIL,TIL,UFJsTUYVJWUGK,V[P\r\nJC,[PCL[] OKDp odih PUOFHiurof ph9uye98', 2, NULL, NULL, 0, '2025-06-26 17:28:19', '0', 1, 0);

-- --------------------------------------------------------

--
-- Structure de la table `utilisateurs`
--

DROP TABLE IF EXISTS `utilisateurs`;
CREATE TABLE IF NOT EXISTS `utilisateurs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(200) NOT NULL,
  `email` varchar(200) NOT NULL,
  `mot_de_passe` varchar(200) NOT NULL,
  `date_inscription` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `historique_mission`
--
ALTER TABLE `historique_mission`
  ADD CONSTRAINT `historique_mission_ibfk_1` FOREIGN KEY (`id_mission`) REFERENCES `mission` (`id_mission`) ON DELETE CASCADE;

--
-- Contraintes pour la table `mission`
--
ALTER TABLE `mission`
  ADD CONSTRAINT `fk_mission_technicien` FOREIGN KEY (`id_technicien`) REFERENCES `technicien` (`id_technicien`) ON DELETE SET NULL,
  ADD CONSTRAINT `mission_ibfk_1` FOREIGN KEY (`id_client`) REFERENCES `client` (`id_client`) ON DELETE CASCADE;

--
-- Contraintes pour la table `mission_technicien`
--
ALTER TABLE `mission_technicien`
  ADD CONSTRAINT `mission_technicien_ibfk_1` FOREIGN KEY (`id_technicien`) REFERENCES `technicien` (`id_technicien`) ON DELETE CASCADE,
  ADD CONSTRAINT `mission_technicien_ibfk_2` FOREIGN KEY (`id_mission`) REFERENCES `mission` (`id_mission`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
