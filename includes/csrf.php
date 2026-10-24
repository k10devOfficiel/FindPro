<?php
/**
 * Protection contre les attaques CSRF (Cross-Site Request Forgery) pour FindPro.
 * 
 * - Génération cryptographique aléatoire de jetons avec random_bytes (32 octets).
 * - Injection simple dans les formulaires HTML via csrf_field().
 * - Vérification temporelle constante avec hash_equals() via csrf_verifier().
 * - Prise en charge des requêtes classiques (POST) et asynchrones AJAX (en-tête X-CSRF-Token).
 */

declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/functions.php';

/**
 * Retourne le jeton CSRF courant stocké en session.
 * S'il n'existe pas encore, il est généré de façon aléatoire et sécurisée.
 *
 * @return string Jeton CSRF hexadécimal de 64 caractères
 */
function csrf_token(): string
{
    demarrer_session();

    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Génère un champ de formulaire HTML invisible contenant le jeton CSRF.
 * Prêt à être inséré directement dans toute balise <form method="POST">.
 *
 * @return string Balise HTML <input type="hidden"> échappée
 */
function csrf_field(): string
{
    $token = csrf_token();

    return '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
}

/**
 * Alias français de csrf_field().
 *
 * @return string Balise HTML <input type="hidden">
 */
function csrf_champ(): string
{
    return csrf_field();
}

/**
 * Valide le jeton CSRF transmis par rapport à celui présent en session.
 * 
 * Récupère le jeton soit :
 * - depuis le corps du formulaire : $_POST['csrf_token']
 * - depuis l'en-tête HTTP pour les requêtes AJAX : X-CSRF-Token ($_SERVER['HTTP_X_CSRF_TOKEN'])
 * 
 * En cas d'absence ou de non-correspondance (comparaison avec hash_equals) :
 * - Renvoie un code HTTP 403 Forbidden
 * - Affiche un message d'erreur clair
 * - Interrompt immédiatement l'exécution du script
 *
 * @return void
 */
function csrf_verifier(): void
{
    demarrer_session();

    $jetonSession = $_SESSION['csrf_token'] ?? null;

    // Récupération du jeton depuis POST ou depuis l'en-tête AJAX X-CSRF-Token
    $jetonSoumis = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

    $estValide = is_string($jetonSession)
        && is_string($jetonSoumis)
        && $jetonSession !== ''
        && hash_equals($jetonSession, $jetonSoumis);

    if (!$estValide) {
        http_response_code(403);

        // Détection d'une requête AJAX / JSON
        $estAjax = (
            (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
            (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) ||
            isset($_SERVER['HTTP_X_CSRF_TOKEN'])
        );

        if ($estAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'succes'  => false,
                'message' => 'Action non autorisée : jeton CSRF manquant ou invalide.',
            ], JSON_UNESCAPED_UNICODE);
        } else {
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html>';
            echo '<html lang="fr"><head><meta charset="UTF-8"><title>403 - Requête non autorisée</title>';
            echo '<style>body{font-family:sans-serif;background:#f8fafc;color:#1f2937;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}';
            echo '.box{background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:32px;max-width:480px;text-align:center;box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);}';
            echo 'h1{font-size:1.5rem;color:#b91c1c;margin-bottom:12px;}p{color:#64748b;line-height:1.5;margin-bottom:20px;}';
            echo 'a{color:#111827;font-weight:600;text-decoration:none;border:1px solid #e2e8f0;padding:8px 16px;border-radius:6px;}</style></head>';
            echo '<body><div class="box">';
            echo '<h1>Requête non autorisée (Erreur 403)</h1>';
            echo '<p>Le jeton de sécurité de votre session est manquant ou a expiré. Veuillez rafraîchir la page et réessayer.</p>';
            echo '<a href="javascript:history.back()">← Retourner au formulaire</a>';
            echo '</div></body></html>';
        }

        exit;
    }
}
