const THEME_KEY = 'whats-theme-preference';
const TAB_KEY_PREFIX = 'whats-tabs-';
const dataTables = new Map();
const commandRegistry = [];
let commandDialog;
let commandBackdrop;
let commandInput;
let commandResults;
let liveCommandList = [];
let lastKey = null;

function getPreferredTheme() {
    const stored = localStorage.getItem(THEME_KEY);
    if (stored) {
        return stored;
    }
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function syncColorScheme(theme) {
    const meta = document.querySelector('meta[name="color-scheme"]');
    if (meta) {
        meta.setAttribute('content', theme === 'dark' ? 'dark light' : 'light dark');
    }
}

export function applyTheme(theme) {
    const normalized = theme === 'dark' ? 'dark' : 'light';
    const root = document.documentElement;
    root.setAttribute('data-bs-theme', normalized);
    root.setAttribute('data-theme', normalized);
    document.body?.setAttribute('data-theme', normalized);
    localStorage.setItem(THEME_KEY, normalized);
    syncColorScheme(normalized);
    document.querySelectorAll('[data-theme-toggle] i').forEach((icon) => {
        if (normalized === 'dark') {
            icon.classList.remove('bi-brightness-high');
            icon.classList.add('bi-moon-stars');
        } else {
            icon.classList.remove('bi-moon-stars');
            icon.classList.add('bi-brightness-high');
        }
    });
}

export function toggleTheme() {
    const current = document.documentElement.getAttribute('data-theme') || 'light';
    applyTheme(current === 'light' ? 'dark' : 'light');
}

export function showToast(message, type = 'success') {
    if (!window.toastr) {
        return;
    }
    const toastType = type === 'error' ? 'error' : type === 'warning' ? 'warning' : type === 'info' ? 'info' : 'success';
    const normalized = typeof message === 'string' ? message : String(message ?? '');
    window.toastr[toastType](normalized);
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
        confirmButtonColor: '#2563eb',
        cancelButtonColor: '#6b7280',
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
            Accept: 'application/json',
        },
        credentials: 'same-origin',
    };

    const config = { ...defaults, ...options };
    config.method = (config.method || 'GET').toUpperCase();
    config.headers = { ...defaults.headers, ...(options.headers || {}) };

    if (
        config.body &&
        !(config.body instanceof FormData) &&
        !(config.body instanceof URLSearchParams)
    ) {
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

    const media = window.matchMedia('(prefers-color-scheme: dark)');
    media.addEventListener('change', (event) => {
        const stored = localStorage.getItem(THEME_KEY);
        if (!stored) {
            applyTheme(event.matches ? 'dark' : 'light');
        }
    });

    window.addEventListener('storage', (event) => {
        if (event.key === THEME_KEY && event.newValue) {
            applyTheme(event.newValue);
        }
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
        escapeHtml: true,
        preventDuplicates: true,
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
        const hasFileInput = Array.from(form.elements || []).some(
            (element) =>
                element instanceof HTMLInputElement &&
                element.type === 'file' &&
                element.files &&
                element.files.length > 0
        );
        const isMultipart =
            hasFileInput ||
            (typeof form.enctype === 'string' &&
                form.enctype.toLowerCase().includes('multipart'));
        let body;
        if (isMultipart) {
            body = formData;
        } else {
            body = new URLSearchParams();
            formData.forEach((value, key) => {
                if (value instanceof File) {
                    if (value.name) {
                        body.append(key, value.name);
                    }
                    return;
                }
                body.append(key, typeof value === 'string' ? value : String(value ?? ''));
            });
        }
        const method = (form.dataset.method || form.method || 'POST').toUpperCase();
        const url = form.getAttribute('action') || window.location.href;
        const data = await request(url, {
            method,
            body,
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

function registerCommand(command) {
    commandRegistry.push(command);
}

function buildNavigationCommands() {
    document.querySelectorAll('.app-sidebar__link').forEach((link) => {
        const label = link.textContent?.trim();
        if (!label) {
            return;
        }
        registerCommand({
            id: link.href,
            label,
            hint: 'Ir para ' + label,
            action: () => window.location.assign(link.href),
        });
    });
}

function filterCommands(query) {
    const normalized = query.trim().toLowerCase();
    if (!normalized) {
        return commandRegistry.slice(0, 12);
    }
    return commandRegistry.filter((command) =>
        command.label.toLowerCase().includes(normalized)
        || (command.hint?.toLowerCase().includes(normalized))
    ).slice(0, 12);
}

function renderCommands(items) {
    if (!commandResults) {
        return;
    }
    if (!items.length) {
        commandResults.innerHTML = '<ul><li class="text-muted">Nenhum comando encontrado.</li></ul>';
        return;
    }
    liveCommandList = items;
    const list = document.createElement('ul');
    items.forEach((item, index) => {
        const element = document.createElement('li');
        if (index === 0) {
            element.classList.add('is-active');
        }
        element.innerHTML = `
            <span>${item.label}</span>
            ${item.shortcut ? `<kbd>${item.shortcut}</kbd>` : ''}
        `;
        element.addEventListener('mouseenter', () => setActiveCommand(index));
        element.addEventListener('click', () => executeCommand(index));
        list.appendChild(element);
    });
    commandResults.innerHTML = '';
    commandResults.appendChild(list);
}

function setActiveCommand(index) {
    const items = commandResults?.querySelectorAll('li');
    if (!items) {
        return;
    }
    items.forEach((item, idx) => {
        item.classList.toggle('is-active', idx === index);
    });
}

function getActiveCommandIndex() {
    const items = commandResults?.querySelectorAll('li');
    if (!items) {
        return -1;
    }
    return Array.from(items).findIndex((item) => item.classList.contains('is-active'));
}

function executeCommand(index) {
    const command = liveCommandList[index];
    if (!command) {
        return;
    }
    closeCommandPalette();
    command.action?.();
}

function navigateCommand(delta) {
    const items = commandResults?.querySelectorAll('li');
    if (!items || !items.length) {
        return;
    }
    const current = getActiveCommandIndex();
    const next = (current + delta + items.length) % items.length;
    setActiveCommand(next);
}

function openCommandPalette() {
    if (!commandDialog || !commandBackdrop) {
        return;
    }
    commandBackdrop.hidden = false;
    commandDialog.hidden = false;
    commandInput.value = '';
    renderCommands(commandRegistry.slice(0, 12));
    commandInput.focus();
}

function closeCommandPalette() {
    if (!commandDialog || !commandBackdrop) {
        return;
    }
    commandDialog.hidden = true;
    commandBackdrop.hidden = true;
}

function initCommandPalette() {
    commandDialog = document.querySelector('[data-command-dialog]');
    commandBackdrop = document.querySelector('[data-command-backdrop]');
    commandInput = document.querySelector('[data-command-input]');
    commandResults = document.querySelector('[data-command-results]');

    if (!commandDialog || !commandBackdrop || !commandInput || !commandResults) {
        return;
    }

    buildNavigationCommands();

    document.querySelectorAll('[data-command-palette]').forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            openCommandPalette();
        });
    });

    document.querySelector('[data-command-close]')?.addEventListener('click', closeCommandPalette);
    commandBackdrop.addEventListener('click', closeCommandPalette);

    commandInput.addEventListener('input', (event) => {
        const value = event.target.value || '';
        renderCommands(filterCommands(value));
    });

    commandDialog.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            navigateCommand(1);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            navigateCommand(-1);
        } else if (event.key === 'Enter') {
            event.preventDefault();
            executeCommand(getActiveCommandIndex());
        } else if (event.key === 'Escape') {
            event.preventDefault();
            closeCommandPalette();
        }
    });

    window.__openCommandPalette = openCommandPalette;
    window.__setCommandQuery = (value) => {
        if (!commandInput) {
            return;
        }
        commandInput.value = value;
        renderCommands(filterCommands(value));
    };
}

