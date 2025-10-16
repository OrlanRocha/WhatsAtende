const THEME_KEY = 'whats-theme-preference';
const dataTables = new Map();

function getPreferredTheme() {
    const stored = localStorage.getItem(THEME_KEY);
    if (stored) {
        return stored;
    }
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

export function applyTheme(theme) {
    const root = document.documentElement;
    const normalized = theme === 'dark' ? 'dark' : 'light';
    root.setAttribute('data-bs-theme', normalized);
    document.body.dataset.theme = normalized;
    localStorage.setItem(THEME_KEY, normalized);
    document.querySelectorAll('[data-theme-toggle] i').forEach((icon) => {
        if (normalized === 'dark') {
            icon.classList.remove('bi-brightness-high', 'bi-sun');
            icon.classList.add('bi-moon-stars');
        } else {
            icon.classList.remove('bi-moon-stars');
            icon.classList.add('bi-brightness-high');
        }
    });
}

export function toggleTheme() {
    const current = document.documentElement.getAttribute('data-bs-theme') || 'light';
    applyTheme(current === 'light' ? 'dark' : 'light');
}

export function showToast(message, type = 'success') {
    if (!window.toastr) {
        return;
    }
    const toastType = type === 'error' ? 'error' : type === 'warning' ? 'warning' : type === 'info' ? 'info' : 'success';
    window.toastr[toastType](message);
}

export async function confirmAction(message, confirmText = 'Sim, confirmar') {
    if (!window.Swal) {
        return window.confirm(message);
    }
    const result = await window.Swal.fire({
        title: 'Confirmar ação',
        text: message,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0d6efd',
        cancelButtonColor: '#6c757d',
        confirmButtonText: confirmText,
        cancelButtonText: 'Cancelar',
    });

    return result.isConfirmed;
}

export async function request(url, options = {}) {
    const defaults = {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        },
    };

    const config = { ...defaults, ...options };
    config.method = (config.method || 'GET').toUpperCase();
    config.headers = { ...defaults.headers, ...(options.headers || {}) };

    if (config.body && !(config.body instanceof FormData)) {
        config.headers['Content-Type'] = 'application/json';
        config.body = JSON.stringify(config.body);
    }

    const response = await fetch(url, config);
    const contentType = response.headers.get('Content-Type') || '';
    let data = null;
    if (contentType.includes('application/json')) {
        data = await response.json();
    } else {
        data = await response.text();
    }

    if (!response.ok) {
        const error = new Error(typeof data === 'string' ? data : (data?.message || 'Erro desconhecido'));
        error.data = data;
        error.status = response.status;
        throw error;
    }

    return data;
}

export function initTable(table) {
    const element = typeof table === 'string' ? document.querySelector(table) : table;
    if (!element || !window.DataTable) {
        return null;
    }
    if (dataTables.has(element)) {
        const existing = dataTables.get(element);
        existing.destroy();
        dataTables.delete(element);
    }
    const dt = new window.DataTable(element, {
        perPage: 10,
        layout: {
            topStart: 'search',
            topEnd: 'pageLength',
        },
        language: {
            search: 'Buscar:',
            lengthMenu: 'Mostrar _MENU_',
            info: 'Exibindo _START_ a _END_ de _TOTAL_ registros',
            paginate: {
                next: 'Próximo',
                previous: 'Anterior',
            },
            emptyTable: 'Nenhum dado disponível',
        },
    });
    dataTables.set(element, dt);
    return dt;
}

export function reloadTable(table) {
    const element = typeof table === 'string' ? document.querySelector(table) : table;
    if (!element) {
        return;
    }
    initTable(element);
}

function setupTheme() {
    applyTheme(getPreferredTheme());
    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.addEventListener('click', () => toggleTheme());
    });
}

function setupToastr() {
    if (!window.toastr) {
        return;
    }
    window.toastr.options = {
        closeButton: true,
        newestOnTop: true,
        progressBar: true,
        timeOut: 3000,
        positionClass: 'toast-bottom-right',
    };
}

async function handleAjaxSubmit(event) {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.matches('form[data-ajax]')) {
        return;
    }
    event.preventDefault();

    if (form.dataset.confirm) {
        const confirmed = await confirmAction(form.dataset.confirm);
        if (!confirmed) {
            return;
        }
    }

    const submitButton = form.querySelector('[type="submit"]');
    if (submitButton) {
        submitButton.setAttribute('data-original-text', submitButton.innerHTML);
        submitButton.disabled = true;
        submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
    }

    try {
        const formData = new FormData(form);
        const method = (form.dataset.method || form.method || 'POST').toUpperCase();
        const url = form.getAttribute('action') || window.location.href;
        const data = await request(url, {
            method,
            body: formData,
        });

        const message = form.dataset.successMessage || data?.message;
        if (message) {
            showToast(message, 'success');
        }

        if (form.dataset.successEvent) {
            window.dispatchEvent(new CustomEvent(form.dataset.successEvent, { detail: data }));
        }

        if (form.dataset.hideModal) {
            const modalElement = document.querySelector(form.dataset.hideModal);
            if (modalElement) {
                const modalInstance = window.bootstrap?.Modal.getInstance(modalElement) || new window.bootstrap.Modal(modalElement);
                modalInstance.hide();
            }
        }

        if (form.dataset.reset !== 'false') {
            form.reset();
        }

        if (data?.redirect) {
            window.location.assign(data.redirect);
        } else if (form.dataset.successRedirect) {
            window.location.assign(form.dataset.successRedirect);
        }
    } catch (error) {
        const details = error?.data;
        if (details?.errors) {
            showToast(Array.isArray(details.errors) ? details.errors.join('\n') : details.errors, 'error');
        } else {
            showToast(details?.message || error.message || 'Não foi possível concluir a requisição.', 'error');
        }
    } finally {
        if (submitButton) {
            submitButton.disabled = false;
            const original = submitButton.getAttribute('data-original-text');
            if (original) {
                submitButton.innerHTML = original;
            }
        }
    }
}

function setupAjaxForms() {
    document.addEventListener('submit', handleAjaxSubmit);
}

function setupConfirmLinks() {
    document.addEventListener('click', async (event) => {
        const target = event.target instanceof HTMLElement ? event.target.closest('[data-confirm-click]') : null;
        if (!target) {
            return;
        }
        event.preventDefault();
        const message = target.getAttribute('data-confirm-click') || 'Deseja continuar?';
        const confirmed = await confirmAction(message);
        if (!confirmed) {
            return;
        }
        const href = target.getAttribute('href');
        if (href) {
            window.location.href = href;
        }
    });
}

function setupTables() {
    document.querySelectorAll('table[data-table]').forEach((table) => initTable(table));
}

function setupAutoRefresh() {
    document.querySelectorAll('[data-refresh-target]').forEach((button) => {
        button.addEventListener('click', () => {
            const selector = button.getAttribute('data-refresh-target');
            if (!selector) {
                return;
            }
            const target = document.querySelector(selector);
            if (target) {
                target.dispatchEvent(new CustomEvent('refresh'));
            }
        });
    });
}

setupTheme();
setupToastr();
setupAjaxForms();
setupConfirmLinks();
setupTables();
setupAutoRefresh();

window.addEventListener('users:refresh', () => reloadTable('#users-table'));
window.addEventListener('templates:refresh', () => reloadTable('#templates-table'));

export default {
    applyTheme,
    toggleTheme,
    showToast,
    confirmAction,
    request,
    initTable,
    reloadTable,
};
