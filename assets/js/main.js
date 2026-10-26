/**
 * FindPro — Script JavaScript Global
 * 
 * Fonctionnalités UI :
 * 1. Protection contre les doubles soumissions (double-submit) sur tous les formulaires POST.
 * 2. Amélioration de l'ergonomie mobile et feedback visuel au clic.
 */

document.addEventListener('DOMContentLoaded', function () {
    // Écouteur global sur la soumission de tout formulaire
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form || (form.method && form.method.toUpperCase() !== 'POST')) {
            return;
        }

        // Si la validation native HTML5 échoue, ne pas désactiver le bouton
        if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
            return;
        }

        // Recherche du bouton submit actif
        var submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
        if (submitBtn && !submitBtn.disabled) {
            // Différer légèrement pour laisser le navigateur déclencher l'événement submit
            setTimeout(function () {
                submitBtn.disabled = true;
                if (submitBtn.tagName === 'BUTTON') {
                    submitBtn.classList.add('disabled');
                    var spinner = document.createElement('span');
                    spinner.className = 'spinner-border spinner-border-sm me-1.5';
                    spinner.setAttribute('role', 'status');
                    spinner.setAttribute('aria-hidden', 'true');
                    submitBtn.prepend(spinner);
                }
            }, 10);
        }
    });
});
