import { request, showToast, initTable } from '/js/app.js';

const escapeHtml = (value) => {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
};

const avatarCache = new Map();

const loadAvatar = async (element) => {
    const url = element.getAttribute('data-profile-url');
    const fallback = element.querySelector('[data-profile-fallback]');

    if (!url) {
        element.classList.add('avatar-empty');
        return;
    }

    const apply = (src) => {
        element.style.backgroundImage = `url('${src}')`;
        element.classList.add('avatar-has-image');
        if (fallback) {
            fallback.textContent = '';
        }
    };

    if (avatarCache.has(url)) {
        apply(avatarCache.get(url));
        return;
    }

    try {
        const response = await fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!response.ok) {
            throw new Error('Request failed');
        }

        const contentType = response.headers.get('Content-Type') || '';
        if (!contentType.startsWith('image/')) {
            throw new Error('Not an image');
        }

        const blob = await response.blob();
        const objectUrl = URL.createObjectURL(blob);
        avatarCache.set(url, objectUrl);
        apply(objectUrl);
    } catch (error) {
        element.classList.add('avatar-empty');
    }
};

const hydrateAvatars = (scope) => {
    if (!scope) {
        return;
    }

    scope.querySelectorAll('[data-profile-url]').forEach((element) => {
        if (element.dataset.profileLoaded === 'true') {
            return;
        }
        element.dataset.profileLoaded = 'true';
        loadAvatar(element);
    });
};

const renderOpenedAt = (ticket) => {
    const opened = ticket?.opened_at ? escapeHtml(ticket.opened_at) : '';
    if (!ticket?.opened_today) {
        return opened;
    }
    return `${opened} <span class="badge badge--today">Hoje</span>`;
};

const renderSlaBadge = (ticket) => {
    const status = ticket?.sla_status ?? 'unset';
    if (status === 'breach') {
        return '<span class="status-badge status-badge--critical">SLA</span>';
    }
    if (status === 'warning') {
        return '<span class="status-badge status-badge--warning">SLA</span>';
    }
    if (status === 'ok') {
        return '<span class="status-badge status-badge--assigned">Em dia</span>';
    }
    return '<span class="status-badge">Sem SLA</span>';
};

const formatKanbanCard = (ticket) => {
    return `
        <article class="template-card">
            <div class="template-card__content">
                <h4>#${escapeHtml(String(ticket.id ?? ''))}</h4>
                <p class="mb-1">${escapeHtml(ticket.contact_name ?? 'Contato')}</p>
                <small class="text-muted">${renderOpenedAt(ticket)}</small>
            </div>
            <div class="template-card__actions">
                <button class="btn btn-sm btn-outline-primary" data-assign data-ticket="${escapeHtml(String(ticket.id ?? ''))}">
                    <i class="bi bi-headset"></i>
                </button>
            </div>
        </article>`;
};

const renderNativeChatRow = (chat) => {
    const remoteId = escapeHtml(chat?.id ?? '');
    const name = chat?.name ? escapeHtml(chat.name) : remoteId;
    const badge = chat?.opened_today
        ? '<span class="badge badge--today">Hoje</span>'
        : '';
    const unreadCount = Number.isFinite(chat?.unread) ? chat.unread : parseInt(chat?.unread ?? 0, 10) || 0;
    const unreadBadge = unreadCount > 0
        ? `<span class="status-badge status-badge--warning">${unreadCount}</span>`
        : '<span class="text-muted">0</span>';
    const profileUrl = typeof chat?.profile_url === 'string' ? chat.profile_url : '';
    const profileAttr = profileUrl ? ` data-profile-url="${escapeHtml(profileUrl)}"` : '';
    const baseInitial = (chat?.name && chat.name.trim()) ? chat.name.trim() : (chat?.id ?? '');
    const initial = escapeHtml((baseInitial || '#').charAt(0).toUpperCase() || '#');

    return `
        <tr>
            <td>
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar avatar-sm"${profileAttr}>
                        <span data-profile-fallback>${initial}</span>
                    </div>
                    <div>
                        <strong>${name}</strong>
                        <div class="text-muted small">ID: ${remoteId}</div>
                        ${badge}
                    </div>
                </div>
            </td>
            <td>${unreadBadge}</td>
            <td>${chat?.last_message_at ? escapeHtml(chat.last_message_at) : '—'}</td>
            <td class="text-end">
                <button class="btn btn-sm btn-primary" data-start-native data-remote="${remoteId}" data-name="${escapeHtml(chat?.name ?? '')}">
                    <i class="bi bi-chat-dots"></i> Iniciar conversa
                </button>
            </td>
        </tr>
    `;
};

