<?php
/**
 * Tableau de bord Professionnel — FindPro.
 * 
 * - Accès strictement réservé aux utilisateurs ayant le rôle "professionnel".
 * - Encadré d'alerte avec le statut du profil et le dernier motif de décision (table validation_profil)
 *   quand le statut est 'a_corriger', 'refuse' ou 'suspendu', avec la date.
 * - Quand 'a_corriger' : bouton menant à pro/profil.php en mode modifiable.
 * - Quand 'refuse' : message de rejet définitif avec motif (pas de nouvelle soumission sans autorisation).
 * - Quand 'suspendu' : encadré spécifique avec motif et consignes.
 * - Liste chronologique de toutes les décisions administratives passées.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';

// 1. Contrôle d'accès strict
demarrer_session();
$utilisateur = exiger_role('professionnel');
$idUtilisateur = (int) $utilisateur['id'];

$pdo = db();

// 2. Récupération du profil professionnel associé
$stmtProfil = $pdo->prepare('
    SELECT 
        p.*,
        c.libelle AS categorie_libelle,
        c.slug AS categorie_slug
    FROM profil_professionnel p
    LEFT JOIN categorie c ON p.id_categorie = c.id_categorie
    WHERE p.id_utilisateur = :id_user
    LIMIT 1
');
$stmtProfil->execute(['id_user' => $idUtilisateur]);
$profil = $stmtProfil->fetch();

$idProfil = $profil ? (int) $profil['id_profil'] : null;
$statutCourant = $profil ? (string) $profil['statut_validation'] : 'brouillon';

// 3. Récupération de l'historique complet des décisions (table validation_profil)
$historiqueDecisions = [];
$derniereDecision = null;

if ($idProfil !== null) {
    $stmtDecisions = $pdo->prepare('
        SELECT 
            vp.*,
            u.prenom AS admin_prenom,
            u.nom AS admin_nom,
            u.email AS admin_email
        FROM validation_profil vp
        JOIN utilisateur u ON vp.id_administrateur = u.id_utilisateur
        WHERE vp.id_profil = :id_profil
        ORDER BY vp.date_decision DESC, vp.id_validation DESC
    ');
    $stmtDecisions->execute(['id_profil' => $idProfil]);
    $historiqueDecisions = $stmtDecisions->fetchAll();

    if (!empty($historiqueDecisions)) {
        $derniereDecision = $historiqueDecisions[0];
    }
}

// 4. Statistiques du tableau de bord
$nbDemandes = 0;
$nbMissionsEnCours = 0;
if ($idProfil !== null) {
    $stmtDemandes = $pdo->prepare('SELECT COUNT(*) FROM demande_service WHERE id_profil = :id_profil');
    $stmtDemandes->execute(['id_profil' => $idProfil]);
    $nbDemandes = (int) $stmtDemandes->fetchColumn();

    $stmtEnCours = $pdo->prepare('SELECT COUNT(*) FROM demande_service WHERE id_profil = :id_profil AND statut = "en_cours"');
    $stmtEnCours->execute(['id_profil' => $idProfil]);
    $nbMissionsEnCours = (int) $stmtEnCours->fetchColumn();
}

// Configuration des badges de décision et de statut
$badgesStatut = [
    'brouillon'       => ['label' => 'Brouillon', 'badge' => 'bg-secondary text-white', 'icon' => 'bi-pencil'],
    'soumis'          => ['label' => 'Soumis', 'badge' => 'bg-info text-dark', 'icon' => 'bi-hourglass-split'],
    'en_verification' => ['label' => 'En cours d\'examen', 'badge' => 'bg-warning text-dark', 'icon' => 'bi-search'],
    'a_corriger'      => ['label' => 'À corriger', 'badge' => 'bg-warning text-dark', 'icon' => 'bi-exclamation-triangle'],
    'approuve'        => ['label' => 'Approuvé (Public)', 'badge' => 'bg-success text-white', 'icon' => 'bi-check-circle'],
    'refuse'          => ['label' => 'Refusé', 'badge' => 'bg-danger text-white', 'icon' => 'bi-x-circle'],
    'suspendu'        => ['label' => 'Suspendu', 'badge' => 'bg-dark text-white', 'icon' => 'bi-slash-circle'],
];
$badgeActuel = $badgesStatut[$statutCourant] ?? ['label' => $statutCourant, 'badge' => 'bg-light text-dark', 'icon' => 'bi-dot'];

$titre_page = 'Tableau de bord Professionnel';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4 py-lg-5">
    
    <!-- En-tête principal du tableau de bord -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-2 border-bottom">
        <div>
            <h1 class="h3 fw-bold mb-1">
                Tableau de bord — <?= e($utilisateur['prenom'] . ' ' . $utilisateur['nom']) ?>
            </h1>
            <p class="text-secondary small mb-0">
                Gérez votre profil professionnel, vos réalisations et suivez le statut de validation administrative.
            </p>
        </div>
        <div>
            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 py-2 rounded-pill font-monospace small">
                <i class="bi bi-briefcase me-1"></i>Espace Professionnel
            </span>
        </div>
    </div>

    <!-- Messages Flash -->
    <?php 
    $flashes = flash_get();
    foreach ($flashes as $flash): 
    ?>
        <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show shadow-sm mb-4" role="alert">
            <?= e($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
        </div>
    <?php endforeach; ?>

    <!-- Navigation mobile : Onglets défilants horizontaux (affichés uniquement < lg) -->
    <div class="d-lg-none mb-4">
        <ul class="nav nav-pills flex-nowrap overflow-x-auto pb-2 gap-2 text-nowrap" style="scrollbar-width: thin;">
            <li class="nav-item">
                <a class="nav-link active btn-sm" href="<?= url('pro/index.php') ?>">
                    <i class="bi bi-speedometer2 me-1"></i>Vue d'ensemble
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-secondary btn-sm" href="<?= url('pro/profil.php') ?>">
                    <i class="bi bi-person-lines-fill me-1"></i>Mon profil
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-secondary btn-sm" href="<?= url('pro/competences.php') ?>">
                    <i class="bi bi-award me-1"></i>Compétences
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-secondary btn-sm" href="<?= url('pro/portfolio.php') ?>">
                    <i class="bi bi-images me-1"></i>Portfolio
                </a>
            </li>
        </ul>
    </div>

    <div class="row g-4">
        
        <!-- Navigation latérale Desktop (affichée uniquement >= lg) -->
        <aside class="col-lg-3 d-none d-lg-block">
            <div class="card shadow-sm border mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <span class="fw-semibold small text-uppercase text-secondary tracking-wider">Espace Pro</span>
                </div>
                <div class="list-group list-group-flush small">
                    <a href="<?= url('pro/index.php') ?>" class="list-group-item list-group-item-action active d-flex align-items-center justify-content-between py-2 fw-medium">
                        <span><i class="bi bi-speedometer2 me-2"></i>Vue d'ensemble</span>
                        <i class="bi bi-chevron-right small"></i>
                    </a>
                    <a href="<?= url('pro/profil.php') ?>" class="list-group-item list-group-item-action text-secondary d-flex align-items-center justify-content-between py-2">
                        <span><i class="bi bi-person-lines-fill me-2"></i>Mon profil</span>
                        <i class="bi bi-chevron-right small"></i>
                    </a>
                    <a href="<?= url('pro/competences.php') ?>" class="list-group-item list-group-item-action text-secondary d-flex align-items-center justify-content-between py-2">
                        <span><i class="bi bi-award me-2"></i>Compétences</span>
                        <i class="bi bi-chevron-right small"></i>
                    </a>
                    <a href="<?= url('pro/portfolio.php') ?>" class="list-group-item list-group-item-action text-secondary d-flex align-items-center justify-content-between py-2">
                        <span><i class="bi bi-images me-2"></i>Portfolio</span>
                        <i class="bi bi-chevron-right small"></i>
                    </a>
                </div>
            </div>

            <!-- Récapitulatif du profil dans la barre latérale -->
            <?php if ($profil): ?>
                <div class="card shadow-sm border p-3 small">
                    <span class="fw-semibold text-secondary text-uppercase mb-2 d-block" style="font-size: 0.72rem;">Statut de votre vitrine</span>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge <?= e($badgeActuel['badge']) ?>">
                            <i class="bi <?= e($badgeActuel['icon']) ?> me-1"></i><?= e($badgeActuel['label']) ?>
                        </span>
                    </div>
                    <span class="text-dark fw-medium d-block text-truncate" title="<?= e($profil['titre']) ?>"><?= e($profil['titre']) ?></span>
                    <small class="text-secondary d-block"><?= e($profil['categorie_libelle'] ?? 'Catégorie non assignée') ?></small>
                </div>
            <?php endif; ?>
        </aside>

        <!-- Contenu principal -->
        <main class="col-12 col-lg-9">

            <!-- =============================================================== -->
            <!-- ENCADRÉ DÉCISIONNEL PRIORITAIRE SELON LE STATUT                  -->
            <!-- =============================================================== -->

            <!-- CAS 1 : STATUT 'a_corriger' -->
            <?php if ($statutCourant === 'a_corriger'): 
                $dateDecisionFormat = ($derniereDecision && !empty($derniereDecision['date_decision']))
                    ? date('d/m/Y à H:i', strtotime($derniereDecision['date_decision']))
                    : 'récemment';
                $motifAffiche = $derniereDecision['motif'] ?? 'Veuillez vérifier et ajuster les informations de votre profil.';
            ?>
                <div class="card border-warning shadow-sm mb-4 bg-warning bg-opacity-10">
                    <div class="card-body p-4">
                        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-start gap-3 mb-3">
                            <div class="d-flex align-items-start gap-3">
                                <div class="bg-warning text-dark p-2 rounded-circle flex-shrink-0">
                                    <i class="bi bi-exclamation-triangle-fill fs-4"></i>
                                </div>
                                <div>
                                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                        <h2 class="h5 fw-bold text-dark mb-0">Corrections demandées sur votre profil</h2>
                                        <span class="badge bg-warning text-dark">Statut : À corriger</span>
                                    </div>
                                    <p class="small text-secondary mb-0">
                                        Décision administrative notifiée le <strong><?= e($dateDecisionFormat) ?></strong>.
                                    </p>
                                </div>
                            </div>
                            <a href="<?= url('pro/profil.php') ?>" class="btn btn-warning btn-sm fw-semibold text-dark text-nowrap shadow-sm">
                                <i class="bi bi-pencil-square me-1"></i>Modifier mon profil
                            </a>
                        </div>

                        <!-- Motif consigné par l'administration -->
                        <div class="p-3 bg-white rounded border border-warning-subtle mb-3">
                            <strong class="text-secondary text-uppercase d-block small mb-1" style="font-size: 0.74rem;">
                                Motif des corrections demandées par l'équipe de modération :
                            </strong>
                            <div class="small text-dark fw-medium" style="white-space: pre-wrap; line-height: 1.5;"><?= e($motifAffiche) ?></div>
                        </div>

                        <div class="small text-secondary d-flex align-items-center gap-2">
                            <i class="bi bi-info-circle-fill text-warning fs-5 flex-shrink-0"></i>
                            <span>
                                Votre profil est actuellement <strong>en mode modifiable</strong>. Vous pouvez actualiser vos informations, vos compétences ou votre portfolio. Après modification, le bouton <strong>« Soumettre mon profil »</strong> est à nouveau disponible pour relancer l'arbitrage.
                            </span>
                        </div>
                    </div>
                </div>

            <!-- CAS 2 : STATUT 'refuse' -->
            <?php elseif ($statutCourant === 'refuse'): 
                $dateDecisionFormat = ($derniereDecision && !empty($derniereDecision['date_decision']))
                    ? date('d/m/Y à H:i', strtotime($derniereDecision['date_decision']))
                    : 'récemment';
                $motifAffiche = $derniereDecision['motif'] ?? 'Votre profil ne répond pas aux critères d\'éligibilité de la plateforme.';
            ?>
                <div class="card border-danger shadow-sm mb-4 bg-danger bg-opacity-10">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-start gap-3 mb-3">
                            <div class="bg-danger text-white p-2 rounded-circle flex-shrink-0">
                                <i class="bi bi-x-octagon-fill fs-4"></i>
                            </div>
                            <div>
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                    <h2 class="h5 fw-bold text-danger mb-0">Dossier de candidature refusé</h2>
                                    <span class="badge bg-danger text-white">Rejet définitif</span>
                                </div>
                                <p class="small text-secondary mb-0">
                                    Décision administrative prise le <strong><?= e($dateDecisionFormat) ?></strong>.
                                </p>
                            </div>
                        </div>

                        <!-- Motif de rejet -->
                        <div class="p-3 bg-white rounded border border-danger-subtle mb-3">
                            <strong class="text-danger text-uppercase d-block small mb-1" style="font-size: 0.74rem;">
                                Motif du refus notifié par l'administration :
                            </strong>
                            <div class="small text-dark fw-medium" style="white-space: pre-wrap; line-height: 1.5;"><?= e($motifAffiche) ?></div>
                        </div>

                        <div class="alert alert-danger bg-white border-danger-subtle small mb-0 d-flex align-items-center gap-2">
                            <i class="bi bi-shield-x fs-5 text-danger flex-shrink-0"></i>
                            <div>
                                <strong>Message de rejet définitif :</strong> Ce profil a fait l'objet d'un refus ferme par l'équipe FindPro. 
                                <span class="d-block text-danger-emphasis">Pas de nouvelle soumission possible sans nouvelle autorisation préalable délivrée par l'administration.</span>
                            </div>
                        </div>
                    </div>
                </div>

            <!-- CAS 3 : STATUT 'suspendu' -->
            <?php elseif ($statutCourant === 'suspendu'): 
                $dateDecisionFormat = ($derniereDecision && !empty($derniereDecision['date_decision']))
                    ? date('d/m/Y à H:i', strtotime($derniereDecision['date_decision']))
                    : 'récemment';
                $motifAffiche = $derniereDecision['motif'] ?? 'Activité ou profil suspendu suite à vérification administrative.';
            ?>
                <div class="card border-dark shadow-sm mb-4 bg-dark bg-opacity-10">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-start gap-3 mb-3">
                            <div class="bg-dark text-white p-2 rounded-circle flex-shrink-0">
                                <i class="bi bi-slash-circle-fill fs-4"></i>
                            </div>
                            <div>
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                    <h2 class="h5 fw-bold text-dark mb-0">Profil professionnel suspendu</h2>
                                    <span class="badge bg-dark text-white">Suspension administrative</span>
                                </div>
                                <p class="small text-secondary mb-0">
                                    Mesure conservatoire enregistrée le <strong><?= e($dateDecisionFormat) ?></strong>.
                                </p>
                            </div>
                        </div>

                        <!-- Motif de suspension -->
                        <div class="p-3 bg-white rounded border border-secondary mb-3">
                            <strong class="text-secondary text-uppercase d-block small mb-1" style="font-size: 0.74rem;">
                                Motif de la suspension :
                            </strong>
                            <div class="small text-dark fw-medium" style="white-space: pre-wrap; line-height: 1.5;"><?= e($motifAffiche) ?></div>
                        </div>

                        <div class="small text-secondary mb-0">
                            <i class="bi bi-info-circle me-1"></i>Votre vitrine publique est temporairement désactivée et retirée de la recherche. Pour toute demande de régularisation ou réactivation, veuillez vous rapprocher de l'administration FindPro.
                        </div>
                    </div>
                </div>

            <!-- CAS 4 : STATUT 'soumis' OU 'en_verification' -->
            <?php elseif (in_array($statutCourant, ['soumis', 'en_verification'], true)): ?>
                <div class="alert alert-info shadow-sm mb-4 border-info d-flex align-items-start gap-3" role="alert">
                    <i class="bi bi-hourglass-split fs-3 text-info flex-shrink-0"></i>
                    <div>
                        <strong class="d-block mb-1">Dossier en cours d'examen par notre équipe (<?= e($badgeActuel['label']) ?>)</strong>
                        <p class="small mb-0">
                            Votre profil a été transmis à la modération le <?= !empty($profil['date_soumission']) ? e(date('d/m/Y à H:i', strtotime($profil['date_soumission']))) : 'récemment' ?>. Pendant toute la période d'arbitrage, votre fiche est consultable en lecture seule. Vous serez notifié dès qu'une décision sera prise.
                        </p>
                    </div>
                </div>

            <!-- CAS 5 : STATUT 'approuve' -->
            <?php elseif ($statutCourant === 'approuve'): ?>
                <div class="alert alert-success shadow-sm mb-4 border-success d-flex align-items-start gap-3" role="alert">
                    <i class="bi bi-check-circle-fill fs-3 text-success flex-shrink-0"></i>
                    <div>
                        <strong class="d-block mb-1">Votre profil est validé et actif dans la recherche publique !</strong>
                        <p class="small mb-0">
                            Les clients peuvent désormais consulter vos réalisations, vos compétences et vous envoyer des demandes de prestations.
                        </p>
                    </div>
                </div>

            <!-- CAS 6 : STATUT 'brouillon' OU INEXISTANT -->
            <?php else: ?>
                <div class="alert alert-secondary shadow-sm mb-4 d-flex align-items-start gap-3" role="alert">
                    <i class="bi bi-pencil-square fs-3 text-secondary flex-shrink-0"></i>
                    <div>
                        <strong class="d-block mb-1">Votre profil est encore à l'état de brouillon</strong>
                        <p class="small mb-2">
                            Complétez vos compétences, décrivez votre métier et ajoutez au moins un projet de portfolio pour pouvoir soumettre votre candidature à l'administration.
                        </p>
                        <a href="<?= url('pro/profil.php') ?>" class="btn btn-primary btn-sm">
                            <i class="bi bi-arrow-right me-1"></i>Accéder à mon profil
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Cartes récapitulatives d'activité -->
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-md-4">
                    <div class="card p-3 shadow-sm border text-center h-100">
                        <small class="text-secondary mb-1">Demandes reçues</small>
                        <span class="h4 fw-bold mb-0 text-primary"><?= $nbDemandes ?></span>
                    </div>
                </div>
                <div class="col-sm-6 col-md-4">
                    <div class="card p-3 shadow-sm border text-center h-100">
                        <small class="text-secondary mb-1">Missions en cours</small>
                        <span class="h4 fw-bold mb-0 text-dark"><?= $nbMissionsEnCours ?></span>
                    </div>
                </div>
                <div class="col-sm-6 col-md-4">
                    <div class="card p-3 shadow-sm border text-center h-100">
                        <small class="text-secondary mb-1">Statut administratif</small>
                        <span class="badge <?= e($badgeActuel['badge']) ?> align-self-center mt-1 py-1.5 px-3">
                            <i class="bi <?= e($badgeActuel['icon']) ?> me-1"></i><?= e($badgeActuel['label']) ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- =============================================================== -->
            <!-- HISTORIQUE DES DÉCISIONS PASSÉES (table validation_profil)       -->
            <!-- =============================================================== -->
            <div class="card shadow-sm border mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                    <h2 class="h6 fw-semibold text-dark text-uppercase tracking-wider mb-0">
                        <i class="bi bi-clock-history me-1.5 text-primary"></i>
                        Historique des décisions passées (<?= count($historiqueDecisions) ?>)
                    </h2>
                    <span class="badge bg-light text-secondary border small">Traçabilité administrative</span>
                </div>
                <div class="card-body p-4">
                    <?php if (empty($historiqueDecisions)): ?>
                        <div class="p-4 bg-light rounded text-center text-muted small">
                            <i class="bi bi-inbox fs-2 d-block mb-2 opacity-50"></i>
                            <p class="mb-0">Aucune décision administrative n'a encore été enregistrée pour votre profil.</p>
                        </div>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($historiqueDecisions as $decision): 
                                $cfgDec = $badgesStatut[$decision['decision']] ?? ['label' => $decision['decision'], 'badge' => 'bg-secondary text-white', 'icon' => 'bi-dot'];
                                $dateDec = !empty($decision['date_decision']) ? date('d/m/Y à H:i', strtotime($decision['date_decision'])) : 'Date non précisée';
                            ?>
                                <div class="p-3 bg-light rounded border">
                                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-2 pb-2 border-bottom">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge <?= e($cfgDec['badge']) ?> px-2.5 py-1">
                                                <i class="bi <?= e($cfgDec['icon']) ?> me-1"></i><?= e($cfgDec['label']) ?>
                                            </span>
                                            <span class="small text-secondary">
                                                Évalué par <strong><?= e($decision['admin_prenom'] . ' ' . $decision['admin_nom']) ?></strong> (Administration)
                                            </span>
                                        </div>
                                        <span class="small text-muted font-monospace"><?= e($dateDec) ?></span>
                                    </div>
                                    
                                    <?php if (!empty($decision['motif'])): ?>
                                        <div class="small p-2.5 bg-white rounded border text-dark">
                                            <strong class="text-secondary text-uppercase d-block" style="font-size: 0.70rem;">Motif / Remarques :</strong>
                                            <div class="mt-1" style="white-space: pre-wrap; line-height: 1.5;"><?= e($decision['motif']) ?></div>
                                        </div>
                                    <?php else: ?>
                                        <small class="text-muted fst-italic">Aucun commentaire particulier consigné lors de cette décision.</small>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Raccourcis de gestion rapide -->
            <div class="card shadow-sm border p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="h6 fw-bold mb-1">Gérer vos informations professionnelles</h3>
                        <p class="text-secondary small mb-0">Accédez à votre fiche de compétences, vos réalisations et votre présentation.</p>
                    </div>
                    <a href="<?= url('pro/profil.php') ?>" class="btn btn-outline-primary btn-sm">
                        Ouvrir mon profil <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>

        </main>
    </div>

</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
