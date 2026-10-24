import { request, showToast, confirmAction } from '/js/app.js';

const RECENT_KEY = 'whats-recent-responses';

const profileCache = new Map();

const escapeHtml = (value) => {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
};

const formatPhone = (phone) => {
    if (!phone) return phone;
    const clean = phone.replace(/\D/g, '');
    if (clean.length >= 10) {
        let p = clean;
        if (p.startsWith('55')) p = p.substring(2);
        if (p.length === 11) {
            return `(${p.substring(0, 2)}) ${p.substring(2, 7)}-${p.substring(7)}`;
        }
        if (p.length === 10) {
            return `(${p.substring(0, 2)}) ${p.substring(2, 6)}-${p.substring(6)}`;
        }
    }
    return phone;
};

const formatDuration = (seconds) => {
    if (!Number.isFinite(seconds)) {
        return 'Sem SLA';
    }
    if (seconds <= 0) {
        return 'SLA ultrapassado';
    }
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) {
        return `${minutes} min`;
    }
    const hours = Math.floor(minutes / 60);
    const remaining = minutes % 60;
    return `${hours}h ${remaining}min`;
};

const loadProfileAvatar = async (element) => {
    if (!element) {
        return;
    }

    const url = element.getAttribute('data-profile-url');
    const fallback = element.querySelector('[data-profile-fallback]');

    if (!url) {
        element.classList.add('avatar-empty');
        return;
    }

    const applyAvatar = (src) => {
        element.style.backgroundImage = `url('${src}')`;
        element.classList.add('avatar-has-image');
        if (fallback) {
            fallback.textContent = '';
        }
    };

    if (profileCache.has(url)) {
        const cached = profileCache.get(url);
        if (cached) {
            applyAvatar(cached);
        } else {
            element.classList.add('avatar-empty');
        }
        return;
    }

    try {
        const response = await fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (response.status === 204) {
            profileCache.set(url, null);
            element.classList.add('avatar-empty');
            return;
        }
        if (!response.ok) {
            throw new Error('Request failed');
        }

        const contentType = response.headers.get('Content-Type') || '';
        if (!contentType.startsWith('image/')) {
            profileCache.set(url, null);
            element.classList.add('avatar-empty');
            return;
        }

        const blob = await response.blob();
        const objectUrl = URL.createObjectURL(blob);
        profileCache.set(url, objectUrl);
        applyAvatar(objectUrl);
    } catch (error) {
        profileCache.set(url, null);
        element.classList.add('avatar-empty');
    }
};

const getRecentResponses = () => {
    try {
        const raw = localStorage.getItem(RECENT_KEY);
        if (!raw) {
            return [];
        }
        const parsed = JSON.parse(raw);
        return Array.isArray(parsed) ? parsed.slice(0, 5) : [];
    } catch (error) {
        return [];
    }
};

const storeRecentResponse = (message) => {
    if (!message || message.length < 4) {
        return;
    }
    const recent = getRecentResponses().filter((item) => item !== message);
    recent.unshift(message);
    localStorage.setItem(RECENT_KEY, JSON.stringify(recent.slice(0, 8)));
};

