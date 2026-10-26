<?php
/**
 * Vue détaillée d'une demande de service — FindPro
 *
 * Règles d'accès (RG01, RG02, RG03, RG04) :
 * - Autorise UNIQUEMENT :
 *   1. Le client auteur de la demande (id_client = utilisateur connecté)
 *   2. Le professionnel destinataire (id_utilisateur rattaché au profil = utilisateur connecté)
 *   3. L'administrateur (lecture seule stricte, SANS accès aux messages/conversations privées)
 * - Tout autre utilisateur reçoit une réponse HTTP 403.
 * - Affiche toutes les informations de la demande, l'historique des statuts et le canal d'échange.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/errors.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/demandes.php';

demarrer_session();
$utilisateur = exiger_connexion();
$roleConnecte = $utilisateur['role'];
$idUtilisateurConnecte = (int) ($utilisateur['id'] ?? $utilisateur['id_utilisateur']);

$pdo = db();

// ============================================================================
// 1. VALIDATION DU PARAMÈTRE D'IDENTIFIANT
// ============================================================================

$idDemandeParam = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($idDemandeParam === false || (int) $idDemandeParam <= 0) {
    page_introuvable("L'identifiant de la demande est invalide ou manquant.", "Demande introuvable");
}
$idDemande = (int) $idDemandeParam;

// ============================================================================
// 2. RÉCUPÉRATION COMPLÈTE DE LA DEMANDE ET DE SES PARTIES PRENANTES
// ============================================================================

$stmt = $pdo->prepare('
    SELECT 
        d.id_demande,
        d.id_client,
        d.id_profil,
        d.id_categorie,
        d.titre,
        d.description,
        d.budget_indicatif,
        d.delai_souhaite,
        d.statut,
        d.date_creation,
        d.date_cloture,
        d.date_confirmation,
        cat.libelle AS categorie_libelle,
        cat.slug AS categorie_slug,
        p.id_utilisateur AS pro_id_utilisateur,
        p.titre AS pro_titre,
        p.photo AS pro_photo,
        p.zone AS pro_zone,
        p.disponibilite AS pro_disponibilite,
        u_pro.nom AS pro_nom,
        u_pro.prenom AS pro_prenom,
        u_pro.email AS pro_email,
        u_pro.telephone AS pro_telephone,
        u_client.nom AS client_nom,
        u_client.prenom AS client_prenom,
        u_client.email AS client_email,
        u_client.telephone AS client_telephone,
        pc.type_client AS client_type,
        pc.nom_societe AS client_nom_societe,
        pc.secteur_activite AS client_secteur,
        pc.photo AS client_photo,
        pc.ville AS client_ville,
        conv.id_conversation,
        conv.statut AS conversation_statut,
        conv.date_creation AS conversation_date_creation
    FROM demande_service d
    JOIN profil_professionnel p ON d.id_profil = p.id_profil
    JOIN utilisateur u_pro ON p.id_utilisateur = u_pro.id_utilisateur
    JOIN utilisateur u_client ON d.id_client = u_client.id_utilisateur
    LEFT JOIN profil_client pc ON d.id_client = pc.id_utilisateur
    LEFT JOIN categorie cat ON d.id_categorie = cat.id_categorie
    LEFT JOIN conversation conv ON d.id_demande = conv.id_demande
    WHERE d.id_demande = :id
    LIMIT 1
');
$stmt->execute(['id' => $idDemande]);
$demande = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$demande) {
    page_introuvable("Cette demande de service n'existe pas ou a été supprimée.", "Demande introuvable");
}

// ============================================================================
// 3. CONTRÔLE D'AUTORISATION STRICT (CLIENT AUTEUR, PRO DESTINATAIRE OU ADMIN)
// ============================================================================

$estClientAuteur    = ($roleConnecte === 'client' && (int) $demande['id_client'] === $idUtilisateurConnecte);
$estProDestinataire = ($roleConnecte === 'professionnel' && (int) $demande['pro_id_utilisateur'] === $idUtilisateurConnecte);
$estAdmin           = ($roleConnecte === 'admin');

if (!$estClientAuteur && !$estProDestinataire && !$estAdmin) {
    $titre403 = 'Accès refusé';
    $message403 = "Vous n'êtes pas autorisé à consulter cette demande de prestation.";
    require __DIR__ . '/includes/403.php';
    exit;
}

// ============================================================================
// 4. TRAITEMENT POST — TRANSITION DE STATUT STRICTE (VIA CHANGER_STATUT_DEMANDE)
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifier();

    $action = trim((string) ($_POST['action'] ?? ''));

    // --- Action : Dépôt d'un avis / note par le client (RG07) ---
    if ($action === 'deposer_avis') {
        if (!$estClientAuteur) {
            flash_set('danger', 'Seul le client ayant commandé cette prestation peut déposer une évaluation.');
            redirect(url('demande_detail.php?id=' . $idDemande));
        }

        if ($demande['statut'] !== 'terminee') {
            flash_set('danger', 'Une évaluation ne peut être déposée que lorsque la prestation est clôturée au statut terminée.');
            redirect(url('demande_detail.php?id=' . $idDemande));
        }

        // Vérification d'unicité de l'avis
        $stmtVerifAvis = $pdo->prepare('SELECT id_avis FROM avis WHERE id_demande = :id');
        $stmtVerifAvis->execute(['id' => $idDemande]);
        if ($stmtVerifAvis->fetch()) {
            flash_set('warning', 'Vous avez déjà évalué cette prestation.');
            redirect(url('demande_detail.php?id=' . $idDemande . '#evaluation'));
        }

        $note = filter_input(INPUT_POST, 'note', FILTER_VALIDATE_INT);
        $commentaire = trim((string) ($_POST['commentaire'] ?? ''));

        if ($note === false || $note < 1 || $note > 5) {
            flash_set('danger', 'Veuillez sélectionner une note comprise entre 1 et 5 étoiles.');
            redirect(url('demande_detail.php?id=' . $idDemande . '#evaluation'));
        }

        try {
            $stmtInsertAvis = $pdo->prepare('
                INSERT INTO avis (id_demande, id_client, note, commentaire, statut, date_avis)
                VALUES (:id_demande, :id_client, :note, :commentaire, "publie", NOW())
            ');
            $stmtInsertAvis->execute([
                'id_demande'  => $idDemande,
                'id_client'   => $idUtilisateurConnecte,
                'note'        => $note,
                'commentaire' => $commentaire !== '' ? $commentaire : null,
            ]);

            flash_set('success', 'Votre avis avec une note de ' . $note . '/5 a été publié avec succès sur le profil du prestataire.');
        } catch (PDOException $e) {
            flash_set('danger', 'Impossible d\'enregistrer l\'évaluation : ' . $e->getMessage());
        }

        redirect(url('demande_detail.php?id=' . $idDemande . '#evaluation'));
    }

    // --- Action : Transition de statut standard ---
    $nouveauStatut = trim((string) ($_POST['nouveau_statut'] ?? $_POST['statut'] ?? ''));
    $resultat = changer_statut_demande($pdo, $idDemande, $nouveauStatut, $utilisateur);

    if ($resultat['succes']) {
        flash_set('success', $resultat['message']);
    } else {
        flash_set('danger', $resultat['message']);
    }

    redirect(url('demande_detail.php?id=' . $idDemande));
}

// ============================================================================
// 5. RÉCUPÉRATION DES MESSAGES (UNIQUEMENT CLIENT ET PRO — SANS ACCÈS ADMIN)
// ============================================================================

$messages = [];
// Règle de gouvernance : l'administrateur n'a PAS accès aux messages privés (RG04)
if (!$estAdmin && !empty($demande['id_conversation'])) {
    $stmtMsg = $pdo->prepare('
        SELECT 
            m.id_message,
            m.id_conversation,
            m.id_expediteur,
            m.contenu,
            m.date_envoi,
            m.lu,
            u.nom,
            u.prenom,
            u.role
        FROM message m
        JOIN utilisateur u ON m.id_expediteur = u.id_utilisateur
        WHERE m.id_conversation = :id_conv
        ORDER BY m.date_envoi ASC
    ');
    $stmtMsg->execute(['id_conv' => (int) $demande['id_conversation']]);
    $messages = $stmtMsg->fetchAll(PDO::FETCH_ASSOC);
}

// Récupération de l'avis éventuel lié à cette demande (RG07)
$stmtAvisDemande = $pdo->prepare('
    SELECT a.id_avis, a.note, a.commentaire, a.statut, a.date_avis, u.prenom, u.nom
    FROM avis a
    JOIN utilisateur u ON a.id_client = u.id_utilisateur
    WHERE a.id_demande = :id
    LIMIT 1
');
$stmtAvisDemande->execute(['id' => $idDemande]);
$avisDemande = $stmtAvisDemande->fetch(PDO::FETCH_ASSOC);

// Formatage du nom public du professionnel (RG05)
$prenomPro = trim((string) $demande['pro_prenom']);
$nomPro = trim((string) $demande['pro_nom']);
$initialeNomPro = $nomPro !== '' ? mb_strtoupper(mb_substr($nomPro, 0, 1, 'UTF-8'), 'UTF-8') . '.' : '';
$nomAffichePro = trim($prenomPro . ' ' . $initialeNomPro);
if ($nomAffichePro === '') {
    $nomAffichePro = 'Professionnel FindPro';
}

// Photo du professionnel
$photoProUrl = null;
if (!empty($demande['pro_photo']) && file_exists(__DIR__ . '/' . $demande['pro_photo'])) {
    $photoProUrl = url($demande['pro_photo']);
} else {
    $photoProUrl = url('assets/img/avatar-defaut.svg');
}

// Nom complet du client
$clientNomComplet = trim($demande['client_prenom'] . ' ' . $demande['client_nom']);

// Configuration des badges et libellés de statut
$statutClasses = [
    'envoyee'     => ['badge' => 'bg-info bg-opacity-10 text-info border border-info border-opacity-25', 'label' => 'Envoyée — En attente du professionnel', 'icon' => 'bi-clock-history'],
    'acceptee'    => ['badge' => 'bg-success bg-opacity-10 text-success border border-success border-opacity-25', 'label' => 'Acceptée par le professionnel', 'icon' => 'bi-check-circle'],
    'refusee'     => ['badge' => 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25', 'label' => 'Demande refusée', 'icon' => 'bi-x-circle'],
    'en_cours'    => ['badge' => 'bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25', 'label' => 'Prestation en cours', 'icon' => 'bi-play-circle'],
    'a_confirmer' => ['badge' => 'bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25', 'label' => 'À confirmer par le client', 'icon' => 'bi-hourglass-split'],
    'terminee'    => ['badge' => 'bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25', 'label' => 'Prestation terminée', 'icon' => 'bi-flag-fill'],
    'annulee'     => ['badge' => 'bg-dark bg-opacity-10 text-dark border border-dark border-opacity-25', 'label' => 'Demande annulée', 'icon' => 'bi-dash-circle'],
];
$statutInfo = $statutClasses[$demande['statut']] ?? ['badge' => 'bg-light text-dark border', 'label' => ucfirst($demande['statut']), 'icon' => 'bi-info-circle'];

// Calcul des transitions d'état possibles pour l'utilisateur connecté (RG01 / Matrice stricte)
$actionsPossibles = transitions_possibles($demande['statut'], $utilisateur, $demande);

$titre_page = e($demande['titre']) . ' — Demande de service';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Fil d'Ariane contextuel selon le rôle -->
<nav class="py-3 bg-light border-bottom" aria-label="Fil d'Ariane">
    <div class="container">
        <ol class="breadcrumb small mb-0">
            <li class="breadcrumb-item">
                <a href="<?= url('') ?>" class="text-secondary text-decoration-none hover-primary">Accueil</a>
            </li>
            <?php if ($estClientAuteur): ?>
                <li class="breadcrumb-item">
                    <a href="<?= url('client/index.php') ?>" class="text-secondary text-decoration-none hover-primary">Tableau de bord</a>
                </li>
                <li class="breadcrumb-item">
                    <a href="<?= url('client/demandes.php') ?>" class="text-secondary text-decoration-none hover-primary">Mes demandes</a>
                </li>
            <?php elseif ($estProDestinataire): ?>
                <li class="breadcrumb-item">
                    <a href="<?= url('pro/index.php') ?>" class="text-secondary text-decoration-none hover-primary">Espace Pro</a>
                </li>
                <li class="breadcrumb-item">
                    <a href="<?= url('pro/demandes.php') ?>" class="text-secondary text-decoration-none hover-primary">Demandes reçues</a>
                </li>
            <?php else: ?>
                <li class="breadcrumb-item">
                    <a href="<?= url('admin/index.php') ?>" class="text-secondary text-decoration-none hover-primary">Administration</a>
                </li>
                <li class="breadcrumb-item">
                    <a href="<?= url('admin/demandes.php') ?>" class="text-secondary text-decoration-none hover-primary">Demandes</a>
                </li>
            <?php endif; ?>
            <li class="breadcrumb-item active text-dark fw-medium" aria-current="page">
                Détail de la demande
            </li>
        </ol>
    </div>
</nav>

<div class="container py-4 py-lg-5">

    <!-- Bandeau spécial Administration : Lecture seule sans accès messages -->
    <?php if ($estAdmin): ?>
        <div class="alert alert-secondary border d-flex align-items-center gap-3 shadow-2xs mb-4" role="alert">
            <i class="bi bi-shield-lock-fill fs-3 text-secondary flex-shrink-0" aria-hidden="true"></i>
            <div>
                <strong class="d-block mb-1">Supervision administrative (Mode lecture seule)</strong>
                <span class="small">Conformément aux règles de confidentialité de FindPro, le contenu des messages échangés entre les parties prenantes est strictement privé et inoffert à l'administration.</span>
            </div>
        </div>
    <?php endif; ?>

    <!-- En-tête principal de la demande -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-3 border-bottom">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                <span class="badge <?= $statutInfo['badge'] ?> px-3 py-1.5 rounded-pill font-monospace small fw-semibold">
                    <i class="bi <?= $statutInfo['icon'] ?> me-1"></i><?= e($statutInfo['label']) ?>
                </span>
                <span class="text-secondary small">
                    <i class="bi bi-calendar-event me-1"></i>Émise le <?= date('d/m/Y à H:i', strtotime($demande['date_creation'])) ?>
                </span>
            </div>
            <h1 class="h3 fw-bold mb-1 text-dark text-break">
                <?= e($demande['titre']) ?>
            </h1>
            <p class="text-secondary small mb-0">
                Catégorie : <span class="badge bg-light text-dark border"><?= e($demande['categorie_libelle'] ?? 'Service') ?></span>
            </p>
        </div>
        <div class="d-flex gap-2">
            <?php if ($estClientAuteur): ?>
                <a href="<?= url('client/demandes.php') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Mes demandes
                </a>
            <?php elseif ($estProDestinataire): ?>
                <a href="<?= url('pro/demandes.php') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Demandes reçues
                </a>
            <?php else: ?>
                <a href="<?= url('admin/demandes.php') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Supervision
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-4">

        <!-- Colonne gauche : Informations de la demande et timeline -->
        <div class="col-12 col-lg-7">

            <!-- Carte 1 : Détails de la prestation demandée -->
            <div class="card shadow-sm border mb-4">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h2 class="h6 fw-semibold mb-0 text-dark">
                        <i class="bi bi-file-earmark-text me-2 text-primary"></i>Détails de la demande
                    </h2>
                    <span class="badge bg-light text-secondary border small">Prestation FindPro</span>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3 mb-4">
                        <div class="col-sm-6">
                            <small class="text-secondary d-block mb-1">Catégorie professionnelle</small>
                            <span class="fw-medium text-dark"><?= e($demande['categorie_libelle'] ?? 'Non spécifiée') ?></span>
                        </div>
                        <div class="col-sm-6">
                            <small class="text-secondary d-block mb-1">Budget indicatif</small>
                            <?php if ($demande['budget_indicatif'] !== null): ?>
                                <span class="fw-bold text-dark fs-6"><?= number_format((float) $demande['budget_indicatif'], 0, ',', ' ') ?> FCFA</span>
                                <small class="text-muted d-block" style="font-size: 0.72rem;">(Estimation indicative)</small>
                            <?php else: ?>
                                <span class="text-secondary fst-italic">Non précisé</span>
                            <?php endif; ?>
                        </div>
                        <div class="col-sm-6">
                            <small class="text-secondary d-block mb-1">Délai souhaité</small>
                            <?php if (!empty($demande['delai_souhaite'])): ?>
                                <span class="fw-medium text-dark">
                                    <i class="bi bi-calendar-check me-1 text-primary"></i><?= date('d/m/Y', strtotime($demande['delai_souhaite'])) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-secondary fst-italic">Flexible / Non précisé</span>
                            <?php endif; ?>
                        </div>
                        <div class="col-sm-6">
                            <small class="text-secondary d-block mb-1">Statut du cycle</small>
                            <span class="badge <?= $statutInfo['badge'] ?> px-2 py-1 small">
                                <?= e($statutInfo['label']) ?>
                            </span>
                        </div>
                    </div>

                    <div class="border-top pt-3">
                        <small class="text-secondary d-block mb-2 fw-semibold">Description détaillée du besoin</small>
                        <div class="p-3 bg-light rounded-2 text-dark small text-break" style="white-space: pre-wrap; line-height: 1.6; word-break: break-word;"><?= e($demande['description']) ?></div>
                    </div>
                </div>
            </div>

            <!-- Carte 2 : Historique et progression des statuts -->
            <div class="card shadow-sm border mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h2 class="h6 fw-semibold mb-0 text-dark">
                        <i class="bi bi-clock-history me-2 text-primary"></i>Historique du cycle de la prestation
                    </h2>
                </div>
                <div class="card-body p-4">
                    <div class="position-relative ps-4" style="border-left: 2px solid var(--border, #e5e7eb);">
                        
                        <!-- Étape 1 : Émission -->
                        <div class="mb-4 position-relative">
                            <span class="position-absolute translate-middle-x rounded-circle bg-primary text-white d-flex align-items-center justify-content-center shadow-2xs" style="left: -25px; top: 0px; width: 22px; height: 22px; font-size: 0.65rem;">
                                <i class="bi bi-check"></i>
                            </span>
                            <div class="d-flex justify-content-between align-items-baseline">
                                <strong class="small text-dark">Demande émise par le client</strong>
                                <span class="text-muted small"><?= date('d/m/Y à H:i', strtotime($demande['date_creation'])) ?></span>
                            </div>
                            <p class="text-secondary small mb-0">La demande a été initialisée au statut <code>envoyee</code>.</p>
                        </div>

                        <!-- Étape 2 : Statut intermédiaire ou actuel -->
                        <?php if (in_array($demande['statut'], ['acceptee', 'en_cours', 'a_confirmer', 'terminee'], true)): ?>
                            <div class="mb-4 position-relative">
                                <span class="position-absolute translate-middle-x rounded-circle bg-success text-white d-flex align-items-center justify-content-center shadow-2xs" style="left: -25px; top: 0px; width: 22px; height: 22px; font-size: 0.65rem;">
                                    <i class="bi bi-check"></i>
                                </span>
                                <div class="d-flex justify-content-between align-items-baseline">
                                    <strong class="small text-dark">Traitement par le professionnel</strong>
                                    <span class="badge <?= $statutInfo['badge'] ?> small"><?= e($demande['statut']) ?></span>
                                </div>
                                <p class="text-secondary small mb-0">Prestation acceptée et en cours d'exécution.</p>
                            </div>
                        <?php elseif ($demande['statut'] === 'refusee'): ?>
                            <div class="mb-4 position-relative">
                                <span class="position-absolute translate-middle-x rounded-circle bg-danger text-white d-flex align-items-center justify-content-center shadow-2xs" style="left: -25px; top: 0px; width: 22px; height: 22px; font-size: 0.65rem;">
                                    <i class="bi bi-x"></i>
                                </span>
                                <div class="d-flex justify-content-between align-items-baseline">
                                    <strong class="small text-danger">Demande refusée par le professionnel</strong>
                                    <?php if (!empty($demande['date_cloture'])): ?>
                                        <span class="text-muted small"><?= date('d/m/Y à H:i', strtotime($demande['date_cloture'])) ?></span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-secondary small mb-0">Le professionnel a décliné la réalisation de cette prestation.</p>
                            </div>
                        <?php elseif ($demande['statut'] === 'annulee'): ?>
                            <div class="mb-4 position-relative">
                                <span class="position-absolute translate-middle-x rounded-circle bg-dark text-white d-flex align-items-center justify-content-center shadow-2xs" style="left: -25px; top: 0px; width: 22px; height: 22px; font-size: 0.65rem;">
                                    <i class="bi bi-x"></i>
                                </span>
                                <div class="d-flex justify-content-between align-items-baseline">
                                    <strong class="small text-dark">Demande annulée par le client</strong>
                                    <?php if (!empty($demande['date_cloture'])): ?>
                                        <span class="text-muted small"><?= date('d/m/Y à H:i', strtotime($demande['date_cloture'])) ?></span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-secondary small mb-0">Le client a annulé sa demande de prestation.</p>
                            </div>
                        <?php endif; ?>

                        <!-- Étape 3 : Clôture / Confirmation -->
                        <?php if (!empty($demande['date_cloture']) && $demande['statut'] !== 'refusee' && $demande['statut'] !== 'annulee'): ?>
                            <div class="mb-4 position-relative">
                                <span class="position-absolute translate-middle-x rounded-circle bg-info text-white d-flex align-items-center justify-content-center shadow-2xs" style="left: -25px; top: 0px; width: 22px; height: 22px; font-size: 0.65rem;">
                                    <i class="bi bi-check"></i>
                                </span>
                                <div class="d-flex justify-content-between align-items-baseline">
                                    <strong class="small text-dark">Travaux marqués terminés (pro)</strong>
                                    <span class="text-muted small"><?= date('d/m/Y à H:i', strtotime($demande['date_cloture'])) ?></span>
                                </div>
                                <p class="text-secondary small mb-0">En attente de confirmation finale par le client.</p>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($demande['date_confirmation'])): ?>
                            <div class="position-relative">
                                <span class="position-absolute translate-middle-x rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center shadow-2xs" style="left: -25px; top: 0px; width: 22px; height: 22px; font-size: 0.65rem;">
                                    <i class="bi bi-flag-fill"></i>
                                </span>
                                <div class="d-flex justify-content-between align-items-baseline">
                                    <strong class="small text-dark">Prestation validée et terminée</strong>
                                    <span class="text-muted small"><?= date('d/m/Y à H:i', strtotime($demande['date_confirmation'])) ?></span>
                                </div>
                                <p class="text-secondary small mb-0">Le client a confirmé l'achèvement complet des travaux.</p>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>

            <!-- Carte : Évaluation & Avis certifié sur la prestation (RG07) -->
            <?php if ($demande['statut'] === 'terminee'): ?>
                <div class="card shadow-sm border mb-4" id="evaluation">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <h2 class="h6 fw-semibold mb-0 text-dark d-flex align-items-center gap-2">
                            <i class="bi bi-star-fill text-warning"></i>
                            <span>Évaluation &amp; Avis sur la prestation</span>
                        </h2>
                        <?php if ($avisDemande): ?>
                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 small">
                                <i class="bi bi-check-circle me-1"></i>Avis publié
                            </span>
                        <?php else: ?>
                            <span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-50 small">
                                <i class="bi bi-clock me-1"></i>En attente d'évaluation
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($avisDemande): ?>
                            <!-- Avis déjà publié -->
                            <div class="p-3 bg-light rounded-3 border">
                                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-2 pb-2 border-bottom">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle bg-white border d-flex align-items-center justify-content-center text-primary fw-bold" style="width: 36px; height: 36px;">
                                            <?= e(mb_substr((string) ($avisDemande['prenom'] ?? 'C'), 0, 1, 'UTF-8')) ?>
                                        </div>
                                        <div>
                                            <strong class="text-dark small d-block"><?= e($avisDemande['prenom'] . ' ' . $avisDemande['nom']) ?></strong>
                                            <span class="text-muted" style="font-size: 0.75rem;">Publié le <?= date('d/m/Y à H:i', strtotime($avisDemande['date_avis'])) ?></span>
                                        </div>
                                    </div>
                                    <div class="text-warning d-flex align-items-center gap-1">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="bi bi-star<?= $i <= (int) $avisDemande['note'] ? '-fill' : '' ?> fs-5"></i>
                                        <?php endfor; ?>
                                        <span class="fw-bold text-dark ms-1 font-monospace"><?= (int) $avisDemande['note'] ?>/5</span>
                                    </div>
                                </div>
                                <?php if (!empty($avisDemande['commentaire'])): ?>
                                    <p class="text-secondary small mb-0 fst-italic">
                                        « <?= nl2br(e($avisDemande['commentaire'])) ?> »
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="small text-muted mt-2 d-flex align-items-center gap-1">
                                <i class="bi bi-patch-check-fill text-success"></i>
                                <span>Cet avis authentifié est affiché sur la vitrine publique du professionnel.</span>
                            </div>
                        <?php elseif ($estClientAuteur): ?>
                            <!-- Formulaire de notation pour le client -->
                            <form action="<?= url('demande_detail.php?id=' . $idDemande) ?>" method="POST">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="deposer_avis">

                                <p class="text-secondary small mb-3">
                                    La prestation est terminée ! Veuillez attribuer une note globale et partager votre retour d'expérience avec <strong><?= e($nomAffichePro) ?></strong>.
                                </p>

                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-dark d-block mb-1">
                                        Note globale sur 5 étoiles <span class="text-danger">*</span>
                                    </label>
                                    <div class="d-flex flex-wrap align-items-center gap-2 py-1">
                                        <?php 
                                        $labelsEtoiles = [
                                            5 => '5 - Excellent',
                                            4 => '4 - Très bien',
                                            3 => '3 - Moyen',
                                            2 => '2 - Insatisfaisant',
                                            1 => '1 - Médiocre'
                                        ];
                                        foreach ($labelsEtoiles as $val => $lbl): ?>
                                            <div class="form-check form-check-inline m-0 border rounded px-2.5 py-1.5 bg-light">
                                                <input class="form-check-input" type="radio" name="note" id="star_<?= $val ?>" value="<?= $val ?>" <?= $val === 5 ? 'checked' : '' ?> required>
                                                <label class="form-check-label cursor-pointer text-dark small fw-semibold" for="star_<?= $val ?>">
                                                    <?= $val ?> <i class="bi bi-star-fill text-warning"></i>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="commentaire" class="form-label small fw-bold text-dark">
                                        Commentaire d'appréciation (recommandé)
                                    </label>
                                    <textarea class="form-control small" id="commentaire" name="commentaire" rows="3" 
                                              placeholder="Précisez la qualité du travail, le respect des délais convenus, la clarté des échanges..."></textarea>
                                </div>

                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-warning px-4 py-2 d-inline-flex align-items-center gap-2 shadow-2xs text-dark fw-bold">
                                        <i class="bi bi-star-fill text-dark"></i>
                                        <span>Publier mon avis certifié</span>
                                    </button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="text-center py-3 text-secondary">
                                <i class="bi bi-hourglass-split text-warning fs-3 d-block mb-1"></i>
                                <span class="small">Prestation clôturée. En attente de l'évaluation du client.</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Carte 3 : Conversation et fil d'échange privé -->
            <?php if (!$estAdmin): ?>
                <div class="card shadow-sm border mb-4" id="conversation">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="h6 fw-semibold mb-0 text-dark">
                                <i class="bi bi-chat-dots me-2 text-primary"></i>Conversation privée
                            </h2>
                            <small class="text-secondary">Échanges directs et sécurisés entre les participants</small>
                        </div>
                        <?php if (!empty($demande['conversation_statut'])): ?>
                            <?php $estLectureSeule = ($demande['conversation_statut'] === 'lecture_seule'); ?>
                            <span class="badge <?= $estLectureSeule ? 'bg-secondary bg-opacity-10 text-secondary border border-secondary' : 'bg-success bg-opacity-10 text-success border border-success' ?> border-opacity-25 px-2 py-1 small">
                                <i class="bi <?= $estLectureSeule ? 'bi-lock-fill' : 'bi-check-circle' ?> me-1"></i>Canal <?= e($estLectureSeule ? 'lecture seule' : $demande['conversation_statut']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!empty($demande['conversation_statut']) && $demande['conversation_statut'] === 'lecture_seule'): ?>
                            <div class="alert alert-secondary border d-flex align-items-center gap-2 mb-3" role="alert">
                                <i class="bi bi-lock-fill fs-5 text-secondary flex-shrink-0"></i>
                                <span class="small">Cette conversation est passée en <strong>lecture seule</strong>. La demande étant clôturée ou refusée, aucun nouveau message ne peut être échangé.</span>
                            </div>
                        <?php endif; ?>

                        <?php if (empty($messages)): ?>
                            <div class="text-center py-4 text-secondary">
                                <i class="bi bi-chat-square-text fs-2 opacity-50 mb-2 d-block"></i>
                                <p class="small mb-0">Aucun message pour le moment dans cette conversation.</p>
                            </div>
                        <?php else: ?>
                            <div class="d-flex flex-column gap-3 mb-3">
                                <?php foreach ($messages as $msg): 
                                    $estMonMessage = ((int) $msg['id_expediteur'] === $idUtilisateurConnecte);
                                    $nomExp = $estMonMessage ? 'Vous' : ($msg['role'] === 'professionnel' ? e($nomAffichePro . ' (Pro)') : e($clientNomComplet . ' (Client)'));
                                ?>
                                    <div class="d-flex <?= $estMonMessage ? 'justify-content-end' : 'justify-content-start' ?>">
                                        <div class="card <?= $estMonMessage ? 'bg-primary text-white border-0' : 'bg-light border' ?> p-3 shadow-2xs" style="max-width: 85%;">
                                            <div class="d-flex justify-content-between align-items-center gap-3 mb-1" style="font-size: 0.75rem;">
                                                <span class="fw-semibold <?= $estMonMessage ? 'text-white-50' : 'text-secondary' ?>"><?= $nomExp ?></span>
                                                <span class="<?= $estMonMessage ? 'text-white-50' : 'text-muted' ?>">
                                                    <?= date('d/m/Y H:i', strtotime($msg['date_envoi'])) ?>
                                                </span>
                                            </div>
                                            <div class="small" style="white-space: pre-wrap; word-break: break-word;"><?= e($msg['contenu']) ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="mt-3 pt-3 border-top d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2">
                            <span class="small text-secondary">
                                <i class="bi bi-shield-check text-success me-1"></i>Échanges directs et confidentiels
                            </span>
                            <a href="<?= url('chat/conversation.php?id=' . (int) $demande['id_conversation']) ?>" 
                               class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1.5 shadow-2xs">
                                <i class="bi bi-chat-dots-fill" aria-hidden="true"></i>
                                <span>Accéder au chat en plein écran</span>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div>

        <!-- Colonne droite : Fiches des parties prenantes -->
        <div class="col-12 col-lg-5">

            <!-- Bloc Actions de transition de statut -->
            <?php if (!empty($actionsPossibles)): ?>
                <div class="card shadow-sm border border-primary border-opacity-25 mb-4 bg-primary bg-opacity-10">
                    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                        <h2 class="h6 fw-bold mb-0 text-dark">
                            <i class="bi bi-lightning-charge-fill me-2 text-primary"></i>Actions disponibles
                        </h2>
                        <span class="badge bg-primary text-white small">Action requise</span>
                    </div>
                    <div class="card-body p-4 bg-white">
                        <p class="text-secondary small mb-3">
                            Faites évoluer l'état de cette prestation selon l'avancement réel des travaux :
                        </p>
                        <div class="d-flex flex-column gap-2">
                            <?php foreach ($actionsPossibles as $act): ?>
                                <form method="POST" action="<?= url('demande_detail.php?id=' . (int) $demande['id_demande']) ?>" onsubmit="return confirm(<?= json_encode($act['confirmation']) ?>);">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="nouveau_statut" value="<?= e($act['statut']) ?>">
                                    <button type="submit" class="btn <?= e($act['btn_class']) ?> w-100 d-flex align-items-center justify-content-center gap-2 py-2">
                                        <i class="bi <?= e($act['icon']) ?>"></i>
                                        <span class="fw-semibold"><?= e($act['label']) ?></span>
                                    </button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Fiche Professionnel -->
            <div class="card shadow-sm border mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h2 class="h6 fw-semibold mb-0 text-dark">
                        <i class="bi bi-person-badge me-2 text-primary"></i>Professionnel sollicité
                    </h2>
                </div>
                <div class="card-body p-4 text-center">
                    <div class="mb-3">
                        <img src="<?= e($photoProUrl) ?>" alt="Photo de <?= e($nomAffichePro) ?>" class="rounded-circle object-fit-cover shadow-2xs border" style="width: 80px; height: 80px;">
                    </div>
                    
                    <h3 class="h5 fw-bold mb-1 text-dark"><?= e($nomAffichePro) ?></h3>
                    <p class="text-secondary small mb-2"><?= e($demande['pro_titre']) ?></p>

                    <div class="d-flex justify-content-center align-items-center gap-2 mb-3">
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 small rounded-pill">
                            <i class="bi bi-shield-check me-1"></i>Profil approuvé FindPro
                        </span>
                        <?php if (!empty($demande['pro_zone'])): ?>
                            <span class="text-muted small">
                                <i class="bi bi-geo-alt me-1"></i><?= e($demande['pro_zone']) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="border-top pt-3 mt-3">
                        <a href="<?= url('profile.php?id=' . $demande['id_profil']) ?>" class="btn btn-outline-primary btn-sm w-100">
                            <i class="bi bi-arrow-up-right-square me-1"></i>Consulter la vitrine publique
                        </a>
                    </div>
                </div>
            </div>

            <!-- Fiche Client demandeur -->
            <div class="card shadow-sm border mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h2 class="h6 fw-semibold mb-0 text-dark">
                        <i class="bi bi-person-check me-2 text-primary"></i>Client demandeur
                    </h2>
                </div>
                <div class="card-body p-4">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="position-relative flex-shrink-0">
                            <?php if (!empty($demande['client_photo']) && file_exists(__DIR__ . '/' . $demande['client_photo'])): ?>
                                <img src="<?= url($demande['client_photo']) ?>" alt="Photo client" class="rounded-circle object-fit-cover shadow-2xs border" style="width: 48px; height: 48px;">
                            <?php else: ?>
                                <div class="rounded-circle bg-secondary bg-opacity-10 text-secondary d-flex align-items-center justify-content-center shadow-2xs" style="width: 48px; height: 48px;">
                                    <i class="bi bi-person fs-3"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <?php 
                                $isClientSociete = in_array((string) ($demande['client_type'] ?? ''), ['societe', 'entreprise', 'organisation'], true); 
                            ?>
                            <strong class="d-block text-dark"><?= e($clientNomComplet) ?></strong>
                            <span class="badge <?= $isClientSociete ? 'bg-primary' : 'bg-success' ?> bg-opacity-10 text-<?= $isClientSociete ? 'primary' : 'success' ?> border small font-monospace">
                                <i class="bi <?= $isClientSociete ? 'bi-building' : 'bi-person' ?> me-1"></i>
                                <?= $isClientSociete ? 'Société / Entreprise' : 'Particulier' ?>
                            </span>
                        </div>
                    </div>

                    <?php if ($isClientSociete && !empty($demande['client_nom_societe'])): ?>
                        <div class="p-2.5 bg-light rounded border mb-3 small">
                            <strong class="text-dark d-block"><i class="bi bi-briefcase me-1 text-primary"></i><?= e($demande['client_nom_societe']) ?></strong>
                            <?php if (!empty($demande['client_secteur'])): ?>
                                <span class="text-muted d-block"><?= e($demande['client_secteur']) ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="small border-top pt-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-secondary">Statut du compte :</span>
                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Actif</span>
                        </div>
                        <?php if (!empty($demande['client_ville'])): ?>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-secondary">Localisation :</span>
                                <span class="text-dark fw-medium"><?= e($demande['client_ville']) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($estProDestinataire || $estAdmin): ?>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-secondary">Email de contact :</span>
                                <span class="text-dark fw-medium"><?= e($demande['client_email']) ?></span>
                            </div>
                            <?php if (!empty($demande['client_telephone'])): ?>
                                <div class="d-flex justify-content-between">
                                    <span class="text-secondary">Téléphone :</span>
                                    <span class="text-dark fw-medium"><?= e($demande['client_telephone']) ?></span>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Rappel déontologique et de confiance -->
            <div class="card shadow-sm border bg-light">
                <div class="card-body p-3">
                    <div class="d-flex gap-2 align-items-start">
                        <i class="bi bi-shield-check text-primary fs-5 flex-shrink-0"></i>
                        <div class="small">
                            <strong class="d-block mb-1 text-dark">Rappel de confiance</strong>
                            <span class="text-secondary">
                                FindPro sécurise les mises en relation et garantit l'approbation des profils. Aucun flux financier n'est débité sur la plateforme ; les modalités de règlement s'effectuent directement entre les parties.
                            </span>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>

</div>

<?php
require_once __DIR__ . '/includes/footer.php';
