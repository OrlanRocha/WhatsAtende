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
        const trigger = event.relatedTarget || event.detail?.relatedTarget;
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
            const updatedAt = template.updated_at ? new Date(template.updated_at).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }).replace(',', '') : '—';
            return `
                <tr class="group hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors" data-template-row data-template="${payload}">
                    <td class="px-8 py-5 text-sm font-bold text-slate-400">#${template.id}</td>
                    <td class="px-6 py-5">
                        <span class="text-sm font-bold text-slate-900 dark:text-white">${escapeHtml(template.title)}</span>
                    </td>
                    <td class="px-6 py-5">
                        <span class="inline-flex px-3 py-1 rounded-lg bg-primary/10 text-primary text-[10px] font-bold uppercase tracking-widest">
                            ${escapeHtml(template.category || 'geral')}
                        </span>
                    </td>
                    <td class="px-6 py-5 text-sm text-slate-500 dark:text-slate-400">
                        ${escapeHtml(updatedAt)}
                    </td>
                    <td class="px-8 py-5 text-end">
                        <div class="inline-flex items-center gap-1 p-1 bg-slate-100 dark:bg-slate-800 rounded-xl">
                            <button class="p-2 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-white dark:hover:bg-slate-700 hover:text-primary transition-all" data-modal-target="#templateModal" data-mode="edit">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <form method="POST" action="/admin/templates/${template.id}/delete" class="inline" data-ajax data-confirm="Excluir este template?" data-success-event="templates:refresh">
                                <button type="submit" class="p-2 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-white dark:hover:bg-slate-700 hover:text-rose-500 transition-all">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>`;
        }).join('');
        
        // Destruir tabela anterior para o Simple-DataTables recriar
        const { destroyTable } = await import('/js/app.js');
        destroyTable(table);
        initTable(table);
    } catch (error) {
        showToast('Não foi possível atualizar os templates.', 'error');
    }
}