const renderMessages = (chatWindow, messages = []) => {
    if (!chatWindow) {
        return;
    }

    chatWindow.innerHTML = messages.map((message) => {
        const mediaType = message?.media_type ?? 'text';
        const mediaUrl = message?.media_url ?? null;
        const bodyText = (message?.body ?? '').trim();
        const hasMedia = Boolean(mediaUrl) && ['image', 'audio', 'video', 'file'].includes(mediaType);
        const isAgent = message?.sender_type === 'agent';

        let mediaHtml = '';
        if (hasMedia && mediaUrl) {
            const safeUrl = escapeHtml(mediaUrl);
            if (mediaType === 'image') {
                mediaHtml = `<img src="${safeUrl}" class="chat-media chat-media--image" alt="Mídia recebida">`;
            } else if (mediaType === 'audio') {
                mediaHtml = `<audio controls class="chat-media chat-media--audio" src="${safeUrl}"></audio>`;
            } else if (mediaType === 'video') {
                mediaHtml = `<video controls class="chat-media chat-media--video" src="${safeUrl}"></video>`;
            } else if (mediaType === 'file') {
                mediaHtml = `
                    <a href="${safeUrl}" target="_blank" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-paperclip"></i> Baixar arquivo
                    </a>`;
            }
        }

        let bodyHtml = '';
        if (bodyText !== '') {
            bodyHtml = `<p>${escapeHtml(bodyText).replace(/\n/g, '<br>')}</p>`;
        } else if (!hasMedia) {
            bodyHtml = '<p class="text-muted fst-italic">Mensagem sem conteúdo.</p>';
        }

        const sentAt = message?.sent_at ? new Date(message.sent_at) : null;
        const time = sentAt ? sentAt.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) : '';
        const agent = message?.agent_name ? ` · ${escapeHtml(message.agent_name)}` : '';
        
        const messageStatusHtml = isAgent ? `<i class="bi bi-check2-all ms-1 text-blue-300" title="Enviada"></i>` : '';

        return `
            <article class="chat-message ${isAgent ? 'chat-message--agent' : 'chat-message--contact'}">
                <div class="chat-bubble" data-message-type="${escapeHtml(mediaType)}">
                    ${mediaHtml}${bodyHtml}
                    <footer>
                        <time>${time}</time>${agent}${messageStatusHtml}
                    </footer>
                </div>
            </article>`;
    }).join('');

    chatWindow.scrollTop = chatWindow.scrollHeight;
};



const renderNativeChats = (tbody, chats = []) => {
    if (!tbody) {
        return;
    }
    if (!chats.length) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Nenhuma conversa pendente.</td></tr>';
        return;
    }
    tbody.innerHTML = chats.map((chat) => {
        const remoteId = escapeHtml(chat?.id ?? '');
        const name = chat?.name ? escapeHtml(chat.name) : remoteId;
        const unreadCount = Number.isFinite(chat?.unread) ? chat.unread : parseInt(chat?.unread ?? 0, 10) || 0;
        const profileUrl = typeof chat?.profile_url === 'string' ? chat.profile_url : '';
        const profileAttr = profileUrl ? ` data-profile-url="${escapeHtml(profileUrl)}"` : '';
        const initial = escapeHtml((chat?.name || chat?.id || '#').charAt(0).toUpperCase() || '#');
        const badge = unreadCount > 0 ? `<span class="badge badge--neutral">${unreadCount}</span>` : '<span class="text-muted">0</span>';

        return `
            <tr>
                <td>
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar avatar-sm"${profileAttr}>
                            <span data-profile-fallback>${initial}</span>
                        </div>
                        <div>
                            <strong>${name}</strong>
                            <div class="text-muted small">${remoteId}</div>
                        </div>
                    </div>
                </td>
                <td>${badge}</td>
                <td>${chat?.last_message_at ? escapeHtml(chat.last_message_at) : '—'}</td>
                <td class="text-end">
                    <button class="btn btn-sm btn-primary" data-start-native data-remote="${remoteId}" data-name="${escapeHtml(chat?.name ?? '')}">
                        <i class="bi bi-chat-dots"></i> Iniciar
                    </button>
                </td>
            </tr>`;
    }).join('');

    tbody.querySelectorAll('.avatar').forEach((avatar) => {
        loadProfileAvatar(avatar);
    });
};

