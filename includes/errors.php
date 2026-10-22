<?php
/**
 * Gestion centralisée des erreurs et exceptions pour FindPro.
 * 
 * - Configuration de l'affichage selon l'environnement (dev vs prod).
 * - Gestionnaire d'exceptions non attrapées avec code HTTP 500 et page propre.
 * - Fonction réutilisable log_erreur() avec masquage automatique des secrets
 *   (mots de passe, jetons CSRF, identifiants et contenu de session).
 */

declare(strict_types=1);

// Chargement de la configuration générale
$config = require __DIR__ . '/../config/config.php';
$appEnv = $config['app_env'] ?? 'prod';

// Répertoire et fichier de journalisation
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
$logFile = $logDir . '/erreurs.log';

// 1. Configuration selon app_env
if ($appEnv === 'dev') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', $logFile);
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
}

/**
 * Filtre et masque les données sensibles d'un message avant journalisation.
 * Protège les mots de passe, hashs, jetons CSRF et variables de session.
 *
 * @param string $message Message brut
 * @return string Message assaini
 */
function assainir_message_log(string $message): string
{
    $patterns = [
        // Mots de passe, secrets, hashs
        '/(mot_de_passe|mot_de_passe_hash|password|secret|mdp)\s*(=|:|=>)\s*["\']?[^\s"\'&,;]+["\']?/i' => '$1$2[MASQUÉ]',
        // Jetons CSRF et tokens
        '/(csrf|csrf_token|token|jeton|jeton_csrf)\s*(=|:|=>)\s*["\']?[^\s"\'&,;]+["\']?/i' => '$1$2[MASQUÉ]',
        // Identifiants de session et cookies
        '/(PHPSESSID|session_id|sessid)\s*(=|:|=>)\s*["\']?[^\s"\'&,;]+["\']?/i' => '$1$2[MASQUÉ]',
        // Dumps ou mentions de tableaux de session
        '/(\$_SESSION\[.*?\]\s*=\s*)(.*?)(;|\n|$)/i' => '$1[SESSION_MASQUÉE]$3',
    ];

    foreach ($patterns as $pattern => $replacement) {
        $message = (string) preg_replace($pattern, $replacement, $message);
    }

    return $message;
}

/**
 * Enregistre un message d'erreur dans logs/erreurs.log de manière sécurisée.
 *
 * @param string $message Message d'erreur à journaliser
 * @return void
 */
function log_erreur(string $message): void
{
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $logPath = $logDir . '/erreurs.log';

    $date = date('Y-m-d H:i:s');
    $messageSecurise = assainir_message_log($message);

    $ligne = sprintf("[%s] [APPLICATION_ERROR] %s\n", $date, $messageSecurise);
    error_log($ligne, 3, $logPath);
}

// 2. Gestionnaire d'exceptions non attrapées
set_exception_handler(function (Throwable $e) use ($appEnv, $logFile): void {
    $date = date('Y-m-d H:i:s');
    $messageSecurise = assainir_message_log($e->getMessage());

    $logEntry = sprintf(
        "[%s] [UNCAUGHT_EXCEPTION] %s in %s on line %d\n",
        $date,
        $messageSecurise,
        $e->getFile(),
        $e->getLine()
    );

    error_log($logEntry, 3, $logFile);

    http_response_code(500);

    // Mode CLI : sortie texte concise
    if (PHP_SAPI === 'cli') {
        if ($appEnv === 'dev') {
            fwrite(STDERR, "[500] Une erreur est survenue : {$messageSecurise} ({$e->getFile()}:{$e->getLine()})\n");
        } else {
            fwrite(STDERR, "Une erreur est survenue\n");
        }
        exit(1);
    }

    // Mode Web : page propre avec code HTTP 500
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erreur 500 - FindPro</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f8f9fa;
            color: #212529;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
        }
        .error-card {
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
            max-width: 520px;
            width: 100%;
            padding: 36px;
            text-align: center;
        }
        h1 {
            font-size: 1.6rem;
            margin-top: 0;
            margin-bottom: 12px;
            color: #dc3545;
        }
        p {
            margin: 12px 0 24px;
            color: #6c757d;
            line-height: 1.5;
        }
        a.btn-home {
            display: inline-block;
            background-color: #0d6efd;
            color: #ffffff;
            text-decoration: none;
            padding: 10px 22px;
            border-radius: 6px;
            font-weight: 500;
            transition: background-color 0.15s ease-in-out;
        }
        a.btn-home:hover {
            background-color: #0b5ed7;
        }
        .dev-box {
            margin-top: 24px;
            text-align: left;
            background-color: #fff3cd;
            color: #664d03;
            border: 1px solid #ffecb5;
            padding: 14px;
            border-radius: 6px;
            font-family: Consolas, monospace;
            font-size: 0.85rem;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="error-card">
        <h1>Une erreur est survenue</h1>
        <p>Une anomalie imprévue a empêché le traitement de votre demande. Nos équipes ont été averties de l'incident.</p>
        <a href="/" class="btn-home">Retour à l'accueil</a>
        <?php if ($appEnv === 'dev'): ?>
            <div class="dev-box">
                <strong>Détails techniques (Mode DEV) :</strong><br>
                <?= htmlspecialchars($messageSecurise, ENT_QUOTES, 'UTF-8'); ?><br><br>
                <small><?= htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8'); ?>:<?= (int) $e->getLine(); ?></small>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
    <?php
    exit(1);
});
