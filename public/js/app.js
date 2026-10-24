const THEME_KEY = 'whats-atende-theme-v2';
const TAB_KEY_PREFIX = 'whats-tabs-';
const dataTables = new Map();
const commandRegistry = [];
let commandDialog;
let commandBackdrop;
let commandInput;
let commandResults;
let liveCommandList = [];
let lastKey = null;
const APP_BASE_PATH = window.__APP_BASE_PATH || '';
const APP_BASE_ORIGIN = window.__APP_BASE_ORIGIN || window.location.origin;
const APP_BASE_URL = window.__APP_BASE_URL || `${APP_BASE_ORIGIN}${APP_BASE_PATH || ''}`;
const CURRENT_USER_ROLE = window.__APP_USER_ROLE || null;

function isExternalUrl(value) {
    if (typeof value !== 'string') {
        return false;
    }
    const lower = value.toLowerCase();
    return lower.startsWith('http://')
        || lower.startsWith('https://')
        || lower.startsWith('mailto:')
        || lower.startsWith('tel:')
        || lower.startsWith('data:')
        || lower.startsWith('javascript:');
}

/**
 * Vanilla Modal Class
 */
export class Modal {
    constructor(id) {
        this.id = id;
        this.element = document.querySelector(id);
        this.backdrop = document.querySelector('[data-modal-backdrop]');
        if (!this.backdrop) {
            this.backdrop = document.createElement('div');
            this.backdrop.setAttribute('data-modal-backdrop', '');
            this.backdrop.className = 'fixed inset-0 z-[90] bg-slate-900/40 backdrop-blur-sm hidden animate-in fade-in duration-300';
            document.body.appendChild(this.backdrop);
        }
    }

    show(trigger = null) {
        if (!this.element) return;
        
        // Dispatch show event before showing
        const event = new CustomEvent('show.bs.modal', { 
            detail: { trigger },
            bubbles: true 
        });
        // Compatibilidade com código legado que busca relatedTarget
        event.relatedTarget = trigger;
        this.element.dispatchEvent(event);

        this.element.classList.remove('hidden');
        this.element.classList.add('flex');
        this.backdrop.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    hide() {
        if (!this.element) return;
        
        // Dispatch hide event
        const event = new CustomEvent('hide.bs.modal', { bubbles: true });
        this.element.dispatchEvent(event);

        this.element.classList.add('hidden');
        this.element.classList.remove('flex');
        this.backdrop.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }
}

window.Modal = Modal;

function buildPath(base, segment) {
    let normalizedBase = typeof base === 'string' ? base.trim() : '';
    let normalizedSegment = typeof segment === 'string' ? segment.trim() : '';

    if (normalizedBase === '/' || normalizedBase === '') {
        normalizedBase = '';
    } else {
        normalizedBase = normalizedBase.replace(/\/+$/g, '');
        if (!normalizedBase.startsWith('/')) {
            normalizedBase = `/${normalizedBase}`;
        }
    }

    if (normalizedSegment.startsWith('/')) {
        normalizedSegment = normalizedSegment.replace(/^\/+/, '');
    }

    if (normalizedSegment === '') {
        return normalizedBase === '' ? '/' : normalizedBase;
    }

    const joined = normalizedBase === ''
        ? `/${normalizedSegment}`
        : `${normalizedBase}/${normalizedSegment}`;

    return joined.replace(/\/{2,}/g, '/');
}

function joinUrl(origin, path) {
    const normalizedOrigin = typeof origin === 'string' ? origin.replace(/\/+$/g, '') : '';

    if (typeof path !== 'string' || path === '') {
        return normalizedOrigin || origin || '';
    }

    if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('//')) {
        return path;
    }

    if (path.startsWith('/')) {
        return `${normalizedOrigin}${path}`;
    }

    if (normalizedOrigin === '') {
        return `/${path}`.replace(/\/{2,}/g, '/');
    }

    return `${normalizedOrigin}/${path}`.replace(/\/{2,}/g, '/');
}

