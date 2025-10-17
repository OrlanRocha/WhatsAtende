import { request, showToast, initTable } from '/js/app.js';

const escapeHtml = (value) => {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
};

export function initTicketList(selector, endpoint) {
    const container = document.querySelector(selector);
    if (!container) {
        return;
    }
    const table = container.querySelector('#tickets-table');
    const filter = container.querySelector('[data-filter]');

    const fetchTickets = async () => {
        if (!table) {
            return;
        }
        const status = filter?.value || '';
        try {
            const data = await request(`${endpoint}?status=${encodeURIComponent(status)}`, { method: 'GET' });
            if (!data?.tickets) {
                return;
            }
            const tbody = table.querySelector('tbody');
            if (!tbody) {
                return;
            }
            tbody.innerHTML = data.tickets.map((ticket) => `
                <tr>
                    <td>#${ticket.id}</td>
                    <td>${escapeHtml(ticket.contact_name)}</td>
                    <td><span class="badge bg-secondary text-capitalize">${escapeHtml(ticket.status)}</span></td>
                    <td>${escapeHtml(ticket.agent_name || 'Não atribuído')}</td>
                    <td>${escapeHtml(ticket.channel)}</td>
                    <td>${ticket.opened_at ? new Date(ticket.opened_at).toLocaleString('pt-BR') : ''}</td>
                    <td>${ticket.closed_at ? new Date(ticket.closed_at).toLocaleString('pt-BR') : '—'}</td>
                    <td><span class="badge bg-info-subtle text-info text-capitalize fw-semibold">${escapeHtml(ticket.priority)}</span></td>
                    <td class="text-end">
                        <a href="/tickets/${ticket.id}" class="btn btn-sm btn-outline-primary"><i class="bi bi-chat-dots"></i> Abrir chat</a>
                    </td>
                </tr>`).join('');
            initTable(table);
        } catch (error) {
            showToast('Não foi possível atualizar as solicitações.', 'error');
        }
    };

    if (filter) {
        filter.addEventListener('change', fetchTickets);
    }
    fetchTickets();
}
