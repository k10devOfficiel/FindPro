-- =============================================================================
-- Schéma Relationnel de la Base de Données — FindPro (MVP)
-- Moteur de stockage : InnoDB
-- Jeu de caractères  : utf8mb4 (Collation : utf8mb4_unicode_ci)
-- Description        : Structure de tables sans données pour la plateforme FindPro.
-- =============================================================================

DROP DATABASE IF EXISTS findpro;
CREATE DATABASE findpro
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE findpro;

-- =============================================================================
-- 1. Table : utilisateur
-- Description : Comptes d'accès et d'authentification (clients, pros, admins).
-- =============================================================================
CREATE TABLE utilisateur (
    id_utilisateur INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    telephone VARCHAR(25) NULL,
    mot_de_passe_hash VARCHAR(255) NOT NULL,
    role ENUM('client', 'professionnel', 'admin') NOT NULL,
    statut_compte ENUM('actif', 'desactive') NOT NULL DEFAULT 'actif',
    motif_desactivation VARCHAR(255) NULL,
    date_inscription DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Comptes utilisateurs unifiés (clients, professionnels et administrateurs)';

-- =============================================================================
-- 2. Table : categorie
-- Description : Domaines et grands corps de métier du numérique.
-- =============================================================================
CREATE TABLE categorie (
    id_categorie INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    libelle VARCHAR(100) NOT NULL UNIQUE,
    slug VARCHAR(120) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    icone VARCHAR(50) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Catégories principales des métiers du numérique';

-- =============================================================================
-- 3. Table : competence
-- Description : Savoir-faire et technologies associables aux catégories et profils.
-- =============================================================================
CREATE TABLE competence (
    id_competence INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    libelle VARCHAR(100) NOT NULL UNIQUE,
    id_categorie INT UNSIGNED NULL,
    CONSTRAINT fk_competence_categorie
        FOREIGN KEY (id_categorie) REFERENCES categorie(id_categorie)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Compétences techniques spécifiques (ex: PHP, Premiere Pro, Audit)';

-- =============================================================================
-- 4. Table : profil_professionnel
-- Description : Vitrine publique, disponibilité et cycle de validation du pro.
-- =============================================================================
CREATE TABLE profil_professionnel (
    id_profil INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_utilisateur INT UNSIGNED NOT NULL UNIQUE,
    id_categorie INT UNSIGNED NOT NULL,
    titre VARCHAR(120) NOT NULL,
    description TEXT NOT NULL,
    zone VARCHAR(120) NOT NULL,
    disponibilite ENUM('disponible', 'limitee', 'indisponible') NOT NULL DEFAULT 'disponible',
    photo VARCHAR(255) NULL,
    statut_validation ENUM('brouillon', 'soumis', 'en_verification', 'a_corriger', 'approuve', 'refuse', 'suspendu') NOT NULL DEFAULT 'brouillon',
    date_soumission DATETIME NULL,
    date_validation DATETIME NULL,
    date_maj DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_profil_utilisateur
        FOREIGN KEY (id_utilisateur) REFERENCES utilisateur(id_utilisateur)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_profil_categorie
        FOREIGN KEY (id_categorie) REFERENCES categorie(id_categorie)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_profil_statut_cat (statut_validation, id_categorie)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Fiche professionnelle, coordonnées de visibilité et statut administratif';

-- =============================================================================
-- 5. Table : profil_competence
-- Description : Association N:N entre professionnels et compétences déclarées.
-- =============================================================================
CREATE TABLE profil_competence (
    id_profil INT UNSIGNED NOT NULL,
    id_competence INT UNSIGNED NOT NULL,
    niveau_declare ENUM('debutant', 'intermediaire', 'avance', 'expert') NOT NULL DEFAULT 'intermediaire',
    PRIMARY KEY (id_profil, id_competence),
    CONSTRAINT fk_pc_profil
        FOREIGN KEY (id_profil) REFERENCES profil_professionnel(id_profil)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_pc_competence
        FOREIGN KEY (id_competence) REFERENCES competence(id_competence)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Compétences maîtrisées déclarées par chaque professionnel';

-- =============================================================================
-- 6. Table : projet_portfolio
-- Description : Réalisations passées (images, captures, liens de démo).
-- =============================================================================
CREATE TABLE projet_portfolio (
    id_projet INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_profil INT UNSIGNED NOT NULL,
    titre VARCHAR(150) NOT NULL,
    description TEXT NULL,
    lien_externe VARCHAR(500) NULL,
    image VARCHAR(255) NULL,
    date_projet DATE NULL,
    url_media VARCHAR(500) NULL,
    type_media ENUM('lien', 'image') NULL DEFAULT 'image',
    date_ajout DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_portfolio_profil
        FOREIGN KEY (id_profil) REFERENCES profil_professionnel(id_profil)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Galerie de réalisations et travaux des professionnels';

-- =============================================================================
-- 7. Table : demande_service
-- Description : Commandes de prestations de service initiées par les clients.
-- =============================================================================
CREATE TABLE demande_service (
    id_demande INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_client INT UNSIGNED NOT NULL,
    id_profil INT UNSIGNED NOT NULL,
    id_categorie INT UNSIGNED NOT NULL,
    titre VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    budget_indicatif INT UNSIGNED NULL,
    delai_souhaite DATE NULL,
    statut ENUM('envoyee', 'acceptee', 'refusee', 'en_cours', 'a_confirmer', 'terminee', 'annulee') NOT NULL DEFAULT 'envoyee',
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_cloture DATETIME NULL,
    date_confirmation DATETIME NULL,
    CONSTRAINT fk_demande_client
        FOREIGN KEY (id_client) REFERENCES utilisateur(id_utilisateur)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_demande_profil
        FOREIGN KEY (id_profil) REFERENCES profil_professionnel(id_profil)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_demande_categorie
        FOREIGN KEY (id_categorie) REFERENCES categorie(id_categorie)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_demande_client (id_client),
    INDEX idx_demande_profil_statut (id_profil, statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Prestations demandées par les clients aux professionnels';

-- =============================================================================
-- 8. Table : conversation
-- Description : Canaux de discussion privés liés à une demande de service.
-- =============================================================================
CREATE TABLE conversation (
    id_conversation INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_demande INT UNSIGNED NOT NULL UNIQUE,
    statut ENUM('active', 'lecture_seule') NOT NULL DEFAULT 'active',
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_conversation_demande
        FOREIGN KEY (id_demande) REFERENCES demande_service(id_demande)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Fil de discussion privé déclenché par une demande de service';

-- =============================================================================
-- 9. Table : message
-- Description : Messages échangés au sein d une conversation.
-- =============================================================================
CREATE TABLE message (
    id_message INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_conversation INT UNSIGNED NOT NULL,
    id_expediteur INT UNSIGNED NOT NULL,
    contenu TEXT NOT NULL,
    date_envoi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    lu TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_message_conversation
        FOREIGN KEY (id_conversation) REFERENCES conversation(id_conversation)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_message_expediteur
        FOREIGN KEY (id_expediteur) REFERENCES utilisateur(id_utilisateur)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_message_conv_date (id_conversation, date_envoi)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Messages échangés entre les parties prenantes d une conversation';

-- =============================================================================
-- 10. Table : avis
-- Description : Évaluations et notes vérifiées après clôture de prestation.
-- =============================================================================
CREATE TABLE avis (
    id_avis INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_demande INT UNSIGNED NOT NULL UNIQUE,
    id_client INT UNSIGNED NOT NULL,
    note TINYINT NOT NULL,
    commentaire TEXT NULL,
    statut ENUM('publie', 'masque') NOT NULL DEFAULT 'publie',
    motif_masquage VARCHAR(255) NULL,
    date_avis DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_avis_note CHECK (note BETWEEN 1 AND 5),
    CONSTRAINT fk_avis_demande
        FOREIGN KEY (id_demande) REFERENCES demande_service(id_demande)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_avis_client
        FOREIGN KEY (id_client) REFERENCES utilisateur(id_utilisateur)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Évaluations vérifiées déposées par les clients (note de 1 à 5)';

-- =============================================================================
-- 11. Table : validation_profil
-- Description : Décisions administratives portant sur les profils professionnels.
-- =============================================================================
CREATE TABLE validation_profil (
    id_validation INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_profil INT UNSIGNED NOT NULL,
    id_administrateur INT UNSIGNED NOT NULL,
    decision ENUM('approuve', 'refuse', 'a_corriger', 'suspendu') NOT NULL,
    motif TEXT NULL,
    date_decision DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_validation_profil
        FOREIGN KEY (id_profil) REFERENCES profil_professionnel(id_profil)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_validation_admin
        FOREIGN KEY (id_administrateur) REFERENCES utilisateur(id_utilisateur)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Historique des décisions de vérification administrative des profils';

-- =============================================================================
-- 12. Table : signalement
-- Description : Procédures de signalement d abus, de fraude ou de harcèlement.
-- =============================================================================
CREATE TABLE signalement (
    id_signalement INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_auteur INT UNSIGNED NOT NULL,
    type_cible ENUM('profil', 'portfolio', 'conversation', 'avis') NOT NULL,
    id_cible INT UNSIGNED NOT NULL,
    motif ENUM('fraude', 'usurpation', 'harcelement', 'contenu_interdit', 'autre') NOT NULL,
    description TEXT NULL,
    statut ENUM('nouveau', 'en_cours', 'traite', 'rejete') NOT NULL DEFAULT 'nouveau',
    id_traite_par INT UNSIGNED NULL,
    note_admin VARCHAR(255) NULL,
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_traitement DATETIME NULL,
    CONSTRAINT fk_signalement_auteur
        FOREIGN KEY (id_auteur) REFERENCES utilisateur(id_utilisateur)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_signalement_traite_par
        FOREIGN KEY (id_traite_par) REFERENCES utilisateur(id_utilisateur)
        ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_signalement_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Signalements pour abus, fraudes ou infractions déposés sur la plateforme';

-- =============================================================================
-- 13. Table : journal_moderation
-- Description : Registre d audit traçant toutes les décisions administratives.
-- =============================================================================
CREATE TABLE journal_moderation (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_admin INT UNSIGNED NOT NULL,
    action VARCHAR(60) NOT NULL,
    type_cible VARCHAR(30) NOT NULL,
    id_cible INT UNSIGNED NOT NULL,
    motif TEXT NULL,
    date_action DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_journal_admin
        FOREIGN KEY (id_admin) REFERENCES utilisateur(id_utilisateur)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Audit trail des actions administratives (RG12 : qui, quand, quoi, pourquoi)';

-- =============================================================================
-- 14. Table : tentative_connexion
-- Description : Sécurité anti-bruteforce (comptabilisation des tentatives).
-- =============================================================================
CREATE TABLE tentative_connexion (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    adresse_ip VARCHAR(45) NOT NULL,
    succes TINYINT(1) NOT NULL,
    date_tentative DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tentative_email_date (email, date_tentative)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Journal de sécurité anti-bruteforce des connexions';
