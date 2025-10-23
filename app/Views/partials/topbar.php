<?php
$user = auth();
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$breadcrumbs = $breadcrumbs ?? [];
$workspaceTabs = $workspaceTabs ?? [];
$instances = $instances ?? [
    ['value' => 'default', 'label' => 'Instância padrão'],
];

$navItems = [
    ['href' => route_path('/tickets'), 'icon' => 'bi-chat-dots', 'label' => 'Atendimentos'],
    ['href' => route_path('/tickets/today'), 'icon' => 'bi-inboxes', 'label' => 'Fila'],
    ['href' => route_path('/admin/templates'), 'icon' => 'bi-stickies', 'label' => 'Templates', 'roles' => ['admin', 'dev']],
    ['href' => route_path('/admin/logs'), 'icon' => 'bi-activity', 'label' => 'Logs', 'roles' => ['dev']],
    ['href' => route_path('/admin/webhook'), 'icon' => 'bi-plug', 'label' => 'Webhook', 'roles' => ['dev']],
    ['href' => route_path('/admin'), 'icon' => 'bi-speedometer2', 'label' => 'Configurações', 'roles' => ['admin', 'dev']],
    ['href' => route_path('/admin/evolution/chats'), 'icon' => 'bi-lightning-charge', 'label' => 'Admin', 'roles' => ['admin', 'dev']],
];

$navItems = array_filter($navItems, static function (array $item) use ($user): bool {
    $roles = $item['roles'] ?? null;
    if ($roles === null) {
        return true;
    }
    $role = $user->role ?? null;
    return $role !== null && in_array($role, $roles, true);
});

$userName = trim((string) ($user->full_name ?? $user->email ?? 'Usuário'));
$initial = function_exists('mb_substr') ? mb_strtoupper(mb_substr($userName, 0, 1)) : strtoupper(substr($userName, 0, 1));
?>
<aside class="app-sidebar" data-sidebar aria-label="Menu principal">
    <div class="app-sidebar__brand">
        <button class="btn btn-icon btn-outline-secondary app-sidebar__collapse" type="button" data-sidebar-toggle aria-label="Recolher menu">
            <i class="bi bi-layout-sidebar-inset"></i>
        </button>
        <a class="app-sidebar__logo" href="<?= htmlspecialchars(route_path('/tickets'), ENT_QUOTES) ?>">
            <span class="app-sidebar__logo-mark">WA</span>
            <span class="app-sidebar__logo-text">WhatsAtende</span>
        </a>
    </div>
    <nav class="app-sidebar__nav" aria-label="Navegação">
        <ul>
            <?php foreach ($navItems as $item):
                $isActive = $currentPath === $item['href'] || str_starts_with($currentPath, $item['href'] . '/');
            ?>
                <li>
                    <a href="<?= htmlspecialchars($item['href']) ?>" class="app-sidebar__link<?= $isActive ? ' is-active' : '' ?>">
                        <i class="bi <?= htmlspecialchars($item['icon']) ?>" aria-hidden="true"></i>
                        <span><?= htmlspecialchars($item['label']) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>
    <div class="app-sidebar__footer">
        <button type="button" class="btn btn-outline-secondary w-100" data-command-palette aria-label="Abrir Command Palette">
            <i class="bi bi-cursor-text"></i>
            <span>Command Palette</span>
            <kbd>Ctrl</kbd><kbd>K</kbd>
        </button>
    </div>
