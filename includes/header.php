<?php
/**
 * En-tête HTML5 et navigation principale de FindPro.
 * 
 * Variables attendues :
 * - $titre_page (string, optionnelle) : Titre spécifique de la page en cours.
 */

declare(strict_types=1);

require_once __DIR__ . '/securite.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';

demarrer_session();

$utilisateurCourant = utilisateur_courant();
$estConnecte = $utilisateurCourant !== null;
$roleUtilisateur = $estConnecte ? $utilisateurCourant['role'] : null;
$nomUtilisateur = $estConnecte && $utilisateurCourant['prenom'] !== '' ? $utilisateurCourant['prenom'] : 'Mon compte';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($titre_page) && $titre_page !== '' ? e($titre_page) . ' — FindPro' : 'FindPro — Trouvez le bon professionnel' ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?= url('assets/img/favicon.png') ?>">

    <!-- Polices Google : Oxanium (titres) & Inter (texte courant) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Oxanium:wght@600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <!-- Bootstrap Icons CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Feuille de style personnalisée FindPro -->
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body>

    <!-- Barre de navigation responsive -->
    <nav class="navbar navbar-expand-lg sticky-top shadow-sm" aria-label="Navigation principale">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="<?= url('') ?>">
                <img src="<?= url('assets/img/logo.svg') ?>" alt="FindPro" height="40" class="d-inline-block">
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarFindPro" aria-controls="navbarFindPro" aria-expanded="false" aria-label="Basculer le menu">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarFindPro">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= url('') ?>">Accueil</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= (($_GET['slug'] ?? '') === 'developpement') ? 'active' : '' ?>" href="<?= url('category.php?slug=developpement') ?>">Développement informatique</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= (($_GET['slug'] ?? '') === 'montage-video') ? 'active' : '' ?>" href="<?= url('category.php?slug=montage-video') ?>">Montage vidéo</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= (($_GET['slug'] ?? '') === 'cybersecurite') ? 'active' : '' ?>" href="<?= url('category.php?slug=cybersecurite') ?>">Cybersécurité</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= url('search.php') ?>">
                            <i class="bi bi-search me-1" aria-hidden="true"></i>Recherche
                        </a>
                    </li>
                </ul>

                <div class="d-flex align-items-center gap-2 pt-2 pt-lg-0">
                    <?php if (!$estConnecte): ?>
                        <!-- Emplacements pour visiteur non connecté -->
                        <a href="<?= url('login.php') ?>" class="btn btn-outline-primary btn-sm">Connexion</a>
                        <a href="<?= url('register.php') ?>" class="btn btn-brand btn-sm">Inscription</a>
                    <?php else: ?>
                        <!-- Emplacement pour utilisateur connecté selon le rôle -->
                        <div class="dropdown">
                            <button class="btn btn-outline-primary btn-sm dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle"></i>
                                <span><?= e($nomUtilisateur) ?></span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                <?php if ($roleUtilisateur === 'admin'): ?>
                                    <li><h6 class="dropdown-header">Administration</h6></li>
                                    <li><a class="dropdown-item" href="<?= url('admin/index.php') ?>"><i class="bi bi-speedometer2 me-2"></i>Tableau de bord</a></li>
                                <?php elseif ($roleUtilisateur === 'professionnel'): ?>
                                    <li><h6 class="dropdown-header">Espace Professionnel</h6></li>
                                    <li><a class="dropdown-item" href="<?= url('pro/index.php') ?>"><i class="bi bi-briefcase me-2"></i>Tableau de bord</a></li>
                                    <li><a class="dropdown-item" href="<?= url('pro/demandes.php') ?>"><i class="bi bi-inbox me-2"></i>Demandes reçues</a></li>
                                    <li><a class="dropdown-item" href="<?= url('pro/profil.php') ?>"><i class="bi bi-person-lines-fill me-2"></i>Mon profil</a></li>
                                    <li><a class="dropdown-item" href="<?= url('pro/competences.php') ?>"><i class="bi bi-award me-2"></i>Mes compétences</a></li>
                                    <li><a class="dropdown-item" href="<?= url('pro/portfolio.php') ?>"><i class="bi bi-images me-2"></i>Portfolio</a></li>
                                <?php else: ?>
                                    <li><h6 class="dropdown-header">Espace Client</h6></li>
                                    <li><a class="dropdown-item" href="<?= url('client/index.php') ?>"><i class="bi bi-speedometer2 me-2"></i>Tableau de bord</a></li>
                                    <li><a class="dropdown-item" href="<?= url('client/demandes.php') ?>"><i class="bi bi-grid me-2"></i>Mes demandes</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form action="<?= url('logout.php') ?>" method="POST" class="m-0 p-0">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="dropdown-item text-danger d-flex align-items-center">
                                            <i class="bi bi-box-arrow-right me-2"></i>Déconnexion
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Zone d'affichage des messages flash -->
    <?php
    $messagesFlash = flash_get();
    if (!empty($messagesFlash)): ?>
        <div class="container mt-3" aria-live="polite">
            <?php foreach ($messagesFlash as $flash):
                $type = match ($flash['type'] ?? 'info') {
                    'erreur', 'danger', 'error' => 'danger',
                    'avertissement', 'warning'  => 'warning',
                    'succes', 'success'         => 'success',
                    default                     => 'info',
                };
            ?>
                <div class="alert alert-<?= e($type) ?> alert-dismissible fade show d-flex align-items-center" role="alert">
                    <div><?= e($flash['message'] ?? '') ?></div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Fermer"></button>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Conteneur principal de la page -->
    <main>
