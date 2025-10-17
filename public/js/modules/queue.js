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

export function initQueue(selector, endpoint) {
    const container = document.querySelector(selector);
    if (!container) {
        return;
    }
    const table = container.querySelector('#queue-table');
    const refreshButton = container.querySelector('[data-refresh-queue]');

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
        } catch (error) {
            showToast('Não foi possível atualizar a fila.', 'error');
        }
    };

    container.addEventListener('click', async (event) => {
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

    fetchQueue();
    setInterval(fetchQueue, 10000);
}
