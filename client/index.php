<?php
/**
 * Tableau de bord Client — FindPro.
 * 
 * Accès strictement réservé aux utilisateurs ayant le rôle "client".
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';

demarrer_session();
$utilisateur = exiger_role('client');
$idClient = (int) ($utilisateur['id'] ?? $utilisateur['id_utilisateur']);
$pdo = db();

// ============================================================================
// 1. STATISTIQUES RÉELLES DU CLIENT
// ============================================================================

// Demandes actives
$stmtActives = $pdo->prepare("
    SELECT COUNT(*) 
    FROM demande_service 
    WHERE id_client = :cid AND statut IN ('envoyee', 'acceptee', 'en_cours', 'a_confirmer')
");
$stmtActives->execute(['cid' => $idClient]);
$nbActives = (int) $stmtActives->fetchColumn();

// Demandes terminées
$stmtTerminees = $pdo->prepare("
    SELECT COUNT(*) 
    FROM demande_service 
    WHERE id_client = :cid AND statut = 'terminee'
");
$stmtTerminees->execute(['cid' => $idClient]);
$nbTerminees = (int) $stmtTerminees->fetchColumn();

// Avis déposés
$stmtAvis = $pdo->prepare("
    SELECT COUNT(*) 
    FROM avis 
    WHERE id_client = :cid
");
$stmtAvis->execute(['cid' => $idClient]);
$nbAvis = (int) $stmtAvis->fetchColumn();

// Demandes terminées en attente d'évaluation
$stmtAvisEnAttente = $pdo->prepare("
    SELECT d.id_demande, d.titre, p.titre AS pro_titre, u.prenom AS pro_prenom, u.nom AS pro_nom
    FROM demande_service d
    JOIN profil_professionnel p ON d.id_profil = p.id_profil
    JOIN utilisateur u ON p.id_utilisateur = u.id_utilisateur
    LEFT JOIN avis a ON d.id_demande = a.id_demande
    WHERE d.id_client = :cid AND d.statut = 'terminee' AND a.id_avis IS NULL
    ORDER BY d.date_cloture DESC
    LIMIT 3
");
$stmtAvisEnAttente->execute(['cid' => $idClient]);
$demandesAvisEnAttente = $stmtAvisEnAttente->fetchAll(PDO::FETCH_ASSOC);

// Profil client
$stmtPC = $pdo->prepare("SELECT * FROM profil_client WHERE id_utilisateur = :id");
$stmtPC->execute(['id' => $idClient]);
$profilClient = $stmtPC->fetch(PDO::FETCH_ASSOC);

$typeStructure = (string) ($profilClient['type_structure'] ?? $profilClient['type_client'] ?? 'particulier');
$isSociete = in_array($typeStructure, ['societe', 'entreprise', 'organisation'], true);
$typeClient = $isSociete ? 'societe' : 'particulier';
$nomStructure = (string) ($profilClient['nom_structure'] ?? $profilClient['nom_societe'] ?? '');
$photoUrl = (!empty($profilClient['photo']) && file_exists(__DIR__ . '/../' . $profilClient['photo']))
    ? url($profilClient['photo'])
    : url('assets/img/avatar-defaut.svg');

$titre_page = 'Tableau de bord Client';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4 py-lg-5">
    
    <!-- En-tête principal du tableau de bord -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-2 border-bottom">
        <div>
            <h1 class="h3 fw-bold mb-1">
                Tableau de bord Client — <?= e($utilisateur['prenom']) ?>
            </h1>
            <p class="text-secondary small mb-0">
                Suivez vos prestations numériques, évaluez vos prestataires et gérez vos paramètres de compte.
            </p>
        </div>
        <div>
            <span class="badge <?= $typeClient === 'societe' ? 'bg-primary' : 'bg-success' ?> bg-opacity-10 text-<?= $typeClient === 'societe' ? 'primary' : 'success' ?> border border-<?= $typeClient === 'societe' ? 'primary' : 'success' ?> border-opacity-25 px-3 py-2 rounded-pill small">
                <i class="bi <?= $typeClient === 'societe' ? 'bi-building' : 'bi-person-check' ?> me-1"></i>
                <?= $typeClient === 'societe' ? 'Compte Société / Entreprise' : 'Compte Particulier / Indépendant' ?>
            </span>
        </div>
    </div>

    <!-- Navigation mobile : Onglets défilants horizontaux (< lg) -->
    <div class="d-lg-none mb-4">
        <ul class="nav nav-pills flex-nowrap overflow-x-auto pb-2 gap-2 text-nowrap" style="scrollbar-width: thin;">
            <li class="nav-item">
                <a class="nav-link active btn-sm" href="<?= url('client/index.php') ?>">
                    <i class="bi bi-speedometer2 me-1"></i>Vue d'ensemble
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-secondary btn-sm" href="<?= url('client/demandes.php') ?>">
                    <i class="bi bi-grid me-1"></i>Mes demandes
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-secondary btn-sm" href="<?= url('client/profil.php') ?>">
                    <i class="bi bi-person-gear me-1"></i>Mon profil &amp; Paramètres
                </a>
            </li>
        </ul>
    </div>

    <div class="row g-4">
        
        <!-- Navigation latérale Desktop (>= lg) -->
        <aside class="col-lg-3 d-none d-lg-block">
            <div class="card shadow-sm border mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <span class="fw-semibold small text-uppercase text-secondary tracking-wider">Mon Compte</span>
                </div>
                <div class="list-group list-group-flush small">
                    <a href="<?= url('client/index.php') ?>" class="list-group-item list-group-item-action active d-flex align-items-center justify-content-between py-2 fw-medium">
                        <span><i class="bi bi-speedometer2 me-2"></i>Vue d'ensemble</span>
                        <i class="bi bi-chevron-right small"></i>
                    </a>
                    <a href="<?= url('client/demandes.php') ?>" class="list-group-item list-group-item-action text-secondary d-flex align-items-center justify-content-between py-2">
                        <span><i class="bi bi-grid me-2"></i>Mes demandes</span>
                        <i class="bi bi-chevron-right small"></i>
                    </a>
                    <a href="<?= url('client/profil.php') ?>" class="list-group-item list-group-item-action text-secondary d-flex align-items-center justify-content-between py-2">
                        <span><i class="bi bi-person-gear me-2"></i>Mon profil &amp; Paramètres</span>
                        <i class="bi bi-chevron-right small"></i>
                    </a>
                </div>
            </div>

            <!-- Carte récapitulative profil client -->
            <div class="card shadow-sm border p-3 text-center">
                <div class="position-relative d-inline-block mx-auto mb-2">
                    <img src="<?= e($photoUrl) ?>" alt="Photo de profil" class="rounded-circle object-fit-cover border shadow-sm" style="width: 76px; height: 76px;">
                    <span class="position-absolute bottom-0 end-0 badge rounded-pill <?= $typeClient === 'societe' ? 'bg-primary' : 'bg-success' ?> p-1.5">
                        <i class="bi <?= $typeClient === 'societe' ? 'bi-building' : 'bi-person' ?>"></i>
                    </span>
                </div>
                <h6 class="fw-bold text-dark mb-0"><?= e($utilisateur['prenom']) ?></h6>
                <span class="badge bg-light text-secondary border small mt-1 mb-2">
                    <?= $typeClient === 'societe' ? '🏢 Entreprise' : '👤 Particulier' ?>
                </span>
                <?php if ($typeClient === 'societe' && !empty($nomStructure)): ?>
                    <p class="small text-muted mb-1 fw-semibold text-truncate">
                        <i class="bi bi-building me-1"></i><?= e($nomStructure) ?>
                    </p>
                <?php endif; ?>
                <div class="mt-2 pt-2 border-top">
                    <a href="<?= url('client/profil.php') ?>" class="btn btn-outline-primary btn-sm w-100 d-inline-flex align-items-center justify-content-center gap-1.5">
                        <i class="bi bi-pencil-square"></i>
                        <span>Modifier mon profil</span>
                    </a>
                </div>
            </div>
        </aside>

        <!-- Contenu principal -->
        <main class="col-12 col-lg-9">
            
            <!-- Cartes récapitulatives statistiques réelles -->
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-md-4">
                    <div class="card p-3 shadow-sm border text-center h-100">
                        <small class="text-secondary mb-1">Demandes actives</small>
                        <span class="h3 fw-bold mb-0 text-primary"><?= $nbActives ?></span>
                        <small class="text-muted mt-1" style="font-size: 0.75rem;">En attente ou en cours</small>
                    </div>
                </div>
                <div class="col-sm-6 col-md-4">
                    <div class="card p-3 shadow-sm border text-center h-100">
                        <small class="text-secondary mb-1">Prestations terminées</small>
                        <span class="h3 fw-bold mb-0 text-success"><?= $nbTerminees ?></span>
                        <small class="text-muted mt-1" style="font-size: 0.75rem;">Missions clôturées</small>
                    </div>
                </div>
                <div class="col-sm-6 col-md-4">
                    <div class="card p-3 shadow-sm border text-center h-100">
                        <small class="text-secondary mb-1">Avis &amp; Évaluations déposés</small>
                        <span class="h3 fw-bold mb-0 text-warning"><?= $nbAvis ?></span>
                        <small class="text-muted mt-1" style="font-size: 0.75rem;">Notes certifiées</small>
                    </div>
                </div>
            </div>

            <!-- Alerte spécifique : Demandes terminées à évaluer -->
            <?php if (!empty($demandesAvisEnAttente)): ?>
                <div class="card border-warning border-opacity-50 shadow-sm mb-4">
                    <div class="card-header bg-warning bg-opacity-10 border-bottom border-warning border-opacity-25 py-2.5 d-flex align-items-center justify-content-between">
                        <span class="fw-bold text-dark small d-flex align-items-center gap-1.5">
                            <i class="bi bi-star-fill text-warning"></i>
                            <span>Évaluations en attente — Donnez votre avis !</span>
                        </span>
                        <span class="badge bg-warning text-dark small font-monospace">
                            <?= count($demandesAvisEnAttente) ?> mission<?= count($demandesAvisEnAttente) > 1 ? 's' : '' ?>
                        </span>
                    </div>
                    <div class="card-body p-3">
                        <p class="small text-secondary mb-3">
                            Vous avez récemment finalisé des prestations. Votre avis certifié aide grandement la communauté FindPro et récompense le travail des professionnels.
                        </p>
                        <div class="vstack gap-2">
                            <?php foreach ($demandesAvisEnAttente as $dAtt): 
                                $nomPro = trim($dAtt['pro_prenom'] . ' ' . (isset($dAtt['pro_nom']) ? mb_substr((string) $dAtt['pro_nom'], 0, 1, 'UTF-8') . '.' : ''));
                            ?>
                                <div class="p-2.5 bg-light rounded border d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2">
                                    <div>
                                        <strong class="text-dark small d-block"><?= e($dAtt['titre']) ?></strong>
                                        <span class="text-muted small">Prestataire : <?= e($nomPro) ?> (<?= e($dAtt['pro_titre']) ?>)</span>
                                    </div>
                                    <div>
                                        <a href="<?= url('demande_detail.php?id=' . (int) $dAtt['id_demande'] . '#evaluation') ?>" class="btn btn-warning btn-sm d-inline-flex align-items-center gap-1 shadow-2xs text-nowrap">
                                            <i class="bi bi-star-fill"></i>
                                            <span>Noter ce professionnel</span>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Carte d'action rapide : Trouver un prestataire -->
            <div class="card shadow-sm border mb-4">
                <div class="card-body p-4 p-md-5 text-center bg-light rounded-3">
                    <div class="rounded-circle bg-white border p-3 mx-auto mb-3 text-primary d-inline-flex align-items-center justify-content-center shadow-sm" style="width: 64px; height: 64px;">
                        <i class="bi bi-search fs-2"></i>
                    </div>
                    <h2 class="h5 fw-bold text-dark mb-2">Besoin d'un nouveau professionnel pour votre projet ?</h2>
                    <p class="text-secondary small mb-4 mx-auto" style="max-width: 580px;">
                        Explorez notre annuaire d'experts vérifiés en développement web, mobile, montage vidéo et cybersécurité. Contactez-les directement sans intermédiaire.
                    </p>
                    <div class="d-flex flex-wrap justify-content-center gap-2">
                        <a href="<?= url('search.php') ?>" class="btn btn-brand px-3 py-2 shadow-sm d-inline-flex align-items-center gap-2">
                            <i class="bi bi-search"></i>
                            <span>Explorer tous les professionnels</span>
                        </a>
                        <a href="<?= url('category.php?slug=developpement') ?>" class="btn btn-outline-primary px-3 py-2">
                            <span>Développeurs web &amp; mobile</span>
                        </a>
                    </div>
                </div>
            </div>

        </main>
    </div>

</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
