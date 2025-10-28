import { request, showToast, initTable } from '/js/app.js';

const escapeHtml = (value) => {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
};

const FOCUSABLE_SELECTOR = 'a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])';

const parseContext = (value) => {
    if (value === null || value === undefined) {
        return null;
    }
    if (typeof value === 'object') {
        return value;
    }
    if (typeof value === 'string' && value !== '') {
        try {
            return JSON.parse(value);
        } catch (error) {
            return value;
        }
    }
    return value;
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

const normalizeLog = (log = {}) => {
    const service = log.service && log.service !== ''
        ? log.service
        : ((log.action ?? '').split('.', 1)[0] || 'sistema');
    return {
        id: Number(log.id ?? 0),
        level: log.level ?? 'info',
        service,
        action: log.action ?? '',
        message: log.message ?? '',
        route: log.route ?? '',
        corr_id: log.corr_id ?? '',
        user_name: log.user_name ?? 'Sistema',
        ip_address: log.ip_address ?? '-',
        actor_id: log.actor_id ?? null,
        created_at: log.created_at ?? new Date().toISOString(),
        context: parseContext(log.context ?? null),
    };
};

const renderTable = (table, logs = []) => {
    if (!table) {
        return;
    }
    const tbody = table.querySelector('tbody');
    if (!tbody) {
        return;
    }
    tbody.innerHTML = logs.map((log, index) => `
        <tr data-log-row data-log-index="${index}">
            <td>${escapeHtml(new Date(log.created_at ?? Date.now()).toLocaleString('pt-BR'))}</td>
            <td><span class="status-badge status-badge--${escapeHtml(log.level ?? 'info')}">${escapeHtml(log.level ?? 'info')}</span></td>
            <td>${escapeHtml(log.service ?? '')}</td>
            <td>${escapeHtml(log.action ?? '')}</td>
            <td>${escapeHtml(log.message ?? '')}</td>
            <td><code>${escapeHtml(log.route ?? '')}</code></td>
            <td><code class="text-muted">${escapeHtml(log.corr_id ?? '')}</code></td>
            <td>${escapeHtml(log.user_name ?? 'Sistema')}</td>
            <td>${escapeHtml(log.ip_address ?? '-')}</td>
            <td class="text-end">
                <button class="btn btn-sm btn-outline-secondary" data-log-details>
                    <i class="bi bi-eye"></i>
                </button>
            </td>
        </tr>`).join('');
};

const gatherFilters = (form, options = {}) => {
    const { includeFormat = true } = options;
    const params = new URLSearchParams();
    if (!form) {
        return params;
    }
    const formData = new FormData(form);
    formData.forEach((value, key) => {
        if (value) {
            params.set(key, String(value));
        }
    });
    if (includeFormat) {
        params.set('format', 'json');
    }
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
    const modal = container.querySelector('[data-log-modal]');
    const modalDialog = modal?.querySelector('.modal-dialog') ?? null;
    const detailContent = container.querySelector('[data-log-json]');
    const liveToggle = container.querySelector('[data-live-tail-toggle]');

    let logsData = [];
    let lastLogId = 0;
    let liveEnabled = false;
    let eventSource = null;
    let fallbackInterval = null;
    let modalHideTimeout = null;
    let focusableElements = [];
    let lastFocusedElement = null;

    const closeDetail = () => {
        if (!modal) {
            return;
        }

        modal.classList.remove('is-visible');
        if (modalHideTimeout) {
            clearTimeout(modalHideTimeout);
        }

        modalHideTimeout = window.setTimeout(() => {
            modal?.setAttribute('hidden', 'true');
        }, 200);

        if (detailContent) {
            detailContent.textContent = '';
        }

        if (lastFocusedElement instanceof HTMLElement) {
            lastFocusedElement.focus({ preventScroll: true });
        }

        focusableElements = [];
    };

    const openDetail = () => {
        if (!modal) {
            return;
        }

        if (modalHideTimeout) {
            clearTimeout(modalHideTimeout);
            modalHideTimeout = null;
        }

        lastFocusedElement = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        modal.removeAttribute('hidden');

        window.requestAnimationFrame(() => {
            modal.classList.add('is-visible');
        });

        const nodes = modal.querySelectorAll(FOCUSABLE_SELECTOR);
        focusableElements = Array.from(nodes).filter((node) => node instanceof HTMLElement && !node.hasAttribute('disabled'));

        const firstFocusable = focusableElements[0] ?? modalDialog ?? modal;
        if (firstFocusable instanceof HTMLElement) {
            firstFocusable.focus({ preventScroll: true });
        }
    };

    const trapFocus = (event) => {
        if (!modal?.classList.contains('is-visible') || event.key !== 'Tab' || focusableElements.length === 0) {
            return;
        }

        const first = focusableElements[0];
        const last = focusableElements[focusableElements.length - 1];

        if (event.shiftKey) {
            if (document.activeElement === first) {
                event.preventDefault();
                last.focus();
            }
        } else if (document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    };

    modal?.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            closeDetail();
            return;
        }
        trapFocus(event);
    });

    const renderDetail = (entry) => {
        if (!entry || !modal || !detailContent) {
            return;
        }
        const payload = {
            id: entry.id,
            level: entry.level,
            service: entry.service,
            action: entry.action,
            route: entry.route,
            corr_id: entry.corr_id,
            actor_id: entry.actor_id,
            ip_address: entry.ip_address,
            created_at: entry.created_at,
            message: entry.message,
            context: entry.context,
        };
        openDetail();
        detailContent.textContent = JSON.stringify(payload, null, 2);
    };

    const updateAggregates = (data) => {
        renderMetrics(metrics, data?.levels ?? []);
        renderList(servicesList, data?.services ?? []);
        renderList(actionsList, data?.actions ?? []);
        renderTimeline(timeline, data?.timeline ?? []);
    };

    const normaliseLogs = (logs = []) => logs.map((log) => normalizeLog(log));

    const setLogs = (logs = []) => {
        logsData = normaliseLogs(logs);
        lastLogId = logsData.reduce((max, log) => Math.max(max, Number(log.id ?? 0)), lastLogId);
        renderTable(table, logsData);
        initTable(table);
    };

    const mergeLogs = (logs = []) => {
        if (!Array.isArray(logs) || logs.length === 0) {
            return;
        }
        const map = new Map();
        logsData.forEach((log) => {
            map.set(log.id, log);
        });
        normaliseLogs(logs).forEach((log) => {
            map.set(log.id, log);
            lastLogId = Math.max(lastLogId, Number(log.id ?? 0));
        });
        logsData = Array.from(map.values()).sort((a, b) => {
            const dateA = new Date(a.created_at ?? 0).getTime();
            const dateB = new Date(b.created_at ?? 0).getTime();
            return dateB - dateA;
        });
        if (logsData.length > 250) {
            logsData = logsData.slice(0, 250);
        }
        renderTable(table, logsData);
        initTable(table);
    };

    const stopFallback = () => {
        if (fallbackInterval) {
            clearInterval(fallbackInterval);
            fallbackInterval = null;
        }
    };

    const startFallback = () => {
        stopFallback();
        fallbackInterval = setInterval(() => {
            fetchLogs();
        }, 15000);
    };

    const teardownEventSource = (withFallback = false) => {
        if (eventSource) {
            eventSource.close();
            eventSource = null;
        }
        if (withFallback) {
            startFallback();
        }
    };

    const openEventSource = () => {
        if (!window.EventSource) {
            showToast('Atualização em tempo real não é suportada neste navegador.', 'warning');
            startFallback();
            return;
        }
        const params = gatherFilters(form, { includeFormat: false });
        if (lastLogId > 0) {
            params.set('last_id', String(lastLogId));
        }
        const url = new URL('/api/logs/live', window.location.origin);
        params.forEach((value, key) => {
            url.searchParams.set(key, value);
        });

        teardownEventSource(false);
        stopFallback();

        eventSource = new EventSource(url.toString());
        eventSource.addEventListener('log', (event) => {
            try {
                const payload = JSON.parse(event.data ?? '{}');
                if (Array.isArray(payload.logs)) {
                    mergeLogs(payload.logs);
                }
                if (typeof payload.last_id === 'number') {
                    lastLogId = Math.max(lastLogId, payload.last_id);
                }
            } catch (error) {
                // Ignore malformed payloads
            }
        });
        eventSource.addEventListener('aggregates', (event) => {
            try {
                const payload = JSON.parse(event.data ?? '{}');
                updateAggregates(payload);
            } catch (error) {
                // Ignore malformed payloads
            }
        });
        eventSource.addEventListener('close', () => {
            teardownEventSource(false);
            if (liveEnabled) {
                startFallback();
            }
        });
        eventSource.onerror = () => {
            teardownEventSource(true);
            showToast('Live tail pausado por instabilidade. Voltando ao modo de atualização periódica.', 'warning');
        };
    };

    const enableLive = () => {
        liveEnabled = true;
        if (liveToggle) {
            liveToggle.classList.remove('btn-outline-secondary');
            liveToggle.classList.add('btn-primary');
        }
        openEventSource();
    };

    const disableLive = () => {
        liveEnabled = false;
        if (liveToggle) {
            liveToggle.classList.remove('btn-primary');
            liveToggle.classList.add('btn-outline-secondary');
        }
        teardownEventSource(false);
        stopFallback();
    };

    const restartLive = () => {
        if (!liveEnabled) {
            return;
        }
        openEventSource();
    };

    const fetchLogs = async () => {
        const params = gatherFilters(form, { includeFormat: true });
        try {
            const data = await request(`${endpoint}?${params.toString()}`, { method: 'GET' });
            const logs = data?.logs ?? [];
            setLogs(logs);
            updateAggregates(data?.aggregates ?? {});
            if (typeof data?.last_id === 'number') {
                lastLogId = data.last_id;
            }
            if (liveEnabled) {
                restartLive();
            }
        } catch (error) {
            showToast('Não foi possível carregar os logs.', 'error');
        }
    };

    container.addEventListener('click', (event) => {
        const detailsButton = event.target instanceof HTMLElement ? event.target.closest('[data-log-details]') : null;
        if (detailsButton) {
            const row = detailsButton.closest('[data-log-row]');
            const index = row ? Number(row.getAttribute('data-log-index')) : NaN;
            if (!Number.isNaN(index) && logsData[index]) {
                renderDetail(logsData[index]);
            }
            return;
        }
        if (event.target instanceof HTMLElement && event.target.closest('[data-log-close]')) {
            closeDetail();
            return;
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
        if (liveEnabled) {
            disableLive();
        } else {
            enableLive();
        }
    });

    fetchLogs();
}
