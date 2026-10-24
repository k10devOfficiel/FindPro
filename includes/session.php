<?php
/**
 * Gestion sécurisée des sessions pour FindPro.
 * 
 * - Configuration stricte des cookies de session (HttpOnly, SameSite=Lax, Secure conditionnel).
 * - Prévention de la fixation de session (use_strict_mode, regenerer_session).
 * - Expiration automatique après 2 heures d'inactivité.
 * - Récupération sécurisée de l'utilisateur courant (id, prenom, role).
 * - RÈGLE STRICTE : Aucun mot de passe ni hash de mot de passe n'est stocké en session.
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

/**
 * Durée maximale d'inactivité en secondes (2 heures).
 */
define('FINDPRO_SESSION_INACTIVITE_MAX', 7200);

/**
 * Démarre et configure la session de manière hautement sécurisée.
 * Doit être appelée en début de chaque page.
 *
 * @return void
 */
function demarrer_session(): void
{
    // Si la session est déjà active, ne rien refaire
    if (session_status() === PHP_SESSION_ACTIVE) {
        verifier_inactivite_session();
        return;
    }

    // 1. Nom personnalisé de session
    session_name('findpro_sid');

    // 2. Directives de sécurité INI strictes avant session_start
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    // 3. Détection dynamique du protocole HTTPS
    $estHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) ||
        (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    );

    // 4. Configuration des paramètres du cookie de session
    session_set_cookie_params([
        'lifetime' => 0,          // Cookie de session (expire à la fermeture du navigateur)
        'path'     => '/',
        'domain'   => '',
        'secure'   => $estHttps,  // Uniquement sur HTTPS en production
        'httponly' => true,       // Inaccessible via JavaScript (protection XSS)
        'samesite' => 'Lax',      // Protection contre les requêtes CSRF cross-site
    ]);

    // 5. Démarrage de la session
    session_start();

    // 6. Contrôle de l'inactivité de 2 heures
    verifier_inactivite_session();
}

/**
 * Vérifie si la session d'un utilisateur connecté a expiré pour cause d'inactivité (2 heures).
 * Si expirée : détruit la session, pose un message flash et redirige vers la connexion.
 *
 * @return void
 */
function verifier_inactivite_session(): void
{
    if (!isset($_SESSION['utilisateur']) || !is_array($_SESSION['utilisateur'])) {
        return;
    }

    $tempsActuel = time();

    // Vérification du délai d'inactivité
    if (isset($_SESSION['dernier_acces']) && is_int($_SESSION['dernier_acces'])) {
        $inactivite = $tempsActuel - $_SESSION['dernier_acces'];

        if ($inactivite > FINDPRO_SESSION_INACTIVITE_MAX) {
            // Déconnexion complète
            deconnecter_session();

            // Démarrage d'une nouvelle session propre pour le message flash
            demarrer_session();
            flash_set('warning', 'Votre session a expiré après 2 heures d\'inactivité. Veuillez vous reconnecter.');
            redirect(url('login.php'));
        }
    }

    // Mise à jour de l'horodatage de dernière activité
    $_SESSION['dernier_acces'] = $tempsActuel;
}

/**
 * Régénère l'identifiant de session en supprimant l'ancien.
 * À appeler obligatoirement après une connexion ou un changement de privilèges
 * pour prévenir les attaques par fixation de session.
 *
 * @return void
 */
function regenerer_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
        session_regenerate_id(true);
    }
}

/**
 * Retourne les informations minimales de l'utilisateur connecté ou null s'il est anonyme.
 * Ne stocke ni ne retourne JAMAIS le mot de passe ou son hash.
 *
 * @return array{id: int, prenom: string, role: string}|null
 */
function utilisateur_courant(): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return null;
    }

    if (!isset($_SESSION['utilisateur']) || !is_array($_SESSION['utilisateur'])) {
        return null;
    }

    $user = $_SESSION['utilisateur'];

    $id = isset($user['id']) ? (int) $user['id'] : (isset($user['id_utilisateur']) ? (int) $user['id_utilisateur'] : 0);
    if ($id <= 0) {
        return null;
    }

    return [
        'id'     => $id,
        'nom'    => (string) ($user['nom'] ?? ''),
        'prenom' => (string) ($user['prenom'] ?? ''),
        'role'   => (string) ($user['role'] ?? 'client'),
    ];
}

/**
 * Détruit proprement la session courante et invalide le cookie client.
 *
 * @return void
 */
function deconnecter_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }
}
