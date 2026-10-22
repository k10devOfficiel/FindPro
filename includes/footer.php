<?php
/**
 * Pied de page de FindPro.
 * 
 * Clôture le conteneur principal <main>, affiche les liens de bas de page,
 * intègre le script JavaScript Bootstrap 5 et referme le document HTML.
 */

declare(strict_types=1);
?>
    </main>

    <!-- Pied de page -->
    <footer class="footer mt-auto py-4 bg-white border-top">
        <div class="container">
            <div class="row align-items-center gy-3">
                <div class="col-12 col-md-6 text-center text-md-start">
                    <span class="text-secondary small">
                        &copy; <?= date('Y') ?> FindPro. Tous droits réservés.
                    </span>
                </div>
                <div class="col-12 col-md-6 text-center text-md-end">
                    <ul class="list-inline mb-0 small">
                        <li class="list-inline-item">
                            <a href="<?= url('mentions-legales.php') ?>" class="text-secondary hover-primary">Mentions légales</a>
                        </li>
                        <li class="list-inline-item text-muted">•</li>
                        <li class="list-inline-item">
                            <a href="<?= url('confidentialite.php') ?>" class="text-secondary hover-primary">Confidentialité</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle CDN (avec Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
