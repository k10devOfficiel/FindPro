<?php
/**
 * Fonctions utilitaires globales pour FindPro.
 * 
 * Fournit les fonctions de base :
 * - e()         : Échappement HTML sécurisé anti-XSS
 * - redirect()  : Redirection HTTP avec arrêt immédiat du script
 * - flash_set() : Définition d'un message temporaire en session
 * - flash_get() : Récupération et suppression des messages temporaires
 * - url()       : Génération d'URL absolue basée sur la configuration
 * - old()       : Réaffichage sécurisé des anciennes valeurs de formulaire
 */

declare(strict_types=1);

/**
 * Échappe une chaîne pour un affichage HTML sécurisé (protection contre les failles XSS).
 *
 * @param string|null $s Chaîne brute à échapper
 * @return string Chaîne sécurisée avec encodage UTF-8 et guillemets échappés
 */
function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/**
 * Redirige le navigateur vers une URL puis interrompt l'exécution du script.
 *
 * @param string $url URL de destination
 * @return never
 */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/**
 * Enregistre un message flash dans la session de l'utilisateur.
 *
 * @param string $type Type de message (ex : 'success', 'danger', 'warning', 'info')
 * @param string $message Texte du message à afficher
 * @return void
 */
function flash_set(string $type, string $message): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }

    $_SESSION['flash'][] = [
        'type'    => $type,
        'message' => $message,
    ];
}

/**
 * Récupère tous les messages flash en attente et les supprime de la session.
 *
 * @return array<int, array{type: string, message: string}> Tableau des messages flash
 */
function flash_get(): array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return is_array($flashes) ? $flashes : [];
}

/**
 * Construit une URL absolue ou relative basée sur le paramètre 'app_url' de la configuration.
 *
 * @param string $chemin Chemin relatif optionnel (ex : 'recherche.php', 'assets/css/style.css')
 * @return string URL normalisée
 */
function url(string $chemin = ''): string
{
    static $baseUrl = null;

    if ($baseUrl === null) {
        $config = require __DIR__ . '/../config/config.php';
        $baseUrl = rtrim((string) ($config['app_url'] ?? ''), '/');
    }

    $cheminNettoye = ltrim($chemin, '/');

    if ($cheminNettoye === '') {
        return $baseUrl !== '' ? $baseUrl : '/';
    }

    return $baseUrl . '/' . $cheminNettoye;
}

/**
 * Réaffiche une valeur précédemment saisie dans un formulaire après une erreur de validation.
 * La valeur est automatiquement échappée via e().
 *
 * @param string $champ Nom du champ dans $_POST ou $_SESSION['_old']
 * @param string $default Valeur par défaut si aucune entrée n'existe
 * @return string Valeur sécurisée prête à être injectée dans un attribut HTML
 */
function old(string $champ, string $default = ''): string
{
    if (session_status() === PHP_SESSION_NONE && isset($_COOKIE[session_name()])) {
        session_start();
    }

    $valeur = $_POST[$champ] ?? $_SESSION['_old'][$champ] ?? $default;

    return e((string) $valeur);
}
