/**
 * chat.js — FindPro Messagerie instantanée sécurisée (Architecture WhatsApp)
 * 
 * Spécifications validées :
 * - Polling temps réel bidirectionnel automatique (1s en premier plan, 3s en arrière-plan).
 * - Indicateur de saisie interactif ("Le client écrit… ● ● ●" ou "Le professionnel écrit… ● ● ●").
 * - Gestion fine des événements de frappe (typing:start, typing:stop, debounce 2 secondes).
 * - Arrêt immédiat de l'indicateur sur soumission, champ vidé ou fermeture de fenêtre.
 * - Défilement automatique intelligent (autoscroll uniquement si l'utilisateur est déjà en bas).
 * - Mise à jour en direct des accusés de lecture (simple check -> double check en temps réel).
 * - Bulles d'avatars émetteurs au-dessus de chaque message.
 * - Protection anti-XSS intégrale : insertion exclusive par textContent (ZÉRO innerHTML sur le contenu).
 * - Tolérance aux pannes réseau avec AbortController et reconnexion automatique.
 */

(function () {
    'use strict';

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initChat);
    } else {
        initChat();
    }

    function initChat() {
        const chatApp = document.getElementById('chat-app');
        if (!chatApp) {
            return;
        }

        const conversationId = parseInt(chatApp.dataset.conversationId, 10);
        let lastId = parseInt(chatApp.dataset.lastId, 10) || 0;
        const pollUrl = chatApp.dataset.pollUrl || 'poll.php';
        const sendUrl = chatApp.dataset.sendUrl || 'send.php';
        const typingUrl = chatApp.dataset.typingUrl || 'typing.php';
        const currentUserId = parseInt(chatApp.dataset.currentUserId, 10) || 0;
        const userPhoto = chatApp.dataset.userPhoto || '';
        const proPhoto = chatApp.dataset.proPhoto || '';
        const clientPhoto = chatApp.dataset.clientPhoto || '';

        const messagesContainer = document.getElementById('messages-container');
        let messagesList = document.getElementById('messages-list');
        const emptyPlaceholder = document.getElementById('chat-empty-placeholder');
        const typingIndicator = document.getElementById('typing-indicator');
        const typingText = document.getElementById('typing-text');
        const typingAvatar = document.getElementById('typing-avatar');

        const form = document.getElementById('form-message');
        const textarea = document.getElementById('contenu');
        const feedback = document.getElementById('chat-feedback');
        const submitBtn = form ? form.querySelector('button[type="submit"]') : null;
        const countBadge = document.getElementById('messages-count-badge');

        let pollTimer = null;
        let isPolling = false;
        let isSubmitting = false;
        let typingDebounceTimer = null;
        let lastTypingPingTime = 0;
        let isCurrentlyTyping = false;

        // 1. Défilement automatique initial vers le bas
        scrollToBottom(false);

        // 2. Détection de la position de lecture (Smart Autoscroll)
        function isUserNearBottom() {
            if (!messagesContainer) return true;
            const distanceToBottom = messagesContainer.scrollHeight - messagesContainer.scrollTop - messagesContainer.clientHeight;
            return distanceToBottom <= 140; // Tolérance de 140px
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

        // 3. Protocole d'émission de l'état de frappe (typing:start / typing:stop)
        function emitTyping(status) {
            if (!typingUrl || !form) return;

            const now = Date.now();
            if (status === 'start') {
                // Throttle : pas plus d'un ping toutes les 1.8 secondes pour préserver le réseau
                if (now - lastTypingPingTime < 1800 && isCurrentlyTyping) {
                    return;
                }
                lastTypingPingTime = now;
                isCurrentlyTyping = true;
            } else {
                if (!isCurrentlyTyping && status === 'stop') {
                    return;
                }
                isCurrentlyTyping = false;
                lastTypingPingTime = 0;
            }

            const csrfInput = form.querySelector('input[name="csrf_token"]');
            const csrfToken = csrfInput ? csrfInput.value : '';

            fetch(typingUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    id_conversation: conversationId,
                    action: status
                }),
                keepalive: (status === 'stop')
            }).catch(() => {
                // Tolérance aux micro-coupures réseau
            });
        }

        // 4. Écouteurs de saisie utilisateur
        if (form && textarea && submitBtn) {
            form.addEventListener('submit', handleSendMessage);

            textarea.addEventListener('input', function () {
                const val = textarea.value.trim();
                if (val.length > 0) {
                    emitTyping('start');

                    if (typingDebounceTimer) {
                        clearTimeout(typingDebounceTimer);
                    }
                    // Arrêt automatique après 2 secondes sans nouvelle frappe
                    typingDebounceTimer = setTimeout(function () {
                        emitTyping('stop');
                    }, 2000);
                } else {
                    // Si le champ est vidé
                    if (typingDebounceTimer) {
                        clearTimeout(typingDebounceTimer);
                    }
                    emitTyping('stop');
                }
            });

            // Raccourci Ctrl+Entrée pour envoi rapide
            textarea.addEventListener('keydown', function (e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                    e.preventDefault();
                    form.dispatchEvent(new Event('submit', { cancelable: true }));
                }
            });

            // Quand l'utilisateur quitte la page ou l'onglet
            window.addEventListener('beforeunload', function () {
                if (isCurrentlyTyping) {
                    emitTyping('stop');
                }
            });
        }

        // 5. Envoi d'un message en AJAX sans rechargement
        async function handleSendMessage(e) {
            e.preventDefault();
            if (isSubmitting) return;

            const contenu = textarea.value.trim();
            if (!contenu) {
                textarea.focus();
                return;
            }

            // Arrêt immédiat de l'indicateur de saisie côté serveur
            if (typingDebounceTimer) {
                clearTimeout(typingDebounceTimer);
            }
            emitTyping('stop');

            const csrfInput = form.querySelector('input[name="csrf_token"]');
            const csrfToken = csrfInput ? csrfInput.value : '';

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
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        id_conversation: conversationId,
                        contenu: contenu
                    })
                });

                const data = await response.json().catch(() => null);

                if (response.ok && data && data.succes) {
                    textarea.value = '';
                    textarea.style.height = 'auto';

                    // Insertion immédiate du propre message de l'utilisateur
                    const newMsgPayload = data.data || data.donnees;
                    if (newMsgPayload) {
                        const newMsgId = parseInt(newMsgPayload.id_message, 10);
                        const newMsg = {
                            id_message: newMsgId,
                            id_expediteur: newMsgPayload.id_expediteur || currentUserId,
                            nom_auteur: 'Vous',
                            photo_url: newMsgPayload.photo_url || userPhoto,
                            contenu: newMsgPayload.contenu,
                            date_formatee: formaterDate(newMsgPayload.date_envoi),
                            est_moi: true,
                            lu: 0
                        };
                        appendMessageToDom(newMsg);
                        if (newMsgId > lastId) {
                            lastId = newMsgId;
                        }
                        scrollToBottom(true);
                    }

                    // Déclenchement d'un cycle de vérification immédiat
                    pollMessages();
                } else {
                    const messageErreur = (data && data.message) 
                        ? data.message 
                        : 'Une erreur est survenue lors de l\'envoi du message.';
                    showFeedback(messageErreur, 'danger');
                }
            } catch (err) {
                showFeedback('Impossible d\'acheminer votre message (erreur de connexion). Veuillez réessayer.', 'danger');
            } finally {
                isSubmitting = false;
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnContent;
                textarea.focus();
            }
        }

        // 6. Polling AJAX haute fréquence (1s actif, 3s en arrière-plan)
        function scheduleNextPoll(delayMs) {
            if (pollTimer) {
                clearTimeout(pollTimer);
                pollTimer = null;
            }
            // En arrière-plan, on continue à sonder pour ne jamais manquer un message !
            const defaultDelay = document.hidden ? 3000 : 1000;
            const delay = (typeof delayMs === 'number') ? delayMs : defaultDelay;
            pollTimer = setTimeout(pollMessages, delay);
        }

        async function pollMessages() {
            if (isPolling) {
                return;
            }

            isPolling = true;

            // Timeout de protection de 5 secondes avec AbortController
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 5000);

            try {
                const url = `${pollUrl}?id=${encodeURIComponent(conversationId)}&apres=${encodeURIComponent(lastId)}&_t=${Date.now()}`;
                const response = await fetch(url, {
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

                        // Traitement de l'indicateur de saisie "Est en train d'écrire"
                        if (typingIndicator) {
                            if (data.en_train_decrire) {
                                if (typingText) {
                                    typingText.textContent = data.typing_texte || data.typing_nom || "En train d'écrire…";
                                }
                                if (typingAvatar && data.typing_photo) {
                                    typingAvatar.src = data.typing_photo;
                                }
                                const wasNearBottom = isUserNearBottom();
                                typingIndicator.classList.remove('d-none');
                                if (wasNearBottom) {
                                    scrollToBottom(false);
                                }
                            } else {
                                typingIndicator.classList.add('d-none');
                            }
                        }

                        // Traitement des nouveaux messages reçus
                        if (Array.isArray(data.messages) && data.messages.length > 0) {
                            const wasNearBottom = isUserNearBottom();
                            let hasNew = false;
                            for (const msg of data.messages) {
                                const msgId = parseInt(msg.id_message, 10);
                                const added = appendMessageToDom(msg);
                                if (added) hasNew = true;
                                if (msgId > lastId) {
                                    lastId = msgId;
                                }
                            }
                            if (hasNew) {
                                if (typingIndicator) {
                                    typingIndicator.classList.add('d-none');
                                }
                                // Défilement automatique intelligent : uniquement si déjà en bas
                                if (wasNearBottom) {
                                    scrollToBottom(true);
                                }
                            }
                        }

                        // Mise à jour des accusés de lecture en temps réel (double checkmarks)
                        if (Array.isArray(data.ids_lus) && data.ids_lus.length > 0) {
                            for (const idLu of data.ids_lus) {
                                const msgWrapper = document.querySelector(`[data-message-id="${idLu}"]`);
                                if (msgWrapper) {
                                    const checkIcon = msgWrapper.querySelector('.bi-check2');
                                    if (checkIcon) {
                                        checkIcon.className = 'bi bi-check2-all text-white';
                                        checkIcon.title = 'Message lu';
                                    }
                                }
                            }
                        }

                        if (typeof data.dernier_id === 'number' && data.dernier_id > lastId) {
                            lastId = data.dernier_id;
                        }

                        // Clôture automatique en lecture seule
                        if (data.est_lecture_seule) {
                            activerLectureSeule(data.demande_statut);
                        }
                    }
                }
            } catch (err) {
                // Reprise silencieuse après micro-coupure
            } finally {
                clearTimeout(timeoutId);
                isPolling = false;
                scheduleNextPoll();
            }
        }

        // 7. Gestion de la visibilité et du focus
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                // Dès que l'onglet redevient actif, sonder immédiatement
                pollMessages();
            } else {
                scheduleNextPoll(3000);
            }
        });

        window.addEventListener('focus', function () {
            pollMessages();
        });

        // Démarrage initial
        scheduleNextPoll(500);

        // 8. Insertion sécurisée dans le DOM (Anti-XSS avec textContent)
        function appendMessageToDom(msg) {
            if (!msg || !msg.id_message) return false;

            const msgId = parseInt(msg.id_message, 10);
            const existant = document.querySelector(`[data-message-id="${msgId}"]`);
            if (existant) {
                return false;
            }

            if (emptyPlaceholder && !emptyPlaceholder.classList.contains('d-none')) {
                emptyPlaceholder.classList.add('d-none');
            }

            if (!messagesList && messagesContainer) {
                messagesList = document.createElement('div');
                messagesList.id = 'messages-list';
                messagesList.className = 'd-flex flex-column gap-3';
                messagesContainer.insertBefore(messagesList, typingIndicator || null);
            }

            const wrapper = document.createElement('div');
            wrapper.className = 'd-flex ' + (msg.est_moi ? 'justify-content-end' : 'justify-content-start');
            wrapper.dataset.messageId = String(msgId);

            const card = document.createElement('div');
            card.className = 'card ' + (msg.est_moi ? 'bg-primary text-white border-0' : 'bg-white border text-dark') + ' shadow-2xs p-3';
            card.style.maxWidth = '82%';
            card.style.minWidth = '220px';
            card.style.borderRadius = '12px';

            // En-tête (Avatar, Nom et Date)
            const headerDiv = document.createElement('div');
            headerDiv.className = 'd-flex justify-content-between align-items-center gap-2 mb-1.5';
            headerDiv.style.fontSize = '0.75rem';

            const authorBox = document.createElement('div');
            authorBox.className = 'd-flex align-items-center gap-1.5';

            // Bulle d'avatar
            const avatarImg = document.createElement('img');
            const avatarSrc = msg.photo_url || (msg.est_moi ? userPhoto : (msg.exp_role === 'professionnel' ? proPhoto : clientPhoto));
            avatarImg.src = avatarSrc || 'assets/img/avatar-defaut.svg';
            avatarImg.alt = 'Avatar';
            avatarImg.className = 'chat-avatar-bubble' + (msg.est_moi ? ' is-me' : '');
            avatarImg.width = 28;
            avatarImg.height = 28;
            authorBox.appendChild(avatarImg);

            const authorSpan = document.createElement('span');
            authorSpan.className = 'fw-semibold ' + (msg.est_moi ? 'text-white-50' : 'text-primary');
            authorSpan.textContent = msg.nom_auteur || (msg.est_moi ? 'Vous' : 'Participant');
            authorBox.appendChild(authorSpan);

            const dateSpan = document.createElement('span');
            dateSpan.className = msg.est_moi ? 'text-white-50' : 'text-muted';
            const clockIcon = document.createElement('i');
            clockIcon.className = 'bi bi-clock me-1';
            clockIcon.setAttribute('aria-hidden', 'true');
            dateSpan.appendChild(clockIcon);
            const dateText = document.createTextNode(msg.date_formatee || formaterDate(msg.date_envoi));
            dateSpan.appendChild(dateText);

            headerDiv.appendChild(authorBox);
            headerDiv.appendChild(dateSpan);
            card.appendChild(headerDiv);

            // Corps du message (immunisé contre XSS)
            const bodyDiv = document.createElement('div');
            bodyDiv.className = 'message-content small';
            bodyDiv.style.whiteSpace = 'pre-wrap';
            bodyDiv.style.wordBreak = 'break-word';
            bodyDiv.style.lineHeight = '1.5';
            bodyDiv.textContent = msg.contenu;
            card.appendChild(bodyDiv);

            // Pied de carte : accusé de lecture
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

            if (countBadge) {
                countBadge.textContent = messagesList.children.length;
            }

            return true;
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