function appPath(value) {
    if (typeof value !== 'string') {
        return value;
    }

    const trimmed = value.trim();

    if (trimmed === '') {
        return buildPath(APP_BASE_PATH, '');
    }

    if (trimmed === '#') {
        return trimmed;
    }

    if (isExternalUrl(trimmed) || trimmed.startsWith('//')) {
        return trimmed;
    }

    if (trimmed.startsWith('?')) {
        const current = window.location.pathname || buildPath(APP_BASE_PATH, '');
        return `${current}${trimmed}`;
    }

    if (trimmed.startsWith('#')) {
        return trimmed;
    }

    if (APP_BASE_PATH && trimmed.startsWith(`${APP_BASE_PATH}/`)) {
        return trimmed.replace(/\/{2,}/g, '/');
    }

    if (APP_BASE_PATH && trimmed === APP_BASE_PATH) {
        return APP_BASE_PATH;
    }

    if (trimmed.startsWith('/')) {
        return buildPath(APP_BASE_PATH, trimmed.slice(1));
    }

    return buildPath(APP_BASE_PATH, trimmed);
}

function resolveUrl(value) {
    if (typeof value !== 'string' || value.trim() === '') {
        return APP_BASE_URL || window.location.href;
    }

    if (isExternalUrl(value) || value.startsWith('//')) {
        return value;
    }

    const normalized = appPath(value);

    if (typeof normalized !== 'string') {
        return normalized;
    }

    if (normalized.startsWith('http://') || normalized.startsWith('https://') || normalized.startsWith('//')) {
        return normalized;
    }

    if (normalized.startsWith('#')) {
        return normalized;
    }

    if (normalized.startsWith('?')) {
        return `${APP_BASE_ORIGIN}${normalized}`;
    }

    return joinUrl(APP_BASE_ORIGIN, normalized);
}

function normalizeDomLocations() {
    document.querySelectorAll('a[href]').forEach((anchor) => {
        const href = anchor.getAttribute('href');
        if (!href || href === '#') {
            return;
        }
        const normalized = appPath(href);
        if (typeof normalized === 'string' && normalized !== href) {
            anchor.setAttribute('href', normalized);
        }
    });

    document.querySelectorAll('form[action]').forEach((form) => {
        const action = form.getAttribute('action');
        const normalized = appPath(action || '');
        if (typeof normalized === 'string' && normalized !== action) {
            form.setAttribute('action', normalized);
        }
    });
}

function getPreferredTheme() {
    const stored = localStorage.getItem(THEME_KEY);
    if (stored) {
        return stored;
    }
    return 'light';
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
    root.setAttribute('data-theme', normalized);
    document.body?.setAttribute('data-theme', normalized);
    
    if (normalized === 'dark') {
        root.classList.add('dark');
    } else {
        root.classList.remove('dark');
    }

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
    const root = document.documentElement;
    const current = root.getAttribute('data-theme') || 'light';
    const next = current === 'light' ? 'dark' : 'light';
    applyTheme(next);
}

// Global expose
window.toggleTheme = toggleTheme;
window.applyTheme = applyTheme;

export function showToast(message, type = 'success') {
    if (!window.Swal) {
        return;
    }
    const Toast = window.Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', window.Swal.stopTimer);
            toast.addEventListener('mouseleave', window.Swal.resumeTimer);
        }
    });

    const statusIcons = {
        success: 'success',
        error: 'error',
        warning: 'warning',
        info: 'info'
    };

    Toast.fire({
        icon: statusIcons[type] || 'info',
        title: message
    });
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

    const endpoint = resolveUrl(url);
    const response = await fetch(endpoint, config);
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

export function destroyTable(table) {
    const element = typeof table === 'string' ? document.querySelector(table) : table;
    if (!element || !dataTables.has(element)) {
        return;
    }
    try {
        const existing = dataTables.get(element);
        existing.destroy();
    } catch (e) {
        // ignore if already destroyed or DOM missing
    } finally {
        dataTables.delete(element);
    }
}