function initKeyboardShortcuts() {
    document.addEventListener('keydown', (event) => {
        const modifier = event.metaKey || event.ctrlKey;
        if (modifier && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            openCommandPalette();
            return;
        }

        if (modifier && event.key.toLowerCase() === 'l') {
            event.preventDefault();
            window.location.assign('/admin/logs');
            return;
        }

        if (modifier && event.key.toLowerCase() === 't') {
            event.preventDefault();
            window.location.assign('/tickets');
            return;
        }

        if (!modifier && document.activeElement?.tagName !== 'INPUT' && document.activeElement?.tagName !== 'TEXTAREA') {
            if (event.key.toLowerCase() === 'g') {
                lastKey = 'g';
                setTimeout(() => { lastKey = null; }, 600);
                return;
            }
            if (lastKey === 'g' && event.key.toLowerCase() === 't') {
                event.preventDefault();
                window.location.assign('/tickets');
                lastKey = null;
            } else if (lastKey === 'g' && event.key.toLowerCase() === 'l') {
                event.preventDefault();
                window.location.assign('/admin/logs');
                lastKey = null;
            }
        }
    });
}

function initSidebar() {
    const sidebar = document.querySelector('[data-sidebar]');
    const trigger = document.querySelector('[data-sidebar-trigger]');
    const toggle = document.querySelector('[data-sidebar-toggle]');

    const closeSidebar = () => sidebar?.classList.remove('is-open');

    trigger?.addEventListener('click', () => sidebar?.classList.add('is-open'));
    toggle?.addEventListener('click', closeSidebar);
    document.addEventListener('click', (event) => {
        if (!sidebar || !sidebar.classList.contains('is-open')) {
            return;
        }
        const target = event.target instanceof HTMLElement ? event.target : null;
        if (target && !sidebar.contains(target) && !target.closest('[data-sidebar-trigger]')) {
            closeSidebar();
        }
    });
}

