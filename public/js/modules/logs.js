import { request, showToast, initTable } from '/js/app.js';

const escapeHtml = (value) => {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
};

const parseContext = (value) => {
    if (!value) {
        return null;
    }
    try {
        return JSON.parse(value);
    } catch (error) {
        return value;
    }
};

const renderMetrics = (container, metrics = []) => {
    if (!container) {
        return;
    }
    const map = new Map(metrics.map((item) => [item.label, item.total]));
    container.querySelectorAll('[data-metric-level]').forEach((card) => {
        const key = card.getAttribute('data-metric-level');
        if (!key) {
            return;
        }
        const value = map.get(key) ?? 0;
        const target = card.querySelector('.metric-value');
        if (target) {
            target.textContent = value;
        }
    });
};

const renderList = (container, items = []) => {
    if (!container) {
        return;
    }
    container.innerHTML = items.length
        ? items.map((item) => `
            <li>
                <span>${escapeHtml(item.label ?? '')}</span>
                <span class="badge badge--neutral">${escapeHtml(String(item.total ?? 0))}</span>
            </li>`).join('')
        : '<li class="text-muted">Sem dados.</li>';
};

const renderTimeline = (container, buckets = []) => {
    if (!container) {
        return;
    }
    if (!buckets.length) {
        container.innerHTML = '<p class="text-muted">Sem dados recentes.</p>';
        return;
    }
    container.innerHTML = `
        <ul>
            ${buckets.map((bucket) => `
                <li data-bucket="${escapeHtml(bucket.bucket ?? '')}" data-total="${escapeHtml(String(bucket.total ?? 0))}">
                    <span>${escapeHtml(new Date(bucket.bucket ?? Date.now()).toLocaleTimeString('pt-BR', { hour: '2-digit' }))}</span>
                    <div class="bar" style="--value: ${Math.max(1, bucket.total ?? 0)}"></div>
                </li>`).join('')}
        </ul>`;
};

const renderTable = (table, logs = []) => {
    if (!table) {
        return;
    }
    const tbody = table.querySelector('tbody');
    if (!tbody) {
        return;
    }
    tbody.innerHTML = logs.map((log) => {
        const service = (log.action ?? '').split('.', 1)[0] || 'sistema';
        return `
            <tr data-log-row data-log-context='${escapeHtml(JSON.stringify(log.context ?? null))}'>
                <td>${escapeHtml(new Date(log.created_at ?? Date.now()).toLocaleString('pt-BR'))}</td>
                <td><span class="status-badge status-badge--${escapeHtml(log.level ?? 'info')}">${escapeHtml(log.level ?? 'info')}</span></td>
                <td>${escapeHtml(service)}</td>
                <td>${escapeHtml(log.action ?? '')}</td>
                <td>${escapeHtml(log.message ?? '')}</td>
                <td>${escapeHtml(log.user_name ?? 'Sistema')}</td>
                <td>${escapeHtml(log.ip_address ?? '-')}</td>
                <td class="text-end">
                    <button class="btn btn-sm btn-outline-secondary" data-log-details>
                        <i class="bi bi-eye"></i>
                    </button>
                </td>
            </tr>`;
    }).join('');
};

const gatherFilters = (form) => {
    const formData = new FormData(form);
    const params = new URLSearchParams();
    formData.forEach((value, key) => {
        if (value) {
            params.set(key, String(value));
        }
    });
    params.set('format', 'json');
    return params;
};

export function initLogViewer(selector, endpoint) {
    const container = document.querySelector(selector);
    if (!container) {
        return;
    }
    const form = container.querySelector('[data-log-filters]');
    const table = container.querySelector('[data-log-table]');
    const metrics = container.querySelector('[data-log-metrics]') || container.querySelector('.workspace-metrics');
    const servicesList = container.querySelector('[data-log-services]');
    const actionsList = container.querySelector('[data-log-actions]');
    const timeline = container.querySelector('[data-log-timeline]');
    const refreshButton = container.querySelector('[data-log-refresh]');
    const resetButton = container.querySelector('[data-log-reset]');
    const detailPanel = container.querySelector('[data-log-detail]');
    const detailContent = container.querySelector('[data-log-json]');
    const liveToggle = container.querySelector('[data-live-tail-toggle]');
    let liveInterval = null;

    const closeDetail = () => {
        detailPanel?.setAttribute('hidden', 'true');
        if (detailContent) {
            detailContent.textContent = '';
        }
    };

    const renderDetail = (context) => {
        if (!detailPanel || !detailContent) {
            return;
        }
        detailPanel.removeAttribute('hidden');
        detailContent.textContent = JSON.stringify(context, null, 2);
    };

    container.addEventListener('click', (event) => {
        const detailsButton = event.target instanceof HTMLElement ? event.target.closest('[data-log-details]') : null;
        if (detailsButton) {
            const row = detailsButton.closest('[data-log-row]');
            const payload = parseContext(row?.getAttribute('data-log-context'));
            renderDetail(payload);
            return;
        }
        if (event.target instanceof HTMLElement && event.target.closest('[data-log-close]')) {
            closeDetail();
        }
        if (event.target instanceof HTMLElement && event.target.closest('[data-log-copy]')) {
            if (!detailContent?.textContent) {
                return;
            }
            navigator.clipboard?.writeText(detailContent.textContent).then(() => {
                showToast('Detalhes copiados para a área de transferência.');
            });
        }
    });

    const fetchLogs = async () => {
        if (!form) {
            return;
        }
        const params = gatherFilters(form);
        try {
            const data = await request(`${endpoint}?${params.toString()}`, { method: 'GET' });
            const logs = data?.logs ?? [];
            renderTable(table, logs);
            initTable(table);
            renderMetrics(metrics, data?.aggregates?.levels ?? []);
            renderList(servicesList, data?.aggregates?.services ?? []);
            renderList(actionsList, data?.aggregates?.actions ?? []);
            renderTimeline(timeline, data?.aggregates?.timeline ?? []);
        } catch (error) {
            showToast('Não foi possível carregar os logs.', 'error');
        }
    };

    form?.addEventListener('submit', (event) => {
        event.preventDefault();
        fetchLogs();
    });

    resetButton?.addEventListener('click', () => {
        form?.reset();
        fetchLogs();
    });

    refreshButton?.addEventListener('click', fetchLogs);

    liveToggle?.addEventListener('click', () => {
        if (liveInterval) {
            clearInterval(liveInterval);
            liveInterval = null;
            liveToggle.classList.remove('btn-primary');
            liveToggle.classList.add('btn-outline-secondary');
            return;
        }
        liveToggle.classList.remove('btn-outline-secondary');
        liveToggle.classList.add('btn-primary');
        liveInterval = setInterval(fetchLogs, 5000);
        fetchLogs();
    });

    fetchLogs();
}