export function initTable(table) {
    const element = typeof table === 'string' ? document.querySelector(table) : table;
    if (!element || !window.simpleDatatables) {
        return null;
    }
    
    destroyTable(element);

    const dt = new window.simpleDatatables.DataTable(element, {
        perPage: 10,
        labels: {
            placeholder: "Buscar...",
            perPage: "registros por página",
            noRows: "Nenhum registro encontrado",
            info: "Exibindo {start} a {end} de {rows} registros",
        }
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
    if (document.hasThemeSetup) return;
    document.hasThemeSetup = true;

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
        const action = form.getAttribute('action') || window.location.href;
        const normalizedAction = appPath(action);
        if (typeof normalizedAction === 'string' && normalizedAction !== form.getAttribute('action')) {
            form.setAttribute('action', normalizedAction);
        }
        const data = await request(action, {
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
            const modalId = form.dataset.hideModal;
            const modal = new Modal(modalId);
            modal.hide();
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
    if (document.hasAjaxForms) return;
    document.hasAjaxForms = true;
    document.addEventListener('submit', handleAjaxSubmit);
}

function setupConfirmLinks() {
    if (document.hasConfirmLinks) return;
    document.hasConfirmLinks = true;
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
    document.querySelectorAll('[data-dock-menu] a').forEach((link) => {
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
    list.className = "flex flex-col gap-1";
    items.forEach((item, index) => {
        const element = document.createElement('li');
        element.className = "px-3 py-2 flex items-center justify-between text-sm rounded-lg cursor-pointer transition-colors text-slate-600 dark:text-slate-300";
        if (index === 0) {
            element.classList.add('bg-slate-100', 'dark:bg-slate-800', 'text-primary', 'dark:text-white', 'is-active');
        } else {
            element.classList.add('hover:bg-slate-50', 'dark:hover:bg-slate-800/50');
        }
        element.innerHTML = `
            <span>${item.label}</span>
            ${item.shortcut ? `<kbd class="px-1.5 py-0.5 rounded bg-slate-200 dark:bg-slate-700 text-[10px] text-slate-500 font-bold border border-slate-300 dark:border-slate-600 font-mono">${item.shortcut}</kbd>` : `<span class="text-xs text-slate-400">${item.hint || ''}</span>`}
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
        if (idx === index) {
            item.classList.add('bg-slate-100', 'dark:bg-slate-800', 'text-primary', 'dark:text-white', 'is-active');
            item.classList.remove('hover:bg-slate-50', 'dark:hover:bg-slate-800/50');
            item.scrollIntoView({ block: 'nearest' });
        } else {
            item.classList.remove('bg-slate-100', 'dark:bg-slate-800', 'text-primary', 'dark:text-white', 'is-active');
            item.classList.add('hover:bg-slate-50', 'dark:hover:bg-slate-800/50');
        }
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
            if (CURRENT_USER_ROLE === 'dev') {
                event.preventDefault();
                window.location.assign(resolveUrl('/admin/logs'));
                return;
            }
        }

        if (modifier && event.key.toLowerCase() === 't') {
            event.preventDefault();
            window.location.assign(resolveUrl('/tickets'));
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
                window.location.assign(resolveUrl('/tickets'));
                lastKey = null;
            } else if (lastKey === 'g' && event.key.toLowerCase() === 'l') {
                if (CURRENT_USER_ROLE === 'dev') {
                    event.preventDefault();
                    window.location.assign(resolveUrl('/admin/logs'));
                }
                lastKey = null;
            }
        }
    });
}

function initSidebar() {
    const sidebar = document.querySelector('[data-sidebar]');
    const trigger = document.querySelector('[data-sidebar-trigger]');
    const toggle = document.querySelector('[data-sidebar-toggle]');

    const openSidebar = () => {
        sidebar?.classList.remove('-translate-x-full');
        sidebar?.classList.add('translate-x-0');
        sidebar?.classList.add('is-open');
    };

    const closeSidebar = () => {
        sidebar?.classList.add('-translate-x-full');
        sidebar?.classList.remove('translate-x-0');
        sidebar?.classList.remove('is-open');
    };

    trigger?.addEventListener('click', openSidebar);
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
    if (document.hasGlobalSearch) return;
    const form = document.querySelector('[data-global-search]');
    if (!form) {
        return;
    }
    document.hasGlobalSearch = true;
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

function initNotifications() {
    const list = document.querySelector('[data-notifications-list]');
    const badge = document.querySelector('[data-notifications-badge]');
    const markAllReadBtn = document.querySelector('[data-mark-all-read]');
    
    if (!list || !badge) return;

    const fetchNotifications = async () => {
        try {
            const response = await fetch('/api/notifications/unread', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            
            if (!response.ok) return;
            
            const data = await response.json();
            
            if (data.success && data.notifications) {
                renderNotifications(data.notifications);
            }
        } catch (error) {
            console.error('Failed to fetch notifications', error);
        } finally {
            setTimeout(fetchNotifications, 30000); // Poll every 30s
        }
    };

    const renderNotifications = (notifications) => {
        if (!notifications || notifications.length === 0) {
            badge.classList.add('hidden');
            list.innerHTML = `
                <div class="px-4 py-8 text-center flex flex-col items-center justify-center text-slate-400">
                    <i class="bi bi-bell-slash text-2xl mb-2 opacity-50"></i>
                    <p class="text-xs font-medium">Nenhuma notificação</p>
                </div>
            `;
            return;
        }

        badge.classList.remove('hidden');
        
        let html = '';
        notifications.forEach(notif => {
            html += `
                <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700/50 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors cursor-pointer group">
                    <div class="flex gap-3">
                        <div class="w-8 h-8 rounded-full bg-primary/10 text-primary flex items-center justify-center shrink-0">
                            <i class="bi bi-info-circle"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-bold text-slate-900 dark:text-white mb-0.5 truncate">${escapeHtml(notif.title)}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400 line-clamp-2">${escapeHtml(notif.message)}</p>
                            <p class="text-[10px] text-slate-400 mt-1 font-medium">${new Date(notif.created_at).toLocaleString()}</p>
                        </div>
                    </div>
                </div>
            `;
        });
        
        list.innerHTML = html;
    };
    
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', async (e) => {
            e.preventDefault();
            try {
                const response = await fetch('/api/notifications/read-all', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (response.ok) {
                    renderNotifications([]);
                }
            } catch (err) {
                console.error(err);
            }
        });
    }

    // Start polling
    fetchNotifications();
}

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
}


function initModals() {
    if (document.hasModals) return;
    document.hasModals = true;
    document.addEventListener('click', (event) => {
        // Handle both new and legacy attributes
        const trigger = event.target.closest('[data-modal-target], [data-bs-toggle="modal"]');
        if (trigger) {
            event.preventDefault();
            const modalId = trigger.dataset.modalTarget || trigger.dataset.bsTarget;
            if (modalId) {
                const modal = new Modal(modalId);
                modal.show(trigger);
            }
            return;
        }

        const dismiss = event.target.closest('[data-modal-dismiss], [data-bs-dismiss="modal"]');
        if (dismiss) {
            event.preventDefault();
            const modalElement = dismiss.closest('.modal-vanilla') || dismiss.closest('[role="dialog"]');
            if (modalElement) {
                const modal = new Modal('#' + modalElement.id);
                modal.hide();
            }
            return;
        }

        const backdrop = event.target.matches('[data-modal-backdrop]');
        if (backdrop) {
            document.querySelectorAll('.modal-vanilla:not(.hidden)').forEach(el => {
                const modal = new Modal('#' + el.id);
                modal.hide();
            });
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            document.querySelectorAll('.modal-vanilla:not(.hidden)').forEach(el => {
                const modal = new Modal('#' + el.id);
                modal.hide();
            });
        }
    });
}

function initDropdowns() {
    if (document.hasDropdowns) return;
    document.hasDropdowns = true;
    document.addEventListener('click', (event) => {
        const toggle = event.target instanceof HTMLElement ? event.target.closest('[data-dropdown-toggle]') : null;
        if (toggle) {
            event.preventDefault();
            const targetSelector = toggle.getAttribute('data-dropdown-toggle') || toggle.getAttribute('data-dropdown-target');
            const dropdown = document.querySelector(targetSelector);
            if (dropdown) {
                dropdown.classList.toggle('hidden');
            }
            return;
        }

        // Close all dropdowns when clicking outside
        const openDropdowns = document.querySelectorAll('[id$="-menu"]:not(.hidden), [data-dropdown-panel]:not(.hidden)');
        openDropdowns.forEach((dropdown) => {
            const toggleButton = document.querySelector(`[data-dropdown-toggle="#${dropdown.id}"], [data-dropdown-target="#${dropdown.id}"]`);
            if (toggleButton && !toggleButton.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.add('hidden');
            }
        });
    });
}

function initDockDropdowns() {
    if (document.hasDockDropdowns) return;
    document.hasDockDropdowns = true;
    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-dock-trigger]');
        if (trigger) {
            event.stopPropagation();
            const dropdown = trigger.parentElement.querySelector('[data-dock-menu]');
            if (dropdown) {
                // Close others
                document.querySelectorAll('[data-dock-menu]:not(.hidden)').forEach(d => {
                    if (d !== dropdown) d.classList.add('hidden');
                });
                dropdown.classList.toggle('hidden');
            }
            return;
        }

        // Close when clicking outside
        if (!event.target.closest('[data-dock-menu]')) {
            document.querySelectorAll('[data-dock-menu]:not(.hidden)').forEach(d => {
                d.classList.add('hidden');
            });
        }
    });
}

async function fetchStatus(id, element) {
    try {
        const response = await fetch(`/admin/integrations/${id}/status`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const result = await response.json();
        const stateData = result?.data?.instance || result?.instance || {};
        const status = stateData.state || 'UNKNOWN';
        
        const dot = element.querySelector('span');
        if (dot) {
            dot.className = 'absolute inset-0 rounded-full';
            
            if (status === 'open') {
                dot.classList.add('bg-emerald-500');
                element.title = 'Conectado (OPEN)';
            } else if (status === 'connecting' || status === 'connecting_chat') {
                dot.classList.add('bg-amber-500', 'animate-pulse');
                element.title = 'Conectando...';
            } else {
                dot.classList.add('bg-rose-500');
                element.title = 'Desconectado (' + status + ')';
            }
        }
    } catch (e) {
        console.error('Status check failed:', e);
    }
}

async function handleConnect(id) {
    const modal = document.getElementById('modal-qrcode');
    const container = document.getElementById('qrcode-container');
    if (!modal || !container) return;

    if (window.qrPollTimer) {
        clearTimeout(window.qrPollTimer);
    }

    container.innerHTML = '<div class="flex flex-col items-center text-slate-400 text-xs font-bold gap-2"><div class="w-8 h-8 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>Gerando QR Code...</div>';
    modal.classList.replace('hidden', 'flex');

    const checkConnection = async () => {
        if (modal.classList.contains('hidden')) return;

        try {
            const response = await fetch(`/admin/integrations/${id}/connect`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await response.json();
            
            const qrcodeData = result?.data?.qrcode || result?.qrcode;
            const qrcodeBase64 = qrcodeData?.base64 || result?.data?.base64 || result?.base64;
            const instanceData = result?.data?.instance || result?.instance || result?.data;
            
            if (instanceData?.state === 'open') {
                container.innerHTML = '<div class="text-emerald-500 font-bold text-lg p-4 flex flex-col items-center gap-3"><i class="bi bi-whatsapp text-4xl mb-2"></i>Conectado com Sucesso!</div>';
                setTimeout(() => window.location.reload(), 2000);
                return;
            }

            if (qrcodeBase64) {
                container.innerHTML = `<img src="${qrcodeBase64}" class="w-full h-full object-contain p-2" alt="QR Code">`;
            } else {
                container.innerHTML = '<div class="flex flex-col items-center text-slate-400 text-xs font-bold gap-2 mt-4"><div class="w-8 h-8 border-2 border-slate-300 border-t-transparent rounded-full animate-spin"></div>Aguardando QR Code...</div>';
            }
        } catch (e) {
            console.error('Failed to poll QR code', e);
        }

        window.qrPollTimer = setTimeout(checkConnection, 3000);
    };

    checkConnection();
}

async function handleAction(id, action, confirmMsg) {
    if (!confirm(confirmMsg)) return;
    
    showToast('Processando...', 'info');
    try {
        const response = await fetch(`/admin/integrations/${id}/${action}`, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const result = await response.json();
        if (result.success || result.status === 200 || result.status === 201) {
            showToast('Operação realizada!', 'success');
            setTimeout(() => {
                const indicator = document.querySelector(`.status-indicator[data-instance-id="${id}"]`);
                if (indicator) fetchStatus(id, indicator);
            }, 2000);
        } else {
            showToast(result.message || 'Erro na operação.', 'error');
        }
    } catch (e) {
        showToast('Erro de comunicação.', 'error');
    }
}

function initIntegrationManager() {
    const indicators = document.querySelectorAll('.status-indicator');
    indicators.forEach(indicator => {
        const id = indicator.dataset.instanceId;
        fetchStatus(id, indicator);
    });

    document.querySelectorAll('.action-connect').forEach(btn => {
        btn.onclick = () => handleConnect(btn.dataset.id);
    });

    document.querySelectorAll('.action-restart').forEach(btn => {
        btn.onclick = () => handleAction(btn.dataset.id, 'restart', 'Reiniciar instância?');
    });

    document.querySelectorAll('.action-sync').forEach(btn => {
        btn.onclick = () => handleAction(btn.dataset.id, 'sync', 'Sincronizar Webhooks e configurações?');
    });

    document.querySelectorAll('.action-logout').forEach(btn => {
        btn.onclick = () => handleAction(btn.dataset.id, 'logout', 'Desconectar WhatsApp? Isso removerá a sessão.');
    });
}

function initInstantNavigation() {
    const mainContainer = document.querySelector('main');
    if (!mainContainer) return;

    // Loading bar container
    const loadingBar = document.createElement('div');
    loadingBar.className = 'fixed top-0 left-0 w-full h-[3px] bg-primary z-[200] transition-all duration-300 opacity-0 pointer-events-none origin-left scale-x-0';
    document.body.appendChild(loadingBar);



    window.loadPage = async function loadPage(url, pushState = true) {
        try {
            // Show loading
            loadingBar.classList.remove('opacity-0');
            loadingBar.style.transform = 'scale-x(0.3)';

            const response = await fetch(url, {
                headers: { 'X-SPA-Request': 'true' }
            });

            if (!response.ok) throw new Error('Network response 404');

            loadingBar.style.transform = 'scale-x(0.8)';
            const html = await response.text();
            
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            
            // Replace main content
            const newMain = doc.querySelector('main');
            if (newMain) {
                // Get page title for breadcrumbs/header
                const titleEl = doc.querySelector('h1');
                const breadcrumbsEl = doc.querySelector('nav[class*="text-[9px]"]');
                
                mainContainer.innerHTML = newMain.innerHTML;
                
                // Update title
                document.title = doc.title;
                
                // Update history
                if (pushState) {
                    history.pushState({ url }, doc.title, url);
                }

                // Scroll to top
                window.scrollTo({ top: 0, behavior: 'instant' });

                // Re-evaluate script tags found in the new content
                const scripts = mainContainer.querySelectorAll('script');
                scripts.forEach(oldScript => {
                    const newScript = document.createElement('script');
                    Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
                    newScript.text = oldScript.innerHTML;
                    oldScript.parentNode.replaceChild(newScript, oldScript);
                });

                // Re-initialize scripts
                reInitPage();
            }

            loadingBar.style.transform = 'scale-x(1)';
            setTimeout(() => {
                loadingBar.classList.add('opacity-0');
                setTimeout(() => { loadingBar.style.transform = 'scale-x(0)'; }, 300);
            }, 200);

        } catch (error) {
            console.error('SPA Navigation error:', error);
            window.location.href = url; // Fallback to normal navigation
        }
    }

    function reInitPage() {
        // Re-run all initializations safely
        try {
            setupAjaxForms();
            setupConfirmLinks();
            setupTables();
            initModals();
            initDropdowns();
            initDockDropdowns();
            initWorkspaceTabs();
            initGlobalSearch();
            initHealthWidget();
            setupTheme(); 
            updateActiveStates();
            if (typeof initIntegrationManager === 'function') initIntegrationManager();
        } catch (error) {
            console.error('Page re-initialization failed:', error);
        }
    }

    function updateActiveStates() {
        const currentPath = window.location.pathname;
        const dockTriggers = document.querySelectorAll('[data-dock-trigger]');
        
        document.querySelectorAll('aside nav a').forEach(a => {
            const href = a.getAttribute('href');
            // Aligned with PHP logic in topbar.php
            const isExactMatchPath = href === '/tickets' || href === '/admin' || href === APP_BASE_PATH + '/tickets' || href === APP_BASE_PATH + '/admin';
            
            const isActive = isExactMatchPath 
                ? (currentPath === href || currentPath === href + '/') 
                : (currentPath === href || currentPath.startsWith(href + '/'));
            
            if (isActive) {
                a.classList.add('bg-primary', 'text-white');
                a.classList.remove('text-slate-600', 'dark:text-slate-300', 'hover:bg-slate-100', 'dark:hover:bg-slate-700', 'hover:text-primary');
            } else {
                a.classList.remove('bg-primary', 'text-white');
                a.classList.add('text-slate-600', 'dark:text-slate-300', 'hover:bg-slate-100', 'dark:hover:bg-slate-700');
            }
        });

        // Update group buttons
        dockTriggers.forEach(btn => {
            const menu = btn.parentElement.querySelector('[data-dock-menu]');
            const hasActiveChild = menu && menu.querySelector('a.bg-primary');
            if (hasActiveChild) {
                btn.classList.add('bg-primary/20', 'text-primary', 'shadow-inner');
                btn.classList.remove('text-slate-500', 'dark:text-slate-400', 'hover:bg-slate-100', 'dark:hover:bg-slate-800');
            } else {
                btn.classList.remove('bg-primary/20', 'text-primary', 'shadow-inner');
                btn.classList.add('text-slate-500', 'dark:text-slate-400', 'hover:bg-slate-100', 'dark:hover:bg-slate-800');
            }
        });
    }

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a');
        if (!link) return;

        const url = link.getAttribute('href');
        if (!url || url.startsWith('#') || url.startsWith('javascript:') || link.target === '_blank') return;
        
        // Internal check
        try {
            const targetUrl = new URL(link.href);
            if (targetUrl.origin !== window.location.origin) return;

            event.preventDefault();
            loadPage(link.href);
        } catch(e) {
            // Invalid URL or similar
        }
    });

    window.addEventListener('popstate', (event) => {
        if (event.state && event.state.url) {
            loadPage(event.state.url, false);
        } else {
            // Initial state or manual URL hack
            window.location.reload();
        }
    });
}

normalizeDomLocations();
setupTheme();
setupAjaxForms();
setupConfirmLinks();
setupTables();
initCommandPalette();
initKeyboardShortcuts();
initSidebar();
initHealthWidget();
initWorkspaceTabs();
initGlobalSearch();
initNotifications();
initModals();
initDropdowns();
initDockDropdowns();
initIntegrationManager();
initInstantNavigation();

window.addEventListener('users:refresh', () => {
    if (typeof window.loadPage === 'function') {
        window.loadPage(window.location.href, false);
    } else {
        window.location.reload();
    }
});

window.addEventListener('templates:refresh', () => {
    if (typeof window.loadPage === 'function') {
        window.loadPage(window.location.href, false);
    } else {
        window.location.reload();
    }
});

export default {
    applyTheme,
    toggleTheme,
    showToast,
    confirmAction,
    request,
    initTable,
    reloadTable,
    destroyTable,
};
