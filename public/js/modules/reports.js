import { request, showToast, initTable } from '/js/app.js';

const formatSeconds = (value) => {
    if (value === null || value === undefined || Number.isNaN(value)) {
        return '—';
    }
    const seconds = Number(value);
    if (seconds <= 0) {
        return `${Math.round(seconds)}s`;
    }
    const minutes = Math.floor(seconds / 60);
    const remaining = Math.round(seconds % 60);
    if (minutes <= 0) {
        return `${remaining}s`;
    }
    return `${minutes}min ${remaining.toString().padStart(2, '0')}s`;
};

const sum = (items, key) => items.reduce((total, row) => total + Number.parseInt(row?.[key] ?? 0, 10), 0);

const avg = (items, key) => {
    const values = items
        .map((row) => {
            const value = row?.[key];
            if (value === null || value === undefined) {
                return null;
            }
            const number = Number(value);
            return Number.isFinite(number) ? number : null;
        })
        .filter((value) => value !== null);
    if (values.length === 0) {
        return null;
    }
    return values.reduce((total, value) => total + value, 0) / values.length;
};

const escapeHtml = (value) => {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
};

export function initReports(selector) {
    const container = document.querySelector(selector);
    if (!container) {
        return;
    }

    const endpoint = container.getAttribute('data-report-endpoint');
    const exportEndpoint = container.getAttribute('data-report-export');
    const form = container.querySelector('[data-report-form]');
    const metrics = container.querySelector('[data-report-metrics]');
    const table = container.querySelector('[data-report-table]');
    const timelineContainer = container.querySelector('[data-report-timeline]');
    const exportButton = container.querySelector('[data-report-export-button]');
    const groupSelect = form?.querySelector('#report-group');
    const submitButton = form?.querySelector('[type="submit"]');
    const initialSummary = (() => {
        const raw = container.getAttribute('data-initial-summary');
        try {
            const parsed = JSON.parse(raw || '[]');
            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            return [];
        }
    })();
    const defaultParams = form ? new URLSearchParams(new FormData(form)) : new URLSearchParams();

    const updateMetrics = (data) => {
        if (!metrics) {
            return;
        }
        const opened = sum(data, 'opened');
        const closed = sum(data, 'closed');
        const avgQueueSeconds = avg(data, 'avg_queue_seconds');
        const avgResolutionSeconds = avg(data, 'avg_resolution_seconds');

        const updateCard = (selector, value) => {
            const card = metrics.querySelector(`[data-metric="${selector}"] .metric-value`);
            if (!card) {
                return;
            }
            if (selector === 'opened' || selector === 'closed') {
                card.textContent = Number.isFinite(value) ? value.toLocaleString('pt-BR') : '0';
            } else {
                card.textContent = formatSeconds(value);
            }
        };

        updateCard('opened', opened);
        updateCard('closed', closed);
        updateCard('avg_queue', avgQueueSeconds);
        updateCard('avg_resolution', avgResolutionSeconds);
    };

    const renderTimeline = (data) => {
        if (!timelineContainer) {
            return;
        }
        if (!Array.isArray(data) || data.length === 0) {
            timelineContainer.innerHTML = '<p class="text-muted">Nenhum dado encontrado para o período selecionado.</p>';
            return;
        }
        const items = data
            .map((row) => {
                const opened = Number.parseInt(row?.opened ?? 0, 10) || 0;
                const closed = Number.parseInt(row?.closed ?? 0, 10) || 0;
                const date = escapeHtml(row?.date ?? '');
                return `
                    <li data-date="${date}" data-opened="${opened}" data-closed="${closed}">
                        <span class="report-timeline__date">${date}</span>
                        <div class="report-timeline__bars">
                            <span class="bar bar--opened" style="--value: ${Math.max(opened, 1)}"></span>
                            <span class="bar bar--closed" style="--value: ${Math.max(closed, 1)}"></span>
                        </div>
                    </li>
                `;
            })
            .join('');
        timelineContainer.innerHTML = `<ul class="report-timeline">${items}</ul>`;
    };

    const renderTable = (data) => {
        if (!table) {
            return;
        }
        const tbody = table.querySelector('tbody');
        if (!tbody) {
            return;
        }
        const rows = Array.isArray(data)
            ? data
                  .map((row) => {
                      const date = escapeHtml(row?.date ?? '');
                      const opened = Number.parseInt(row?.opened ?? 0, 10) || 0;
                      const closed = Number.parseInt(row?.closed ?? 0, 10) || 0;
                      return `
                        <tr>
                            <td>${date}</td>
                            <td>${opened}</td>
                            <td>${closed}</td>
                            <td>${escapeHtml(formatSeconds(row?.avg_queue_seconds ?? null))}</td>
                            <td>${escapeHtml(formatSeconds(row?.avg_resolution_seconds ?? null))}</td>
                        </tr>`;
                  })
                  .join('')
            : '';
        tbody.innerHTML = rows;
        initTable(table);
    };

    const updateExportLink = (params) => {
        if (!exportButton || !exportEndpoint) {
            return;
        }
        const query = params.toString();
        const href = `${exportEndpoint}${query ? `?${query}` : ''}`;
        if (exportButton.dataset.disabledHref !== undefined) {
            exportButton.dataset.disabledHref = href;
        } else {
            exportButton.setAttribute('href', href);
        }
    };

    const setExportLoading = (isLoading) => {
        if (!exportButton) {
            return;
        }
        if (isLoading) {
            if (exportButton.dataset.disabledHref === undefined) {
                exportButton.dataset.disabledHref = exportButton.getAttribute('href') || '';
            }
            exportButton.removeAttribute('href');
            exportButton.classList.add('is-loading');
            exportButton.setAttribute('aria-disabled', 'true');
        } else {
            if (exportButton.dataset.disabledHref !== undefined) {
                const restored = exportButton.dataset.disabledHref || '';
                if (restored) {
                    exportButton.setAttribute('href', restored);
                }
                delete exportButton.dataset.disabledHref;
            }
            exportButton.classList.remove('is-loading');
            exportButton.setAttribute('aria-disabled', 'false');
        }
    };

    const setLoading = (isLoading) => {
        if (form) {
            form.classList.toggle('is-loading', isLoading);
        }
        if (submitButton) {
            submitButton.disabled = isLoading;
        }
        setExportLoading(isLoading);
    };

    const render = (data, params = defaultParams, options = {}) => {
        const rows = Array.isArray(data) ? data : [];
        updateMetrics(rows);
        renderTimeline(rows);
        renderTable(rows);
        updateExportLink(params);
        if (!options.silent && rows.length === 0) {
            showToast('Nenhum dado encontrado para o período informado.', 'info');
        }
    };

    render(initialSummary, defaultParams, { silent: true });

    const syncGroupOptions = (groups = []) => {
        if (!groupSelect) {
            return;
        }
        const selected = new Set(Array.from(groupSelect.selectedOptions).map((option) => option.value));
        if (!Array.isArray(groups) || groups.length === 0) {
            groupSelect.innerHTML = '';
            return;
        }
        groupSelect.innerHTML = groups
            .map((group) => {
                const id = String(group?.id ?? '');
                const label = group?.name ?? id;
                const isSelected = selected.has(id);
                return `<option value="${escapeHtml(id)}"${isSelected ? ' selected' : ''}>${escapeHtml(label)}</option>`;
            })
            .join('');
    };

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const formData = new FormData(form);
        const params = new URLSearchParams(formData);
        setLoading(true);
        try {
            const query = params.toString();
            const response = await request(`${endpoint}?${query}`, { method: 'GET' });
            if (response?.groups) {
                syncGroupOptions(response.groups);
            }
            if (response.from && form.elements.namedItem('from')) {
                form.elements.namedItem('from').value = response.from;
            }
            if (response.to && form.elements.namedItem('to')) {
                form.elements.namedItem('to').value = response.to;
            }
            const rows = Array.isArray(response?.summary) ? response.summary : [];
            const hasData = rows.length > 0;
            render(rows, params, { silent: hasData });
        } catch (error) {
            const message = error?.message || 'Não foi possível carregar os relatórios.';
            showToast(message, 'error');
        }
        setLoading(false);
    });

    form?.addEventListener('reset', (event) => {
        event.preventDefault();
        form.reset();
        const params = form ? new URLSearchParams(new FormData(form)) : new URLSearchParams();
        render(initialSummary, params, { silent: true });
    });
}
