import { request, showToast, confirmAction } from '/js/app.js';

const escapeHtml = (value) => {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
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

    const renderMessages = (messages = []) => {
        if (!chatWindow) {
            return;
        }
        chatWindow.innerHTML = messages.map((message) => {
            let bodyHtml = '';
            if (message.media_type === 'image' && message.media_url) {
                bodyHtml = `<img src="${escapeHtml(message.media_url)}" class="img-fluid rounded" alt="Imagem">`;
            } else if (message.media_type === 'audio' && message.media_url) {
                bodyHtml = `<audio controls src="${escapeHtml(message.media_url)}"></audio>`;
            } else {
                bodyHtml = escapeHtml(message.body || '').replace(/\n/g, '<br>');
            }
            const time = message.sent_at ? new Date(message.sent_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) : '';
            const agent = message.agent_name ? ` · ${escapeHtml(message.agent_name)}` : '';
            return `
                <div class="message ${message.sender_type === 'agent' ? 'agent' : 'contact'}">
                    <div class="bubble">
                        ${bodyHtml}
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
            if (!message) {
                return;
            }
            try {
                const payload = new FormData();
                payload.append('message', message);
                await request(`/tickets/${ticketId}/messages`, {
                    method: 'POST',
                    body: payload,
                });
                input.value = '';
                fetchMessages();
                showToast('Mensagem enviada.');
            } catch (error) {
                showToast(error?.data?.error || 'Não foi possível enviar a mensagem.', 'error');
            }
        });
    }

    container.addEventListener('click', (event) => {
        const templateButton = event.target instanceof HTMLElement ? event.target.closest('[data-insert-template]') : null;
        if (templateButton && input) {
            const body = templateButton.closest('[data-template-body]')?.getAttribute('data-template-body') || '';
            input.value = body;
            input.focus();
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
}