</aside>
<div class="app-shell__main" data-shell-main>
    <header class="app-header" data-header>
        <div class="app-header__left">
            <button class="btn btn-icon btn-outline-secondary d-inline-flex d-lg-none" type="button" data-sidebar-trigger aria-label="Abrir menu">
                <i class="bi bi-list"></i>
            </button>
            <form class="app-header__search" role="search" data-global-search>
                <i class="bi bi-search" aria-hidden="true"></i>
                <label class="visually-hidden" for="global-search">Busca global</label>
                <input id="global-search" type="search" name="q" placeholder="Buscar tickets, contatos, logs..." autocomplete="off" data-global-search-input>
            </form>
        </div>
        <div class="app-header__right">
            <div class="health-indicator" data-health-indicator>
                <span class="health-indicator__dot" aria-hidden="true"></span>
                <span class="health-indicator__label">Carregando status...</span>
            </div>
            <div class="app-header__control">
                <label for="instance-switcher" class="visually-hidden">Instância Evolution</label>
                <select id="instance-switcher" class="form-select form-select-sm" data-instance-switcher>
                    <?php foreach ($instances as $instance): ?>
                        <option value="<?= htmlspecialchars((string) ($instance['value'] ?? 'default')) ?>">
                            <?= htmlspecialchars((string) ($instance['label'] ?? 'Instância')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="button" class="btn btn-icon btn-outline-secondary" data-theme-toggle title="Alternar tema" aria-label="Alternar tema">
                <i class="bi bi-brightness-high"></i>
            </button>
            <div class="app-header__profile dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="avatar avatar-sm" aria-hidden="true"><?= htmlspecialchars($initial) ?></span>
                    <span class="d-none d-xl-inline"><?= htmlspecialchars($userName) ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li class="dropdown-header">
                        <div class="fw-semibold"><?= htmlspecialchars($userName) ?></div>
                        <small class="text-muted text-capitalize"><?= htmlspecialchars((string) ($user->role ?? '')) ?></small>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="<?= htmlspecialchars(route_path('/logout'), ENT_QUOTES) ?>" data-ajax data-success-redirect="<?= htmlspecialchars(route_path('/login'), ENT_QUOTES) ?>">
                            <button class="dropdown-item" type="submit">
                                <i class="bi bi-box-arrow-right"></i> Sair
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </header>
    <?php if (!empty($breadcrumbs)): ?>
        <nav class="app-breadcrumbs" aria-label="Breadcrumb">
            <ol>
                <?php foreach ($breadcrumbs as $breadcrumb): ?>
                    <li>
                        <?php if (!empty($breadcrumb['href'])): ?>
                            <a href="<?= htmlspecialchars(route_path((string) $breadcrumb['href']), ENT_QUOTES) ?>"><?= htmlspecialchars((string) $breadcrumb['label']) ?></a>
                        <?php else: ?>
                            <span aria-current="page"><?= htmlspecialchars((string) $breadcrumb['label']) ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </nav>
    <?php endif; ?>
    <?php if (!empty($workspaceTabs)): ?>
        <div class="workspace-tabs" role="tablist" data-tabs data-tabs-key="workspace-tabs">
            <?php foreach ($workspaceTabs as $tab): ?>
                <button type="button" role="tab" class="workspace-tabs__tab<?= !empty($tab['active']) ? ' is-active' : '' ?>" data-tab-target="<?= htmlspecialchars((string) ($tab['target'] ?? '')) ?>">
                    <span><?= htmlspecialchars((string) $tab['label']) ?></span>
                    <?php if (!empty($tab['closable'])): ?>
                        <i class="bi bi-x" aria-hidden="true"></i>
                    <?php endif; ?>
                </button>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <div class="app-header__divider"></div>
    <div class="command-backdrop" data-command-backdrop hidden></div>
    <div class="command-dialog" role="dialog" aria-modal="true" aria-hidden="true" data-command-dialog hidden>
        <div class="command-dialog__surface">
            <div class="command-dialog__header">
                <i class="bi bi-search" aria-hidden="true"></i>
                <input type="search" placeholder="Buscar comandos, tickets ou ações" autocomplete="off" data-command-input>
                <button type="button" class="btn btn-icon" data-command-close aria-label="Fechar">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <div class="command-dialog__body" data-command-results></div>
            <div class="command-dialog__footer">
                <div><kbd>Enter</kbd> selecionar</div>
                <div><kbd>Esc</kbd> fechar</div>
            </div>
        </div>
    </div>
    <main class="app-workspace" id="app-workspace" tabindex="-1">
