import { initTable, request, showToast } from '/js/app.js';

const escapeHtml = (value) => {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
};

const renderChatRow = (chat) => {
    const name = chat?.name ? escapeHtml(chat.name) : escapeHtml(chat?.id ?? '');
    const unread = Number.isFinite(chat?.unread) ? chat.unread : parseInt(chat?.unread ?? 0, 10) || 0;
    const created = chat?.created_at ? escapeHtml(chat.created_at) : '—';
    const lastMessage = chat?.last_message_at ? escapeHtml(chat.last_message_at) : '—';
    const badge = chat?.opened_today ? '<span class="badge bg-success-subtle text-success mt-1">Iniciado hoje</span>' : '';
    const unreadBadge = unread > 0
        ? `<span class="badge text-bg-warning text-dark">${unread}</span>`
        : '<span class="text-muted">0</span>';
    const raw = escapeHtml(JSON.stringify(chat?.raw ?? {}, null, 2));

    return `
        <tr>
            <td>
                <strong>${name || escapeHtml(chat?.id ?? '')}</strong>
                <div class="text-muted small">ID: ${escapeHtml(chat?.id ?? '')}</div>
                ${badge}
            </td>
            <td>${unreadBadge}</td>
            <td>${created}</td>
            <td>${lastMessage}</td>
            <td>
                <details>
                    <summary class="text-primary">Ver JSON</summary>
                    <pre class="small bg-body-secondary p-3 rounded border mt-2 text-break">${raw}</pre>
                </details>
            </td>
        </tr>
    `;
};

export function initEvolutionChats(selector, endpoint) {
    const container = document.querySelector(selector);
    if (!container) {
        return;
    }

    const table = container.querySelector('#evolution-chats-table');
    const refreshButton = container.querySelector('[data-refresh-chats]');
    const alertStack = container.querySelector('.alert-stack');

    const render = (data) => {
        if (!table) {
            return;
        }
        const tbody = table.querySelector('tbody');
        if (!tbody) {
            return;
        }
        const rows = Array.isArray(data) ? data : [];
        tbody.innerHTML = rows.map((chat) => renderChatRow(chat)).join('');
        initTable(table);
    };

    const setError = (message) => {
        if (!alertStack) {
            return;
        }
        alertStack.innerHTML = message
            ? `<div class="alert alert-danger shadow-sm" role="alert">${escapeHtml(message)}</div>`
            : '';
    };

    const fetchChats = async () => {
        try {
            const data = await request(endpoint, { method: 'GET' });
            if (data?.error) {
                setError(data.error);
                showToast(data.error, 'error');
            } else {
                setError('');
            }
            render(data?.chats ?? []);
        } catch (error) {
            const message = error?.data?.error || error?.message || 'Não foi possível consultar a Evolution.';
            setError(message);
            showToast(message, 'error');
        }
    };

    if (refreshButton) {
        refreshButton.addEventListener('click', fetchChats);
    }

    fetchChats();
    setInterval(fetchChats, 15000);
}
