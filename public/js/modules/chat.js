import { request, showToast, confirmAction } from '/js/app.js';

const escapeHtml = (value) => {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
};

const profileCache = new Map();

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
        applyAvatar(profileCache.get(url));
        return;
    }

    try {
        const response = await fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!response.ok) {
            throw new Error('Request failed');
        }

        const contentType = response.headers.get('Content-Type') || '';
        if (!contentType.startsWith('image/')) {
            throw new Error('Unsupported content');
        }

        const blob = await response.blob();
        const objectUrl = URL.createObjectURL(blob);
        profileCache.set(url, objectUrl);
        applyAvatar(objectUrl);
    } catch (error) {
        element.classList.add('avatar-empty');
    }
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
    const attachmentInput = container.querySelector('[data-chat-attachment]');
    const attachButton = container.querySelector('[data-attach-trigger]');
    const statusBadge = container.querySelector('[data-ticket-status]');
    const refreshButton = container.querySelector('[data-refresh-chat]');
    const resolveButton = container.querySelector('[data-resolve-ticket]');
    const profileAvatar = container.querySelector('[data-profile-avatar]');

    const renderMessages = (messages = []) => {
        if (!chatWindow) {
            return;
        }

        chatWindow.innerHTML = messages.map((message) => {
            const mediaType = message?.media_type ?? 'text';
            const mediaUrl = message?.media_url ?? null;
            const bodyText = (message?.body ?? '').trim();
            const hasMedia = Boolean(mediaUrl) && ['image', 'audio', 'video', 'file'].includes(mediaType);

            let mediaHtml = '';
            if (hasMedia && mediaUrl) {
                const safeUrl = escapeHtml(mediaUrl);
                if (mediaType === 'image') {
                    mediaHtml = `<img src="${safeUrl}" class="img-fluid rounded" alt="Mídia recebida">`;
                } else if (mediaType === 'audio') {
                    mediaHtml = `<audio controls class="w-100" src="${safeUrl}"></audio>`;
                } else if (mediaType === 'video') {
                    mediaHtml = `<video controls class="w-100 rounded" src="${safeUrl}"></video>`;
                } else if (mediaType === 'file') {
                    mediaHtml = `
                        <a href="${safeUrl}" target="_blank" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-paperclip"></i> Baixar arquivo
                        </a>`;
                }
            }

            let bodyHtml = '';
            if (bodyText !== '') {
                bodyHtml = `<div class="${hasMedia ? 'mt-2' : ''}">${escapeHtml(bodyText).replace(/\n/g, '<br>')}</div>`;
            } else if (!hasMedia) {
                bodyHtml = '<span class="text-muted fst-italic">Mensagem sem conteúdo.</span>';
            }

            const time = message?.sent_at
                ? new Date(message.sent_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
                : '';
            const agent = message?.agent_name ? ` · ${escapeHtml(message.agent_name)}` : '';

            return `
                <div class="message ${message?.sender_type === 'agent' ? 'agent' : 'contact'}">
                    <div class="bubble">
                        ${mediaHtml}${bodyHtml}
                        <div class="small text-muted mt-1">${time}${agent}</div>
                    </div>
                </div>`;
        }).join('');

        chatWindow.scrollTop = chatWindow.scrollHeight;
    };

    const fetchMessages = async () => {
        if (!ticketId) {
            return;
        }
        try {
            const data = await request(`/tickets/${ticketId}/messages`, { method: 'GET' });
            renderMessages(Array.isArray(data) ? data : []);
        } catch (error) {
            showToast('Não foi possível atualizar o chat.', 'error');
        }
    };

    if (form && input) {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const message = input.value.trim();
            const hasAttachment = attachmentInput && attachmentInput.files && attachmentInput.files.length > 0;
            if (!message && !hasAttachment) {
                return;
            }

            const submitButton = form.querySelector('button[type="submit"]');
            if (submitButton) {
                submitButton.disabled = true;
            }

            try {
                const payload = new FormData(form);
                payload.set('message', message);
                if (!hasAttachment) {
                    payload.delete('attachment');
                }

                await request(`/tickets/${ticketId}/messages`, {
                    method: 'POST',
                    body: payload,
                });

                input.value = '';
                if (attachmentInput) {
                    attachmentInput.value = '';
                }
                fetchMessages();
                showToast('Mensagem enviada.');
            } catch (error) {
                showToast(error?.data?.error || 'Não foi possível enviar a mensagem.', 'error');
            } finally {
                if (submitButton) {
                    submitButton.disabled = false;
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
                showToast('Template enviado.');
                fetchMessages();
            }).catch((error) => {
                showToast(error?.data?.error || 'Não foi possível enviar o template.', 'error');
            }).finally(() => {
                templateButton.disabled = false;
            });

            return;
        }
    });

    if (refreshButton) {
        refreshButton.addEventListener('click', fetchMessages);
    }

    if (resolveButton) {
        resolveButton.addEventListener('click', async () => {
            const confirmed = await confirmAction('Deseja finalizar este atendimento?', 'Sim, finalizar');
            if (!confirmed) {
                return;
            }
            try {
                const data = await request(`/tickets/${ticketId}/resolve`, { method: 'POST' });
                showToast(data?.message || 'Chamado resolvido.');
                if (statusBadge) {
                    statusBadge.textContent = 'resolved';
                    statusBadge.className = 'badge bg-success text-light text-capitalize';
                }
                if (data?.redirect) {
                    window.location.assign(data.redirect);
                }
            } catch (error) {
                showToast('Não foi possível finalizar o atendimento.', 'error');
            }
        });
    }

    fetchMessages();
    setInterval(fetchMessages, 5000);

    if (attachButton && attachmentInput) {
        attachButton.addEventListener('click', () => attachmentInput.click());
        attachmentInput.addEventListener('change', () => {
            if (attachmentInput.files && attachmentInput.files.length > 0) {
                showToast(`Arquivo selecionado: ${attachmentInput.files[0].name}`, 'info');
            }
        });
    }

    if (profileAvatar) {
        loadProfileAvatar(profileAvatar);
    }
}
