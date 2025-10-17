import { request, showToast, initTable } from '/js/app.js';

export function initDashboard(selector) {
    const root = document.querySelector(selector);
    if (!root) {
        return;
    }
    const queueTable = root.querySelector('#queue-table');
    const recentTable = root.querySelector('#recent-tickets');

    const renderSummary = (summary = {}) => {
        root.querySelectorAll('[data-kpi]').forEach((card) => {
            const key = card.getAttribute('data-kpi');
            const value = summary[key] ?? '--';
            const target = card.querySelector('.display-6');
            if (target) {
                target.textContent = value === null ? '--' : value;
            }
        });
    };

    const renderRecent = (tickets = []) => {
        if (!recentTable) {
            return;
        }
        const tbody = recentTable.querySelector('tbody');
        if (!tbody) {
            return;
        }
        tbody.innerHTML = tickets.map((ticket) => `
            <tr>
                <td>#${ticket.id}</td>
                <td>${escapeHtml(ticket.contact_name)}</td>
                <td><span class="badge bg-secondary text-capitalize">${escapeHtml(ticket.status)}</span></td>
                <td>${escapeHtml(ticket.channel)}</td>
                <td>${ticket.opened_at ? new Date(ticket.opened_at).toLocaleString('pt-BR') : ''}</td>
            </tr>`).join('');
        initTable(recentTable);
    };

    const renderQueue = (queue = []) => {
        if (!queueTable) {
            return;
        }
        const tbody = queueTable.querySelector('tbody');
        if (!tbody) {
            return;
        }
        tbody.innerHTML = queue.map((ticket) => `
            <tr>
                <td>#${ticket.id}</td>
                <td>${escapeHtml(ticket.contact_name)}</td>
                <td>${escapeHtml(ticket.channel)}</td>
                <td>${ticket.opened_at ? new Date(ticket.opened_at).toLocaleString('pt-BR') : ''}</td>
            </tr>`).join('');
        initTable(queueTable);
    };

    const renderChannels = (channels = []) => {
        const container = root.querySelector('#channel-list');
        if (!container) {
            return;
        }
        container.innerHTML = channels.map((channel) => `
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="text-capitalize fw-medium"><i class="bi bi-broadcast me-2 text-primary"></i>${escapeHtml(channel.channel)}</span>
                <span class="badge bg-primary-subtle text-primary fw-semibold">${channel.total}</span>
            </div>`).join('') || '<p class="text-muted mb-0">Nenhum dado disponível.</p>';
    };

    const renderLeaderboard = (leaderboard = []) => {
        const container = root.querySelector('#leaderboard ol');
        if (!container) {
            return;
        }
        container.innerHTML = leaderboard.map((agent) => `
            <li class="mb-2">
                <strong>${escapeHtml(agent.full_name)}</strong>
                <span class="badge bg-success-subtle text-success fw-semibold ms-2"><i class="bi bi-award"></i> ${agent.resolved}</span>
            </li>`).join('') || '<li class="text-muted">Ainda não há atendentes com chamados encerrados.</li>';
    };

    const refresh = async () => {
        try {
            const data = await request('/admin', { method: 'GET' });
            renderSummary(data.summary);
            renderRecent(data.recentTickets);
            renderQueue(data.queue);
            renderChannels(data.channels);
            renderLeaderboard(data.leaderboard);
        } catch (error) {
            showToast('Não foi possível atualizar o dashboard.', 'error');
        }
    };

    const escapeHtml = (value) => {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    };

    refresh();
    setInterval(refresh, 15000);
}