const updateMetrics = (container, summary = {}) => {
    if (!container) {
        return;
    }
    container.querySelectorAll('[data-summary]').forEach((metric) => {
        const key = metric.getAttribute('data-summary');
        if (!key) {
            return;
        }
        const value = summary[key] ?? 0;
        const target = metric.querySelector('.metric-value');
        if (target) {
            target.textContent = value;
        }
    });
};

const renderQueueTable = (table, queue = []) => {
    if (!table) {
        return;
    }
    const tbody = table.querySelector('tbody');
    if (!tbody) {
        return;
    }
    tbody.innerHTML = queue.map((ticket) => `
        <tr>
            <td class="fw-semibold">#${escapeHtml(String(ticket.id ?? ''))}</td>
            <td>
                <div class="d-flex align-items-center gap-2">
                    <span class="presence-indicator" data-presence="${ticket?.presence ?? 'offline'}" aria-hidden="true"></span>
                    <div>
                        <div class="fw-semibold">${escapeHtml(ticket.contact_name ?? 'Contato')}</div>
                        <small class="text-muted">${escapeHtml(ticket.channel ?? 'whatsapp')}</small>
                    </div>
                </div>
            </td>
            <td>${escapeHtml(ticket.channel ?? 'whatsapp')}</td>
            <td><span class="status-badge status-badge--${escapeHtml(ticket.status ?? 'open')}">${escapeHtml(ticket.status ?? 'open')}</span></td>
            <td>${renderSlaBadge(ticket)}</td>
            <td>${renderOpenedAt(ticket)}</td>
            <td class="text-end">
                <button class="btn btn-success btn-sm" data-assign data-ticket="${escapeHtml(String(ticket.id ?? ''))}">
                    <i class="bi bi-headset"></i> Iniciar
                </button>
            </td>
        </tr>
    `).join('');
};

