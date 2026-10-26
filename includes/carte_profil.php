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
    // RÈGLE OBLIGATOIRE (RG05) : Seuls les profils approuvés sont affichés publiquement.
    // Interdiction formelle d'afficher un profil suspendu, refusé ou en attente.
    if (($p['statut_validation'] ?? '') !== 'approuve') {
        return;
    }

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
    <article class="card h-100 shadow-sm border rounded-3 hover-shadow transition-all bg-white" data-profil-id="<?= $idProfil ?>">
        <div class="card-body p-3 p-md-3.5 d-flex flex-column justify-content-between">
            
            <div>
                <!-- En-tête : Avatar + Identité -->
                <div class="d-flex align-items-start gap-2.5 mb-3">
                    <div class="flex-shrink-0">
                        <?php if ($photoUrl !== null): ?>
                            <img src="<?= $photoUrl ?>" alt="Photo de <?= e($nomAffiche) ?>" class="rounded-circle object-fit-cover border shadow-2xs" style="width: 52px; height: 52px;">
                        <?php elseif ($avatarDefaut !== null): ?>
                            <img src="<?= $avatarDefaut ?>" alt="Avatar par défaut" class="rounded-circle object-fit-cover border shadow-2xs" style="width: 52px; height: 52px;">
                        <?php else: ?>
                            <!-- Espace réservé gris si visuel manquant -->
                            <div class="rounded-circle bg-secondary bg-opacity-25 d-flex align-items-center justify-content-center border shadow-2xs text-secondary" style="width: 52px; height: 52px;" title="Espace réservé gris">
                                <i class="bi bi-person fs-3"></i>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="flex-grow-1" style="min-width: 0;">
                        <!-- Ligne 1 : Nom affiché (pleine largeur de la colonne, sans écrasement) -->
                        <h3 class="h6 fw-bold text-dark mb-1 text-truncate" title="<?= e($nomAffiche) ?>" style="font-size: 0.95rem; line-height: 1.25;">
                            <?= e($nomAffiche) ?>
                        </h3>

                        <!-- Ligne 2 : Titre professionnel -->
                        <p class="text-primary fw-medium small mb-1.5 text-truncate" title="<?= e($titre) ?>" style="font-size: 0.82rem; line-height: 1.3;">
                            <?= e($titre) ?>
                        </p>

                        <!-- Ligne 3 : Badges de statut et catégorie -->
                        <div class="d-flex align-items-center gap-1.5 flex-wrap">
                            <!-- Badge de disponibilité coloré -->
                            <?php if ($dispo === 'disponible'): ?>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-0.5 px-2 font-monospace" style="font-size: 0.68rem;">
                                    <i class="bi bi-circle-fill me-1" style="font-size: 0.38rem;"></i>Disponible
                                </span>
                            <?php elseif ($dispo === 'limitee'): ?>
                                <span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-50 py-0.5 px-2 font-monospace" style="font-size: 0.68rem;">
                                    <i class="bi bi-clock-fill text-warning me-1"></i>Limité
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary border py-0.5 px-2 font-monospace" style="font-size: 0.68rem;">
                                    <i class="bi bi-dash-circle me-1"></i>Indisponible
                                </span>
                            <?php endif; ?>

                            <!-- Catégorie de métier -->
                            <span class="badge bg-light text-secondary border py-0.5 px-2 small text-truncate" style="max-width: 150px;">
                                <?= e($categorie) ?>
                            </span>

                            <!-- Badge Cyber -->
                            <?php if ($isCyber): ?>
                                <span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-50 py-0.5 px-1.5 small" title="Engagement déontologique certifié">
                                    <i class="bi bi-shield-check text-warning me-0.5"></i>Cyber
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Badge officiel FindPro -->
                <div class="mb-3">
                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 py-1 px-2.5 small fw-semibold d-inline-flex align-items-center gap-1.5 text-wrap text-start" style="line-height: 1.3;">
                        <i class="bi bi-patch-check-fill text-primary flex-shrink-0"></i>
                        <span>Profil approuvé par FindPro</span>
                    </span>
                </div>

                <!-- 3 Compétences principales (sans coupure de texte, retours à la ligne naturels) -->
                <div class="mb-3">
                    <span class="text-secondary d-block mb-1.5" style="font-size: 0.70rem; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700;">
                        Compétences clés :
                    </span>
                    <?php if (!empty($competences)): ?>
                        <div class="d-flex flex-column gap-1.5">
                            <?php foreach ($competences as $comp): ?>
                                <div class="bg-light text-dark border rounded px-2 py-1 font-monospace" style="font-size: 0.73rem; line-height: 1.35; word-break: break-word; white-space: normal;">
                                    <?= e($comp) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <span class="text-muted small fst-italic">Compétences en cours de qualification</span>
                    <?php endif; ?>
                </div>

                <!-- Zone d'intervention si renseignée -->
                <?php if (!empty($p['zone'])): ?>
                    <div class="text-secondary small mb-3 d-flex align-items-center gap-1.5" style="font-size: 0.78rem;">
                        <i class="bi bi-geo-alt-fill text-danger opacity-75 flex-shrink-0"></i>
                        <span class="text-truncate"><?= e($p['zone']) ?></span>
                    </div>
                <?php endif; ?>

            </div>

            <!-- Pied de carte : Avis & Bouton Voir le profil -->
            <div class="pt-3 border-top mt-auto">
                <!-- Note moyenne et avis -->
                <div class="mb-2.5">
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

                <!-- Bouton d'action pleine largeur -->
                <a href="<?= $urlProfil ?>" class="btn btn-brand btn-sm w-100 d-flex align-items-center justify-content-center gap-2 shadow-sm py-2" style="background: linear-gradient(135deg, #E11D2E 0%, #F97316 100%) !important; color: #FFFFFF !important; border: none !important; font-weight: 600 !important;">
                    <span class="fw-semibold">Voir le profil</span>
                    <i class="bi bi-arrow-right"></i>
                </a>
            </div>

        </div>
    </article>
    <?php
}
