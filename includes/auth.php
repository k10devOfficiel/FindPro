<?php
/**
 * Gestion du contrôle d'accès et des autorisations (FindPro).
 * 
 * Fournit les contrôles stricts de sécurité à appeler en tête de chaque page protégée :
 * - exiger_connexion()        : Vérifie l'authentification et l'activité du compte.
 * - exiger_role(...)          : Vérifie le rôle de l'utilisateur (HTTP 403 si non autorisé).
 * - verifier_compte_actif()   : Relit statut_compte en base de données à chaque requête.
 * - est_admin(), est_client(), est_professionnel() : Fonctions d'aide contextuelles.
 */

declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/db.php';

/**
 * Exige qu'un utilisateur soit authentifié.
 * 
 * Si non connecté :
 * - Détecte l'URL courante pour alimenter le paramètre "retour".
 * - Dépose un message flash d'avertissement en session.
 * - Redirige immédiatement vers login.php.
 * 
 * Si connecté :
 * - Contrôle la validité du compte en base via verifier_compte_actif().
 *
 * @return array{id: int, prenom: string, role: string} Données de l'utilisateur connecté
 */
function exiger_connexion(): array
{
    demarrer_session();

    $utilisateur = utilisateur_courant();

    if ($utilisateur === null) {
        // Capture de l'URL courante pour redirection post-connexion
        $urlCourante = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $urlRetourParam = '';

        if ($urlCourante !== '') {
            // Nettoyage de base : conserver le chemin interne
            $parties = parse_url($urlCourante);
            $chemin = $parties['path'] ?? '';
            $query = isset($parties['query']) ? '?' . $parties['query'] : '';
            $cible = $chemin . $query;

            // Vérifier que le chemin commence bien par / et n'est pas malveillant
            if (str_starts_with($cible, '/') && !str_contains($cible, '//') && !str_contains($cible, ':') && !str_contains($cible, '\\')) {
                $urlRetourParam = '?retour=' . urlencode($cible);
            }
        }

        flash_set('warning', 'Veuillez vous connecter pour accéder à cette page.');
        redirect(url('login.php' . $urlRetourParam));
    }

    // Vérifier que le compte est toujours actif en base de données
    verifier_compte_actif();

    return $utilisateur;
}

/**
 * Exige qu'un utilisateur possède l'un des rôles autorisés.
 * 
 * RÈGLE STRICTE :
 * - Doit être appelée en tête de fichier avant toute logique métier.
 * - Si le rôle ne correspond pas, émet un code HTTP 403 avec affichage de la vue 403.
 * - AUCUNE redirection silencieuse n'est effectuée pour les utilisateurs non autorisés.
 *
 * @param string ...$roles Liste des rôles autorisés ('client', 'professionnel', 'admin')
 * @return array{id: int, prenom: string, role: string} Données de l'utilisateur autorisé
 */
function exiger_role(string ...$roles): array
{
    $utilisateur = exiger_connexion();

    if (!in_array($utilisateur['role'], $roles, true)) {
        // Émission formelle d'un HTTP 403 avec affichage de la page d'interdiction
        require __DIR__ . '/403.php';
        exit;
    }

    return $utilisateur;
}

/**
 * Vérifie en temps réel dans la base de données si le compte de l'utilisateur est toujours actif.
 * 
 * Si le compte a été désactivé entre-temps par un administrateur :
 * - Détruit immédiatement la session courante et invalide le cookie.
 * - Dépose un message flash d'erreur.
 * - Redirige vers login.php.
 *
 * @return void
 */
function verifier_compte_actif(): void
{
    $utilisateur = utilisateur_courant();
    if ($utilisateur === null) {
        return;
    }

    try {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT statut_compte FROM utilisateur WHERE id_utilisateur = :id LIMIT 1');
        $stmt->execute(['id' => $utilisateur['id']]);
        $statut = $stmt->fetchColumn();

        if ($statut !== 'actif') {
            // Le compte a été désactivé ou supprimé
            deconnecter_session();
            demarrer_session();
            flash_set('danger', 'Votre compte a été désactivé. Veuillez contacter l\'administration de FindPro.');
            redirect(url('login.php'));
        }
    } catch (PDOException $e) {
        // En cas d'erreur de base temporaire, la gestion d'erreur de db() s'applique
    }
}

/**
 * Indique si l'utilisateur connecté est un administrateur.
 *
 * @return bool
 */
function est_admin(): bool
{
    $u = utilisateur_courant();
    return $u !== null && $u['role'] === 'admin';
}

/**
 * Indique si l'utilisateur connecté est un client.
 *
 * @return bool
 */
function est_client(): bool
{
    $u = utilisateur_courant();
    return $u !== null && $u['role'] === 'client';
}

/**
 * Indique si l'utilisateur connecté est un professionnel.
 *
 * @return bool
 */
function est_professionnel(): bool
{
    $u = utilisateur_courant();
    return $u !== null && $u['role'] === 'professionnel';
}
