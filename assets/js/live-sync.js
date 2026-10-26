/**
 * live-sync.js — Synchronisation temps réel globale FindPro (Scénario 3 & Notifications)
 * 
 * - Surveillance des nouvelles demandes reçues en direct sans rechargement de page.
 * - Surveillance des messages non lus sur tout le site avec mise à jour des badges navbar.
 * - Notification sonore discrète (chime audio Web Audio API, sans dépendance externe).
 * - Insertion dynamique des nouvelles cartes dans pro/demandes.php avec animation fluide.
 */

(function () {
    'use strict';

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLiveSync);
    } else {
        initLiveSync();
    }

    function initLiveSync() {
        const currentScript = document.currentScript || document.querySelector('script[data-sync-url]');
        if (!currentScript) return;

        const syncUrl = currentScript.dataset.syncUrl || 'chat/sync.php';
        const userRole = currentScript.dataset.userRole || '';
        if (!userRole) return;

        let syncTimer = null;
        let isSyncing = false;
        let initialLoadDone = false;
        const knownDemandIds = new Set();

        // 1. Découverte initiale des demandes déjà présentes dans le DOM
        document.querySelectorAll('[data-demande-id]').forEach(function (el) {
            const id = parseInt(el.dataset.demandeId, 10);
            if (id > 0) {
                knownDemandIds.add(id);
            }
        });

        // 2. Synthèse sonore discrète pour notification (Web Audio API, zéro fichier externe)
        function playNotificationChime() {
            try {
                const AudioCtx = window.AudioContext || window.webkitAudioContext;
                if (!AudioCtx) return;
                const ctx = new AudioCtx();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();

                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.type = 'sine';

                // Tonalité douce FindPro (D5 -> A5)
                osc.frequency.setValueAtTime(587.33, ctx.currentTime);
                osc.frequency.setValueAtTime(880.00, ctx.currentTime + 0.12);

                gain.gain.setValueAtTime(0.18, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.55);

                osc.start(ctx.currentTime);
                osc.stop(ctx.currentTime + 0.55);
            } catch (e) {
                // Ignore silent audio context restriction on un-interacted pages
            }
        }

        // 3. Affichage d'un toast de notification flottant
        function showNotificationToast(titre, message, lienUrl) {
            let container = document.getElementById('live-toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'live-toast-container';
                container.className = 'position-fixed top-0 end-0 p-3';
                container.style.zIndex = '1090';
                container.style.maxWidth = '380px';
                document.body.appendChild(container);
            }

            const toast = document.createElement('div');
            toast.className = 'alert alert-light border border-primary shadow-lg p-3 rounded-3 mb-2 live-toast-alert d-flex align-items-start gap-2.5';
            toast.setAttribute('role', 'alert');

            const icon = document.createElement('i');
            icon.className = 'bi bi-bell-fill text-primary fs-5 flex-shrink-0 mt-0.5';

            const content = document.createElement('div');
            content.className = 'flex-grow-1 small';

            const strong = document.createElement('strong');
            strong.className = 'd-block text-dark mb-1';
            strong.textContent = titre;

            const text = document.createElement('p');
            text.className = 'text-secondary mb-1';
            text.textContent = message;

            content.appendChild(strong);
            content.appendChild(text);

            if (lienUrl) {
                const btn = document.createElement('a');
                btn.href = lienUrl;
                btn.className = 'btn btn-primary btn-sm px-2.5 py-1 text-white fw-medium text-decoration-none';
                btn.textContent = 'Voir la demande';
                content.appendChild(btn);
            }

            const closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.className = 'btn-close ms-auto';
            closeBtn.setAttribute('aria-label', 'Fermer');
            closeBtn.addEventListener('click', function () {
                toast.remove();
            });

            toast.appendChild(icon);
            toast.appendChild(content);
            toast.appendChild(closeBtn);

            container.appendChild(toast);

            // Auto-suppression après 8 secondes
            setTimeout(function () {
                if (toast.parentNode) {
                    toast.remove();
                }
            }, 8000);
        }

        // 4. Mise à jour des badges dans la barre de navigation
        function updateNavBadges(data) {
            const dropdownBtn = document.querySelector('.navbar .dropdown-toggle');
            if (!dropdownBtn) return;

            const totalAlertes = (data.nb_messages_non_lus || 0) + (data.nb_nouvelles_demandes || 0);
            let navBadge = dropdownBtn.querySelector('.live-unread-badge');

            if (totalAlertes > 0) {
                if (!navBadge) {
                    navBadge = document.createElement('span');
                    navBadge.className = 'badge bg-danger rounded-pill live-unread-badge ms-1.5';
                    dropdownBtn.appendChild(navBadge);
                }
                navBadge.textContent = totalAlertes;
                navBadge.classList.remove('d-none');
            } else if (navBadge) {
                navBadge.classList.add('d-none');
            }

            // Badge sur "Demandes reçues" dans le menu déroulant
            const demandesLink = document.querySelector('a.dropdown-item[href*="demandes.php"]');
            if (demandesLink) {
                let dBadge = demandesLink.querySelector('.live-dropdown-badge');
                if (data.nb_nouvelles_demandes > 0 || data.nb_messages_non_lus > 0) {
                    if (!dBadge) {
                        dBadge = document.createElement('span');
                        dBadge.className = 'badge bg-danger rounded-pill live-dropdown-badge ms-auto small';
                        demandesLink.appendChild(dBadge);
                    }
                    dBadge.textContent = data.nb_nouvelles_demandes || data.nb_messages_non_lus;
                } else if (dBadge) {
                    dBadge.remove();
                }
            }
        }

        // 5. Insertion dynamique d'une nouvelle demande sur pro/demandes.php
        function insererNouvelleDemandePro(d) {
            const listePro = document.getElementById('liste-demandes-pro');
            if (!listePro) return;

            // Retirer le placeholder vide s'il existe
            const emptyCard = document.querySelector('.card.p-5.text-center');
            if (emptyCard) {
                emptyCard.remove();
            }

            const card = document.createElement('div');
            card.className = 'card shadow-sm border hover-shadow transition live-card-highlight mb-3';
            card.dataset.demandeId = String(d.id_demande);

            const cardBody = document.createElement('div');
            cardBody.className = 'card-body p-3 p-md-4';

            const flexRow = document.createElement('div');
            flexRow.className = 'd-flex flex-column flex-md-row justify-content-between align-items-stretch align-items-md-center gap-3';

            const leftCol = document.createElement('div');
            leftCol.className = 'flex-grow-1';

            // Badges
            const badgesWrap = document.createElement('div');
            badgesWrap.className = 'd-flex align-items-center gap-2 mb-1 flex-wrap';
            badgesWrap.innerHTML = `
                <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-2.5 py-1 rounded-pill small fw-semibold font-monospace">
                    <i class="bi bi-clock-history me-1"></i>Envoyée
                </span>
                <span class="badge bg-light text-secondary border small">
                    ${escapeHtml(d.categorie_libelle || 'Service')}
                </span>
                <span class="badge bg-danger text-white rounded-pill small">Nouveau !</span>
            `;

            // Titre
            const h2 = document.createElement('h2');
            h2.className = 'h6 fw-bold mb-1 text-dark text-break';
            const aTitre = document.createElement('a');
            aTitre.href = `../demande_detail.php?id=${encodeURIComponent(d.id_demande)}`;
            aTitre.className = 'text-dark text-decoration-none hover-primary';
            aTitre.textContent = d.titre;
            h2.appendChild(aTitre);

            // Demandeur
            const pClient = document.createElement('p');
            pClient.className = 'text-secondary small mb-2 text-break';
            pClient.innerHTML = `Client demandeur : <strong class="text-dark"><i class="bi bi-person me-1"></i>${escapeHtml(d.client_prenom + ' ' + d.client_nom)}</strong>`;

            // Métadonnées
            const metaWrap = document.createElement('div');
            metaWrap.className = 'd-flex flex-wrap gap-3 text-secondary small pt-1 border-top border-light';
            const budgetText = d.budget_indicatif ? `${Number(d.budget_indicatif).toLocaleString('fr-FR')} FCFA` : 'Non précisé';
            metaWrap.innerHTML = `
                <span><i class="bi bi-calendar3 me-1 text-primary"></i>À l'instant</span>
                <span><i class="bi bi-cash me-1 text-success"></i>${escapeHtml(budgetText)}</span>
            `;

            leftCol.appendChild(badgesWrap);
            leftCol.appendChild(h2);
            leftCol.appendChild(pClient);
            leftCol.appendChild(metaWrap);

            // Action
            const actionCol = document.createElement('div');
            actionCol.className = 'card-action-column flex-shrink-0 d-flex flex-column justify-content-center align-items-stretch gap-2';

            const btnDetail = document.createElement('a');
            btnDetail.href = `../demande_detail.php?id=${encodeURIComponent(d.id_demande)}`;
            btnDetail.className = 'btn btn-primary btn-sm d-inline-flex align-items-center justify-content-between gap-2 shadow-2xs px-3 py-2 fw-semibold w-100 text-nowrap';
            btnDetail.innerHTML = `
                <span class="d-inline-flex align-items-center gap-1.5">
                    <i class="bi bi-file-earmark-text"></i>
                    <span>Examiner &amp; Répondre</span>
                </span>
                <i class="bi bi-arrow-right small"></i>
            `;
            actionCol.appendChild(btnDetail);

            if (d.id_conversation) {
                const btnChat = document.createElement('a');
                btnChat.href = `../chat/conversation.php?id=${encodeURIComponent(d.id_conversation)}`;
                btnChat.className = 'btn btn-outline-primary btn-sm d-inline-flex align-items-center justify-content-center gap-1.5 px-2.5 py-1.5 small text-nowrap w-100 fw-medium';
                btnChat.innerHTML = `<i class="bi bi-chat-text me-1"></i>Ouvrir le chat direct`;
                actionCol.appendChild(btnChat);
            }

            flexRow.appendChild(leftCol);
            flexRow.appendChild(actionCol);
            cardBody.appendChild(flexRow);
            card.appendChild(cardBody);

            // Insertion en haut de liste
            listePro.insertBefore(card, listePro.firstChild);
        }

        // 6. Boucle principale de synchronisation
        async function runSync() {
            if (isSyncing) return;
            isSyncing = true;

            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 4000);

            try {
                const response = await fetch(`${syncUrl}?_t=${Date.now()}`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin',
                    cache: 'no-store',
                    signal: controller.signal
                });
                clearTimeout(timeoutId);

                if (response.ok) {
                    const data = await response.json();
                    if (data && data.succes) {
                        updateNavBadges(data);

                        // Détection de nouvelles demandes pour le professionnel
                        if (userRole === 'professionnel' && Array.isArray(data.demandes)) {
                            for (const dem of data.demandes) {
                                const demId = parseInt(dem.id_demande, 10);
                                if (!knownDemandIds.has(demId)) {
                                    knownDemandIds.add(demId);

                                    // Si ce n'est pas le tout premier chargement de page : notifier !
                                    if (initialLoadDone) {
                                        playNotificationChime();
                                        showNotificationToast(
                                            'Nouvelle demande reçue !',
                                            `${dem.client_prenom} ${dem.client_nom} : "${dem.titre}"`,
                                            `../demande_detail.php?id=${dem.id_demande}`
                                        );
                                        insererNouvelleDemandePro(dem);
                                    }
                                }
                            }
                        }

                        initialLoadDone = true;
                    }
                }
            } catch (e) {
                // Ignore transient network errors
            } finally {
                clearTimeout(timeoutId);
                isSyncing = false;
                const nextDelay = document.hidden ? 6000 : 3000;
                syncTimer = setTimeout(runSync, nextDelay);
            }
        }

        // Écoute de l'activité
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                runSync();
            }
        });

        // Démarrage initial
        runSync();

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str || '';
            return div.innerHTML;
        }
    }
})();