const renderKanban = (board, tickets = []) => {
    if (!board) {
        return;
    }
    const columns = board.querySelectorAll('[data-kanban-list]');
    columns.forEach((column) => {
        column.innerHTML = '';
    });
    tickets.forEach((ticket) => {
        const target = board.querySelector(`[data-kanban-list="${ticket.status ?? 'open'}"]`)
            || board.querySelector('[data-kanban-list="open"]');
        if (!target) {
            return;
        }
        target.insertAdjacentHTML('beforeend', formatKanbanCard(ticket));
    });
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
    const viewToggles = container.querySelectorAll('[data-view-toggle]');
    const tablePanel = container.querySelector('[data-view="table"]');
    const kanbanPanel = container.querySelector('[data-view="kanban"]');
    const kanbanBoard = container.querySelector('[data-kanban]');
    const metrics = container.querySelector('[data-log-metrics]') || container.querySelector('.workspace-metrics');
    const savedFilters = container.querySelectorAll('[data-saved-filter]');
    const densityToggle = container.querySelector('[data-density-toggle]');
    const searchInput = container.querySelector('[data-queue-search]');

    const nativeState = {
        enabled: !!(nativeConfig?.enabled),
        chats: Array.isArray(nativeConfig?.chats) ? nativeConfig.chats : [],
        error: nativeConfig?.error ?? null,
        startEndpoint: nativeConfig?.startEndpoint || '/tickets/native/start',
    };

    const queueState = {
        status: null,
        mine: false,
        hide_resolved: true,
        search: '',
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
            nativeCount.textContent = `${nativeState.chats.length} pendentes`;
        }

        if (!nativeBody) {
            return;
        }

        renderNativeChats(nativeBody, nativeState.chats);
        hydrateAvatars(nativeBody);
    };

    renderNative();

    const applyView = (view) => {
        viewToggles.forEach((button) => {
            button.classList.toggle('active', button.getAttribute('data-view-toggle') === view);
        });
        if (view === 'kanban') {
            tablePanel?.setAttribute('hidden', 'true');
            kanbanPanel?.removeAttribute('hidden');
        } else {
            kanbanPanel?.setAttribute('hidden', 'true');
            tablePanel?.removeAttribute('hidden');
        }
    };

    viewToggles.forEach((button) => {
        button.addEventListener('click', () => {
            applyView(button.getAttribute('data-view-toggle') || 'table');
        });
    });

    const applyDensity = () => {
        if (!table) {
            return;
        }
        const currentDensity = table.getAttribute('data-density') === 'compact' ? 'compact' : 'comfortable';
        const next = currentDensity === 'compact' ? 'comfortable' : 'compact';
        table.setAttribute('data-density', next);
        densityToggle?.classList.toggle('btn-primary', next === 'compact');
    };

    densityToggle?.addEventListener('click', applyDensity);

    savedFilters.forEach((button) => {
        button.addEventListener('click', () => {
            savedFilters.forEach((chip) => chip.classList.remove('is-active'));
            button.classList.add('is-active');
            try {
                const payload = JSON.parse(button.getAttribute('data-saved-filter') || '{}');
                queueState.status = payload.status ?? null;
                queueState.mine = Boolean(payload.mine);
                queueState.hide_resolved = payload.hide_resolved !== false;
            } catch (error) {
                // ignore invalid payloads
            }
            fetchQueue();
        });
    });

    searchInput?.addEventListener('input', () => {
        if (searchInput.dataset.timeoutId) {
            clearTimeout(Number(searchInput.dataset.timeoutId));
        }
        const timeoutId = window.setTimeout(() => {
            queueState.search = searchInput.value.trim();
            fetchQueue();
        }, 350);
        searchInput.dataset.timeoutId = String(timeoutId);
    });

    const fetchQueue = async () => {
        try {
            const queueResponse = await request(endpoint, { method: 'GET' });
            const tableData = queueResponse?.queue ?? [];
            renderQueueTable(table, tableData);
            hydrateAvatars(table);
            initTable(table);
            if (queueResponse?.native) {
                nativeState.chats = queueResponse.native.chats ?? nativeState.chats;
                nativeState.error = queueResponse.native.error ?? null;
                nativeState.enabled = queueResponse.native.enabled ?? nativeState.enabled;
                renderNative();
            }
        } catch (error) {
            showToast('Não foi possível atualizar a fila.', 'error');
        }

        try {
            const params = new URLSearchParams();
            if (queueState.status) {
                params.set('status', queueState.status);
            }
            if (queueState.mine) {
                params.set('mine', '1');
            }
            if (queueState.hide_resolved) {
                params.set('hide_resolved', '1');
            }
            if (queueState.search) {
                params.set('q', queueState.search);
            }
            params.set('format', 'json');
            const overview = await request(`/tickets/overview?${params.toString()}`);
            updateMetrics(metrics, overview?.summary ?? {});
            renderKanban(kanbanBoard, overview?.tickets ?? []);
        } catch (error) {
            updateMetrics(metrics, {});
            renderKanban(kanbanBoard, []);
        }
    };

    container.addEventListener('click', async (event) => {
        const assignButton = event.target instanceof HTMLElement ? event.target.closest('[data-assign]') : null;
        if (!assignButton) {
            return;
        }
        const ticketId = assignButton.getAttribute('data-ticket');
        if (!ticketId) {
            return;
        }
        assignButton.disabled = true;
        try {
            const data = await request(`/tickets/${ticketId}/assign`, { method: 'POST' });
            showToast(data?.message || 'Chamado atribuído.');
            if (data?.redirect) {
                window.location.assign(data.redirect);
                return;
            }
            fetchQueue();
        } catch (error) {
            showToast(error?.data?.message || 'Não foi possível atribuir o chamado.', 'error');
        } finally {
            assignButton.disabled = false;
        }
    });

    container.addEventListener('click', async (event) => {
        const startNative = event.target instanceof HTMLElement ? event.target.closest('[data-start-native]') : null;
        if (!startNative) {
            return;
        }
        startNative.disabled = true;
       try {
           const payload = {
               remote_jid: startNative.getAttribute('data-remote') ?? '',
               name: startNative.getAttribute('data-name') ?? '',
           };
           const data = await request(nativeState.startEndpoint, {
               method: 'POST',
               body: payload,
           });
           showToast('Conversa iniciada.');
           if (data?.redirect) {
               window.location.assign(data.redirect);
           }
            fetchQueue();
        } catch (error) {
            showToast(error?.data?.error || 'Não foi possível iniciar a conversa.', 'error');
        } finally {
            startNative.disabled = false;
        }
    });

    refreshButton?.addEventListener('click', fetchQueue);
    fetchQueue();
    setInterval(fetchQueue, 10000);
}