function initHealthWidget() {
    const indicator = document.querySelector('[data-health-indicator]');
    if (!indicator) {
        return;
    }

    const update = async () => {
        try {
            const snapshot = await request('/health');
            indicator.dataset.state = snapshot?.overall ?? 'ok';
            const label = indicator.querySelector('.health-indicator__label');
            if (label) {
                const checked = new Date(snapshot?.checked_at || Date.now());
                label.textContent = `Status ${snapshot?.overall ?? 'ok'} · ${checked.toLocaleTimeString('pt-BR')}`;
            }
        } catch (error) {
            indicator.dataset.state = 'degraded';
        }
    };

    update();
    setInterval(update, 60_000);
}

function initWorkspaceTabs() {
    document.querySelectorAll('[data-tabs]').forEach((tabs) => {
        const storageKey = TAB_KEY_PREFIX + (tabs.getAttribute('data-tabs-key') || 'default');
        const buttons = tabs.querySelectorAll('[data-tab-target]');

        const persist = (target) => sessionStorage.setItem(storageKey, target);
        const restore = () => sessionStorage.getItem(storageKey);

        const activate = (target) => {
            buttons.forEach((button) => {
                const isActive = button.getAttribute('data-tab-target') === target;
                button.classList.toggle('is-active', isActive);
            });
        };

        buttons.forEach((button) => {
            button.addEventListener('click', () => {
                const target = button.getAttribute('data-tab-target');
                if (!target) {
                    return;
                }
                persist(target);
                activate(target);
            });
        });

        const restored = restore();
        if (restored) {
            activate(restored);
        }
    });
}

function initGlobalSearch() {
    const form = document.querySelector('[data-global-search]');
    if (!form) {
        return;
    }
    const input = form.querySelector('[data-global-search-input]');
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        const value = input?.value.trim();
        if (!value) {
            return;
        }
        if (typeof window.__openCommandPalette === 'function') {
            window.__openCommandPalette();
            if (typeof window.__setCommandQuery === 'function') {
                window.__setCommandQuery(value);
            }
        }
    });
}

function initAutoRefresh() {
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
initCommandPalette();
initKeyboardShortcuts();
initSidebar();
initHealthWidget();
initWorkspaceTabs();
initGlobalSearch();
initAutoRefresh();

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
