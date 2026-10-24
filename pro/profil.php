<?php
/**
 * Édition, complétude et soumission du profil professionnel — FindPro.
 * 
 * - Accès strictement réservé aux professionnels authentifiés (exiger_role('professionnel')).
 * - L'identifiant du profil est STRICTEMENT dérivé de la session ($_SESSION['utilisateur']['id']).
 * - Aucun paramètre d'URL n'est accepté pour cibler un profil (protection anti-IDOR).
 * - Enregistrement en brouillon possible même si incomplet.
 * - Bouton "Soumettre mon profil" avec CSRF et contrôle rigoureux de complétude côté serveur :
 *     * Catégorie choisie
 *     * Titre professionnel
 *     * Description d'au moins 100 caractères
 *     * Ville / zone d'intervention
 *     * Disponibilité
 *     * Au moins 3 compétences déclarées dans le métier
 *     * Au moins 1 projet dans le portfolio
 *     * Photo recommandée mais non obligatoire
 *     * Si catégorie Cybersécurité : engagement déontologique obligatoire enregistré avec horodatage
 * - En cas d'éléments manquants : refus de soumission avec liste précise des éléments manquants.
 * - Si complet : statut passe à 'soumis', date_soumission mise à jour à NOW(), profil non public tant que non approuvé.
 * - Règle de resoumission : un profil 'soumis', 'en_verification', 'approuve' ou 'suspendu' ne peut pas être soumis à nouveau (sauf passage par 'a_corriger').
 * - Règle de lecture seule : tant que le profil est 'soumis' ou 'en_verification', le formulaire est verrouillé en lecture seule.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/upload.php';

// 1. Authentification stricte du professionnel
demarrer_session();
$utilisateur = exiger_role('professionnel');
$idUtilisateur = (int) $utilisateur['id'];

$pdo = db();

// 2. Récupération du profil professionnel existant (toujours via l'ID de session)
$stmtProfil = $pdo->prepare('
    SELECT p.*, c.libelle AS categorie_libelle, c.slug AS categorie_slug
    FROM profil_professionnel p
    LEFT JOIN categorie c ON p.id_categorie = c.id_categorie
    WHERE p.id_utilisateur = :id_user
    LIMIT 1
');
$stmtProfil->execute(['id_user' => $idUtilisateur]);
$profil = $stmtProfil->fetch();

// Statut courant et vérification de la lecture seule
$statutCourant = $profil ? (string) $profil['statut_validation'] : 'brouillon';
$estLectureSeule = in_array($statutCourant, ['soumis', 'en_verification', 'refuse'], true);

// Récupération de la dernière décision administrative pour afficher le motif
$derniereDecision = null;
if ($profil) {
    $stmtLastDec = $pdo->prepare('
        SELECT * FROM validation_profil 
        WHERE id_profil = :id_profil 
        ORDER BY date_decision DESC, id_validation DESC 
        LIMIT 1
    ');
    $stmtLastDec->execute(['id_profil' => (int) $profil['id_profil']]);
    $derniereDecision = $stmtLastDec->fetch();
}

// 3. Chargement des catégories disponibles
$categories = $pdo->query('SELECT id_categorie, libelle, slug FROM categorie ORDER BY libelle ASC')->fetchAll();

// 4. Initialisation des champs du formulaire de profil
$idCategorie = $profil ? (int) $profil['id_categorie'] : ($categories[0]['id_categorie'] ?? 0);
$titre = $profil ? (string) $profil['titre'] : '';
$description = $profil ? (string) $profil['description'] : '';
$zone = $profil ? (string) $profil['zone'] : '';
$disponibilite = $profil ? (string) $profil['disponibilite'] : 'disponible';
$photoActuelle = $profil && !empty($profil['photo']) ? (string) $profil['photo'] : null;
$statutValidation = $statutCourant;
$engagementCyber = $profil ? (bool) ($profil['engagement_cyber'] ?? false) : false;
$dateEngagementCyber = $profil ? ($profil['date_engagement_cyber'] ?? null) : null;

$erreurs = [];

// 5. Traitement des requêtes POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifier();

    // RÈGLE MÉTIER : Si le profil est définitivement refusé
    if ($statutCourant === 'refuse') {
        flash_set('danger', 'Votre profil a fait l\'objet d\'un rejet définitif. Aucune nouvelle soumission n\'est possible sans nouvelle autorisation de l\'administration.');
        redirect(url('pro/profil.php'));
    }

    // RÈGLE MÉTIER : Tant que le profil est soumis ou en_verification, le formulaire est en lecture seule
    if ($estLectureSeule) {
        flash_set('warning', 'Votre profil est actuellement en cours d\'examen par notre équipe. Les modifications sont verrouillées en lecture seule.');
        redirect(url('pro/profil.php'));
    }

    $action = (string) ($_POST['action'] ?? 'brouillon');

    // -------------------------------------------------------------------------
    // CAS A : Ajout d'une compétence
    // -------------------------------------------------------------------------
    if ($action === 'ajouter_competence') {
        if (!$profil) {
            flash_set('warning', 'Veuillez d\'abord enregistrer votre profil ci-dessous avant d\'y associer des compétences.');
            redirect(url('pro/profil.php'));
        }

        $idProfil = (int) $profil['id_profil'];
        $idCompetence = (int) ($_POST['id_competence'] ?? 0);
        $niveauDeclare = (string) ($_POST['niveau_declare'] ?? 'intermediaire');

        if (!in_array($niveauDeclare, ['debutant', 'intermediaire', 'avance', 'expert'], true)) {
            $niveauDeclare = 'intermediaire';
        }

        $stmtCountComp = $pdo->prepare('SELECT COUNT(*) FROM profil_competence WHERE id_profil = :id_profil');
        $stmtCountComp->execute(['id_profil' => $idProfil]);
        $totalCompetences = (int) $stmtCountComp->fetchColumn();

        if ($totalCompetences >= 15) {
            flash_set('danger', 'Limite atteinte : vous ne pouvez pas associer plus de 15 compétences à votre profil.');
            redirect(url('pro/profil.php#competences'));
        }

        $stmtVerifCompCat = $pdo->prepare('
            SELECT COUNT(*) 
            FROM competence 
            WHERE id_competence = :id_comp AND id_categorie = :id_cat
        ');
        $stmtVerifCompCat->execute([
            'id_comp' => $idCompetence,
            'id_cat'  => (int) $profil['id_categorie'],
        ]);

        if ((int) $stmtVerifCompCat->fetchColumn() === 0) {
            flash_set('danger', 'Cette compétence n\'appartient pas à votre corps de métier actuel.');
            redirect(url('pro/profil.php#competences'));
        }

        $stmtVerifDoublon = $pdo->prepare('
            SELECT COUNT(*) 
            FROM profil_competence 
            WHERE id_profil = :id_profil AND id_competence = :id_comp
        ');
        $stmtVerifDoublon->execute([
            'id_profil' => $idProfil,
            'id_comp'   => $idCompetence,
        ]);

        if ((int) $stmtVerifDoublon->fetchColumn() > 0) {
            flash_set('warning', 'Cette compétence est déjà présente dans votre profil.');
            redirect(url('pro/profil.php#competences'));
        }

        $stmtInsertComp = $pdo->prepare('
            INSERT INTO profil_competence (id_profil, id_competence, niveau_declare)
            VALUES (:id_profil, :id_comp, :niveau)
        ');
        $stmtInsertComp->execute([
            'id_profil' => $idProfil,
            'id_comp'   => $idCompetence,
            'niveau'    => $niveauDeclare,
        ]);

        flash_set('success', 'Compétence ajoutée avec succès à votre profil.');
        redirect(url('pro/profil.php#competences'));
    }

    // -------------------------------------------------------------------------
    // CAS B : Suppression d'une compétence
    // -------------------------------------------------------------------------
    if ($action === 'supprimer_competence') {
        if (!$profil) {
            redirect(url('pro/profil.php'));
        }

        $idProfil = (int) $profil['id_profil'];
        $idCompetence = (int) ($_POST['id_competence'] ?? 0);

        $stmtSupprComp = $pdo->prepare('
            DELETE FROM profil_competence 
            WHERE id_profil = :id_profil AND id_competence = :id_comp
        ');
        $stmtSupprComp->execute([
            'id_profil' => $idProfil,
            'id_comp'   => $idCompetence,
        ]);

        if ($stmtSupprComp->rowCount() > 0) {
            flash_set('info', 'Compétence retirée de votre profil.');
        }

        redirect(url('pro/profil.php#competences'));
    }

    // -------------------------------------------------------------------------
    // CAS C : Enregistrement Brouillon OU Soumission du profil
    // -------------------------------------------------------------------------
    
    // RÈGLE DE RESOUMISSION :
    // Un profil soumis, en_verification, approuve ou suspendu ne peut pas être soumis à nouveau (sauf passage par a_corriger)
    if ($action === 'soumettre' && $profil) {
        if (in_array($statutCourant, ['soumis', 'en_verification', 'approuve', 'suspendu'], true)) {
            if ($statutCourant === 'approuve') {
                flash_set('info', 'Votre profil est déjà validé et actif publiquement. Il ne peut pas être soumis à nouveau.');
            } elseif ($statutCourant === 'suspendu') {
                flash_set('danger', 'Votre profil est actuellement suspendu. Veuillez contacter le support.');
            } else {
                flash_set('warning', 'Votre profil est déjà en cours de vérification par nos modérateurs.');
            }
            redirect(url('pro/profil.php'));
        }
    }

    $idCategorie = (int) ($_POST['id_categorie'] ?? 0);
    $titre = trim((string) ($_POST['titre'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $zone = trim((string) ($_POST['zone'] ?? ''));
    $disponibiliteSaisie = (string) ($_POST['disponibilite'] ?? 'disponible');
    $engagementCyberSaisie = !empty($_POST['engagement_cyber']);

    if (in_array($disponibiliteSaisie, ['disponible', 'limitee', 'indisponible'], true)) {
        $disponibilite = $disponibiliteSaisie;
    } else {
        $disponibilite = 'disponible';
    }

    // Vérification de la catégorie sélectionnée
    $stmtVerifCat = $pdo->prepare('SELECT id_categorie, libelle, slug FROM categorie WHERE id_categorie = :id');
    $stmtVerifCat->execute(['id' => $idCategorie]);
    $catSelectionnee = $stmtVerifCat->fetch();
    if (!$catSelectionnee) {
        $erreurs['id_categorie'] = 'Veuillez sélectionner une catégorie valide.';
    }

    if (mb_strlen($titre) > 120) {
        $erreurs['titre'] = 'Le titre ne peut pas dépasser 120 caractères.';
    }

    if (mb_strlen($description) > 2000) {
        $erreurs['description'] = 'La description ne peut pas dépasser 2000 caractères.';
    }

    if (mb_strlen($zone) > 120) {
        $erreurs['zone'] = 'La zone d\'intervention ne peut pas dépasser 120 caractères.';
    }

    // Gestion du téléversement de la photo de profil (optionnelle)
    $nouvellePhotoChemin = null;
    $supprimerPhotoDemandee = isset($_POST['supprimer_photo']) && $_POST['supprimer_photo'] === '1';

    if (isset($_FILES['photo']) && is_array($_FILES['photo']) && (int) ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        try {
            $nouvellePhotoChemin = televerser_image($_FILES['photo'], 'avatars');

            if ($photoActuelle !== null) {
                $cheminAncien = realpath(__DIR__ . '/../' . $photoActuelle);
                $racineUploads = realpath(__DIR__ . '/../uploads');
                if ($cheminAncien !== false && $racineUploads !== false && str_starts_with($cheminAncien, $racineUploads) && file_exists($cheminAncien)) {
                    @unlink($cheminAncien);
                }
            }
            $photoActuelle = $nouvellePhotoChemin;
        } catch (Exception $e) {
            $erreurs['photo'] = $e->getMessage();
        }
    } elseif ($supprimerPhotoDemandee && $photoActuelle !== null) {
        $cheminAncien = realpath(__DIR__ . '/../' . $photoActuelle);
        $racineUploads = realpath(__DIR__ . '/../uploads');
        if ($cheminAncien !== false && $racineUploads !== false && str_starts_with($cheminAncien, $racineUploads) && file_exists($cheminAncien)) {
            @unlink($cheminAncien);
        }
        $photoActuelle = null;
    }

    // Vérifier si la catégorie sélectionnée relève de la cybersécurité
    $slugCat = $catSelectionnee ? (string) ($catSelectionnee['slug'] ?? '') : '';
    $libelleCat = $catSelectionnee ? (string) ($catSelectionnee['libelle'] ?? '') : '';
    $estCatCyber = ($slugCat === 'cybersecurite' || str_contains(mb_strtolower($libelleCat), 'cyber'));

    // -------------------------------------------------------------------------
    // VÉRIFICATION DE COMPLÉTUDE CÔTÉ SERVEUR POUR SOUMISSION
    // -------------------------------------------------------------------------
    if ($action === 'soumettre') {
        $elementsManquants = [];

        // 1. Catégorie choisie
        if (!$catSelectionnee) {
            $elementsManquants[] = 'Une catégorie de métier valide doit être choisie.';
        }

        // 2. Titre professionnel
        if ($titre === '') {
            $elementsManquants[] = 'Un titre professionnel clair (ex: Développeur PHP Fullstack, Auditeur de sécurité).';
        }

        // 3. Description d\'au moins 100 caractères
        $longueurDesc = mb_strlen($description);
        if ($longueurDesc < 100) {
            $elementsManquants[] = 'Une présentation détaillée d\'au moins 100 caractères (actuellement ' . $longueurDesc . ' caractère' . ($longueurDesc > 1 ? 's' : '') . ').';
        }

        // 4. Zone / Ville
        if ($zone === '') {
            $elementsManquants[] = 'Une ville ou zone d\'intervention (ex: Abidjan - Cocody, Télétravail).';
        }

        // 5. Disponibilité
        if (!in_array($disponibiliteSaisie, ['disponible', 'limitee', 'indisponible'], true)) {
            $elementsManquants[] = 'Votre statut de disponibilité pour de nouvelles missions.';
        }

        // 6. Au moins 3 compétences déclarées pour cette catégorie
        $idProfilActuel = $profil ? (int) $profil['id_profil'] : null;
        $nbCompetencesValides = 0;
        if ($idProfilActuel) {
            $stmtCountCompCat = $pdo->prepare('
                SELECT COUNT(*) 
                FROM profil_competence pc
                JOIN competence c ON pc.id_competence = c.id_competence
                WHERE pc.id_profil = :id_profil AND c.id_categorie = :id_cat
            ');
            $stmtCountCompCat->execute([
                'id_profil' => $idProfilActuel,
                'id_cat'    => $idCategorie,
            ]);
            $nbCompetencesValides = (int) $stmtCountCompCat->fetchColumn();
        }
        if ($nbCompetencesValides < 3) {
            $elementsManquants[] = 'Au moins 3 compétences associées à votre métier (actuellement ' . $nbCompetencesValides . ' compétence' . ($nbCompetencesValides > 1 ? 's' : '') . ').';
        }

        // 7. Au moins 1 projet de portfolio
        $nbProjetsPortfolio = 0;
        if ($idProfilActuel) {
            $stmtCountPort = $pdo->prepare('SELECT COUNT(*) FROM projet_portfolio WHERE id_profil = :id_profil');
            $stmtCountPort->execute(['id_profil' => $idProfilActuel]);
            $nbProjetsPortfolio = (int) $stmtCountPort->fetchColumn();
        }
        if ($nbProjetsPortfolio < 1) {
            $elementsManquants[] = 'Au moins 1 projet ou réalisation dans votre portfolio (actuellement 0).';
        }

        // 8. Photo recommandée mais NON obligatoire (ne bloque pas la soumission)

        // 9. Si catégorie Cybersécurité : exige la case cochée
        if ($estCatCyber && !$engagementCyberSaisie) {
            $elementsManquants[] = 'Engagement déontologique obligatoire en cybersécurité : vous devez cocher la mention « Je m\'engage à ne proposer que des prestations autorisées par le propriétaire du système concerné (aucun piratage, aucun accès non autorisé) ».';
        }

        // SI QUELQUE CHOSE MANQUE : NE SOUMET PAS, AFFICHE LA LISTE PRÉCISE DES ÉLÉMENTS MANQUANTS
        if (!empty($elementsManquants)) {
            // Sauvegarder les données saisies en brouillon pour ne pas perdre la saisie
            if (empty($erreurs)) {
                if (!$profil) {
                    $stmtInsertDraft = $pdo->prepare('
                        INSERT INTO profil_professionnel (
                            id_utilisateur, id_categorie, titre, description, zone, disponibilite, photo, statut_validation, date_soumission, engagement_cyber, date_engagement_cyber
                        ) VALUES (
                            :id_user, :id_cat, :titre, :description, :zone, :dispo, :photo, \'brouillon\', NULL, :engagement_cyber, :date_engagement_cyber
                        )
                    ');
                    $stmtInsertDraft->execute([
                        'id_user'                => $idUtilisateur,
                        'id_cat'                 => $idCategorie,
                        'titre'                  => $titre !== '' ? $titre : 'Professionnel du numérique',
                        'description'            => $description,
                        'zone'                   => $zone !== '' ? $zone : 'Abidjan',
                        'dispo'                  => $disponibilite,
                        'photo'                  => $photoActuelle,
                        'engagement_cyber'       => ($estCatCyber && $engagementCyberSaisie) ? 1 : 0,
                        'date_engagement_cyber'  => ($estCatCyber && $engagementCyberSaisie) ? date('Y-m-d H:i:s') : null,
                    ]);
                } else {
                    $cyberFlagDraft = ($estCatCyber && $engagementCyberSaisie) ? 1 : (int) ($profil['engagement_cyber'] ?? 0);
                    $dateCyberDraft = ($cyberFlagDraft === 1) ? ($profil['date_engagement_cyber'] ?? date('Y-m-d H:i:s')) : null;

                    $stmtUpdateDraft = $pdo->prepare('
                        UPDATE profil_professionnel SET
                            id_categorie = :id_cat,
                            titre = :titre,
                            description = :description,
                            zone = :zone,
                            disponibilite = :dispo,
                            photo = :photo,
                            engagement_cyber = :engagement_cyber,
                            date_engagement_cyber = :date_engagement_cyber
                        WHERE id_utilisateur = :id_user
                    ');
                    $stmtUpdateDraft->execute([
                        'id_cat'                 => $idCategorie,
                        'titre'                  => $titre,
                        'description'            => $description,
                        'zone'                   => $zone,
                        'dispo'                  => $disponibilite,
                        'photo'                  => $photoActuelle,
                        'engagement_cyber'       => $cyberFlagDraft,
                        'date_engagement_cyber'  => $dateCyberDraft,
                        'id_user'                => $idUtilisateur,
                    ]);
                }
            }

            $_SESSION['profil_elements_manquants'] = $elementsManquants;
            flash_set('danger', 'Votre profil est incomplet pour être soumis à validation. Veuillez compléter les ' . count($elementsManquants) . ' élément(s) manquant(s) listé(s) ci-dessous.');
            redirect(url('pro/profil.php'));
        }

        // SI TOUT EST COMPLET : statut passe à 'soumis', date_soumission mise à jour, confirmation
        if (empty($erreurs)) {
            if (!$profil) {
                $stmtInsert = $pdo->prepare('
                    INSERT INTO profil_professionnel (
                        id_utilisateur, id_categorie, titre, description, zone, disponibilite, photo, statut_validation, date_soumission, engagement_cyber, date_engagement_cyber
                    ) VALUES (
                        :id_user, :id_cat, :titre, :description, :zone, :dispo, :photo, \'soumis\', NOW(), :engagement_cyber, :date_engagement_cyber
                    )
                ');
                $stmtInsert->execute([
                    'id_user'                => $idUtilisateur,
                    'id_cat'                 => $idCategorie,
                    'titre'                  => $titre,
                    'description'            => $description,
                    'zone'                   => $zone,
                    'dispo'                  => $disponibilite,
                    'photo'                  => $photoActuelle,
                    'engagement_cyber'       => ($estCatCyber && $engagementCyberSaisie) ? 1 : 0,
                    'date_engagement_cyber'  => ($estCatCyber && $engagementCyberSaisie) ? date('Y-m-d H:i:s') : null,
                ]);
            } else {
                $ancienneCategorie = (int) $profil['id_categorie'];
                if ($ancienneCategorie !== $idCategorie) {
                    $stmtPurge = $pdo->prepare('
                        DELETE FROM profil_competence 
                        WHERE id_profil = :id_profil 
                          AND id_competence IN (
                              SELECT id_competence FROM competence WHERE id_categorie != :nouveau_id_cat OR id_categorie IS NULL
                          )
                    ');
                    $stmtPurge->execute([
                        'id_profil'       => (int) $profil['id_profil'],
                        'nouveau_id_cat'  => $idCategorie,
                    ]);
                }

                $cyberFlagSubmit = ($estCatCyber && $engagementCyberSaisie) ? 1 : 0;
                $dateCyberSubmit = ($cyberFlagSubmit === 1) ? ($profil['date_engagement_cyber'] ?? date('Y-m-d H:i:s')) : null;

                $stmtUpdate = $pdo->prepare('
                    UPDATE profil_professionnel SET
                        id_categorie = :id_cat,
                        titre = :titre,
                        description = :description,
                        zone = :zone,
                        disponibilite = :dispo,
                        photo = :photo,
                        statut_validation = \'soumis\',
                        date_soumission = NOW(),
                        engagement_cyber = :engagement_cyber,
                        date_engagement_cyber = :date_engagement_cyber
                    WHERE id_utilisateur = :id_user
                ');
                $stmtUpdate->execute([
                    'id_cat'                 => $idCategorie,
                    'titre'                  => $titre,
                    'description'            => $description,
                    'zone'                   => $zone,
                    'dispo'                  => $disponibilite,
                    'photo'                  => $photoActuelle,
                    'engagement_cyber'       => $cyberFlagSubmit,
                    'date_engagement_cyber'  => $dateCyberSubmit,
                    'id_user'                => $idUtilisateur,
                ]);
            }

            unset($_SESSION['profil_elements_manquants']);
            flash_set('success', 'Félicitations ! Votre profil complet a été soumis avec succès à l\'équipe de modération FindPro. Il est désormais en attente de vérification et n\'apparaîtra publiquement qu\'après approbation administrative.');
            redirect(url('pro/profil.php'));
        }
    }

    // -------------------------------------------------------------------------
    // ENREGISTREMENT EN MODE BROUILLON
    // -------------------------------------------------------------------------
    if ($action === 'brouillon' && empty($erreurs)) {
        $nouveauStatut = $statutValidation;
        $messageRg06Declenche = false;
        $nbCompetencesPurgees = 0;

        // RÈGLE RG06 : Si le profil était déjà approuvé et qu'une information clé est modifiée
        if ($profil && $profil['statut_validation'] === 'approuve') {
            $aModifieCategorie = ((int) $profil['id_categorie'] !== $idCategorie);
            $aModifieTitre = ($profil['titre'] !== $titre);
            $aModifieDescription = ($profil['description'] !== $description);

            if ($aModifieCategorie || $aModifieTitre || $aModifieDescription) {
                $nouveauStatut = 'soumis';
                $messageRg06Declenche = true;
            }
        } elseif (!$profil || $profil['statut_validation'] === 'brouillon' || $profil['statut_validation'] === 'a_corriger') {
            $nouveauStatut = 'brouillon';
        }

        if (!$profil) {
            $stmtInsert = $pdo->prepare('
                INSERT INTO profil_professionnel (
                    id_utilisateur, id_categorie, titre, description, zone, disponibilite, photo, statut_validation, date_soumission, engagement_cyber, date_engagement_cyber
                ) VALUES (
                    :id_user, :id_cat, :titre, :description, :zone, :dispo, :photo, :statut, :date_soumission, :engagement_cyber, :date_engagement_cyber
                )
            ');
            $stmtInsert->execute([
                'id_user'                => $idUtilisateur,
                'id_cat'                 => $idCategorie,
                'titre'                  => $titre !== '' ? $titre : 'Professionnel du numérique',
                'description'            => $description,
                'zone'                   => $zone !== '' ? $zone : 'Abidjan',
                'dispo'                  => $disponibilite,
                'photo'                  => $photoActuelle,
                'statut'                 => $nouveauStatut,
                'date_soumission'        => ($nouveauStatut === 'soumis') ? date('Y-m-d H:i:s') : null,
                'engagement_cyber'       => ($estCatCyber && $engagementCyberSaisie) ? 1 : 0,
                'date_engagement_cyber'  => ($estCatCyber && $engagementCyberSaisie) ? date('Y-m-d H:i:s') : null,
            ]);

            flash_set('success', 'Votre profil a été créé et enregistré en mode brouillon.');
        } else {
            $idProfil = (int) $profil['id_profil'];
            $ancienneCategorie = (int) $profil['id_categorie'];

            if ($ancienneCategorie !== $idCategorie) {
                $stmtPurge = $pdo->prepare('
                    DELETE FROM profil_competence 
                    WHERE id_profil = :id_profil 
                      AND id_competence IN (
                          SELECT id_competence FROM competence WHERE id_categorie != :nouveau_id_cat OR id_categorie IS NULL
                      )
                ');
                $stmtPurge->execute([
                    'id_profil'       => $idProfil,
                    'nouveau_id_cat'  => $idCategorie,
                ]);
                $nbCompetencesPurgees = $stmtPurge->rowCount();
            }

            $cyberFlagBrouillon = ($estCatCyber && $engagementCyberSaisie) ? 1 : (int) ($profil['engagement_cyber'] ?? 0);
            $dateCyberBrouillon = ($cyberFlagBrouillon === 1) ? ($profil['date_engagement_cyber'] ?? date('Y-m-d H:i:s')) : null;
            $dateSoumBrouillon = ($nouveauStatut === 'soumis') ? date('Y-m-d H:i:s') : ($profil['date_soumission'] ?? null);

            $stmtUpdate = $pdo->prepare('
                UPDATE profil_professionnel SET
                    id_categorie = :id_cat,
                    titre = :titre,
                    description = :description,
                    zone = :zone,
                    disponibilite = :dispo,
                    photo = :photo,
                    statut_validation = :statut,
                    date_soumission = :date_soumission,
                    engagement_cyber = :engagement_cyber,
                    date_engagement_cyber = :date_engagement_cyber
                WHERE id_utilisateur = :id_user
            ');
            $stmtUpdate->execute([
                'id_cat'                 => $idCategorie,
                'titre'                  => $titre,
                'description'            => $description,
                'zone'                   => $zone,
                'dispo'                  => $disponibilite,
                'photo'                  => $photoActuelle,
                'statut'                 => $nouveauStatut,
                'date_soumission'        => $dateSoumBrouillon,
                'engagement_cyber'       => $cyberFlagBrouillon,
                'date_engagement_cyber'  => $dateCyberBrouillon,
                'id_user'                => $idUtilisateur,
            ]);

            if ($nbCompetencesPurgees > 0) {
                flash_set('info', "Votre corps de métier principal a été modifié : {$nbCompetencesPurgees} compétence(s) ne correspondant plus à cette catégorie ont été automatiquement retirées de votre profil.");
            }

            if ($messageRg06Declenche) {
                flash_set('warning', 'Vos modifications sur vos informations clés nécessitent une nouvelle vérification administrative. Votre profil est temporairement retiré de la recherche publique.');
            } else {
                flash_set('success', 'Vos modifications ont été enregistrées avec succès en mode brouillon.');
            }
        }

        redirect(url('pro/profil.php'));
    }
}

// 6. Récupération des éléments manquants stockés en session
$elementsManquantsAffiches = $_SESSION['profil_elements_manquants'] ?? [];
unset($_SESSION['profil_elements_manquants']);

// 7. Chargement des compétences associées et disponibles (si profil existant)
$competencesProfil = [];
$competencesDisponibles = [];
$totalCompetencesProfil = 0;
$totalProjetsPortfolio = 0;

if ($profil) {
    $idProfil = (int) $profil['id_profil'];
    $catActuelle = (int) $profil['id_categorie'];

    $stmtMesComp = $pdo->prepare('
        SELECT c.id_competence, c.libelle, pc.niveau_declare 
        FROM profil_competence pc
        JOIN competence c ON pc.id_competence = c.id_competence
        WHERE pc.id_profil = :id_profil
        ORDER BY c.libelle ASC
    ');
    $stmtMesComp->execute(['id_profil' => $idProfil]);
    $competencesProfil = $stmtMesComp->fetchAll();
    $totalCompetencesProfil = count($competencesProfil);

    $stmtCompDispo = $pdo->prepare('
        SELECT id_competence, libelle 
        FROM competence 
        WHERE id_categorie = :id_cat 
          AND id_competence NOT IN (
              SELECT id_competence FROM profil_competence WHERE id_profil = :id_profil
          )
        ORDER BY libelle ASC
    ');
    $stmtCompDispo->execute([
        'id_cat'    => $catActuelle,
        'id_profil' => $idProfil,
    ]);
    $competencesDisponibles = $stmtCompDispo->fetchAll();

    // Nombre de projets de portfolio
    $stmtCountPort = $pdo->prepare('SELECT COUNT(*) FROM projet_portfolio WHERE id_profil = :id_profil');
    $stmtCountPort->execute(['id_profil' => $idProfil]);
    $totalProjetsPortfolio = (int) $stmtCountPort->fetchColumn();
}

// 8. Configuration visuelle du badge de statut
$badgesStatut = [
    'brouillon'       => ['label' => 'Brouillon', 'class' => 'bg-secondary text-white', 'icon' => 'bi-pencil-square'],
    'soumis'          => ['label' => 'En attente de validation', 'class' => 'bg-info text-dark', 'icon' => 'bi-hourglass-split'],
    'en_verification' => ['label' => 'En cours d\'examen', 'class' => 'bg-warning text-dark', 'icon' => 'bi-search'],
    'a_corriger'      => ['label' => 'Corrections demandées', 'class' => 'bg-warning text-dark', 'icon' => 'bi-exclamation-circle'],
    'approuve'        => ['label' => 'Profil vérifié & public', 'class' => 'bg-success text-white', 'icon' => 'bi-check-circle-fill'],
    'refuse'          => ['label' => 'Profil refusé', 'class' => 'bg-danger text-white', 'icon' => 'bi-x-circle-fill'],
    'suspendu'        => ['label' => 'Profil suspendu', 'class' => 'bg-dark text-white', 'icon' => 'bi-slash-circle-fill'],
];
$badgeActuel = $badgesStatut[$statutCourant] ?? $badgesStatut['brouillon'];

$labelsNiveaux = [
    'debutant'      => ['label' => 'Débutant', 'badge' => 'bg-light text-secondary border'],
    'intermediaire' => ['label' => 'Intermédiaire', 'badge' => 'bg-info bg-opacity-10 text-info border border-info border-opacity-25'],
    'avance'        => ['label' => 'Avancé', 'badge' => 'bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25'],
    'expert'        => ['label' => 'Expert', 'badge' => 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25'],
];

// Vérification de la présence de l'avatar par défaut
$cheminAvatarDefautRel = 'assets/img/avatar-defaut.svg';
$avatarDefautExiste = file_exists(__DIR__ . '/../' . $cheminAvatarDefautRel);

// Vérifier si la catégorie courante est Cybersécurité
$slugCatCourante = '';
foreach ($categories as $c) {
    if ((int) $c['id_categorie'] === $idCategorie) {
        $slugCatCourante = (string) $c['slug'];
        break;
    }
}
$estCatCyberCourante = ($slugCatCourante === 'cybersecurite');

$titre_page = 'Mon profil professionnel';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4 py-lg-5">
    
    <!-- En-tête de page avec badge de statut dynamique -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-2 border-bottom">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h1 class="h3 fw-bold mb-0">Mon profil professionnel</h1>
                <span class="badge <?= e($badgeActuel['class']) ?> px-2.5 py-1.5 rounded-pill small">
                    <i class="bi <?= e($badgeActuel['icon']) ?> me-1"></i><?= e($badgeActuel['label']) ?>
                </span>
            </div>
            <p class="text-secondary small mb-0">
                Renseignez votre photo, vos compétences et votre portfolio pour attirer des clients sur FindPro.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="<?= url('pro/index.php') ?>" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Tableau de bord
            </a>
            <a href="<?= url('pro/competences.php') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-award me-1"></i>Mes compétences (<?= $totalCompetencesProfil ?>)
            </a>
            <a href="<?= url('pro/portfolio.php') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-images me-1"></i>Mon Portfolio (<?= $totalProjetsPortfolio ?>)
            </a>
        </div>
    </div>

    <!-- BANNIÈRE 1 : Rejet définitif (refuse) -->
    <?php if ($statutCourant === 'refuse'): ?>
        <div class="alert alert-danger d-flex align-items-start mb-4 shadow-sm border-danger" role="alert">
            <i class="bi bi-x-octagon-fill fs-3 text-danger me-3 flex-shrink-0"></i>
            <div>
                <strong class="d-block mb-1">Dossier de candidature refusé</strong>
                <p class="small mb-2">
                    Votre profil a fait l'objet d'un refus définitif par l'administration. Aucune nouvelle soumission n'est possible sans autorisation préalable de l'équipe de modération.
                </p>
                <?php if ($derniereDecision && !empty($derniereDecision['motif'])): ?>
                    <div class="p-2.5 bg-white rounded border border-danger-subtle small text-dark">
                        <strong class="text-danger d-block mb-1" style="font-size: 0.72rem;">MOTIF DU REFUS NOTIFIÉ :</strong>
                        <div style="white-space: pre-wrap; line-height: 1.5;"><?= e($derniereDecision['motif']) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <!-- BANNIÈRE 2 : Profil en cours d'examen (soumis ou en_verification) -->
    <?php elseif (in_array($statutCourant, ['soumis', 'en_verification'], true)): ?>
        <div class="alert alert-info d-flex align-items-start mb-4 shadow-sm border-info" role="alert">
            <i class="bi bi-shield-lock-fill fs-3 text-info me-3 flex-shrink-0"></i>
            <div>
                <strong class="d-block mb-1">Dossier en cours d'examen administratif (<?= e($badgeActuel['label']) ?>)</strong>
                <p class="small mb-0">
                    Votre profil a été soumis le <?= !empty($profil['date_soumission']) ? e(date('d/m/Y à H:i', strtotime($profil['date_soumission']))) : 'récemment' ?>.
                    Pendant toute la phase de vérification par notre équipe de modération, le formulaire est <strong>verrouillé en lecture seule</strong>. Votre profil n'apparaîtra publiquement dans la recherche qu'après validation.
                </p>
            </div>
        </div>
    <?php endif; ?>

    <!-- BANNIÈRE 3 : Corrections demandées (a_corriger) -->
    <?php if ($statutCourant === 'a_corriger'): ?>
        <div class="alert alert-warning d-flex align-items-start mb-4 shadow-sm border-warning" role="alert">
            <i class="bi bi-exclamation-triangle-fill fs-3 text-warning me-3 flex-shrink-0"></i>
            <div>
                <strong class="d-block mb-1">Ajustements requis sur votre profil</strong>
                <p class="small mb-2">
                    L'équipe de modération FindPro a demandé des corrections sur votre dossier. Votre formulaire est à nouveau <strong>en mode modifiable</strong>. Veuillez corriger vos informations selon les remarques ci-dessous, puis cliquer sur <strong>« Soumettre mon profil »</strong> pour relancer l'arbitrage.
                </p>
                <?php if ($derniereDecision && !empty($derniereDecision['motif'])): ?>
                    <div class="p-2.5 bg-white rounded border border-warning-subtle small text-dark">
                        <strong class="text-secondary d-block mb-1" style="font-size: 0.72rem;">INSTRUCTIONS DE L'ADMINISTRATION :</strong>
                        <div style="white-space: pre-wrap; line-height: 1.5;"><?= e($derniereDecision['motif']) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- BANNIÈRE 3 : Alerte si le profil est déjà approuvé -->
    <?php if ($statutCourant === 'approuve'): ?>
        <div class="alert alert-warning d-flex align-items-start mb-4 shadow-sm" role="alert">
            <i class="bi bi-shield-exclamation fs-4 me-3 flex-shrink-0 text-warning"></i>
            <div>
                <strong class="d-block mb-1">Information importante :</strong>
                <p class="small mb-0">
                    Votre profil est actuellement <strong>approuvé et visible dans la recherche publique</strong>. 
                    Si vous modifiez votre <strong>catégorie de métier</strong>, votre <strong>titre</strong> ou votre <strong>description</strong>, votre profil repassera automatiquement au statut <em>« En attente de validation »</em> et disparaîtra temporairement des résultats publics le temps de la revérification administrative.
                </p>
            </div>
        </div>
    <?php endif; ?>

    <!-- BANNIÈRE 4 : Liste précise des éléments manquants (si refus de soumission) -->
    <?php if (!empty($elementsManquantsAffiches)): ?>
        <div class="alert alert-danger shadow-sm mb-4 border-danger" role="alert">
            <div class="d-flex align-items-center mb-2">
                <i class="bi bi-exclamation-octagon-fill fs-4 me-2 text-danger"></i>
                <strong class="h6 mb-0 text-danger">Soumission refusée : profil incomplet</strong>
            </div>
            <p class="small mb-2">
                Votre profil ne peut pas être soumis à la modération tant que les critères d'exhaustivité suivants ne sont pas remplis :
            </p>
            <ul class="mb-2 small ps-3">
                <?php foreach ($elementsManquantsAffiches as $manquant): ?>
                    <li class="mb-1 fw-medium text-danger-emphasis"><?= e($manquant) ?></li>
                <?php endforeach; ?>
            </ul>
            <div class="small text-muted border-top pt-2">
                <i class="bi bi-info-circle me-1"></i>Remarque : la photo de profil est recommandée pour attirer des clients mais n'est pas bloquante.
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4 justify-content-center">
        
        <!-- COLONNE 1 : Informations générales et Photo -->
        <div class="col-12 col-lg-7">
            <div class="card p-4 p-md-5 shadow-sm border h-100">
                
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <h2 class="h5 fw-bold mb-0">
                        <i class="bi bi-person-lines-fill text-primary me-2"></i>Informations générales
                    </h2>
                    <?php if ($estLectureSeule): ?>
                        <span class="badge bg-secondary bg-opacity-10 text-secondary border small">
                            <i class="bi bi-lock me-1"></i>Lecture seule
                        </span>
                    <?php endif; ?>
                </div>

                <form method="POST" action="<?= url('pro/profil.php') ?>" enctype="multipart/form-data" novalidate>
                    <?= csrf_field() ?>

                    <!-- Bloc Photo de profil avec prévisualisation -->
                    <div class="mb-4 p-3 bg-light rounded border">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label fw-semibold small mb-0">
                                Photo de profil professionnelle <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 ms-1">Recommandée</span>
                            </label>
                            <?php if (empty($photoActuelle)): ?>
                                <span class="badge bg-light text-muted border small">Facultative</span>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            
                            <!-- Rendu Avatar ou Espace Réservé Gris -->
                            <div class="position-relative flex-shrink-0">
                                <?php if ($photoActuelle !== null && file_exists(__DIR__ . '/../' . $photoActuelle)): ?>
                                    <img 
                                        src="<?= url($photoActuelle) ?>" 
                                        id="apercu-avatar" 
                                        alt="Photo de profil" 
                                        class="rounded-circle border shadow-sm object-fit-cover" 
                                        style="width: 80px; height: 80px;"
                                    >
                                <?php elseif ($avatarDefautExiste): ?>
                                    <img 
                                        src="<?= url($cheminAvatarDefautRel) ?>" 
                                        id="apercu-avatar" 
                                        alt="Avatar par défaut" 
                                        class="rounded-circle border shadow-sm object-fit-cover" 
                                        style="width: 80px; height: 80px;"
                                    >
                                <?php else: ?>
                                    <div 
                                        id="apercu-avatar-placeholder" 
                                        class="rounded-circle bg-secondary bg-opacity-25 text-secondary d-flex align-items-center justify-content-center border shadow-sm" 
                                        style="width: 80px; height: 80px;"
                                        title="Espace réservé gris : assets/img/avatar-defaut.svg manquant"
                                    >
                                        <i class="bi bi-person-fill fs-1 text-secondary opacity-75"></i>
                                    </div>
                                    <img 
                                        src="" 
                                        id="apercu-avatar" 
                                        alt="Aperçu photo" 
                                        class="rounded-circle border shadow-sm object-fit-cover d-none" 
                                        style="width: 80px; height: 80px;"
                                    >
                                <?php endif; ?>
                            </div>

                            <div class="flex-grow-1">
                                <input 
                                    type="file" 
                                    name="photo" 
                                    id="photo" 
                                    class="form-control form-control-sm <?= isset($erreurs['photo']) ? 'is-invalid' : '' ?>"
                                    accept="image/jpeg,image/png,image/webp"
                                    <?= $estLectureSeule ? 'disabled' : '' ?>
                                >
                                <?php if (isset($erreurs['photo'])): ?>
                                    <div class="invalid-feedback d-block"><?= e($erreurs['photo']) ?></div>
                                <?php else: ?>
                                    <div class="form-text small text-secondary mt-1">
                                        JPG, PNG ou WEBP. Max 2 Mo. Dimensions max 4000x4000 px.
                                    </div>
                                <?php endif; ?>

                                <?php if ($photoActuelle !== null && !$estLectureSeule): ?>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" name="supprimer_photo" value="1" id="supprimer_photo">
                                        <label class="form-check-label small text-danger" for="supprimer_photo">
                                            Supprimer la photo actuelle
                                        </label>
                                    </div>
                                <?php endif; ?>
                            </div>

                        </div>
                    </div>

                    <!-- Catégorie principale -->
                    <div class="mb-3">
                        <label for="id_categorie" class="form-label fw-medium small">
                            Corps de métier principal <span class="text-danger">*</span>
                        </label>
                        <select 
                            name="id_categorie" 
                            id="id_categorie" 
                            class="form-select <?= isset($erreurs['id_categorie']) ? 'is-invalid' : '' ?>"
                            <?= $estLectureSeule ? 'disabled' : '' ?>
                            required
                        >
                            <?php foreach ($categories as $cat): ?>
                                <option 
                                    value="<?= (int) $cat['id_categorie'] ?>" 
                                    data-slug="<?= e($cat['slug']) ?>" 
                                    <?= $idCategorie === (int) $cat['id_categorie'] ? 'selected' : '' ?>
                                >
                                    <?= e($cat['libelle']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($erreurs['id_categorie'])): ?>
                            <div class="invalid-feedback"><?= e($erreurs['id_categorie']) ?></div>
                        <?php else: ?>
                            <div class="form-text small text-secondary">
                                Attention : changer de catégorie retirera les compétences qui ne s'y rattachent plus.
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Titre professionnel -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label for="titre" class="form-label fw-medium small mb-0">
                                Titre professionnel <span class="text-danger">*</span> <span class="text-secondary small">(ex: Développeur PHP Fullstack)</span>
                            </label>
                            <span class="text-muted small" id="compteur-titre">0/120</span>
                        </div>
                        <input 
                            type="text" 
                            class="form-control <?= isset($erreurs['titre']) ? 'is-invalid' : '' ?>" 
                            id="titre" 
                            name="titre" 
                            value="<?= e($titre) ?>" 
                            maxlength="120"
                            placeholder="Votre spécialité ou titre d'activité"
                            <?= $estLectureSeule ? 'readonly disabled' : '' ?>
                            required
                        >
                        <?php if (isset($erreurs['titre'])): ?>
                            <div class="invalid-feedback"><?= e($erreurs['titre']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Ville / Zone d'intervention -->
                    <div class="mb-3">
                        <label for="zone" class="form-label fw-medium small">
                            Ville / Zone de service <span class="text-danger">*</span> <span class="text-secondary small">(ex: Abidjan - Cocody, Télétravail)</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-secondary"><i class="bi bi-geo-alt"></i></span>
                            <input 
                                type="text" 
                                class="form-control <?= isset($erreurs['zone']) ? 'is-invalid' : '' ?>" 
                                id="zone" 
                                name="zone" 
                                value="<?= e($zone) ?>" 
                                maxlength="120"
                                placeholder="Localisation ou périmètre d'intervention"
                                <?= $estLectureSeule ? 'readonly disabled' : '' ?>
                                required
                            >
                        </div>
                        <?php if (isset($erreurs['zone'])): ?>
                            <div class="invalid-feedback d-block"><?= e($erreurs['zone']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Disponibilité -->
                    <div class="mb-3">
                        <label for="disponibilite" class="form-label fw-medium small">
                            Disponibilité actuelle pour des missions <span class="text-danger">*</span>
                        </label>
                        <select 
                            name="disponibilite" 
                            id="disponibilite" 
                            class="form-select"
                            <?= $estLectureSeule ? 'disabled' : '' ?>
                            required
                        >
                            <option value="disponible" <?= $disponibilite === 'disponible' ? 'selected' : '' ?>>Disponible immédiatement</option>
                            <option value="limitee" <?= $disponibilite === 'limitee' ? 'selected' : '' ?>>Disponibilité limitée (délais à prévoir)</option>
                            <option value="indisponible" <?= $disponibilite === 'indisponible' ? 'selected' : '' ?>>Indisponible pour le moment</option>
                        </select>
                        <div class="form-text small text-secondary">Aide les clients à évaluer votre réactivité.</div>
                    </div>

                    <!-- Description détaillée -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label for="description" class="form-label fw-medium small mb-0">
                                Présentation détaillée & prestations <span class="text-danger">*</span>
                            </label>
                            <span class="text-muted small" id="compteur-desc">0/2000 (min 100 pour soumission)</span>
                        </div>
                        <textarea 
                            class="form-control <?= isset($erreurs['description']) ? 'is-invalid' : '' ?>" 
                            id="description" 
                            name="description" 
                            rows="7" 
                            maxlength="2000"
                            placeholder="Décrivez votre expérience, les technologies ou outils que vous maîtrisez, vos réalisations et votre méthode de travail (au moins 100 caractères)..."
                            <?= $estLectureSeule ? 'readonly disabled' : '' ?>
                            required
                        ><?= e($description) ?></textarea>
                        <?php if (isset($erreurs['description'])): ?>
                            <div class="invalid-feedback"><?= e($erreurs['description']) ?></div>
                        <?php else: ?>
                            <div class="form-text small text-secondary">Au moins 100 caractères exigés pour soumettre à validation. Les brouillons acceptent un texte court.</div>
                        <?php endif; ?>
                    </div>

                    <!-- BLOC SPÉCIFIQUE : Engagement déontologique Cybersécurité -->
                    <div class="mb-4 p-3 rounded border <?= $estCatCyberCourante ? '' : 'd-none' ?>" id="bloc-engagement-cyber" style="background-color: #fff9e6; border-color: #ffe082 !important;">
                        <div class="d-flex align-items-start gap-2">
                            <i class="bi bi-shield-check text-warning fs-3 flex-shrink-0"></i>
                            <div class="flex-grow-1">
                                <label class="form-label fw-bold text-dark small mb-1">
                                    Engagement déontologique obligatoire — Cybersécurité <span class="text-danger">*</span>
                                </label>
                                <div class="form-check">
                                    <input 
                                        class="form-check-input" 
                                        type="checkbox" 
                                        name="engagement_cyber" 
                                        id="engagement_cyber" 
                                        value="1" 
                                        <?= $engagementCyber ? 'checked' : '' ?> 
                                        <?= $estLectureSeule ? 'disabled' : '' ?>
                                    >
                                    <label class="form-check-label text-dark small fw-medium" for="engagement_cyber">
                                        Je m'engage à ne proposer que des prestations autorisées par le propriétaire du système concerné (aucun piratage, aucun accès non autorisé).
                                    </label>
                                </div>
                                <?php if (!empty($dateEngagementCyber)): ?>
                                    <div class="form-text text-success small mt-1">
                                        <i class="bi bi-check2-circle me-1"></i>Engagement attesté le <?= e(date('d/m/Y à H:i', strtotime($dateEngagementCyber))) ?>
                                    </div>
                                <?php else: ?>
                                    <div class="form-text text-muted small mt-1">
                                        Exigé pour les professionnels de la cybersécurité. Horodaté et consigné en base de données.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Actions du formulaire -->
                    <?php if ($statutCourant === 'refuse'): ?>
                        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2 pt-2 border-top">
                            <span class="text-danger small">
                                <i class="bi bi-x-circle-fill me-1"></i>Dossier refusé — Formulaire verrouillé
                            </span>
                            <button type="button" class="btn btn-outline-danger py-2 px-4" disabled>
                                <i class="bi bi-slash-circle me-1"></i>Rejet définitif
                            </button>
                        </div>
                    <?php elseif (in_array($statutCourant, ['soumis', 'en_verification'], true)): ?>
                        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2 pt-2 border-top">
                            <span class="text-secondary small">
                                <i class="bi bi-lock-fill me-1"></i>Formulaire en lecture seule — Profil <?= e($badgeActuel['label']) ?>
                            </span>
                            <button type="button" class="btn btn-secondary py-2 px-4" disabled>
                                <i class="bi bi-hourglass-split me-1"></i>Examen en cours
                            </button>
                        </div>
                    <?php elseif (in_array($statutCourant, ['approuve', 'suspendu'], true)): ?>
                        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2 pt-2 border-top">
                            <button type="submit" name="action" value="brouillon" class="btn btn-outline-primary py-2 px-3">
                                <i class="bi bi-save me-1"></i>Mettre à jour le profil
                            </button>
                            <span class="badge bg-light text-secondary border small py-2 px-3">
                                <i class="bi bi-info-circle me-1"></i>Déjà <?= e($badgeActuel['label']) ?>
                            </span>
                        </div>
                    <?php else: ?>
                        <!-- Bouton Enregistrer Brouillon et Bouton Soumettre mon profil -->
                        <div class="d-flex flex-column flex-sm-row justify-content-between gap-2 pt-2 border-top">
                            <button type="submit" name="action" value="brouillon" class="btn btn-outline-primary py-2 px-3">
                                <i class="bi bi-save me-1"></i>Enregistrer le brouillon
                            </button>
                            <button type="submit" name="action" value="soumettre" class="btn btn-brand py-2 px-4">
                                <i class="bi bi-send-check me-1"></i>Soumettre mon profil
                            </button>
                        </div>
                    <?php endif; ?>

                </form>
            </div>
        </div>

        <!-- COLONNE 2 : Gestion des compétences du profil -->
        <div class="col-12 col-lg-5" id="competences">
            <div class="card p-4 shadow-sm border h-100 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                        <h2 class="h5 fw-bold mb-0">
                            <i class="bi bi-award text-primary me-2"></i>Compétences clés
                        </h2>
                        <span class="badge bg-light text-dark border px-2.5 py-1.5 font-monospace small">
                            <?= $totalCompetencesProfil ?> / 15
                        </span>
                    </div>

                    <?php if (!$profil): ?>
                        <div class="alert alert-secondary text-center p-4">
                            <i class="bi bi-info-circle fs-3 text-secondary d-block mb-2"></i>
                            <p class="small mb-0 text-secondary">
                                Enregistrez d'abord vos informations générales ci-contre pour activer l'ajout de compétences.
                            </p>
                        </div>
                    <?php else: ?>

                        <!-- Formulaire d'ajout de compétence (verrouillé si lecture seule) -->
                        <?php if ($estLectureSeule): ?>
                            <div class="alert alert-light border small py-2 mb-3 text-secondary">
                                <i class="bi bi-lock me-1"></i>Gestion des compétences verrouillée pendant l'examen de votre profil.
                            </div>
                        <?php elseif ($totalCompetencesProfil < 15 && !empty($competencesDisponibles)): ?>
                            <form method="POST" action="<?= url('pro/profil.php#competences') ?>" class="bg-light p-3 rounded border mb-4">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="ajouter_competence">

                                <div class="mb-2">
                                    <label for="id_competence" class="form-label fw-semibold small mb-1">
                                        Sélectionner une compétence
                                    </label>
                                    <select name="id_competence" id="id_competence" class="form-select form-select-sm" required>
                                        <option value="" disabled selected>-- Choisir dans votre métier --</option>
                                        <?php foreach ($competencesDisponibles as $comp): ?>
                                            <option value="<?= (int) $comp['id_competence'] ?>">
                                                <?= e($comp['libelle']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="niveau_declare" class="form-label fw-semibold small mb-1">
                                        Niveau déclaré
                                    </label>
                                    <select name="niveau_declare" id="niveau_declare" class="form-select form-select-sm" required>
                                        <option value="debutant">Débutant (Bases théoriques / premiers projets)</option>
                                        <option value="intermediaire" selected>Intermédiaire (Autonome sur tâches courantes)</option>
                                        <option value="avance">Avancé (Maîtrise approfondie / projets complexes)</option>
                                        <option value="expert">Expert (Référence technique / résolution pointue)</option>
                                    </select>
                                </div>

                                <button type="submit" class="btn btn-brand btn-sm w-100">
                                    <i class="bi bi-plus-circle me-1"></i>Ajouter à mon profil
                                </button>
                            </form>
                        <?php elseif ($totalCompetencesProfil >= 15): ?>
                            <div class="alert alert-warning small py-2 mb-4">
                                <i class="bi bi-exclamation-triangle me-1"></i>Vous avez atteint le quota maximal de 15 compétences. Supprimez-en une pour en ajouter une autre.
                            </div>
                        <?php elseif (empty($competencesDisponibles)): ?>
                            <div class="alert alert-info small py-2 mb-4">
                                <i class="bi bi-check-circle me-1"></i>Vous avez associé toutes les compétences disponibles pour votre métier.
                            </div>
                        <?php endif; ?>

                        <!-- Liste des compétences actuelles -->
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h3 class="h6 fw-semibold text-secondary mb-0">Compétences associées :</h3>
                            <span class="small text-muted"><?= $totalCompetencesProfil ?> / 3 requises</span>
                        </div>

                        <?php if (empty($competencesProfil)): ?>
                            <div class="text-center py-4 text-muted bg-light rounded border border-dashed mb-3">
                                <i class="bi bi-award fs-2 text-secondary opacity-50 d-block mb-1"></i>
                                <small>Aucune compétence associée pour l'instant (min 3 requises pour soumission).</small>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush border rounded mb-3">
                                <?php foreach ($competencesProfil as $cp): 
                                    $cfgNiveau = $labelsNiveaux[$cp['niveau_declare']] ?? $labelsNiveaux['intermediaire'];
                                ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="fw-medium small"><?= e($cp['libelle']) ?></span>
                                            <span class="badge <?= e($cfgNiveau['badge']) ?> rounded-pill font-monospace" style="font-size: 0.72rem;">
                                                <?= e($cfgNiveau['label']) ?>
                                            </span>
                                        </div>
                                        <?php if (!$estLectureSeule): ?>
                                            <form method="POST" action="<?= url('pro/profil.php#competences') ?>" class="m-0 p-0" onsubmit="return confirm('Retirer cette compétence de votre profil ?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="supprimer_competence">
                                                <input type="hidden" name="id_competence" value="<?= (int) $cp['id_competence'] ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm p-1 border-0" title="Retirer cette compétence" aria-label="Supprimer <?= e($cp['libelle']) ?>">
                                                    <i class="bi bi-trash3"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Raccourci vers Portfolio -->
                        <div class="bg-light p-3 rounded border">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="fw-semibold small d-block">Projets de portfolio</span>
                                    <small class="text-secondary"><?= $totalProjetsPortfolio ?> projet(s) enregistré(s) (min 1 requis)</small>
                                </div>
                                <a href="<?= url('pro/portfolio.php') ?>" class="btn btn-sm btn-outline-primary">
                                    Gérer <i class="bi bi-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>

                    <?php endif; ?>

                </div>
            </div>
        </div>

    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const titreInput = document.getElementById('titre');
    const compteurTitre = document.getElementById('compteur-titre');
    const descInput = document.getElementById('description');
    const compteurDesc = document.getElementById('compteur-desc');
    const photoInput = document.getElementById('photo');
    const apercuAvatar = document.getElementById('apercu-avatar');
    const placeholderAvatar = document.getElementById('apercu-avatar-placeholder');
    const selectCat = document.getElementById('id_categorie');
    const blocCyber = document.getElementById('bloc-engagement-cyber');

    function updateCompteurs() {
        if (titreInput && compteurTitre) {
            compteurTitre.textContent = titreInput.value.length + '/120';
        }
        if (descInput && compteurDesc) {
            const len = descInput.value.length;
            compteurDesc.textContent = len + '/2000 (min 100 pour soumission)';
            if (len >= 100) {
                compteurDesc.classList.remove('text-danger');
                compteurDesc.classList.add('text-success');
            } else {
                compteurDesc.classList.remove('text-success');
                compteurDesc.classList.add('text-danger');
            }
        }
    }

    function updateBlocCyber() {
        if (!selectCat || !blocCyber) return;
        const selectedOption = selectCat.options[selectCat.selectedIndex];
        const slug = selectedOption ? selectedOption.getAttribute('data-slug') : '';
        const text = selectedOption ? selectedOption.textContent.toLowerCase() : '';
        if (slug === 'cybersecurite' || text.includes('cyber')) {
            blocCyber.classList.remove('d-none');
        } else {
            blocCyber.classList.add('d-none');
        }
    }

    if (titreInput) titreInput.addEventListener('input', updateCompteurs);
    if (descInput) descInput.addEventListener('input', updateCompteurs);
    if (selectCat) selectCat.addEventListener('change', updateBlocCyber);
    
    updateCompteurs();
    updateBlocCyber();

    // Prévisualisation instantanée de l'avatar sélectionné
    if (photoInput) {
        photoInput.addEventListener('change', function (e) {
            const fichier = e.target.files[0];
            if (fichier) {
                const reader = new FileReader();
                reader.onload = function (evt) {
                    if (apercuAvatar) {
                        apercuAvatar.src = evt.target.result;
                        apercuAvatar.classList.remove('d-none');
                    }
                    if (placeholderAvatar) {
                        placeholderAvatar.classList.add('d-none');
                    }
                };
                reader.readAsDataURL(fichier);
            }
        });
    }
});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
