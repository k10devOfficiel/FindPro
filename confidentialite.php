<?php
/**
 * Politique de Confidentialité — FindPro
 *
 * Transparence sur les données collectées, leurs finalités, les accès,
 * les durées de conservation et l'exercice des droits utilisateurs.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/session.php';

demarrer_session();

$titre_page = 'Politique de confidentialité — FindPro';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-4 py-lg-5">
    
    <!-- Fil d'Ariane -->
    <nav class="mb-4" aria-label="Fil d'Ariane">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="<?= url('') ?>" class="text-secondary text-decoration-none">Accueil</a></li>
            <li class="breadcrumb-item active text-dark fw-medium" aria-current="page">Confidentialité</li>
        </ol>
    </nav>

    <div class="row justify-content-center">
        <div class="col-12 col-lg-10">

            <div class="card shadow-sm border mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h1 class="h3 fw-bold text-dark mb-1">Politique de Confidentialité</h1>
                    <p class="text-secondary small mb-0">Engagement et transparence concernant le traitement de vos données personnelles sur FindPro</p>
                </div>

                <div class="card-body p-4 p-md-5">

                    <!-- Introduction -->
                    <div class="p-3 bg-light rounded border mb-4 small text-secondary leading-relaxed">
                        FindPro accorde une importance primordiale à la protection de la vie privée de ses utilisateurs (clients et professionnels). Ce document expose en toute transparence les données que nous collectons, la façon dont elles sont utilisées, protégées, et les moyens à votre disposition pour exercer vos droits.
                    </div>

                    <!-- 1. Quelles données sont collectées ? -->
                    <section class="mb-5">
                        <h2 class="h5 fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-person-vcard text-primary" aria-hidden="true"></i>
                            <span>1. Quelles données personnelles sont collectées ?</span>
                        </h2>
                        <p class="text-secondary small leading-relaxed mb-3">
                            Nous collectons uniquement les informations strictement nécessaires au bon fonctionnement de la plateforme et à la réalisation des prestations :
                        </p>
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <div class="p-3 border rounded h-100 bg-white shadow-2xs">
                                    <h3 class="h6 fw-semibold text-dark mb-1"><i class="bi bi-person me-1 text-primary"></i>Données d'identité & Coordonnées</h3>
                                    <p class="small text-secondary mb-0">
                                        Nom, prénom, adresse e-mail valide, numéro de téléphone, rôle (client ou professionnel) et commune de résidence ou zone d'intervention.
                                    </p>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="p-3 border rounded h-100 bg-white shadow-2xs">
                                    <h3 class="h6 fw-semibold text-dark mb-1"><i class="bi bi-file-earmark-text me-1 text-primary"></i>Demandes de prestations</h3>
                                    <p class="small text-secondary mb-0">
                                        Titre du besoin, description détaillée, budget indicatif, délai souhaité et historique des statuts de chaque intervention.
                                    </p>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="p-3 border rounded h-100 bg-white shadow-2xs">
                                    <h3 class="h6 fw-semibold text-dark mb-1"><i class="bi bi-chat-dots me-1 text-primary"></i>Messagerie interne</h3>
                                    <p class="small text-secondary mb-0">
                                        Contenu textuel, date, heure et statut de lecture des messages échangés entre le client et le professionnel après initialisation d'une demande.
                                    </p>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="p-3 border rounded h-100 bg-white shadow-2xs">
                                    <h3 class="h6 fw-semibold text-dark mb-1"><i class="bi bi-briefcase me-1 text-primary"></i>Données professionnelles</h3>
                                    <p class="small text-secondary mb-0">
                                        Titre professionnel, biographie, compétences et niveaux déclarés, photos et réalisations du portfolio, documents d'examen et statut de validation.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- 2. À quoi servent ces données ? -->
                    <section class="mb-5">
                        <h2 class="h5 fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-gear-wide-connected text-primary" aria-hidden="true"></i>
                            <span>2. À quoi servent vos données (finalités) ?</span>
                        </h2>
                        <ul class="text-secondary small leading-relaxed">
                            <li class="mb-2"><strong>Mise en relation qualifiée :</strong> permettre aux clients de découvrir les compétences des professionnels vérifiés et de leur soumettre des opportunités de mission.</li>
                            <li class="mb-2"><strong>Exécution et suivi des prestations :</strong> assurer le cycle de vie complet de chaque demande (acceptation, démarrage des travaux, confirmation et clôture).</li>
                            <li class="mb-2"><strong>Communication directe :</strong> assurer le fonctionnement du canal de discussion textuelle en direct lié à chaque prestation.</li>
                            <li class="mb-2"><strong>Sécurité et prévention des abus :</strong> protéger les utilisateurs contre les spams, attaques automatisées, faux profils et tentatives de fraude.</li>
                            <li class="mb-0"><strong>Avis et réputation certifiée :</strong> collecter des évaluations et commentaires authentiques réservés aux prestations effectivement réalisées.</li>
                        </ul>
                    </section>

                    <!-- 3. Qui a accès à vos données ? -->
                    <section class="mb-5">
                        <h2 class="h5 fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-people text-primary" aria-hidden="true"></i>
                            <span>3. Qui a accès à vos données ?</span>
                        </h2>
                        <div class="bg-light p-3.5 rounded border small leading-relaxed text-secondary mb-3">
                            <ul class="mb-0 ps-3">
                                <li class="mb-2"><strong>Les parties prenantes directes :</strong> le professionnel et le client concernés par une demande ont accès aux informations réciproques strictement nécessaires au bon déroulement de la mission.</li>
                                <li class="mb-2"><strong>L'équipe technique et administrative de FindPro :</strong> autorisée à intervenir pour la modération, la vérification des profils et le support technique. <em>Règle de gouvernance stricte (RG04) : les administrateurs n'ont pas accès au contenu des conversations privées entre clients et professionnels.</em></li>
                                <li class="mb-0"><strong>Aucun tiers commercial :</strong> FindPro ne vend, ne loue, ni ne cède vos données personnelles à des fins publicitaires ou de prospection commerciale tierce.</li>
                            </ul>
                        </div>
                    </section>

                    <!-- 4. Durées de conservation -->
                    <section class="mb-5">
                        <h2 class="h5 fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-hourglass-split text-primary" aria-hidden="true"></i>
                            <span>4. Combien de temps vos données sont-elles conservées ?</span>
                        </h2>
                        <p class="text-secondary small leading-relaxed mb-3">
                            Les données sont conservées pour une durée strictement proportionnée aux finalités pour lesquelles elles ont été collectées :
                        </p>
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm small text-secondary">
                                <thead class="table-light text-dark">
                                    <tr>
                                        <th>Catégorie de données</th>
                                        <th>Durée de conservation de référence</th>
                                        <th>Sort à l'échéance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong>Compte utilisateur actif</strong></td>
                                        <td>Pendant toute la durée d'activité du compte</td>
                                        <td>Suppression sur demande ou après clôture</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Compte inactif (sans connexion)</strong></td>
                                        <td>3 ans à compter de la dernière connexion</td>
                                        <td>Suppression automatique ou anonymisation</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Demandes de prestation & Messages</strong></td>
                                        <td>3 ans après la clôture de la prestation</td>
                                        <td>Archivage intermédiaire puis purge</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Logs techniques et de sécurité</strong></td>
                                        <td>1 an glissant</td>
                                        <td>Écrasement automatique rotatif</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <!-- 5. Vos droits et comment les exercer -->
                    <section class="mb-5">
                        <h2 class="h5 fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-shield-check text-primary" aria-hidden="true"></i>
                            <span>5. Quels sont vos droits et comment les exercer ?</span>
                        </h2>
                        <p class="text-secondary small leading-relaxed mb-2">
                            Conformément aux principes fondamentaux de protection des données personnelles, vous disposez des droits suivants :
                        </p>
                        <ul class="text-secondary small leading-relaxed mb-3">
                            <li><strong>Droit d'accès et de rectification :</strong> vous pouvez à tout moment consulter et modifier vos informations personnelles depuis votre espace personnel.</li>
                            <li><strong>Droit à l'effacement (droit à l'oubli) :</strong> vous pouvez demander la suppression définitive de votre compte et de vos données personnelles.</li>
                            <li><strong>Droit d'opposition et de limitation :</strong> vous pouvez vous opposer à un traitement spécifique de vos données pour motifs légitimes.</li>
                        </ul>
                        <div class="p-3 bg-light rounded border small">
                            <strong class="text-dark d-block mb-1">Pour exercer vos droits :</strong>
                            <p class="text-secondary mb-1">
                                Envoyez votre demande accompagnée d'un justificatif d'identité à l'adresse e-mail dédiée : <strong>contact@findpro.ci</strong>
                            </p>
                            <span class="text-muted" style="font-size: 0.8rem;">
                                Une réponse vous sera adressée dans un délai maximum de 30 jours ouvrés.
                            </span>
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
