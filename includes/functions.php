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

/**
 * Retourne la liste des profils professionnels publics et vérifiés (RG05).
 * 
 * RÈGLE FONDAMENTALE RG05 :
 * Cette fonction est la SEULE ET UNIQUE source des profils publics sur FindPro.
 * Elle filtre impérativement sur :
 * - profil_professionnel.statut_validation = 'approuve'
 * - utilisateur.statut_compte = 'actif'
 * 
 * Calcule la note moyenne par AVG sur les avis dont statut = 'publie' uniquement.
 * N'expose jamais l'email ni le numéro de téléphone des professionnels.
 *
 * @param PDO   $pdo      Instance active de connexion PDO
 * @param array $filtres  Filtres optionnels (categorie, zone, disponibilite, recherche)
 * @param int   $limite   Nombre maximal de profils (défaut: 20)
 * @param int   $decalage Décalage pour la pagination (défaut: 0)
 * @return array<int, array> Liste des profils approuvés prêts à l'affichage
 */
function profils_publics(PDO $pdo, array $filtres = [], int $limite = 20, int $decalage = 0): array
{
    $limite = max(1, min(100, $limite));
    $decalage = max(0, $decalage);

    $conditions = [
        "p.statut_validation = 'approuve'",
        "u.statut_compte = 'actif'",
    ];
    $params = [];

    // Filtre par catégorie (ID ou slug)
    if (!empty($filtres['categorie'])) {
        if (is_numeric($filtres['categorie'])) {
            $conditions[] = "p.id_categorie = :id_cat";
            $params['id_cat'] = (int) $filtres['categorie'];
        } else {
            $conditions[] = "c.slug = :slug_cat";
            $params['slug_cat'] = (string) $filtres['categorie'];
        }
    }

    // Filtre par zone / commune
    if (!empty($filtres['zone'])) {
        $zoneNettoyee = trim((string) $filtres['zone']);
        if ($zoneNettoyee !== '') {
            $conditions[] = "p.zone LIKE :zone";
            $params['zone'] = '%' . addcslashes($zoneNettoyee, '%_\\') . '%';
        }
    }

    // Filtre par disponibilité (liste blanche)
    if (!empty($filtres['disponibilite']) && in_array($filtres['disponibilite'], ['disponible', 'limitee', 'indisponible'], true)) {
        $conditions[] = "p.disponibilite = :disponibilite";
        $params['disponibilite'] = (string) $filtres['disponibilite'];
    }

    // Filtre par compétence (ID entier)
    if (!empty($filtres['competence']) && (is_numeric($filtres['competence']) || ctype_digit((string) $filtres['competence']))) {
        $idCompetence = (int) $filtres['competence'];
        if ($idCompetence > 0) {
            $conditions[] = "EXISTS (
                SELECT 1 FROM profil_competence pc_filtre 
                WHERE pc_filtre.id_profil = p.id_profil 
                AND pc_filtre.id_competence = :id_comp_filtre
            )";
            $params['id_comp_filtre'] = $idCompetence;
        }
    }

    // Filtre mot-clé : recherche dans le titre, la description, le nom et les compétences
    $motCleBrut = trim((string) ($filtres['q'] ?? $filtres['recherche'] ?? ''));
    $motCleLike = '';
    if ($motCleBrut !== '') {
        $motCleEchappe = addcslashes($motCleBrut, '%_\\');
        $motCleLike = '%' . $motCleEchappe . '%';
        $conditions[] = "(
            p.titre LIKE :q_titre 
            OR p.description LIKE :q_desc 
            OR u.prenom LIKE :q_prenom 
            OR u.nom LIKE :q_nom 
            OR CONCAT(u.prenom, ' ', u.nom) LIKE :q_nomcomplet 
            OR comps.competences_brutes LIKE :q_comp
        )";
        $params['q_titre'] = $motCleLike;
        $params['q_desc'] = $motCleLike;
        $params['q_prenom'] = $motCleLike;
        $params['q_nom'] = $motCleLike;
        $params['q_nomcomplet'] = $motCleLike;
        $params['q_comp'] = $motCleLike;
    }

    $whereSql = implode(' AND ', $conditions);

    // Détermination du tri (liste blanche : pertinence, recents, note)
    $tri = (string) ($filtres['tri'] ?? '');
    if (!in_array($tri, ['pertinence', 'recents', 'note'], true)) {
        $tri = ($motCleBrut !== '') ? 'pertinence' : 'recents';
    }

    $selectScoreSql = '';
    $orderBySql = '';

    if ($tri === 'pertinence') {
        if ($motCleBrut !== '') {
            $selectScoreSql = ", (
                (CASE WHEN p.titre LIKE :q_score_titre THEN 1 ELSE 0 END) +
                (CASE WHEN p.description LIKE :q_score_desc THEN 1 ELSE 0 END) +
                (CASE WHEN u.prenom LIKE :q_score_prenom OR u.nom LIKE :q_score_nom OR CONCAT(u.prenom, ' ', u.nom) LIKE :q_score_nomcomplet THEN 1 ELSE 0 END) +
                (CASE WHEN comps.competences_brutes LIKE :q_score_comp THEN 1 ELSE 0 END)
            ) AS score_pertinence";
            $orderBySql = "score_pertinence DESC, p.date_maj DESC, p.id_profil DESC";
            $params['q_score_titre'] = $motCleLike;
            $params['q_score_desc'] = $motCleLike;
            $params['q_score_prenom'] = $motCleLike;
            $params['q_score_nom'] = $motCleLike;
            $params['q_score_nomcomplet'] = $motCleLike;
            $params['q_score_comp'] = $motCleLike;
        } else {
            $orderBySql = "p.date_maj DESC, p.id_profil DESC";
        }
    } elseif ($tri === 'note') {
        // Tri : note moyenne décroissante (les profils sans avis en dernier)
        $orderBySql = "CASE WHEN notes.note_moyenne IS NULL THEN 1 ELSE 0 END ASC, notes.note_moyenne DESC, COALESCE(notes.nb_avis, 0) DESC, p.date_maj DESC, p.id_profil DESC";
    } else {
        // Tri : récents = date_maj décroissante
        $orderBySql = "p.date_maj DESC, p.date_validation DESC, p.id_profil DESC";
    }

    // Requête préparée avec agrégation des avis publiés et compétences
    $sql = "
        SELECT 
            p.id_profil,
            p.id_utilisateur,
            p.id_categorie,
            p.titre,
            p.description,
            p.zone,
            p.disponibilite,
            p.photo,
            p.statut_validation,
            p.date_validation,
            p.engagement_cyber,
            u.prenom,
            u.nom,
            c.libelle AS categorie_libelle,
            c.slug AS categorie_slug,
            notes.note_moyenne,
            COALESCE(notes.nb_avis, 0) AS nb_avis,
            comps.competences_brutes
            {$selectScoreSql}
        FROM profil_professionnel p
        JOIN utilisateur u ON p.id_utilisateur = u.id_utilisateur
        LEFT JOIN categorie c ON p.id_categorie = c.id_categorie
        LEFT JOIN (
            SELECT 
                d.id_profil,
                ROUND(AVG(a.note), 1) AS note_moyenne,
                COUNT(a.id_avis) AS nb_avis
            FROM avis a
            JOIN demande_service d ON a.id_demande = d.id_demande
            WHERE a.statut = 'publie'
            GROUP BY d.id_profil
        ) notes ON p.id_profil = notes.id_profil
        LEFT JOIN (
            SELECT 
                pc.id_profil,
                GROUP_CONCAT(comp.libelle ORDER BY 
                    CASE pc.niveau_declare 
                        WHEN 'expert' THEN 4 
                        WHEN 'avance' THEN 3 
                        WHEN 'intermediaire' THEN 2 
                        ELSE 1 
                    END DESC, comp.libelle ASC SEPARATOR '||') AS competences_brutes
            FROM profil_competence pc
            JOIN competence comp ON pc.id_competence = comp.id_competence
            GROUP BY pc.id_profil
        ) comps ON p.id_profil = comps.id_profil
        WHERE {$whereSql}
        ORDER BY {$orderBySql}
        LIMIT {$limite} OFFSET {$decalage}
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $lignes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $profils = [];
    foreach ($lignes as $ligne) {
        // Extraction des 3 compétences principales
        $competences = [];
        if (!empty($ligne['competences_brutes'])) {
            $toutesComps = explode('||', (string) $ligne['competences_brutes']);
            $competences = array_slice($toutesComps, 0, 3);
        }

        // Formatage du nom affiché : Prénom + initiale du Nom + '.'
        $prenom = trim((string) $ligne['prenom']);
        $nom = trim((string) $ligne['nom']);
        $initialeNom = $nom !== '' ? mb_strtoupper(mb_substr($nom, 0, 1, 'UTF-8'), 'UTF-8') . '.' : '';
        $nomAffiche = trim($prenom . ' ' . $initialeNom);

        $profils[] = [
            'id_profil'          => (int) $ligne['id_profil'],
            'id_utilisateur'     => (int) $ligne['id_utilisateur'],
            'id_categorie'       => (int) $ligne['id_categorie'],
            'prenom'             => $prenom,
            'nom'                => $nom,
            'nom_affiche'        => $nomAffiche,
            'titre'              => (string) $ligne['titre'],
            'description'        => (string) $ligne['description'],
            'zone'               => (string) $ligne['zone'],
            'disponibilite'      => (string) $ligne['disponibilite'],
            'photo'              => !empty($ligne['photo']) ? (string) $ligne['photo'] : null,
            'statut_validation'  => (string) $ligne['statut_validation'],
            'categorie_libelle'  => (string) ($ligne['categorie_libelle'] ?? 'Général'),
            'categorie_slug'     => (string) ($ligne['categorie_slug'] ?? ''),
            'competences'        => $competences,
            'note_moyenne'       => $ligne['note_moyenne'] !== null ? (float) $ligne['note_moyenne'] : null,
            'nb_avis'            => (int) $ligne['nb_avis'],
            'engagement_cyber'   => !empty($ligne['engagement_cyber']),
        ];
    }

    return $profils;
}

