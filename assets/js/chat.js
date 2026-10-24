/**
 * chat.js — FindPro Messagerie instantanée sécurisée (Polling AJAX)
 * 
 * Spécifications :
 * - Polling toutes les 5 secondes vers chat/poll.php?id=[id]&apres=[dernier_id].
 * - Suspension automatique du rafraîchissement quand l'onglet est masqué (document.hidden).
 * - Envoi asynchrone par fetch vers chat/send.php sans rechargement de page.
 * - Gestion silencieuse des erreurs réseau avec reprise automatique au tour suivant.
 * - SÉCURITÉ CRITIQUE : insertion exclusive des contenus avec textContent (JAMAIS innerHTML)
 *   pour immuniser l'application contre les attaques XSS et l'exécution de code malveillant.
 * - Aucun WebSocket.
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', initChat);

    function initChat() {
        const chatApp = document.getElementById('chat-app');
        if (!chatApp) {
            return;
        }

        const conversationId = parseInt(chatApp.dataset.conversationId, 10);
        let lastId = parseInt(chatApp.dataset.lastId, 10) || 0;
        const pollUrl = chatApp.dataset.pollUrl || 'poll.php';
        const sendUrl = chatApp.dataset.sendUrl || 'send.php';
        const currentUserId = parseInt(chatApp.dataset.currentUserId, 10) || 0;

        const messagesContainer = document.getElementById('messages-container');
        let messagesList = document.getElementById('messages-list');
        const emptyPlaceholder = document.getElementById('chat-empty-placeholder');
        const form = document.getElementById('form-message');
        const textarea = document.getElementById('contenu');
        const feedback = document.getElementById('chat-feedback');
        const submitBtn = form ? form.querySelector('button[type="submit"]') : null;
        const countBadge = document.getElementById('messages-count-badge');

        let pollTimer = null;
        let isPolling = false;
        let isSubmitting = false;

        // 1. Initialisation du défilement vers le bas
        scrollToBottom(false);

        // 2. Gestion de l'envoi de message (Fetch sans rechargement)
        if (form && textarea && submitBtn) {
            form.addEventListener('submit', handleSendMessage);

            // Raccourci pratique Ctrl+Entrée ou Cmd+Entrée
            textarea.addEventListener('keydown', function (e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                    e.preventDefault();
                    form.dispatchEvent(new Event('submit', { cancelable: true }));
                }
            });
        }

        async function handleSendMessage(e) {
            e.preventDefault();
            if (isSubmitting) return;

            const contenu = textarea.value.trim();
            if (!contenu) {
                textarea.focus();
                return;
            }

            const csrfInput = form.querySelector('input[name="csrf_token"]');
            const csrfToken = csrfInput ? csrfInput.value : '';

            // Verrouillage du bouton pendant l'envoi
            isSubmitting = true;
            submitBtn.disabled = true;
            const originalBtnContent = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Envoi...';

            if (feedback) {
                feedback.className = 'd-none';
                feedback.textContent = '';
            }

            try {
                const response = await fetch(sendUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-Token': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        id_conversation: conversationId,
                        contenu: contenu
                    })
                });

                const data = await response.json().catch(() => null);

                if (response.ok && data && data.succes) {
                    // Réinitialisation du champ
                    textarea.value = '';
                    textarea.style.height = 'auto';

                    // Insertion immédiate du message de l'utilisateur
                    const newMsgPayload = data.data || data.donnees;
                    if (newMsgPayload) {
                        const newMsg = {
                            id_message: newMsgPayload.id_message,
                            id_expediteur: newMsgPayload.id_expediteur || currentUserId,
                            nom_auteur: 'Vous',
                            contenu: newMsgPayload.contenu,
                            date_formatee: formaterDate(newMsgPayload.date_envoi),
                            est_moi: true,
                            lu: 0
                        };
                        appendMessageToDom(newMsg);
                        if (newMsg.id_message > lastId) {
                            lastId = newMsg.id_message;
                        }
                        scrollToBottom(true);
                    }

                    // Déclenchement d'un cycle de vérification rapide immédiat
                    pollMessages();
                } else {
                    const messageErreur = (data && data.message) 
                        ? data.message 
                        : 'Une erreur est survenue lors de l\'envoi du message.';
                    showFeedback(messageErreur, 'danger');
                }
            } catch (err) {
                // Erreur de connexion ou réseau lors de l'envoi
                showFeedback('Impossible d\'acheminer votre message (erreur de connexion). Veuillez réessayer.', 'danger');
            } finally {
                isSubmitting = false;
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnContent;
                textarea.focus();
            }
        }

        // 3. Polling AJAX ultra-rapide (toutes les 1.5 secondes) pour une sensation temps réel instantanée
        function scheduleNextPoll(delayMs = 1500) {
            if (pollTimer) {
                clearTimeout(pollTimer);
                pollTimer = null;
            }
            // Suspendre si l'onglet est masqué
            if (document.hidden) {
                return;
            }
            pollTimer = setTimeout(pollMessages, delayMs);
        }

        async function pollMessages() {
            // Ne pas émettre si l'onglet est masqué ou si une requête est en cours
            if (document.hidden || isPolling) {
                return;
            }

            isPolling = true;

            try {
                const url = `${pollUrl}?id=${encodeURIComponent(conversationId)}&apres=${encodeURIComponent(lastId)}`;
                const response = await fetch(url, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    cache: 'no-store'
                });

                if (response.ok) {
                    const data = await response.json();
                    if (data && data.succes) {
                        // Traitement des nouveaux messages
                        if (Array.isArray(data.messages) && data.messages.length > 0) {
                            let hasNew = false;
                            for (const msg of data.messages) {
                                const added = appendMessageToDom(msg);
                                if (added) hasNew = true;
                                if (msg.id_message > lastId) {
                                    lastId = msg.id_message;
                                }
                            }
                            if (hasNew) {
                                scrollToBottom(true);
                            }
                        }

                        if (typeof data.dernier_id === 'number' && data.dernier_id > lastId) {
                            lastId = data.dernier_id;
                        }

                        // Passage en lecture seule si la conversation a été clôturée
                        if (data.est_lecture_seule) {
                            activerLectureSeule(data.demande_statut);
                        }
                    }
                }
            } catch (err) {
                // Gestion silencieuse des erreurs réseau avec nouvel essai automatique
            } finally {
                isPolling = false;
                scheduleNextPoll(1500);
            }
        }

        // 4. Gestion de la visibilité et du focus de la fenêtre (Page Visibility & Window Focus API)
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                if (pollTimer) {
                    clearTimeout(pollTimer);
                    pollTimer = null;
                }
            } else {
                // L'onglet redevient visible : interroger immédiatement puis relancer le cycle
                pollMessages();
            }
        });

        window.addEventListener('focus', function () {
            // L'utilisateur clique ou revient sur la fenêtre : interroger immédiatement
            pollMessages();
        });

        // Démarrage initial de la boucle de polling (1.5s)
        scheduleNextPoll(1500);

        // 5. Création et insertion des éléments dans le DOM
        // RÈGLE DE SÉCURITÉ ABSOLUE : insertion par textContent uniquement (ZÉRO innerHTML sur le contenu)
        function appendMessageToDom(msg) {
            if (!msg || !msg.id_message) return false;

            // Éviter les doublons éventuels
            const existant = document.querySelector(`[data-message-id="${msg.id_message}"]`);
            if (existant) {
                return false;
            }

            // Masquer le placeholder vide si présent
            if (emptyPlaceholder && !emptyPlaceholder.classList.contains('d-none')) {
                emptyPlaceholder.classList.add('d-none');
            }

            // S'assurer que le conteneur de liste existe
            if (!messagesList && messagesContainer) {
                messagesList = document.createElement('div');
                messagesList.id = 'messages-list';
                messagesList.className = 'd-flex flex-column gap-3';
                messagesContainer.appendChild(messagesList);
            }

            const wrapper = document.createElement('div');
            wrapper.className = 'd-flex ' + (msg.est_moi ? 'justify-content-end' : 'justify-content-start');
            wrapper.dataset.messageId = String(msg.id_message);

            const card = document.createElement('div');
            card.className = 'card ' + (msg.est_moi ? 'bg-primary text-white border-0' : 'bg-white border text-dark') + ' shadow-2xs p-3';
            card.style.maxWidth = '82%';
            card.style.minWidth = '220px';
            card.style.borderRadius = '12px';

            // En-tête (Auteur et Date)
            const headerDiv = document.createElement('div');
            headerDiv.className = 'd-flex justify-content-between align-items-center gap-3 mb-1.5';
            headerDiv.style.fontSize = '0.75rem';

            const authorSpan = document.createElement('span');
            authorSpan.className = 'fw-semibold ' + (msg.est_moi ? 'text-white-50' : 'text-primary');
            authorSpan.textContent = msg.nom_auteur || (msg.est_moi ? 'Vous' : 'Participant');

            const dateSpan = document.createElement('span');
            dateSpan.className = msg.est_moi ? 'text-white-50' : 'text-muted';
            const clockIcon = document.createElement('i');
            clockIcon.className = 'bi bi-clock me-1';
            clockIcon.setAttribute('aria-hidden', 'true');
            dateSpan.appendChild(clockIcon);
            const dateText = document.createTextNode(msg.date_formatee || formaterDate(msg.date_envoi));
            dateSpan.appendChild(dateText);

            headerDiv.appendChild(authorSpan);
            headerDiv.appendChild(dateSpan);
            card.appendChild(headerDiv);

            // =========================================================================
            // CORPS DU MESSAGE : STRICTEMENT textContent (NEVER innerHTML)
            // Empêche toute exécution de code malveillant / injections XSS
            // =========================================================================
            const bodyDiv = document.createElement('div');
            bodyDiv.className = 'message-content small';
            bodyDiv.style.whiteSpace = 'pre-wrap';
            bodyDiv.style.wordBreak = 'break-word';
            bodyDiv.style.lineHeight = '1.5';
            bodyDiv.textContent = msg.contenu; // IMMUNISÉ CONTRE LES XSS
            card.appendChild(bodyDiv);

            // Pied de carte : accusé de lecture pour mes messages
            if (msg.est_moi) {
                const footerDiv = document.createElement('div');
                footerDiv.className = 'text-end mt-1 text-white-50';
                footerDiv.style.fontSize = '0.68rem';

                const statusIcon = document.createElement('i');
                statusIcon.className = 'bi ' + (msg.lu ? 'bi-check2-all text-white' : 'bi-check2');
                statusIcon.title = msg.lu ? 'Message lu' : 'Message distribué';
                footerDiv.appendChild(statusIcon);
                card.appendChild(footerDiv);
            }

            wrapper.appendChild(card);
            messagesList.appendChild(wrapper);

            // Mise à jour du compteur si affiché
            if (countBadge) {
                const totalActuel = messagesList.children.length;
                countBadge.textContent = totalActuel;
            }

            return true;
        }

        function scrollToBottom(smooth = false) {
            if (!messagesContainer) return;
            const scrollOptions = {
                top: messagesContainer.scrollHeight,
                behavior: smooth ? 'smooth' : 'auto'
            };
            try {
                messagesContainer.scrollTo(scrollOptions);
            } catch (e) {
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
        }

        function showFeedback(texte, type = 'danger') {
            if (!feedback) return;
            feedback.className = `alert alert-${type} border small mt-2 mb-0 py-2 px-3`;
            feedback.textContent = texte;
        }

        function activerLectureSeule(demandeStatut) {
            if (form) {
                const footer = form.closest('.card-footer');
                if (footer) {
                    // Remplacer le formulaire par l'avertissement de lecture seule
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-secondary border d-flex align-items-start gap-3 p-3 rounded-3 shadow-2xs mb-0';
                    alertDiv.setAttribute('role', 'alert');

                    const icon = document.createElement('i');
                    icon.className = 'bi bi-lock-fill fs-4 text-secondary flex-shrink-0 mt-0.5';
                    icon.setAttribute('aria-hidden', 'true');

                    const contentWrap = document.createElement('div');
                    const strong = document.createElement('strong');
                    strong.className = 'd-block mb-1 text-dark';
                    strong.textContent = 'Zone de saisie verrouillée (Lecture seule)';

                    const p = document.createElement('p');
                    p.className = 'small text-secondary mb-0';
                    p.textContent = `Cette conversation est désormais clôturée (statut : ${demandeStatut || 'fermée'}). L'historique complet reste accessible en consultation mais l'envoi de nouveaux messages n'est plus autorisé.`;

                    contentWrap.appendChild(strong);
                    contentWrap.appendChild(p);
                    alertDiv.appendChild(icon);
                    alertDiv.appendChild(contentWrap);

                    footer.innerHTML = '';
                    footer.appendChild(alertDiv);
                }
            }
        }

        function formaterDate(chaineDate) {
            if (!chaineDate) return 'À l\'instant';
            try {
                const d = new Date(chaineDate.replace(' ', 'T'));
                if (isNaN(d.getTime())) return chaineDate;
                const jour = String(d.getDate()).padStart(2, '0');
                const mois = String(d.getMonth() + 1).padStart(2, '0');
                const annee = d.getFullYear();
                const heures = String(d.getHours()).padStart(2, '0');
                const minutes = String(d.getMinutes()).padStart(2, '0');
                return `${jour}/${mois}/${annee} à ${heures}:${minutes}`;
            } catch (e) {
                return chaineDate;
            }
        }
    }
})();
