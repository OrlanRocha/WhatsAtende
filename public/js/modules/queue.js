import { request, showToast, initTable, destroyTable } from '/js/app.js';

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
        const cached = avatarCache.get(url);
        if (cached) {
            apply(cached);
        } else {
            element.classList.add('avatar-empty');
        }
        return;
    }

    try {
        const response = await fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (response.status === 204) {
            avatarCache.set(url, null);
            element.classList.add('avatar-empty');
            return;
        }
        if (!response.ok) {
            throw new Error('Request failed');
        }

        const contentType = response.headers.get('Content-Type') || '';
        if (!contentType.startsWith('image/')) {
            avatarCache.set(url, null);
            element.classList.add('avatar-empty');
            return;
        }

        const blob = await response.blob();
        const objectUrl = URL.createObjectURL(blob);
        avatarCache.set(url, objectUrl);
        apply(objectUrl);
    } catch (error) {
        avatarCache.set(url, null);
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

const formatKanbanCard = (ticket, seriousnessMap = {}) => {
    const seriousnessKey = String(ticket?.seriousness ?? 'information').toLowerCase();
    const seriousnessLabel = seriousnessMap[seriousnessKey] || seriousnessKey;
    const groupLabel = ticket?.group_name ? escapeHtml(ticket.group_name) : 'Sem grupo';
    const initials = (ticket.contact_name || 'C').substring(0, 1).toUpperCase();
    const profileUrl = ticket.profile_picture_url || (ticket.contact_jid ? `/api/evolution/profile?remoteJid=${encodeURIComponent(ticket.contact_jid)}` : '');

    return `
        <article class="template-card hover:shadow-md transition-shadow">
            <div class="flex items-center gap-3 w-full">
                <div class="avatar avatar-md ${profileUrl ? 'avatar-has-image' : ''}" 
                     style="${profileUrl ? `background-image: url('${profileUrl}')` : ''}"
                     title="${escapeHtml(ticket.contact_name || '')}">
                    ${!profileUrl ? `<span class="flex items-center justify-center w-full h-full font-bold">${initials}</span>` : ''}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex justify-between items-start">
                        <h4 class="truncate text-sm mb-0">#${escapeHtml(String(ticket.id ?? ''))}</h4>
                        <small class="text-xs text-muted">${renderOpenedAt(ticket)}</small>
                    </div>
                    <p class="font-medium text-slate-800 truncate mb-0">${escapeHtml(ticket.contact_name ?? 'Contato')}</p>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="status-badge text-[9px] py-0 px-1.5">${groupLabel}</span>
                        <span class="text-[10px] text-muted truncate">${escapeHtml(seriousnessLabel)}</span>
                    </div>
                </div>
                <div class="shrink-0">
                    <button class="btn btn-sm btn-ghost text-primary" data-assign data-ticket="${escapeHtml(String(ticket.id ?? ''))}" title="Atender">
                        <i class="bi bi-headset text-lg"></i>
                    </button>
                </div>
            </div>
        </article>`;
};

const formatWhatsAppNumber = (id) => {
    const rawId = id.split('@')[0];
    const numMatch = rawId.match(/^55(\d{2})(\d{4,5})(\d{4})$/);
    if (numMatch) {
        return `(${numMatch[1]}) ${numMatch[2]}-${numMatch[3]}`;
    }
    return rawId;
};

const renderNativeChatRow = (chat) => {
    const remoteId = escapeHtml(chat?.id ?? '');
    const cleanId = formatWhatsAppNumber(chat?.id ?? '');

    let rawName = chat?.name ? chat.name.trim() : '';
    const phoneOnly = (chat?.id || '').split('@')[0].replace(/\D/g, '');
    if (!rawName || rawName.replace(/\D/g, '') === phoneOnly) {
        rawName = cleanId;
    }
    const name = escapeHtml(rawName);

    const badge = chat?.opened_today
        ? '<span class="badge badge--today">Hoje</span>'
        : '';
    const unreadCount = Number.isFinite(chat?.unread) ? chat.unread : parseInt(chat?.unread ?? 0, 10) || 0;
    const unreadBadge = unreadCount > 0
        ? `<span class="bg-primary text-white text-[10px] font-bold px-2 py-0.5 rounded-full">${unreadCount}</span>`
        : '<span class="text-slate-300">0</span>';
    
    const initials = (!/^\(?\+?\d+[\d\s\-\(\)]+$/.test(rawName) && rawName) ? escapeHtml(rawName.charAt(0).toUpperCase()) : '<i class="bi bi-person"></i>';
    const profileUrl = chat.profile_url || `/api/evolution/profile?remoteJid=${encodeURIComponent(chat?.id ?? '')}`;

    return `
        <tr class="hover:bg-slate-50 transition-colors">
            <td class="px-4 py-4 w-16">
                <div class="avatar avatar-md w-10 h-10 flex items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 font-bold bg-cover bg-center overflow-hidden shrink-0" 
                     data-profile-url="${escapeHtml(profileUrl)}">
                    <span data-profile-fallback>${initials}</span>
                </div>
            </td>
            <td class="px-4 py-4">
                <div class="flex flex-col">
                    <strong class="text-slate-900 dark:text-white">${name}</strong>
                    ${name !== escapeHtml(cleanId) ? `<div class="text-slate-500 text-xs">${escapeHtml(cleanId)}</div>` : ''}
                    ${badge}
                </div>
            </td>
            <td class="px-4 py-4 text-center">${unreadBadge}</td>
            <td class="px-4 py-4 text-sm text-slate-500">${chat?.last_message_at ? escapeHtml(chat.last_message_at) : '—'}</td>
            <td class="px-4 py-4 text-end">
                <button class="btn btn-sm btn-primary rounded-lg flex items-center gap-2 ml-auto" 
                        data-start-native data-remote="${remoteId}" data-name="${name}">
                    <i class="bi bi-chat-dots-fill"></i> Atender
                </button>
            </td>
        </tr>
    `;
};

const renderNativeChats = (tbody, chats = []) => {
    if (!tbody) {
        return;
    }

    const filteredChats = (Array.isArray(chats) ? chats : []).filter(chat => {
        const id = chat?.id || '';
        return id.startsWith('55') && id.endsWith('@s.whatsapp.net');
    });

    const thead = tbody.closest('table')?.querySelector('thead');
    if (thead) {
        thead.innerHTML = `
            <tr class="bg-slate-50 dark:bg-slate-800/50">
                <th class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-slate-500 border-b border-slate-100 dark:border-slate-800">Foto</th>
                <th class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-slate-500 border-b border-slate-100 dark:border-slate-800">Contato</th>
                <th class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-slate-500 border-b border-slate-100 dark:border-slate-800 text-center">Mensagens</th>
                <th class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-slate-500 border-b border-slate-100 dark:border-slate-800">Última</th>
                <th class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-slate-500 border-b border-slate-100 dark:border-slate-800 text-end">Ação</th>
            </tr>
        `;
    }

    if (filteredChats.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-8">Nenhuma conversa pendente.</td></tr>';
        return;
    }

    tbody.innerHTML = filteredChats.map((chat) => renderNativeChatRow(chat)).join('');
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

const renderQueueTable = (table, queue = [], options = {}) => {
    if (!table) {
        return;
    }
    const tbody = table.querySelector('tbody');
    if (!tbody) {
        return;
    }
    const seriousnessMap = options?.seriousness ?? {};

    tbody.innerHTML = queue.map((ticket) => {
        const seriousnessKey = String(ticket?.seriousness ?? 'information').toLowerCase();
        const seriousnessLabel = seriousnessMap[seriousnessKey] || seriousnessKey;
        const presence = ticket?.presence === 'online' ? 'bg-emerald-500' : 'bg-slate-300';
        const slaStatus = ticket?.sla_status === 'breach' ? 'SLA Crítico' : 'No prazo';
        const slaClass = ticket?.sla_status === 'breach' ? 'text-rose-600' : 'text-emerald-600';
        const profileUrl = ticket.profile_picture_url || (ticket.contact_jid ? `/api/evolution/profile?remoteJid=${encodeURIComponent(ticket.contact_jid)}` : '');

        return `
        <tr class="group hover:bg-slate-50/50 dark:hover:bg-slate-800/60 transition-colors">
            <td class="px-8 py-5 text-sm font-bold text-slate-400 group-hover:text-primary transition-colors">#${escapeHtml(String(ticket.id ?? ''))}</td>
            <td class="px-6 py-5">
                <div class="flex items-center gap-3">
                    <div class="relative">
                        <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-400 bg-cover bg-center" 
                             style="${profileUrl ? `background-image: url('${profileUrl}')` : ''}">
                            ${!profileUrl ? '<i class="bi bi-person text-lg"></i>' : ''}
                        </div>
                        <div class="absolute -bottom-1 -right-1 w-3 h-3 rounded-full border-2 border-white dark:border-slate-900 ${presence}" data-presence="${escapeHtml(ticket?.presence ?? 'offline')}"></div>
                    </div>
                    <div>
                        <div class="text-sm font-bold text-slate-900 dark:text-white">${escapeHtml(ticket.contact_name ?? 'Contato')}</div>
                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">${escapeHtml(ticket.channel ?? 'whatsapp')}</div>
                    </div>
                </div>
            </td>
            <td class="px-6 py-5">
                <span class="inline-flex px-3 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                    ${escapeHtml(ticket.status ?? 'open')}
                </span>
            </td>
            <td class="px-6 py-5">
                <div class="flex flex-col gap-1">
                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300">${escapeHtml(ticket.group_name ?? 'Padrão')}</span>
                    <span class="text-[10px] ${slaClass} font-bold uppercase" data-sla="${escapeHtml(ticket?.sla_status ?? 'ok')}">${slaStatus}</span>
                </div>
            </td>
            <td class="px-8 py-5 text-end">
                <button class="inline-flex items-center gap-2 px-5 py-2 bg-emerald-500 hover:bg-emerald-600 dark:bg-emerald-600 hover:scale-105 text-white rounded-xl text-xs font-bold shadow-lg shadow-emerald-500/20 transition-all active:scale-95" 
                        data-assign data-ticket="${escapeHtml(String(ticket.id ?? ''))}">
                    <i class="bi bi-headset"></i> ATENDER
                </button>
            </td>
        </tr>`;
    }).join('');
};

const renderKanban = (board, tickets = [], options = {}) => {
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
        target.insertAdjacentHTML('beforeend', formatKanbanCard(ticket, options?.seriousness));
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
    const groupFilter = container.querySelector('[data-group-filter]');
    const seriousnessFilter = container.querySelector('[data-seriousness-filter]');

    const parseJson = (value, fallback) => {
        if (!value) {
            return fallback;
        }
        try {
            return JSON.parse(value);
        } catch (error) {
            return fallback;
        }
    };

    let supportGroups = parseJson(container.getAttribute('data-support-groups'), []);
    let userGroups = parseJson(container.getAttribute('data-user-groups'), []);
    const seriousnessOptions = parseJson(container.getAttribute('data-seriousness-options'), {});

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
        group: null,
        seriousness: null,
    };

    if (!queueState.group && Array.isArray(userGroups) && userGroups.length === 1) {
        queueState.group = userGroups[0];
        if (groupFilter) {
            groupFilter.value = String(userGroups[0]);
        }
    }

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
            const count = nativeState.chats.filter(chat => {
                const id = chat?.id || '';
                return id.startsWith('55') && id.endsWith('@s.whatsapp.net');
            }).length;
            nativeCount.textContent = `${count} pendentes`;
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
            const isTarget = button.getAttribute('data-view-toggle') === view;
            button.classList.toggle('active', isTarget);
            
            if (isTarget) {
                button.classList.add('bg-white', 'dark:bg-slate-700', 'text-primary', 'shadow-sm');
                button.classList.remove('text-slate-500', 'hover:text-slate-700', 'dark:hover:text-slate-300');
            } else {
                button.classList.remove('bg-white', 'dark:bg-slate-700', 'text-primary', 'shadow-sm');
                button.classList.add('text-slate-500', 'hover:text-slate-700', 'dark:hover:text-slate-300');
            }
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
                queueState.group = payload.group ?? queueState.group;
                queueState.seriousness = payload.seriousness ?? queueState.seriousness;
                if (groupFilter && payload.group) {
                    groupFilter.value = String(payload.group);
                }
                if (seriousnessFilter && payload.seriousness) {
                    seriousnessFilter.value = String(payload.seriousness);
                }
            } catch (error) {
                // ignore invalid payloads
            }
            fetchQueue(true);
        });
    });

    searchInput?.addEventListener('input', () => {
        if (searchInput.dataset.timeoutId) {
            clearTimeout(Number(searchInput.dataset.timeoutId));
        }
        const timeoutId = window.setTimeout(() => {
            queueState.search = searchInput.value.trim();
            fetchQueue(true);
        }, 350);
        searchInput.dataset.timeoutId = String(timeoutId);
    });

    groupFilter?.addEventListener('change', () => {
        const value = groupFilter.value;
        queueState.group = value ? Number(value) : null;
        fetchQueue(true);
    });

    seriousnessFilter?.addEventListener('change', () => {
        const value = seriousnessFilter.value;
        queueState.seriousness = value || null;
        fetchQueue(true);
    });

    let isFetching = false;
    let pollTimer = null;
    let currentAbortController = null;

    const fetchQueue = async (force = false) => {
        if (isFetching && !force) return;
        
        if (isFetching && force && currentAbortController) {
            currentAbortController.abort();
        }

        isFetching = true;
        currentAbortController = new AbortController();
        const signal = currentAbortController.signal;

        if (pollTimer) {
            clearTimeout(pollTimer);
        }

        try {
            const params = new URLSearchParams();
            if (queueState.status) params.set('status', queueState.status);
            if (queueState.mine) params.set('mine', '1');
            if (queueState.hide_resolved) params.set('hide_resolved', '1');
            if (queueState.search) params.set('q', queueState.search);
            if (queueState.group) params.set('group', String(queueState.group));
            if (queueState.seriousness) params.set('seriousness', queueState.seriousness);
            params.set('format', 'json');

            const data = await request(`/tickets/overview?${params.toString()}`, { method: 'GET', signal });
            if (signal.aborted) return;

            // 1. Update Table
            const tickets = data?.tickets ?? [];
            destroyTable(table);
            renderQueueTable(table, tickets, { seriousness: seriousnessOptions });
            hydrateAvatars(table);
            initTable(table);

            // 2. Update Metrics
            updateMetrics(metrics, data?.summary ?? {});

            // 3. Update Kanban
            renderKanban(kanbanBoard, tickets, { seriousness: seriousnessOptions });

            // 4. Update Native Chats
            if (data?.native) {
                nativeState.chats = data.native.chats ?? nativeState.chats;
                nativeState.error = data.native.error ?? null;
                nativeState.enabled = data.native.enabled ?? nativeState.enabled;
                renderNative();
            }

            // 5. Update Groups (if admin/dev)
            if (Array.isArray(data?.groups)) {
                supportGroups = data.groups;
                if (groupFilter) {
                    const current = groupFilter.value;
                    groupFilter.innerHTML = '<option value="">Todos os grupos</option>';
                    supportGroups.forEach((group) => {
                        const option = document.createElement('option');
                        option.value = String(group?.id ?? '');
                        option.textContent = group?.name ?? option.value;
                        groupFilter.appendChild(option);
                    });
                    groupFilter.value = queueState.group ? String(queueState.group) : current;
                }
            }
        } catch (error) {
            if (error?.name === 'AbortError' || signal.aborted) return;
            console.error('Queue Fetch Error:', error);
            showToast('Não foi possível atualizar a fila.', 'error');
        }

        isFetching = false;
        pollTimer = setTimeout(() => fetchQueue(false), 10000);
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
               last_message_id: startNative.getAttribute('data-last-message-id') ?? '',
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

    if (container.dataset.initialized) {
        return;
    }
    container.dataset.initialized = 'true';

    refreshButton?.addEventListener('click', () => {
        fetchQueue(true);
    });
    fetchQueue();
}