const applySlaStatus = (container) => {
    if (!container) {
        return;
    }
    const countdown = container.querySelector('[data-sla-countdown]');
    const progress = container.querySelector('[data-sla-progress]');
    const slaDue = container.getAttribute('data-sla-due');
    if (!slaDue) {
        if (countdown) {
            countdown.textContent = 'Sem SLA configurado.';
        }
        return;
    }
    const due = new Date(slaDue);
    if (Number.isNaN(due.getTime())) {
        return;
    }

    const update = () => {
        const now = new Date();
        const total = due.getTime() - now.getTime();
        if (countdown) {
            countdown.textContent = formatDuration(Math.floor(total / 1000));
        }
        if (progress) {
            const maxSeconds = 4 * 60 * 60; // 4h window
            const ratio = Math.max(0, Math.min(1, total / 1000 / maxSeconds));
            progress.style.width = `${ratio * 100}%`;
            progress.classList.toggle('bg-danger', total <= 0);
            progress.classList.toggle('bg-warning', total > 0 && total < 30 * 60 * 1000);
        }
    };

    update();
    setInterval(update, 30_000);
};

const hydrateQuickSuggestions = (container) => {
    if (!container) {
        return;
    }
    const recents = getRecentResponses();
    recents.forEach((message) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'chip';
        button.textContent = message;
        button.dataset.suggestion = 'recent';
        container.appendChild(button);
    });
};

const persistNotes = (textarea, ticketId) => {
    if (!textarea) {
        return;
    }
    const key = `whats-ticket-notes-${ticketId}`;
    textarea.value = localStorage.getItem(key) || '';
    textarea.addEventListener('input', () => {
        localStorage.setItem(key, textarea.value);
    });
};

