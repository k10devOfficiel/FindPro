<?php
/**
 * Mentions légales — FindPro
 *
 * Informations sur l'éditeur, l'hébergement et le contact.
 * Rédigé en français clair, accessible à tous les visiteurs et utilisateurs.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/session.php';

demarrer_session();

$titre_page = 'Mentions légales — FindPro';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-4 py-lg-5">
    
    <!-- Fil d'Ariane -->
    <nav class="mb-4" aria-label="Fil d'Ariane">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="<?= url('') ?>" class="text-secondary text-decoration-none">Accueil</a></li>
            <li class="breadcrumb-item active text-dark fw-medium" aria-current="page">Mentions légales</li>
        </ol>
    </nav>

    <div class="row justify-content-center">
        <div class="col-12 col-lg-10">

            <div class="card shadow-sm border mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h1 class="h3 fw-bold text-dark mb-1">Mentions Légales</h1>
                    <p class="text-secondary small mb-0">Informations légales et identification de la plateforme FindPro</p>
                </div>

                <div class="card-body p-4 p-md-5">

                    <!-- 1. Éditeur de la plateforme -->
                    <section class="mb-5">
                        <h2 class="h5 fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-building text-primary" aria-hidden="true"></i>
                            <span>1. Éditeur de la plateforme</span>
                        </h2>
                        <div class="bg-light p-3.5 rounded border small leading-relaxed text-secondary">
                            <p class="mb-2">
                                La plateforme <strong>FindPro</strong> est éditée par :<br>
                                <strong>Société FindPro</strong> (Projet en cours d'immatriculation)<br>
                                Forme juridique : Société à Responsabilité Limitée (SARL)<br>
                                Siège social : Cocody Riviera, Abidjan, Côte d'Ivoire<br>
                                Numéro d'immatriculation : CI-ABJ-2026-B-01425<br>
                                Directeur de la publication : Direction FindPro
                            </p>
                        </div>
                    </section>

                    <!-- 2. Contact -->
                    <section class="mb-5">
                        <h2 class="h5 fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-envelope-at text-primary" aria-hidden="true"></i>
                            <span>2. Contact</span>
                        </h2>
                        <div class="bg-light p-3.5 rounded border small leading-relaxed text-secondary">
                            <p class="mb-1">
                                Pour toute question, demande d'information ou signalement relatif à la plateforme :
                            </p>
                            <ul class="list-unstyled mb-0 mt-2">
                                <li><i class="bi bi-envelope me-2 text-primary"></i>Courriel de contact : <strong>contact@findpro.ci</strong></li>
                                <li><i class="bi bi-telephone me-2 text-primary"></i>Assistance téléphonique : <strong>+225 07 00 00 00 00</strong></li>
                                <li><i class="bi bi-clock me-2 text-primary"></i>Disponibilité : Du lundi au vendredi de 8h30 à 18h00 GMT</li>
                            </ul>
                        </div>
                    </section>

                    <!-- 3. Hébergement -->
                    <section class="mb-5">
                        <h2 class="h5 fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-hdd-network text-primary" aria-hidden="true"></i>
                            <span>3. Hébergement</span>
                        </h2>
                        <div class="bg-light p-3.5 rounded border small leading-relaxed text-secondary">
                            <p class="mb-0">
                                La plateforme FindPro est hébergée sur des infrastructures cloud sécurisées fournies par :<br>
                                <strong>OVH SAS</strong><br>
                                2 rue Kellermann, 59100 Roubaix, France<br>
                                Site web : <a href="https://www.ovhcloud.com" target="_blank" rel="noopener noreferrer" class="text-primary text-decoration-none">www.ovhcloud.com</a>
                            </p>
                        </div>
                    </section>

                    <!-- 4. Propriété intellectuelle -->
                    <section class="mb-5">
                        <h2 class="h5 fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-shield-lock text-primary" aria-hidden="true"></i>
                            <span>4. Propriété intellectuelle</span>
                        </h2>
                        <p class="text-secondary small leading-relaxed">
                            L'ensemble des éléments constituant la plateforme FindPro (structure générale, arborescence, textes, éléments graphiques, photographies, logos, marques et interfaces logicielles) sont protégés par le droit de la propriété intellectuelle. Toute reproduction, représentation, diffusion ou exploitation totale ou partielle sans l'accord exprès préalable de FindPro est strictement interdite.
                        </p>
                    </section>

                    <!-- 5. Recommandations et vérifications juridiques -->
                    <section class="border-top pt-4">
                        <div class="alert alert-warning border-warning-subtle small mb-0">
                            <h3 class="h6 fw-bold text-dark mb-1">
                                <i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>
                                Note d'information juridique
                            </h3>
                            <p class="mb-2 text-secondary">
                                La présente page fournit les informations d'identification de base de FindPro. Elle ne dispense pas d'un audit de conformité formel. Pour la mise en production définitive, il est recommandé de faire vérifier par un <strong>juriste ou avocat spécialisé en droit du numérique et des affaires</strong> :
                            </p>
                            <ul class="mb-0 ps-3 text-secondary">
                                <li>L'exactitude des informations d'immatriculation (RCCM, forme sociétaire définitive et régime fiscal en Côte d'Ivoire).</li>
                                <li>L'adéquation des clauses de limitation de responsabilité de l'hébergeur et de l'intermédiaire technique.</li>
                                <li>L'articulation avec les textes applicables dans l'espace OHADA et la législation ivoirienne relative au commerce électronique.</li>
                            </ul>
                        </div>
                    </section>

                </div>
            </div>

            <div class="text-center">
                <a href="<?= url('') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Retour à l'accueil
                </a>
            </div>

        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