/**
 * Compte le nombre total de profils publics approuvés répondant aux critères (RG05).
 *
 * @param PDO   $pdo     Instance active de connexion PDO
 * @param array $filtres Filtres optionnels (categorie, zone, disponibilite, competence, q/recherche)
 * @return int Nombre total de profils approuvés correspondants
 */
function compter_profils_publics(PDO $pdo, array $filtres = []): int
{
    $conditions = [
        "p.statut_validation = 'approuve'",
        "u.statut_compte = 'actif'",
    ];
    $params = [];

    if (!empty($filtres['categorie'])) {
        if (is_numeric($filtres['categorie'])) {
            $conditions[] = "p.id_categorie = :id_cat";
            $params['id_cat'] = (int) $filtres['categorie'];
        } else {
            $conditions[] = "c.slug = :slug_cat";
            $params['slug_cat'] = (string) $filtres['categorie'];
        }
    }

    if (!empty($filtres['zone'])) {
        $zoneNettoyee = trim((string) $filtres['zone']);
        if ($zoneNettoyee !== '') {
            $conditions[] = "p.zone LIKE :zone";
            $params['zone'] = '%' . addcslashes($zoneNettoyee, '%_\\') . '%';
        }
    }

    if (!empty($filtres['disponibilite']) && in_array($filtres['disponibilite'], ['disponible', 'limitee', 'indisponible'], true)) {
        $conditions[] = "p.disponibilite = :disponibilite";
        $params['disponibilite'] = (string) $filtres['disponibilite'];
    }

    if (!empty($filtres['competence']) && (is_numeric($filtres['competence']) || ctype_digit((string) $filtres['competence']))) {
        $idCompetence = (int) $filtres['competence'];
        if ($idCompetence > 0) {
            $conditions[] = "EXISTS (
                SELECT 1 FROM profil_competence pc_filtre 
                WHERE pc_filtre.id_profil = p.id_profil 
                AND pc_filtre.id_competence = :id_comp_filtre
            )";
            $params['id_comp_filtre'] = $idCompetence;
        }
    }

    $motCleBrut = trim((string) ($filtres['q'] ?? $filtres['recherche'] ?? ''));
    if ($motCleBrut !== '') {
        $motCleEchappe = addcslashes($motCleBrut, '%_\\');
        $motCleLike = '%' . $motCleEchappe . '%';
        $conditions[] = "(
            p.titre LIKE :q_titre 
            OR p.description LIKE :q_desc 
            OR u.prenom LIKE :q_prenom 
            OR u.nom LIKE :q_nom 
            OR CONCAT(u.prenom, ' ', u.nom) LIKE :q_nomcomplet 
            OR comps.competences_brutes LIKE :q_comp
        )";
        $params['q_titre'] = $motCleLike;
        $params['q_desc'] = $motCleLike;
        $params['q_prenom'] = $motCleLike;
        $params['q_nom'] = $motCleLike;
        $params['q_nomcomplet'] = $motCleLike;
        $params['q_comp'] = $motCleLike;
    }

    $whereSql = implode(' AND ', $conditions);

    $sql = "
        SELECT COUNT(DISTINCT p.id_profil)
        FROM profil_professionnel p
        JOIN utilisateur u ON p.id_utilisateur = u.id_utilisateur
        LEFT JOIN categorie c ON p.id_categorie = c.id_categorie
        LEFT JOIN (
            SELECT 
                pc.id_profil,
                GROUP_CONCAT(comp.libelle SEPARATOR '||') AS competences_brutes
            FROM profil_competence pc
            JOIN competence comp ON pc.id_competence = comp.id_competence
            GROUP BY pc.id_profil
        ) comps ON p.id_profil = comps.id_profil
        WHERE {$whereSql}
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

/**
 * Envoie un en-tête HTTP 404, affiche la page 404.php et interrompt l'exécution.
 *
 * @param string|null $message Message d'explication optionnel
 * @param string|null $titre   Titre de l'erreur optionnel
 * @return never
 */
function page_introuvable(?string $message = null, ?string $titre = null): void
{
    http_response_code(404);
    $message404 = $message;
    $titre404 = $titre;
    require __DIR__ . '/../404.php';
    exit;
}


