<?php
/**
 * Connexion à la base de données (Singleton PDO).
 * 
 * Fournit une fonction globale db() retournant une instance unique de PDO.
 * En cas d'erreur de connexion, les détails techniques sont consignés dans logs/erreurs.log
 * et un message générique avec code HTTP 500 est renvoyé au client.
 * 
 * Exemple d'utilisation avec une requête préparée SELECT :
 * ----------------------------------------------------
 * require_once __DIR__ . '/includes/db.php';
 * 
 * $pdo = db();
 * $stmt = $pdo->prepare('SELECT id_utilisateur, nom, prenom, email FROM utilisateur WHERE email = :email AND statut_compte = :statut');
 * $stmt->execute([
 *     'email'  => 'jean.dupont@example.com',
 *     'statut' => 'actif'
 * ]);
 * $utilisateur = $stmt->fetch();
 * 
 * if ($utilisateur) {
 *     echo "Utilisateur trouvé : " . htmlspecialchars($utilisateur['prenom'], ENT_QUOTES, 'UTF-8');
 * }
 * ----------------------------------------------------
 */

declare(strict_types=1);

/**
 * Retourne l'instance unique de PDO (Singleton).
 *
 * @return PDO
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    // Chargement de la configuration
    $config = require __DIR__ . '/../config/config.php';

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $config['db_host'],
        $config['db_name'],
        $config['db_charset'] ?? 'utf8mb4'
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], $options);
    } catch (PDOException $e) {
        // Journalisation de l'erreur technique dans logs/erreurs.log
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/erreurs.log';
        $date = date('Y-m-d H:i:s');
        $logMessage = sprintf(
            "[%s] [DB_ERROR] Code: %s | Message: %s | Fichier: %s:%d\n",
            $date,
            $e->getCode(),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        );

        error_log($logMessage, 3, $logFile);

        // Affichage épuré et sécurisé pour l'utilisateur
        http_response_code(500);
        die('Service momentanement indisponible');
    }

    return $pdo;
}
