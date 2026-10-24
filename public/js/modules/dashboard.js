import { request, showToast, initTable, Modal } from '/js/app.js';

export function initDashboard(selector) {
    const root = document.querySelector(selector);
    if (!root) {
        return;
    }
    const queueTable = root.querySelector('#queue-table');
    const recentTable = root.querySelector('#recent-tickets');
    const targetsButton = root.querySelector('[data-group-targets-trigger]');
    const groupModalElement = document.getElementById('groupTargetsModal');
    let groupModalInstance = null;
    const getGroupModal = () => {
        if (!groupModalElement) {
            return null;
        }
        if (!groupModalInstance) {
            groupModalInstance = new Modal('#groupTargetsModal');
        }
        return groupModalInstance;
    };
    const groupList = groupModalElement?.querySelector('[data-group-list]');
    const groupFeedback = groupModalElement?.querySelector('[data-group-feedback]');
    const groupLoading = groupModalElement?.querySelector('[data-group-loading]');

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
            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                <td class="px-8 py-4 text-sm font-bold text-slate-400">#${ticket.id}</td>
                <td class="px-6 py-4 text-sm font-bold text-slate-900 dark:text-slate-200">${escapeHtml(ticket.contact_name)}</td>
                <td class="px-6 py-4">
                    <span class="inline-flex px-3 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-[10px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-widest">
                        ${escapeHtml(ticket.status)}
                    </span>
                </td>
                <td class="px-6 py-4 text-sm text-slate-500 dark:text-slate-400 capitalize">${escapeHtml(ticket.channel)}</td>
                <td class="px-8 py-4 text-sm text-slate-500 dark:text-slate-400 font-medium">
                    ${ticket.opened_at ? new Date(ticket.opened_at).toLocaleDateString('pt-BR', {day:'2-digit', month:'2-digit'}) + ' ' + new Date(ticket.opened_at).toLocaleTimeString('pt-BR', {hour:'2-digit', minute:'2-digit'}) : '--'}
                </td>
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
            <tr class="hover:bg-white/[0.02] transition-colors border-b border-white/5">
                <td class="px-8 py-4 text-sm font-bold text-slate-500">#${ticket.id}</td>
                <td class="px-6 py-4 text-sm font-bold text-slate-100">${escapeHtml(ticket.contact_name)}</td>
                <td class="px-6 py-4 text-sm text-slate-400 uppercase tracking-wider italic">${escapeHtml(ticket.channel)}</td>
                <td class="px-8 py-4 text-sm text-slate-500 font-medium whitespace-nowrap">
                    ${ticket.opened_at ? new Date(ticket.opened_at).toLocaleDateString('pt-BR', {day:'2-digit', month:'2-digit'}) + ' ' + new Date(ticket.opened_at).toLocaleTimeString('pt-BR', {hour:'2-digit', minute:'2-digit'}) : '--'}
                </td>
            </tr>`).join('');
        initTable(queueTable);
    };

    const renderChannels = (channels = []) => {
        const container = root.querySelector('#channel-list');
        if (!container) {
            return;
        }
        container.innerHTML = channels.map((channel) => `
            <div class="flex items-center justify-between p-4 rounded-2xl bg-slate-50 dark:bg-slate-950/50 border border-slate-100 dark:border-slate-800/30 mb-4 last:mb-0">
                <span class="text-sm font-bold text-slate-700 dark:text-slate-300 capitalize flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center"><i class="bi bi-broadcast"></i></div>
                    ${escapeHtml(channel.channel)}
                </span>
                <span class="px-3 py-1 rounded-lg bg-primary text-white text-xs font-bold italic">${channel.total}</span>
            </div>`).join('') || '<p class="text-sm text-slate-400 text-center py-4">Nenhum canal ativo no momento.</p>';
    };

    const renderLeaderboard = (leaderboard = []) => {
        const container = root.querySelector('#leaderboard ol');
        if (!container) {
            return;
        }
        container.innerHTML = leaderboard.map((agent, index) => `
            <div class="flex items-center justify-between mb-4 last:mb-0">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-xs font-bold text-slate-500">${index + 1}</div>
                    <span class="text-sm font-bold text-slate-700 dark:text-slate-300">${escapeHtml(agent.full_name)}</span>
                </div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-emerald-50 dark:bg-emerald-950/30 text-emerald-600 dark:text-emerald-400 text-xs font-bold underline">
                    <i class="bi bi-award"></i> ${agent.resolved}
                </span>
            </div>`).join('') || '<p class="text-sm text-slate-400 text-center py-4 italic">Aguardando atendimentos...</p>';
    };

    const renderGroupLoading = () => {
        if (groupLoading) {
            groupLoading.classList.remove('d-none');
        }
        if (groupList) {
            groupList.innerHTML = '<tr data-group-empty><td colspan="5" class="text-center text-muted">Carregando grupos de atendimento...</td></tr>';
        }
        if (groupFeedback) {
            groupFeedback.classList.add('d-none');
            groupFeedback.textContent = '';
        }
    };

    const renderGroupTable = (groups = []) => {
        if (!groupList) {
            return;
        }
        if (groupLoading) {
            groupLoading.classList.add('d-none');
        }
        if (!Array.isArray(groups) || groups.length === 0) {
            groupList.innerHTML = '<tr data-group-empty><td colspan="5" class="text-center text-muted">Nenhum grupo configurado.</td></tr>';
            return;
        }
        groupList.innerHTML = groups
            .map((group) => {
                const id = Number.parseInt(group?.id ?? 0, 10) || 0;
                const tma = group?.target_tma ?? '';
                const tme = group?.target_tme ?? '';
                const updatedAt = group?.targets_updated_at
                    ? new Date(group.targets_updated_at).toLocaleString('pt-BR')
                    : '—';
                const description = group?.description ? `<div class="text-[10px] text-slate-400 dark:text-slate-500 font-medium leading-tight mt-0.5">${escapeHtml(group.description)}</div>` : '';
                return `
                    <tr data-group-row data-group-id="${id}" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                        <td class="py-4 pr-4">
                            <div class="text-sm font-bold text-slate-700 dark:text-slate-200">${escapeHtml(group?.name ?? '')}</div>
                            ${description}
                        </td>
                        <td class="px-4 py-4">
                            <input type="number" min="0" 
                                class="w-full h-9 px-3 bg-slate-50 dark:bg-slate-950/50 border border-slate-200 dark:border-slate-800 rounded-lg text-sm font-bold text-slate-700 dark:text-slate-200 focus:border-primary transition-all outline-none" 
                                value="${escapeHtml(tma ?? '')}" data-group-tma>
                        </td>
                        <td class="px-4 py-4">
                            <input type="number" min="0" 
                                class="w-full h-9 px-3 bg-slate-50 dark:bg-slate-950/50 border border-slate-200 dark:border-slate-800 rounded-lg text-sm font-bold text-slate-700 dark:text-slate-200 focus:border-primary transition-all outline-none" 
                                value="${escapeHtml(tme ?? '')}" data-group-tme>
                        </td>
                        <td class="px-4 py-4">
                            <div class="text-[11px] font-bold text-slate-400 dark:text-slate-500 whitespace-nowrap" data-group-updated>${escapeHtml(updatedAt)}</div>
                        </td>
                        <td class="py-4 pl-4 text-end">
                            <button type="button" 
                                class="inline-flex items-center justify-center px-4 py-1.5 bg-primary/10 hover:bg-primary text-primary hover:text-white text-[11px] font-bold uppercase tracking-wider rounded-lg transition-all" 
                                data-group-save>Salvar</button>
                        </td>
                    </tr>`;
            })
            .join('');
    };

    const loadGroups = async () => {
        renderGroupLoading();
        try {
            const response = await request('/admin/groups', { method: 'GET' });
            renderGroupTable(response?.groups ?? []);
        } catch (error) {
            if (groupLoading) {
                groupLoading.classList.add('d-none');
            }
            if (groupFeedback) {
                groupFeedback.textContent = error?.message || 'Não foi possível carregar os grupos.';
                groupFeedback.classList.remove('d-none');
            }
        }
    };

    const handleGroupSave = async (button) => {
        const row = button.closest('[data-group-row]');
        if (!row) {
            return;
        }
        const groupId = Number.parseInt(row.getAttribute('data-group-id') ?? '0', 10);
        if (!groupId) {
            showToast('Grupo inválido.', 'error');
            return;
        }
        const tmaInput = row.querySelector('[data-group-tma]');
        const tmeInput = row.querySelector('[data-group-tme]');
        const updatedLabel = row.querySelector('[data-group-updated]');

        const parseValue = (input) => {
            if (!input) {
                return null;
            }
            const value = input.value.trim();
            if (value === '') {
                return null;
            }
            const numeric = Number.parseInt(value, 10);
            if (!Number.isFinite(numeric) || numeric < 0) {
                return NaN;
            }
            return numeric;
        };

        const tmaValue = parseValue(tmaInput);
        const tmeValue = parseValue(tmeInput);

        if (Number.isNaN(tmaValue) || Number.isNaN(tmeValue)) {
            showToast('Informe valores válidos e positivos para TMA/TME.', 'error');
            return;
        }

        button.disabled = true;
        const originalText = button.innerHTML;
        button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';

        try {
            const payload = {
                target_tma: tmaValue,
                target_tme: tmeValue,
            };
            const response = await request(`/admin/groups/${groupId}/targets`, {
                method: 'POST',
                body: payload,
            });
            showToast(response?.message || 'Metas atualizadas.', 'success');
            if (updatedLabel) {
                updatedLabel.textContent = new Date().toLocaleString('pt-BR');
            }
        } catch (error) {
            showToast(error?.message || 'Não foi possível atualizar as metas.', 'error');
        } finally {
            button.disabled = false;
            button.innerHTML = originalText;
        }
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

    targetsButton?.addEventListener('click', async (event) => {
        event.preventDefault();
        const modal = getGroupModal();
        if (!modal) {
            showToast('Não foi possível abrir a configuração de metas.', 'error');
            return;
        }
        await loadGroups();
        modal.show();
    });

    groupModalElement?.addEventListener('click', async (event) => {
        const saveButton = event.target instanceof HTMLElement ? event.target.closest('[data-group-save]') : null;
        if (!saveButton) {
            return;
        }
        event.preventDefault();
        await handleGroupSave(saveButton);
    });

    refresh();
    setInterval(refresh, 15000);
}
