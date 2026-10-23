<?php
/**
 * Script en ligne de commande pour la création d'un compte Administrateur FindPro.
 * 
 * Usage :
 *   php scripts/creer_admin.php
 * 
 * Sécurité :
 *   - Refuse catégoriquement toute exécution via le web (SAPI !== 'cli').
 *   - Validation stricte des données (email unique, mot de passe >= 12 caractères).
 *   - Hachage sécurisé du mot de passe avec password_hash.
 */

declare(strict_types=1);

// 1. Refus formel de toute exécution via un serveur web
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    die("Accès interdit : ce script peut uniquement être exécuté en ligne de commande (CLI).\n");
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

/**
 * Lit une ligne de texte depuis la console STDIN.
 *
 * @param string $invite Message d'invite affiché
 * @return string Valeur saisie nettoyée
 */
function invite(string $invite): string
{
    echo $invite;
    $ligne = fgets(STDIN);
    return $ligne !== false ? trim($ligne) : '';
}

/**
 * Lit un mot de passe en masquant la saisie lorsque l'environnement le permet.
 *
 * @param string $invite Message d'invite
 * @return string Mot de passe saisi
 */
function invite_mot_de_passe(string $invite): string
{
    echo $invite;

    // Sur environnement Windows : utilisation de Read-Host -AsSecureString
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $cmd = 'powershell -NoProfile -Command "$p = Read-Host -AsSecureString; if ($p) { [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($p)) }"';
        $sortie = @shell_exec($cmd);
        if ($sortie !== null && is_string($sortie)) {
            echo "\n";
            return trim($sortie);
        }
    }

    // Sur environnement Unix / Linux
    if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
        $sortie = @shell_exec('/usr/bin/env bash -c "stty -echo; read -r p; stty echo; echo \"\$p\""');
        if ($sortie !== null && is_string($sortie)) {
            echo "\n";
            return trim($sortie);
        }
    }

    // Fallback standard en cas d'indisponibilité de shell_exec
    $ligne = fgets(STDIN);
    return $ligne !== false ? trim($ligne) : '';
}

echo "=======================================================\n";
echo "       FindPro — Création d'un compte Administrateur     \n";
echo "=======================================================\n\n";

// Support des arguments optionnels CLI (--nom=..., --prenom=..., etc.) pour automatisation
$options = getopt('', ['nom:', 'prenom:', 'email:', 'password:']);

// 2. Saisie du Nom
$nom = trim((string) ($options['nom'] ?? ''));
while ($nom === '') {
    $nom = invite("Nom de l'administrateur : ");
    if ($nom === '') {
        echo " [!] Le nom est obligatoire.\n";
    } elseif (mb_strlen($nom) > 100) {
        echo " [!] Le nom ne peut pas dépasser 100 caractères.\n";
        $nom = '';
    }
}

// 3. Saisie du Prénom
$prenom = trim((string) ($options['prenom'] ?? ''));
while ($prenom === '') {
    $prenom = invite("Prénom de l'administrateur : ");
    if ($prenom === '') {
        echo " [!] Le prénom est obligatoire.\n";
    } elseif (mb_strlen($prenom) > 100) {
        echo " [!] Le prénom ne peut pas dépasser 100 caractères.\n";
        $prenom = '';
    }
}

// 4. Saisie et vérification de l'Email
$pdo = db();
$email = trim((string) ($options['email'] ?? ''));
while (true) {
    if ($email === '') {
        $email = invite("Adresse email : ");
    }

    if ($email === '') {
        echo " [!] L'adresse email est obligatoire.\n";
        continue;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo " [!] Format d'adresse email invalide.\n";
        $email = '';
        continue;
    }

    if (mb_strlen($email) > 190) {
        echo " [!] L'email ne peut pas dépasser 190 caractères.\n";
        $email = '';
        continue;
    }

    // Vérification de l'unicité de l'email
    $stmtVerif = $pdo->prepare('SELECT COUNT(*) FROM utilisateur WHERE email = :email');
    $stmtVerif->execute(['email' => $email]);
    if ((int) $stmtVerif->fetchColumn() > 0) {
        echo " [!] Erreur : un utilisateur avec l'adresse email '{$email}' existe déjà.\n";
        echo "     Refus de créer un second administrateur avec cette adresse.\n";
        if (isset($options['email'])) {
            exit(1);
        }
        $email = '';
        continue;
    }

    break;
}

// 5. Saisie du Mot de passe
$motDePasse = (string) ($options['password'] ?? '');
while (true) {
    if ($motDePasse === '') {
        $motDePasse = invite_mot_de_passe("Mot de passe (minimum 12 caractères) : ");
    }

    if (mb_strlen($motDePasse) < 12) {
        echo " [!] Le mot de passe doit comporter au moins 12 caractères.\n";
        if (isset($options['password'])) {
            exit(1);
        }
        $motDePasse = '';
        continue;
    }

    break;
}

// 6. Hachage sécurisé et insertion en base de données
$motDePasseHash = password_hash($motDePasse, PASSWORD_DEFAULT);

try {
    $stmtInsert = $pdo->prepare('
        INSERT INTO utilisateur (nom, prenom, email, mot_de_passe_hash, role, statut_compte)
        VALUES (:nom, :prenom, :email, :hash, "admin", "actif")
    ');
    $stmtInsert->execute([
        'nom'    => $nom,
        'prenom' => $prenom,
        'email'  => $email,
        'hash'   => $motDePasseHash,
    ]);

    $idAdmin = (int) $pdo->lastInsertId();

    echo "\n-------------------------------------------------------\n";
    echo " [+] Succès ! Le compte administrateur a été créé.\n";
    echo "     - ID utilisateur : {$idAdmin}\n";
    echo "     - Nom complet    : {$prenom} {$nom}\n";
    echo "     - Email          : {$email}\n";
    echo "     - Rôle           : admin\n";
    echo "     - Statut         : actif\n";
    echo "-------------------------------------------------------\n";
    echo " Vous pouvez dès à présent vous connecter sur :\n";
    echo " " . url('login.php') . "\n";
    echo "=======================================================\n";
    exit(0);

} catch (PDOException $e) {
    echo "\n [!] Erreur lors de l'enregistrement en base de données : " . $e->getMessage() . "\n";
    exit(1);
}
