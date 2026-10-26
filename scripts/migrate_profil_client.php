<?php
/**
 * Migration : Synchronisation complète de la table profil_client dans FindPro
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Accès interdit : ce script s'exécute uniquement en ligne de commande (CLI).\n");
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = db();

$sql = "
CREATE TABLE IF NOT EXISTS profil_client (
    id_profil_client INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_utilisateur INT UNSIGNED NOT NULL UNIQUE,
    type_structure ENUM('particulier', 'entreprise', 'organisation') NOT NULL DEFAULT 'particulier',
    nom_structure VARCHAR(150) NULL,
    secteur_activite VARCHAR(150) NULL,
    poste_occupe VARCHAR(150) NULL,
    siret_rccm VARCHAR(100) NULL,
    site_web VARCHAR(255) NULL,
    adresse VARCHAR(255) NULL,
    ville VARCHAR(100) NULL,
    pays VARCHAR(100) NOT NULL DEFAULT 'Côte d\'Ivoire',
    profession VARCHAR(150) NULL,
    bio TEXT NULL,
    photo VARCHAR(255) NULL,
    centres_interet VARCHAR(255) NULL,
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_maj DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_profil_client_user
        FOREIGN KEY (id_utilisateur) REFERENCES utilisateur(id_utilisateur)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Profil détaillé, personnalité et structure du client';
";

try {
    $pdo->exec($sql);
    echo "Succès : Table profil_client synchronisée.\n";

    // Vérification des colonnes virtuelles de compatibilité type_client et nom_societe
    $cols = $pdo->query("SHOW COLUMNS FROM profil_client")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('type_client', $cols, true)) {
        try {
            $pdo->exec("ALTER TABLE profil_client ADD COLUMN type_client VARCHAR(50) GENERATED ALWAYS AS (type_structure) VIRTUAL");
            echo "Colonne de compatibilité type_client ajoutée.\n";
        } catch (PDOException $e) {
            echo "Note type_client : " . $e->getMessage() . "\n";
        }
    }

    if (!in_array('nom_societe', $cols, true)) {
        try {
            $pdo->exec("ALTER TABLE profil_client ADD COLUMN nom_societe VARCHAR(150) GENERATED ALWAYS AS (nom_structure) VIRTUAL");
            echo "Colonne de compatibilité nom_societe ajoutée.\n";
        } catch (PDOException $e) {
            echo "Note nom_societe : " . $e->getMessage() . "\n";
        }
    }

} catch (PDOException $e) {
    echo "Erreur lors de la migration profil_client : " . $e->getMessage() . "\n";
}
