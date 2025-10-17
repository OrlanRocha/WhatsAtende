import { request, showToast, initTable } from '/js/app.js';

const escapeHtml = (value) => {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
};

export function initTemplateModal(modalSelector, formSelector) {
    const modalElement = document.querySelector(modalSelector);
    const form = document.querySelector(formSelector);
    if (!modalElement || !form) {
        return;
    }
    const title = form.querySelector('#template_title');
    const body = form.querySelector('#template_body');
    const category = form.querySelector('#template_category');
    const modalTitle = modalElement.querySelector('.modal-title');

    modalElement.addEventListener('show.bs.modal', (event) => {
        const trigger = event.relatedTarget;
        const mode = trigger?.getAttribute('data-mode') || 'create';
        if (mode === 'edit') {
            const row = trigger.closest('[data-template-row]');
            if (!row) {
                return;
            }
            const raw = row.getAttribute('data-template');
            let template;
            try {
                template = JSON.parse(raw || '{}');
            } catch (error) {
                template = {};
            }
            form.setAttribute('action', `/admin/templates/${template.id}`);
            if (title) title.value = template.title || '';
            if (body) body.value = template.body || '';
            if (category) category.value = template.category || '';
            if (modalTitle) modalTitle.textContent = 'Editar template';
        } else {
            form.setAttribute('action', '/admin/templates');
            form.reset();
            if (modalTitle) modalTitle.textContent = 'Novo template';
        }
    });
}

export async function refreshTemplateTable(tableSelector, endpoint) {
    const table = document.querySelector(tableSelector);
    if (!table) {
        return;
    }
    try {
        const data = await request(endpoint, { method: 'GET' });
        if (!data?.templates) {
            return;
        }
        const tbody = table.querySelector('tbody');
        if (!tbody) {
            return;
        }
        tbody.innerHTML = data.templates.map((template) => {
            const payload = escapeHtml(JSON.stringify(template));
            const updatedAt = template.updated_at ? new Date(template.updated_at).toLocaleString('pt-BR') : '';
            return `
                <tr data-template-row data-template="${payload}">
                    <td>${template.id}</td>
                    <td class="fw-semibold">${escapeHtml(template.title)}</td>
                    <td><span class="badge rounded-pill bg-secondary-subtle text-dark text-capitalize">${escapeHtml(template.category || 'geral')}</span></td>
                    <td>${escapeHtml(updatedAt)}</td>
                    <td class="text-end">
                        <div class="btn-group" role="group">
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#templateModal" data-mode="edit">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <form method="POST" action="/admin/templates/${template.id}/delete" class="d-inline" data-ajax data-confirm="Excluir este template?" data-success-event="templates:refresh">
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
        showToast('Não foi possível atualizar os templates.', 'error');
    }
}
