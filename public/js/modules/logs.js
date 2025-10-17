import { request, showToast, initTable } from '/js/app.js';

const escapeHtml = (value) => {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
};

export function initLogViewer(selector, endpoint) {
    const container = document.querySelector(selector);
    if (!container) {
        return;
    }
    const select = container.querySelector('[data-filter]');
    const table = container.querySelector('#logs-table');

    const fetchLogs = async () => {
        if (!table) {
            return;
        }
        const level = select?.value || '';
        try {
            const data = await request(`${endpoint}?level=${encodeURIComponent(level)}`, { method: 'GET' });
            if (!data?.logs) {
                return;
            }
            const tbody = table.querySelector('tbody');
            if (!tbody) {
                return;
            }
            tbody.innerHTML = data.logs.map((log) => `
                <tr>
                    <td>${log.created_at ? new Date(log.created_at).toLocaleString('pt-BR') : ''}</td>
                    <td><span class="badge bg-secondary text-uppercase">${escapeHtml(log.level)}</span></td>
                    <td>${escapeHtml(log.action)}</td>
                    <td>${escapeHtml(log.message)}</td>
                    <td>${escapeHtml(log.user_name || 'Sistema')}</td>
                    <td>${escapeHtml(log.ip_address || '-')}</td>
                </tr>`).join('');
            initTable(table);
        } catch (error) {
            showToast('Não foi possível carregar os logs.', 'error');
        }
    };

    if (select) {
        select.addEventListener('change', fetchLogs);
    }
    fetchLogs();
}
