import { request, showToast, initTable } from '/js/app.js';

export function initDashboard(selector) {
    const root = document.querySelector(selector);
    if (!root) {
        return;
    }
    const queueTable = root.querySelector('#queue-table');
    const recentTable = root.querySelector('#recent-tickets');
    const targetsButton = root.querySelector('[data-group-targets-trigger]');
    const groupModalElement = document.getElementById('groupTargetsModal');
    const bootstrap = window.bootstrap || null;
    const groupModal = groupModalElement && bootstrap?.Modal ? new bootstrap.Modal(groupModalElement) : null;
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
                const description = group?.description ? `<small class="text-muted">${escapeHtml(group.description)}</small>` : '';
                return `
                    <tr data-group-row data-group-id="${id}">
                        <td>
                            <div class="fw-semibold">${escapeHtml(group?.name ?? '')}</div>
                            ${description}
                        </td>
                        <td><input type="number" min="0" class="form-control form-control-sm" value="${escapeHtml(tma ?? '')}" data-group-tma></td>
                        <td><input type="number" min="0" class="form-control form-control-sm" value="${escapeHtml(tme ?? '')}" data-group-tme></td>
                        <td><small data-group-updated>${escapeHtml(updatedAt)}</small></td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-primary" data-group-save>Salvar</button>
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
        if (!groupModal) {
            showToast('Não foi possível abrir a configuração de metas.', 'error');
            return;
        }
        await loadGroups();
        groupModal.show();
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
