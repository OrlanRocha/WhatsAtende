import { request, showToast, initTable } from '/js/app.js';

const escapeHtml = (value) => {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
};

export function initUserModal(modalSelector, formSelector) {
    const modalElement = document.querySelector(modalSelector);
    const form = document.querySelector(formSelector);
    if (!modalElement || !form) {
        return;
    }
    const password = form.querySelector('#user_password');
    const passwordConfirmation = form.querySelector('#user_password_confirmation');
    const modalTitle = modalElement.querySelector('.modal-title');
    const methodField = form.querySelector('#user-form-method');
    const permissionInputs = Array.from(form.querySelectorAll('[data-permission-checkbox]'));
    const customPermissionsInput = form.querySelector('#user_custom_permissions');
    const groupInputs = Array.from(form.querySelectorAll('[data-group-checkbox]'));

    const resetPermissions = () => {
        permissionInputs.forEach((checkbox) => {
            checkbox.checked = false;
        });
        if (customPermissionsInput) {
            customPermissionsInput.value = '';
        }
    };

    const resetGroups = () => {
        groupInputs.forEach((checkbox) => {
            checkbox.checked = false;
        });
    };

    modalElement.addEventListener('show.bs.modal', (event) => {
        const trigger = event.relatedTarget;
        const mode = trigger?.getAttribute('data-mode') || 'create';
        resetPermissions();
        resetGroups();
        if (mode === 'edit') {
            const row = trigger.closest('[data-user-row]');
            if (!row) {
                return;
            }
            const raw = row.getAttribute('data-user');
            let user;
            try {
                user = JSON.parse(raw || '{}');
            } catch (error) {
                user = {};
            }
            form.setAttribute('action', `/admin/users/${user.id}`);
            form.dataset.method = 'POST';
            methodField.value = 'edit';
            form.querySelector('#user_full_name').value = user.full_name || '';
            form.querySelector('#user_email').value = user.email || '';
            form.querySelector('#user_cpf').value = user.cpf || '';
            form.querySelector('#user_role').value = user.role_id || '';
            form.querySelector('#user_is_active').checked = Boolean(Number(user.is_active ?? 1));
            if (password) {
                password.value = '';
                password.removeAttribute('required');
            }
            if (passwordConfirmation) {
                passwordConfirmation.value = '';
                passwordConfirmation.removeAttribute('required');
            }
            const knownValues = new Map();
            permissionInputs.forEach((input) => {
                knownValues.set(input.value, input);
            });
            const permissionNames = Array.isArray(user.permission_names)
                ? user.permission_names
                : Array.isArray(user.permissions)
                ? user.permissions.map((item) => (typeof item === 'string' ? item : item?.name))
                : [];
            const extras = [];
            permissionNames.forEach((permission) => {
                if (!permission) {
                    return;
                }
                const checkbox = knownValues.get(permission);
                if (checkbox) {
                    checkbox.checked = true;
                } else {
                    extras.push(permission);
                }
            });
            if (customPermissionsInput) {
                customPermissionsInput.value = extras.join(', ');
            }

            const knownGroups = new Map();
            groupInputs.forEach((input) => {
                knownGroups.set(Number.parseInt(input.value, 10), input);
            });
            const groupIds = Array.isArray(user.group_ids)
                ? user.group_ids.map((value) => Number.parseInt(value, 10)).filter((value) => Number.isInteger(value))
                : Array.isArray(user.groups)
                ? user.groups
                      .map((group) => {
                          if (typeof group === 'number') {
                              return group;
                          }
                          if (group && typeof group === 'object' && 'id' in group) {
                              return Number.parseInt(group.id, 10);
                          }
                          return null;
                      })
                      .filter((value) => Number.isInteger(value))
                : [];
            groupIds.forEach((groupId) => {
                const checkbox = knownGroups.get(groupId);
                if (checkbox) {
                    checkbox.checked = true;
                }
            });
            if (modalTitle) {
                modalTitle.textContent = 'Editar usuário';
            }
        } else {
            form.setAttribute('action', '/admin/users');
            form.dataset.method = 'POST';
            methodField.value = 'create';
            form.reset();
            form.querySelector('#user_is_active').checked = true;
            if (password) {
                password.setAttribute('required', 'required');
            }
            if (passwordConfirmation) {
                passwordConfirmation.setAttribute('required', 'required');
            }
            if (customPermissionsInput) {
                customPermissionsInput.value = '';
            }
            if (modalTitle) {
                modalTitle.textContent = 'Novo usuário';
            }
        }
    });
}

export async function refreshUserTable(tableSelector, endpoint) {
    const table = document.querySelector(tableSelector);
    if (!table) {
        return;
    }
    try {
        const data = await request(endpoint, { method: 'GET' });
        if (!data?.users) {
            return;
        }
        const tbody = table.querySelector('tbody');
        if (!tbody) {
            return;
        }
        tbody.innerHTML = data.users.map((user) => {
            const userJson = escapeHtml(JSON.stringify(user));
            const badge = user.is_active ? '<span class="badge rounded-pill text-bg-success"><i class="bi bi-check-circle"></i> Sim</span>' : '<span class="badge rounded-pill text-bg-danger"><i class="bi bi-x-circle"></i> Não</span>';
            const permissions = Array.isArray(user.permissions) ? user.permissions : [];
            const groups = Array.isArray(user.groups) ? user.groups : [];
            const groupBadges = groups.length
                ? `<div class="d-flex flex-wrap gap-1">${groups
                      .map((group) => {
                          const label = typeof group === 'string' ? group : group?.name || group?.slug || '';
                          return `<span class="badge rounded-pill text-bg-primary-subtle text-primary">${escapeHtml(label)}</span>`;
                      })
                      .join('')}</div>`
                : '<span class="text-muted small">—</span>';
            const permissionBadges = permissions.length
                ? `<div class="d-flex flex-wrap gap-1">${permissions
                      .map((permission) => {
                          const label = typeof permission === 'string' ? permission : permission?.label || permission?.name || '';
                          return `<span class="badge rounded-pill text-bg-light border">${escapeHtml(label)}</span>`;
                      })
                      .join('')}</div>`
                : '<span class="text-muted small">—</span>';
            return `
                <tr data-user-row data-user="${userJson}">
                    <td>${user.id}</td>
                    <td class="fw-semibold">${escapeHtml(user.full_name)}</td>
                    <td>${escapeHtml(user.email)}</td>
                    <td>${escapeHtml(user.cpf)}</td>
                    <td><span class="badge bg-gradient text-capitalize">${escapeHtml(user.role)}</span></td>
                    <td>${badge}</td>
                    <td>${user.assigned_tickets ?? 0}</td>
                    <td>${groupBadges}</td>
                    <td>${permissionBadges}</td>
                    <td class="text-end">
                        <div class="btn-group" role="group">
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#userModal" data-mode="edit">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <form method="POST" action="/admin/users/${user.id}/delete" class="d-inline" data-ajax data-confirm="Remover este usuário?" data-success-event="users:refresh">
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>`;
        }).join('');
        initTable(table);
    } catch (error) {
        showToast('Não foi possível atualizar a lista de usuários.', 'error');
    }
}
