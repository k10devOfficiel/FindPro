<?php
/**
 * Règles d'utilisation & Charte de bonne conduite — FindPro
 *
 * Énonce les règles déontologiques, d'usage et de sécurité de la plateforme.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/session.php';

demarrer_session();

$titre_page = "Règles d'utilisation — FindPro";
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-4 py-lg-5">
    
    <!-- Fil d'Ariane -->
    <nav class="mb-4" aria-label="Fil d'Ariane">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="<?= url('') ?>" class="text-secondary text-decoration-none">Accueil</a></li>
            <li class="breadcrumb-item active text-dark fw-medium" aria-current="page">Règles d'utilisation</li>
        </ol>
    </nav>

    <div class="row justify-content-center">
        <div class="col-12 col-lg-10">

            <div class="card shadow-sm border mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h1 class="h3 fw-bold text-dark mb-1">Règles d'Utilisation &amp; Charte de Bonne Conduite</h1>
                    <p class="text-secondary small mb-0">Normes déontologiques, exigences de sécurité et engagements réciproques sur FindPro</p>
                </div>

                <div class="card-body p-4 p-md-5">

                    <!-- Préambule -->
                    <div class="p-3 bg-light rounded border mb-4 small text-secondary leading-relaxed">
                        FindPro a pour mission de connecter en toute confiance les porteurs de projets et les professionnels qualifiés du numérique. L'utilisation de la plateforme implique le respect strict et inconditionnel des règles fondamentales énoncées ci-dessous par l'ensemble des membres (clients comme professionnels).
                    </div>

                    <!-- 1. Respect et courtoisie -->
                    <section class="mb-5">
                        <h2 class="h5 fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-chat-heart text-primary" aria-hidden="true"></i>
                            <span>1. Respect, professionnalisme et courtoisie</span>
                        </h2>
                        <p class="text-secondary small leading-relaxed mb-0">
                            Tous les échanges intervenant sur la plateforme (demandes, messagerie instantanée, avis et profils) doivent être guidés par la bienveillance, la probité et le respect mutuel. Sont strictement interdits : les propos injurieux, diffamatoires, agressifs, discriminatoires, haineux, le harcèlement ainsi que tout comportement de chantage ou d'intimidation.
                        </p>
                    </section>

                    <!-- 2. Aucune activité illégale -->
                    <section class="mb-5">
                        <h2 class="h5 fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-slash-circle text-danger" aria-hidden="true"></i>
                            <span>2. Interdiction absolue de toute activité illégale</span>
                        </h2>
                        <p class="text-secondary small leading-relaxed mb-0">
                            La plateforme ne saurait être utilisée, directement ou indirectement, à des fins frauduleuses ou illégales. Sont formellement prohibés : l'usurpation d'identité, la contrefaçon, la transmission de logiciels malveillants, l'escroquerie, le blanchiment d'argent, l'exercice illégal d'une profession réglementée ou toute transaction portant sur des biens ou prestations illicites.
                        </p>
                    </section>

                    <!-- 3. Prestations de Cybersécurité : Cadre strict -->
                    <section class="mb-5">
                        <div class="border border-warning rounded p-4 bg-warning bg-opacity-10">
                            <h2 class="h5 fw-bold text-dark mb-2 d-flex align-items-center gap-2">
                                <i class="bi bi-shield-fill-exclamation text-warning" aria-hidden="true"></i>
                                <span>3. Prestations de cybersécurité : Autorisation écrite préalable obligatoire</span>
                            </h2>
                            <p class="text-dark small leading-relaxed mb-2 fw-medium">
                                Les missions relatives à la sécurité offensive, tests d'intrusion (pentesting), audits de vulnérabilités, analyses de code source ou ingénierie inverse sont soumises à un encadrement juridique et déontologique strict :
                            </p>
                            <ul class="text-secondary small leading-relaxed mb-0">
                                <li class="mb-1.5"><strong>Accord exprès obligatoire :</strong> Toute mission de sécurité ne peut être réalisée <em>qu'avec l'autorisation écrite, expresse, préalable et vérifiable du propriétaire légitime</em> du système d'information ciblé (mandat de test d'intrusion).</li>
                                <li class="mb-1.5"><strong>Périmètre délimité :</strong> Les interventions doivent se limiter rigoureusement au périmètre technique et aux créneaux horaires stipulés dans le mandat d'intervention.</li>
                                <li class="mb-0"><strong>Interdiction de l'intrusion non autorisée :</strong> Toute tentative d'accès non autorisé, d'altération de données ou d'attaque par déni de service est un délit réprimé par la législation sur la cybercriminalité et entraîne une exclusion immédiate de FindPro.</li>
                            </ul>
                        </div>
                    </section>

                    <!-- 4. Faux avis et notations -->
                    <section class="mb-5">
                        <h2 class="h5 fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-star-half text-warning" aria-hidden="true"></i>
                            <span>4. Authenticité des avis et interdiction des fausses notations</span>
                        </h2>
                        <p class="text-secondary small leading-relaxed mb-0">
                            La confiance repose sur l'intégrité des avis. Tout dépôt d'avis de complaisance, d'avis rémunéré, de faux témoignage ou d'évaluation résultant d'une prestation fictive est formellement interdit. Seul un client ayant conclu une prestation menée jusqu'à son terme sur la plateforme est habilité à émettre un avis vérifié.
                        </p>
                    </section>

                    <!-- 5. Confidentialité et sécurité des comptes -->
                    <section class="mb-5">
                        <h2 class="h5 fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-key text-primary" aria-hidden="true"></i>
                            <span>5. Sécurité des comptes : Aucun partage de mots de passe</span>
                        </h2>
                        <p class="text-secondary small leading-relaxed mb-0">
                            Les identifiants et mots de passe d'accès à FindPro sont strictement personnels, confidentiels et incessibles. Il est formellement interdit de communiquer son mot de passe à un tiers ou de partager un compte entre plusieurs personnes. Chaque titulaire de compte demeure seul responsable de l'activité réalisée sous son identifiant.
                        </p>
                    </section>

                    <!-- 6. Sanctions et conséquences -->
                    <section class="mb-5">
                        <h2 class="h5 fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-exclamation-octagon text-danger" aria-hidden="true"></i>
                            <span>6. Conséquences des manquements : Suspension et radiation</span>
                        </h2>
                        <div class="bg-light p-3.5 rounded border small leading-relaxed text-secondary">
                            <p class="mb-2">
                                En cas de violation avérée ou de signalement fondé de manquement à ces règles, FindPro se réserve le droit de prendre les mesures conservatoires suivantes sans préavis :
                            </p>
                            <ul class="mb-2 ps-3">
                                <li>Envoi d'un avertissement formel par e-mail.</li>
                                <li>Suppression immédiate des contenus, projets de portfolio ou avis litigieux.</li>
                                <li>Suspension temporaire d'accès au compte et aux conversations en cours.</li>
                                <li>Bannissement définitif et clôture irrévocable du profil utilisateur.</li>
                            </ul>
                            <p class="mb-0 text-muted" style="font-size: 0.8rem;">
                                Les infractions à caractère pénal (escroquerie, piratage, menaces graves) feront systématiquement l'objet d'un signalement auprès des autorités judiciaires compétentes.
                            </p>
                        </div>
                    </section>

                    <!-- 7. Recommandation juridique -->
                    <section class="border-top pt-4">
                        <div class="alert alert-warning border-warning-subtle small mb-0">
                            <h3 class="h6 fw-bold text-dark mb-1">
                                <i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>
                                Recommandations juridiques complémentaires
                            </h3>
                            <p class="mb-2 text-secondary">
                                Ces règles d'usage constituent le socle déontologique de la plateforme. Avant le lancement commercial à grande échelle, il est recommandé de faire vérifier par un <strong>juriste ou avocat d'affaires</strong> :
                            </p>
                            <ul class="mb-0 ps-3 text-secondary">
                                <li>L'opposabilité juridique des Conditions Générales d'Utilisation (CGU) et leur mécanisme d'acceptation par case à cocher.</li>
                                <li>Les clauses limitatives et exonératoires de responsabilité relatives au rôle d'intermédiaire technique.</li>
                                <li>L'encadrement des procédures contradictoires de suspension ou de bannissement de compte pour prévenir les contestations.</li>
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
