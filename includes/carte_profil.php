<?php
/**
 * Composant de présentation de la carte professionnelle publique — FindPro.
 * 
 * RÈGLES DE SÉCURITÉ & CONFIDENTIALITÉ (RG05) :
 * - Fonction afficher_carte_profil(array $p) dédiée à l'affichage des profils publics.
 * - Nom affiché sous la forme "Prénom + Initiale du nom" (ex: Ibrahim T.).
 * - Ne divulgue JAMAIS l'adresse email ni le numéro de téléphone.
 * - Échappement anti-XSS intégral de toutes les données avec e().
 * - Bouton "Voir le profil" vers profile.php?id=...
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

/**
 * Affiche la carte Bootstrap d'un profil professionnel vérifié.
 *
 * @param array $p Données du professionnel fournies par profils_publics()
 * @return void
 */
function afficher_carte_profil(array $p): void
{
    $idProfil = (int) ($p['id_profil'] ?? 0);

    // Formatage du nom affiché : Prénom + Initiale du nom + point
    if (!empty($p['nom_affiche'])) {
        $nomAffiche = (string) $p['nom_affiche'];
    } else {
        $prenom = trim((string) ($p['prenom'] ?? ''));
        $nom = trim((string) ($p['nom'] ?? ''));
        $initialeNom = $nom !== '' ? mb_strtoupper(mb_substr($nom, 0, 1, 'UTF-8'), 'UTF-8') . '.' : '';
        $nomAffiche = trim($prenom . ' ' . $initialeNom);
    }
    if ($nomAffiche === '') {
        $nomAffiche = 'Professionnel FindPro';
    }

    $titre = (string) ($p['titre'] ?? 'Professionnel du numérique');
    $categorie = (string) ($p['categorie_libelle'] ?? $p['categorie'] ?? 'Métier du numérique');
    $isCyber = (!empty($p['categorie_slug']) && $p['categorie_slug'] === 'cybersecurite') || !empty($p['engagement_cyber']);

    // Résolution de l'avatar / photo
    $avatarDefautRel = 'assets/img/avatar-defaut.svg';
    $avatarDefautExiste = file_exists(__DIR__ . '/../' . $avatarDefautRel);
    $photoUrl = !empty($p['photo']) && file_exists(__DIR__ . '/../' . $p['photo']) ? url($p['photo']) : null;
    $avatarDefaut = $avatarDefautExiste ? url($avatarDefautRel) : null;

    // Disponibilité
    $dispo = (string) ($p['disponibilite'] ?? 'disponible');

    // Compétences (3 principales)
    $competences = [];
    if (!empty($p['competences']) && is_array($p['competences'])) {
        $competences = array_slice($p['competences'], 0, 3);
    } elseif (!empty($p['competences_brutes'])) {
        $competences = array_slice(explode('||', (string) $p['competences_brutes']), 0, 3);
    }

    // Avis & Notes
    $nbAvis = (int) ($p['nb_avis'] ?? 0);
    $noteMoyenne = isset($p['note_moyenne']) && $p['note_moyenne'] !== null ? (float) $p['note_moyenne'] : null;

    $urlProfil = url('profile.php?id=' . $idProfil);
    ?>
    <article class="card h-100 shadow-sm border rounded-3 hover-shadow transition-all bg-white overflow-hidden" data-profil-id="<?= $idProfil ?>">
        <div class="card-body p-3 p-sm-4 d-flex flex-column justify-content-between">
            
            <div>
                <!-- En-tête : Avatar + Identité + Badge de Disponibilité -->
                <div class="d-flex align-items-start gap-3 mb-3">
                    <div class="flex-shrink-0">
                        <?php if ($photoUrl !== null): ?>
                            <img src="<?= $photoUrl ?>" alt="Photo de <?= e($nomAffiche) ?>" class="rounded-circle object-fit-cover border shadow-sm" style="width: 58px; height: 58px;">
                        <?php elseif ($avatarDefaut !== null): ?>
                            <img src="<?= $avatarDefaut ?>" alt="Avatar par défaut" class="rounded-circle object-fit-cover border shadow-sm" style="width: 58px; height: 58px;">
                        <?php else: ?>
                            <!-- Espace réservé gris si visuel manquant -->
                            <div class="rounded-circle bg-secondary bg-opacity-25 d-flex align-items-center justify-content-center border shadow-sm text-secondary" style="width: 58px; height: 58px;" title="Espace réservé gris">
                                <i class="bi bi-person fs-3"></i>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="flex-grow-1" style="min-width: 0;">
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                            <h3 class="h6 fw-bold text-dark mb-0 text-truncate" style="min-width: 0;" title="<?= e($nomAffiche) ?>">
                                <?= e($nomAffiche) ?>
                            </h3>

                            <!-- Badge de disponibilité coloré -->
                            <div class="flex-shrink-0">
                                <?php if ($dispo === 'disponible'): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-1 px-2 font-monospace" style="font-size: 0.70rem;">
                                        <i class="bi bi-circle-fill me-1" style="font-size: 0.45rem;"></i>Disponible
                                    </span>
                                <?php elseif ($dispo === 'limitee'): ?>
                                    <span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-50 py-1 px-2 font-monospace" style="font-size: 0.70rem;">
                                        <i class="bi bi-clock-fill text-warning me-1"></i>Limité
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border py-1 px-2 font-monospace" style="font-size: 0.70rem;">
                                        <i class="bi bi-dash-circle me-1"></i>Indisponible
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Titre professionnel -->
                        <span class="text-primary fw-medium small d-block text-truncate mb-1" style="min-width: 0;" title="<?= e($titre) ?>">
                            <?= e($titre) ?>
                        </span>

                        <!-- Catégorie de métier -->
                        <div class="d-flex align-items-center gap-1.5 flex-wrap">
                            <span class="badge bg-light text-secondary border small">
                                <?= e($categorie) ?>
                            </span>
                            <?php if ($isCyber): ?>
                                <span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-50 small" title="Engagement déontologique certifié">
                                    <i class="bi bi-shield-check text-warning me-0.5"></i>Cyber
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Badge officiel FindPro -->
                <div class="mb-3">
                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 py-1 px-2.5 small fw-semibold d-inline-flex align-items-center gap-1">
                        <i class="bi bi-patch-check-fill text-primary"></i>
                        <span>Profil approuvé par FindPro</span>
                    </span>
                </div>

                <!-- 3 Compétences principales -->
                <div class="mb-3">
                    <span class="text-secondary d-block mb-1.5" style="font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600;">
                        Compétences clés :
                    </span>
                    <?php if (!empty($competences)): ?>
                        <div class="d-flex flex-wrap gap-1">
                            <?php foreach ($competences as $comp): ?>
                                <span class="badge bg-light text-dark border font-monospace py-1 px-2" style="font-size: 0.74rem;">
                                    <?= e($comp) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <span class="text-muted small fst-italic">Compétences en cours de qualification</span>
                    <?php endif; ?>
                </div>

                <!-- Zone d'intervention si renseignée -->
                <?php if (!empty($p['zone'])): ?>
                    <div class="text-secondary small mb-3 d-flex align-items-center gap-1" style="font-size: 0.80rem;">
                        <i class="bi bi-geo-alt text-danger opacity-75"></i>
                        <span class="text-truncate"><?= e($p['zone']) ?></span>
                    </div>
                <?php endif; ?>

            </div>

            <!-- Pied de carte : Avis & Bouton Voir le profil -->
            <div class="pt-3 border-top d-flex justify-content-between align-items-center gap-2 mt-2">
                <!-- Note moyenne et avis -->
                <div>
                    <?php if ($nbAvis > 0 && $noteMoyenne !== null): ?>
                        <div class="d-flex align-items-center gap-1 small">
                            <i class="bi bi-star-fill text-warning"></i>
                            <strong class="text-dark"><?= number_format($noteMoyenne, 1, ',', '') ?></strong>
                            <span class="text-secondary" style="font-size: 0.76rem;">(<?= $nbAvis ?> avis)</span>
                        </div>
                    <?php else: ?>
                        <span class="text-secondary small fst-italic" style="font-size: 0.78rem;">
                            <i class="bi bi-chat-left-dots me-1 text-muted"></i>Pas encore d'avis
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Bouton d'action -->
                <div>
                    <a href="<?= $urlProfil ?>" class="btn btn-brand btn-sm d-inline-flex align-items-center gap-1.5 shadow-sm">
                        <span>Voir le profil</span>
                        <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>

        </div>
    </article>
    <?php
}
