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
        conv.id_conversation,
        conv.statut AS conversation_statut,
        conv.date_creation AS conversation_date_creation
    FROM demande_service d
    JOIN profil_professionnel p ON d.id_profil = p.id_profil
    JOIN utilisateur u_pro ON p.id_utilisateur = u_pro.id_utilisateur
    JOIN utilisateur u_client ON d.id_client = u_client.id_utilisateur
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
                        <div class="rounded-circle bg-secondary bg-opacity-10 text-secondary d-flex align-items-center justify-content-center shadow-2xs" style="width: 48px; height: 48px;">
                            <i class="bi bi-person fs-3"></i>
                        </div>
                        <div>
                            <strong class="d-block text-dark"><?= e($clientNomComplet) ?></strong>
                            <small class="text-secondary">Client vérifié FindPro</small>
                        </div>
                    </div>

                    <div class="small border-top pt-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-secondary">Statut du compte :</span>
                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Actif</span>
                        </div>
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
