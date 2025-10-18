import { request, showToast, confirmAction, initTable } from '/js/app.js';

const escapeHtml = (value) => {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
};

const renderOpenedAt = (ticket) => {
    const opened = ticket?.opened_at ? escapeHtml(ticket.opened_at) : '';
    if (!ticket?.opened_today) {
        return opened;
    }
    return `${opened} <span class="badge bg-success-subtle text-success ms-1">Hoje</span>`;
};

const renderNativeChatRow = (chat) => {
    const remoteId = escapeHtml(chat?.id ?? '');
    const name = chat?.name ? escapeHtml(chat.name) : remoteId;
    const badge = chat?.opened_today
        ? '<span class="badge bg-success-subtle text-success ms-1">Hoje</span>'
        : '';
    const unreadCount = Number.isFinite(chat?.unread) ? chat.unread : parseInt(chat?.unread ?? 0, 10) || 0;
    const unreadBadge = unreadCount > 0
        ? `<span class="badge text-bg-warning text-dark">${unreadCount}</span>`
        : '<span class="text-muted">0</span>';
    const lastMessage = chat?.last_message_at ? escapeHtml(chat.last_message_at) : '—';
    const contactName = escapeHtml(chat?.name ?? '');

    return `
        <tr>
            <td>
                <strong>${name}</strong>
                <div class="text-muted small">ID: ${remoteId}</div>
                ${badge}
            </td>
            <td>${unreadBadge}</td>
            <td>${lastMessage}</td>
            <td class="text-end">
                <button class="btn btn-sm btn-primary" data-start-native data-remote="${remoteId}" data-name="${contactName}">
                    <i class="bi bi-chat-dots"></i> Iniciar conversa
                </button>
            </td>
        </tr>
    `;
};

export function initQueue(selector, endpoint, nativeConfig = {}) {
    const container = document.querySelector(selector);
    if (!container) {
        return;
    }

    const table = container.querySelector('#queue-table');
    const refreshButton = container.querySelector('[data-refresh-queue]');
    const nativeContainer = container.querySelector('[data-native-chats]');
    const nativeBody = nativeContainer?.querySelector('[data-native-body]');
    const nativeError = nativeContainer?.querySelector('[data-native-error]');
    const nativeCount = nativeContainer?.querySelector('[data-native-count]');

    const nativeState = {
        enabled: !!(nativeConfig?.enabled),
        chats: Array.isArray(nativeConfig?.chats) ? nativeConfig.chats : [],
        error: nativeConfig?.error ?? null,
        startEndpoint: nativeConfig?.startEndpoint || '/tickets/native/start',
    };

    const renderNative = () => {
        if (!nativeContainer) {
            return;
        }

        if (!nativeState.enabled) {
            nativeContainer.classList.add('d-none');
            return;
        }

        nativeContainer.classList.remove('d-none');

        if (nativeError) {
            if (nativeState.error) {
                nativeError.textContent = nativeState.error;
                nativeError.classList.remove('d-none');
            } else {
                nativeError.textContent = '';
                nativeError.classList.add('d-none');
            }
        }

        if (nativeCount) {
            nativeCount.textContent = `Disponíveis: ${nativeState.chats.length}`;
        }

        if (!nativeBody) {
            return;
        }

        if (!nativeState.chats.length) {
            nativeBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Nenhuma conversa pendente.</td></tr>';
            return;
        }

        nativeBody.innerHTML = nativeState.chats.map((chat) => renderNativeChatRow(chat)).join('');
    };

    const fetchQueue = async () => {
        if (!table) {
            return;
        }
        try {
            const data = await request(endpoint, { method: 'GET' });
            const queue = data?.queue ?? [];
            const tbody = table.querySelector('tbody');
            if (!tbody) {
                return;
            }
            tbody.innerHTML = queue.map((ticket) => `
                <tr>
                    <td>#${ticket.id}</td>
                    <td>${escapeHtml(ticket.contact_name)}</td>
                    <td>${escapeHtml(ticket.channel)}</td>
                    <td><span class="badge bg-secondary text-capitalize">${escapeHtml(ticket.status)}</span></td>
                    <td>${renderOpenedAt(ticket)}</td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-success" data-assign data-ticket="${ticket.id}">
                            <i class="bi bi-headset"></i> Iniciar atendimento
                        </button>
                    </td>
                </tr>`).join('');
            initTable(table);

            if (data?.native) {
                if (typeof data.native.enabled === 'boolean') {
                    nativeState.enabled = data.native.enabled;
                }
                nativeState.chats = Array.isArray(data.native.chats) ? data.native.chats : [];
                nativeState.error = data.native.error ?? null;
                renderNative();
            }
        } catch (error) {
            showToast('Não foi possível atualizar a fila.', 'error');
        }
    };

    container.addEventListener('click', async (event) => {
        const startButton = event.target instanceof HTMLElement ? event.target.closest('[data-start-native]') : null;
        if (startButton) {
            const remoteJid = startButton.getAttribute('data-remote');
            if (!remoteJid) {
                return;
            }
            const name = startButton.getAttribute('data-name') || '';
            const confirmed = await confirmAction('Deseja iniciar uma nova conversa com este contato?', 'Sim, iniciar');
            if (!confirmed) {
                return;
            }
            try {
                const response = await request(nativeState.startEndpoint, {
                    method: 'POST',
                    body: { remote_jid: remoteJid, name },
                });
                showToast(response?.message || 'Conversa iniciada com sucesso.');
                nativeState.chats = nativeState.chats.filter((chat) => (chat?.id ?? '') !== remoteJid);
                renderNative();
                if (response?.redirect) {
                    window.location.assign(response.redirect);
                } else {
                    fetchQueue();
                }
            } catch (error) {
                showToast(error?.data?.error || error?.message || 'Não foi possível iniciar a conversa.', 'error');
            }
            return;
        }

        const button = event.target instanceof HTMLElement ? event.target.closest('[data-assign]') : null;
        if (!button) {
            return;
        }
        const ticketId = button.getAttribute('data-ticket');
        if (!ticketId) {
            return;
        }
        const confirmed = await confirmAction('Deseja assumir este atendimento?', 'Sim, iniciar');
        if (!confirmed) {
            return;
        }
        try {
            const data = await request(`${endpoint}/${ticketId}/assign`, { method: 'POST' });
            showToast(data?.message || 'Chamado atribuído com sucesso.');
            if (data?.redirect) {
                window.location.assign(data.redirect);
            } else {
                fetchQueue();
            }
        } catch (error) {
            showToast(error?.data?.message || 'Não foi possível assumir o atendimento.', 'error');
        }
    });

    if (refreshButton) {
        refreshButton.addEventListener('click', fetchQueue);
    }

    renderNative();
    fetchQueue();
    setInterval(fetchQueue, 10000);
}