export function initChat(selector) {
    const container = document.querySelector(selector);
    if (!container) {
        return;
    }
    const ticketId = container.getAttribute('data-ticket-id');
    const chatWindow = container.querySelector('[data-chat-window]');
    const form = container.querySelector('[data-chat-form]');
    const input = container.querySelector('[data-chat-input]');
    const statusBadge = container.querySelector('[data-ticket-status]');
    const refreshButton = container.querySelector('[data-refresh-chat]');
    const resolveButton = container.querySelector('[data-resolve-ticket]');
    const profileAvatar = container.querySelector('[data-profile-avatar]');
    const suggestionBar = container.querySelector('[data-quick-suggestions]');

    const nativeTable = document.querySelector('[data-native-body]');
    const dropzone = container.querySelector('[data-dropzone]');
    const notes = container.querySelector('[data-internal-notes]');
    const slaPanel = container;
    const statusTrack = container.querySelector('[data-status-track]');
    const statusSteps = statusTrack ? Array.from(statusTrack.querySelectorAll('[data-status-step]')) : [];
    const metaSubject = container.querySelector('[data-meta-subject]');
    const metaSeriousness = container.querySelector('[data-meta-seriousness]');
    const metaGroup = container.querySelector('[data-meta-group]');
    const metaSave = container.querySelector('[data-meta-save]');
    const metaFeedback = container.querySelector('[data-meta-feedback]');
    const seriousnessBadge = container.querySelector('[data-ticket-seriousness]');
    let seriousnessOptions = {};
    try {
        seriousnessOptions = JSON.parse(container.getAttribute('data-seriousness-options') || '{}');
    } catch (error) {
        seriousnessOptions = {};
    }
    const statusLabels = {
        open: 'Aberto',
        assigned: 'Em atendimento',
        resolved: 'Resolvido',
        closed: 'Encerrado',
    };
    const normaliseStatus = (value) => (value ?? '').toString().toLowerCase();
    let currentStatus = normaliseStatus(
        statusTrack?.getAttribute('data-current-status')
        || statusBadge?.getAttribute('data-status-key')
        || statusBadge?.textContent
        || 'open',
    );

    const updateStatusBadge = (status, label) => {
        if (!statusBadge) {
            return;
        }
        const normalized = normaliseStatus(status) || 'open';
        const text = label && String(label).trim() !== '' ? label : (statusLabels[normalized] ?? normalized);
        statusBadge.textContent = text;
        statusBadge.className = `status-badge status-badge--${normalized}`;
        statusBadge.setAttribute('data-status-key', normalized);
    };

    const updateStatusTrack = (status) => {
        if (!statusTrack) {
            return;
        }
        currentStatus = normaliseStatus(status) || 'open';
        statusTrack.setAttribute('data-current-status', currentStatus);
        const activeIndex = statusSteps.findIndex((step) => normaliseStatus(step.getAttribute('data-status-step')) === currentStatus);
        const computedIndex = activeIndex === -1 ? 0 : activeIndex;
        statusSteps.forEach((step, index) => {
            const isActive = index === computedIndex;
            const isComplete = index < computedIndex;
            step.classList.toggle('is-active', isActive);
            step.classList.toggle('is-complete', isComplete);
        });
    };

    const applyStatusState = (status, label) => {
        updateStatusBadge(status, label);
        updateStatusTrack(status);
    };

    const updateSeriousnessBadge = (key) => {
        if (!seriousnessBadge) {
            return;
        }
        const normalized = String(key || '').toLowerCase();
        const label = seriousnessOptions[normalized] || normalized || 'Informação';
        seriousnessBadge.textContent = label;
    };

    const draftKey = `whats-ticket-draft-${ticketId}`;
    if (input) {
        const storedDraft = localStorage.getItem(draftKey);
        if (storedDraft) {
            input.value = storedDraft;
        }
        input.addEventListener('input', () => {
            localStorage.setItem(draftKey, input.value);
        });
    }

    hydrateQuickSuggestions(suggestionBar);
    persistNotes(notes, ticketId);
    applySlaStatus(slaPanel);
    applyStatusState(currentStatus, statusBadge?.textContent ?? statusLabels[currentStatus]);
    updateSeriousnessBadge(container.getAttribute('data-current-seriousness'));

    metaSeriousness?.addEventListener('change', () => {
        updateSeriousnessBadge(metaSeriousness.value);
    });

    statusTrack?.addEventListener('click', async (event) => {
        const target = event.target instanceof HTMLElement ? event.target.closest('[data-status-step]') : null;
        if (!target || target.hasAttribute('disabled')) {
            return;
        }

        const nextStatus = normaliseStatus(target.getAttribute('data-status-step'));
        if (!nextStatus || nextStatus === currentStatus) {
            return;
        }

        if (!ticketId) {
            showToast('Não foi possível identificar o ticket selecionado.', 'error');
            return;
        }

        statusTrack.classList.add('is-busy');

        try {
            const response = await request(`/tickets/${ticketId}/status`, {
                method: 'POST',
                body: { status: nextStatus },
            });
            const updatedStatus = normaliseStatus(response?.ticket?.status ?? nextStatus);
            const updatedLabel = response?.ticket?.label ?? statusLabels[updatedStatus] ?? statusLabels[nextStatus] ?? nextStatus;
            applyStatusState(updatedStatus, updatedLabel);
            
            if (updatedStatus === 'resolved' || updatedStatus === 'closed') {
                window.location.assign('/');
                return;
            }

            if (response?.message) {
                showToast(response.message);
            } else {
                showToast(`Status atualizado para ${updatedLabel}.`);
            }
        } catch (error) {
            const message = error?.data?.error || error?.message || 'Não foi possível atualizar o status do ticket.';
            showToast(message, 'error');
        } finally {
            statusTrack.classList.remove('is-busy');
        }
    });

    metaSave?.addEventListener('click', async () => {
        if (!ticketId) {
            showToast('Ticket não identificado.', 'error');
            return;
        }

        metaSave.disabled = true;
        if (metaFeedback) {
            metaFeedback.textContent = 'Salvando...';
        }

        try {
            const payload = {
                subject: metaSubject?.value ?? '',
                seriousness: metaSeriousness?.value ?? '',
                group_id: metaGroup?.value ?? '',
            };
            const response = await request(`/tickets/${ticketId}/meta`, {
                method: 'POST',
                body: payload,
            });
            const message = response?.message || 'Detalhes do ticket atualizados.';
            showToast(message);
            if (metaFeedback) {
                metaFeedback.textContent = message;
                window.setTimeout(() => {
                    metaFeedback.textContent = '';
                }, 4000);
            }
            if (response?.meta?.seriousness) {
                updateSeriousnessBadge(response.meta.seriousness);
                if (metaSeriousness) {
                    metaSeriousness.value = response.meta.seriousness;
                }
            }
            if (response?.meta?.subject !== undefined && metaSubject) {
                metaSubject.value = response.meta.subject ?? '';
            }
            if (response?.meta?.group_id !== undefined && metaGroup) {
                metaGroup.value = response.meta.group_id ? String(response.meta.group_id) : '';
            }
        } catch (error) {
            const message = error?.data?.message || 'Não foi possível atualizar os detalhes do ticket.';
            showToast(message, 'error');
        }
    });

    if (nativeTable) {
        const nativeWrapper = nativeTable.closest('[data-native-chats]');
        const nativeState = nativeWrapper ? nativeWrapper.getAttribute('data-config') : null;
        if (nativeState) {
            try {
                const parsed = JSON.parse(nativeState);
                renderNativeChats(nativeTable, parsed?.chats ?? []);
            } catch (error) {
                renderNativeChats(nativeTable, []);
            }
        }
    }

    let messagePollingHandle = null;
    let messagePollingSuspended = false;
    let messageFetchNotified = false;

    const stopMessagePolling = () => {
        if (messagePollingHandle !== null) {
            window.clearInterval(messagePollingHandle);
            messagePollingHandle = null;
        }
    };

    const ensureMessagePolling = () => {
        if (messagePollingHandle === null && ticketId && !messagePollingSuspended) {
            messagePollingHandle = window.setInterval(() => fetchMessages(), 5000);
        }
    };

    const fetchMessages = async ({ force = false } = {}) => {
        if (!ticketId || (messagePollingSuspended && !force)) {
            return;
        }

        try {
            const data = await request(`/tickets/${ticketId}/messages`, { method: 'GET' });
            const messages = Array.isArray(data)
                ? data
                : Array.isArray(data?.messages)
                    ? data.messages
                    : [];

            renderMessages(chatWindow, messages);

            if (data?.status === 'missing') {
                messagePollingSuspended = true;
                stopMessagePolling();
                if (!messageFetchNotified) {
                    showToast('Ticket não está mais disponível.', 'warning');
                    messageFetchNotified = true;
                }
                return;
            }

            if (messagePollingSuspended) {
                messagePollingSuspended = false;
                ensureMessagePolling();
            }

            if (data?.error && !messageFetchNotified) {
                showToast(data.error, 'warning');
                messageFetchNotified = true;
            } else if (!data?.error) {
                messageFetchNotified = false;
            }
        } catch (error) {
            if (!messageFetchNotified) {
                showToast('Não foi possível atualizar o chat.', 'error');
                messageFetchNotified = true;
            }
        }
    };

    if (form && input) {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const message = input.value.trim();
            if (!message) {
                return;
            }

            const submitButton = form.querySelector('button[type="submit"]');
            if (submitButton) {
                submitButton.disabled = true;
            }

            try {
                const payload = new FormData(form);
                payload.set('message', message);
                // Attachment logic removed per user request

                await request(`/tickets/${ticketId}/messages`, {
                    method: 'POST',
                    body: payload,
                });

                storeRecentResponse(message);
                input.value = '';
                localStorage.removeItem(draftKey);
                fetchMessages({ force: true });
                // Removed toast here in favor of inline message status icon
            } catch (error) {
                showToast(error?.data?.error || 'Não foi possível enviar a mensagem.', 'error');
            } finally {
                if (submitButton) {
                    submitButton.disabled = false;
                }
            }
        });

        form.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                form.requestSubmit();
            }
            if (event.key === '/' && input.selectionStart === 0 && input.selectionEnd === 0) {
                const firstTemplate = container.querySelector('[data-template-body]');
                if (firstTemplate) {
                    firstTemplate.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            }
        });
    }

    container.addEventListener('click', (event) => {
        const templateButton = event.target instanceof HTMLElement ? event.target.closest('[data-send-template]') : null;
        if (templateButton) {
            const body = templateButton.closest('[data-template-body]')?.getAttribute('data-template-body') || '';
            if (!body) {
                return;
            }

            templateButton.disabled = true;
            const payload = new FormData();
            payload.append('message', body);

            request(`/tickets/${ticketId}/messages`, {
                method: 'POST',
                body: payload,
            }).then(() => {
                storeRecentResponse(body);
                showToast('Template enviado.');
                fetchMessages({ force: true });
            }).catch((error) => {
                showToast(error?.data?.error || 'Não foi possível enviar o template.', 'error');
            }).finally(() => {
                templateButton.disabled = false;
            });
            return;
        }

        const suggestion = event.target instanceof HTMLElement ? event.target.closest('[data-suggestion]') : null;
        if (suggestion && input) {
            input.value = suggestion.textContent ?? '';
            input.focus();
        }

        const nativeButton = event.target instanceof HTMLElement ? event.target.closest('[data-start-native]') : null;
        if (nativeButton) {
            nativeButton.disabled = true;
            const payload = {
                remote_jid: nativeButton.getAttribute('data-remote') ?? '',
                name: nativeButton.getAttribute('data-name') ?? '',
            };
            request('/tickets/native/start', {
                method: 'POST',
                body: payload,
            }).then((data) => {
                showToast('Conversa iniciada.');
                if (data?.redirect) {
                    window.location.assign(data.redirect);
                }
            }).catch((error) => {
                showToast(error?.data?.error || 'Falha ao iniciar conversa.', 'error');
            }).finally(() => {
                nativeButton.disabled = false;
            });
        }
    });

    if (refreshButton) {
        refreshButton.addEventListener('click', () => fetchMessages({ force: true }));
    }

    if (resolveButton) {
        resolveButton.addEventListener('click', async () => {
            const confirmed = await confirmAction('Deseja finalizar este atendimento?', 'Sim, finalizar');
            if (!confirmed) {
                return;
            }
            try {
                const data = await request(`/tickets/${ticketId}/resolve`, { method: 'POST' });
                applyStatusState('resolved', statusLabels.resolved);
                window.location.assign('/');
            } catch (error) {
                showToast('Não foi possível finalizar o atendimento.', 'error');
            }
        });
    }

    if (profileAvatar) {
        loadProfileAvatar(profileAvatar);
    }

    if (profileAvatar) {
        loadProfileAvatar(profileAvatar);
    }

    fetchMessages({ force: true });
    ensureMessagePolling();

    // Accordion Logic for Ticket Pane
    const accordion = container.querySelector('#ticketSidebarAccordion');
    if (accordion) {
        const toggles = accordion.querySelectorAll('[data-bs-toggle="collapse"]');
        toggles.forEach(toggle => {
            const newToggle = toggle.cloneNode(true);
            toggle.parentNode.replaceChild(newToggle, toggle);
            
            newToggle.addEventListener('click', (e) => {
                e.preventDefault();
                const targetId = newToggle.getAttribute('data-bs-target');
                const target = document.querySelector(targetId);
                if (!target) return;
                
                const isExpanded = newToggle.getAttribute('aria-expanded') === 'true';
                
                if (!isExpanded) {
                    const parentSelector = target.getAttribute('data-bs-parent');
                    if (parentSelector) {
                        const parent = document.querySelector(parentSelector);
                        if (parent) {
                            const openTargets = parent.querySelectorAll('.accordion-content:not(.hidden)');
                            openTargets.forEach(openTarget => {
                                if (openTarget !== target) {
                                    openTarget.classList.add('hidden');
                                    const openToggle = parent.querySelector(`[data-bs-target="#${openTarget.id}"]`);
                                    if (openToggle) openToggle.setAttribute('aria-expanded', 'false');
                                }
                            });
                        }
                    }
                }
                
                if (isExpanded) {
                    target.classList.add('hidden');
                    newToggle.setAttribute('aria-expanded', 'false');
                } else {
                    target.classList.remove('hidden');
                    newToggle.setAttribute('aria-expanded', 'true');
                }
            });
        });
    }
}
