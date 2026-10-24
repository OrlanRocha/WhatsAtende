<?php
$user = auth();
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$navGroups = [
    'atendimento' => [
        'icon' => 'bi-chat-dots',
        'label' => 'Atendimento',
        'items' => [
            ['href' => route_path('/tickets'), 'icon' => 'bi-chat-dots', 'label' => 'Chats'],
            ['href' => route_path('/tickets/mine'), 'icon' => 'bi-person-check', 'label' => 'Meus'],
            ['href' => route_path('/tickets/today'), 'icon' => 'bi-inbox', 'label' => 'Fila'],
        ]
    ],
    'admin' => [
        'icon' => 'bi-grid',
        'label' => 'Gestão',
        'roles' => ['admin', 'dev', 'supervisor'],
        'items' => [
            ['href' => route_path('/admin/templates'), 'icon' => 'bi-stickies', 'label' => 'Templates', 'roles' => ['admin', 'dev']],
            ['href' => route_path('/admin/groups'), 'icon' => 'bi-diagram-3', 'label' => 'Grupos', 'roles' => ['admin', 'dev']],
            ['href' => route_path('/admin/users'), 'icon' => 'bi-people', 'label' => 'Usuários', 'roles' => ['admin', 'dev']],
            ['href' => route_path('/admin/reports'), 'icon' => 'bi-graph-up', 'label' => 'Relatórios'],
            ['href' => route_path('/admin/avaliacoes'), 'icon' => 'bi-star', 'label' => 'Avaliações'],
        ]
    ],
    'config' => [
        'icon' => 'bi-gear',
        'label' => 'Ajustes',
        'roles' => ['admin', 'dev'],
        'items' => [
            ['href' => route_path('/admin/integrations'), 'icon' => 'bi-lightning-charge', 'label' => 'Instâncias'],
            ['href' => route_path('/admin/settings'), 'icon' => 'bi-shield-check', 'label' => 'Regras'],
            ['href' => route_path('/admin'), 'icon' => 'bi-gear', 'label' => 'Geral'],
        ]
    ],
];

// Helper to check active state more accurately
$isActivePath = static function(string $path, string $currentPath): bool {
    $adminPath = route_path('/admin');
    $ticketsPath = route_path('/tickets');
    
    // For top-level specific paths, use exact match
    if ($path === $ticketsPath || $path === $adminPath) {
        return $currentPath === $path || $currentPath === $path . '/';
    }
    
    // For others (like sub-routes), use prefix match
    return $currentPath === $path || str_starts_with($currentPath, $path . '/');
};

// Filter items by role
foreach ($navGroups as $key => &$navGroup) {
    if (isset($navGroup['roles']) && !in_array($user->role ?? null, $navGroup['roles'], true)) {
        unset($navGroups[$key]);
        continue;
    }
    $navGroup['items'] = array_filter($navGroup['items'], static function (array $item) use ($user): bool {
        $roles = $item['roles'] ?? null;
        return $roles === null || in_array($user->role ?? null, $roles, true);
    });
    if (empty($navGroup['items'])) {
        unset($navGroups[$key]);
    }
}
unset($navGroup);

$userName = (string) ($user->full_name ?? 'Usuário');
$initial = mb_strtoupper(mb_substr($userName, 0, 1));
?>

<!-- Dock Sidebar -->
<aside class="fixed bottom-4 left-1/2 -translate-x-1/2 lg:left-4 lg:translate-x-0 lg:top-4 lg:bottom-4 z-[100] w-[95%] sm:w-auto lg:w-20 transition-all duration-500" data-sidebar>
    <div class="h-full bg-white/90 dark:bg-slate-900/90 backdrop-blur-2xl border border-white/20 dark:border-slate-800/50 rounded-[2.5rem] shadow-2xl flex lg:flex-col items-center justify-between p-2 lg:p-3 overflow-visible">
        
        <a href="<?= route_path('/tickets') ?>" class="hidden lg:flex w-12 h-12 rounded-2xl bg-primary items-center justify-center text-white font-bold text-xl shadow-lg shadow-primary/30 hover:scale-105 transition-transform duration-300">
            WA
        </a>

        <nav class="flex lg:flex-col items-center gap-3 flex-1 lg:mt-8">
            <?php foreach ($navGroups as $navGroup): 
                // Check if any item in group is active
                $groupActive = false;
                foreach ($navGroup['items'] as $item) {
                    if ($isActivePath($item['href'], $currentPath)) {
                        $groupActive = true;
                        break;
                    }
                }
            ?>
                <div class="relative flex items-center justify-center">
                    <button class="w-12 h-12 flex items-center justify-center rounded-2xl transition-all duration-300 <?= $groupActive ? 'bg-primary/20 text-primary shadow-inner' : 'text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800' ?>" data-dock-trigger>
                        <i class="<?= $navGroup['icon'] ?> text-xl"></i>
                    </button>
                    
                    <!-- JS-Controlled Dropdown -->
                    <div class="absolute left-1/2 -translate-x-1/2 bottom-16 lg:left-14 lg:top-0 lg:translate-x-0 lg:bottom-auto hidden animate-in fade-in slide-in-from-left-2 duration-200 z-[110]" data-dock-menu>
                        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-xl p-2 min-w-[160px]">
                            <p class="text-[9px] font-black uppercase tracking-widest text-slate-400 px-3 py-1 mb-1 border-b border-slate-100 dark:border-slate-700/50"><?= $navGroup['label'] ?></p>
                            <?php foreach ($navGroup['items'] as $item): 
                                $isActive = $isActivePath($item['href'], $currentPath);
                            ?>
                                <a href="<?= htmlspecialchars((string)$item['href']) ?>" 
                                   class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-bold transition-colors <?= $isActive ? 'bg-primary text-white' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-primary' ?>">
                                    <i class="<?= $item['icon'] ?> text-sm"></i>
                                    <span><?= htmlspecialchars((string)$item['label']) ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </nav>

        <div class="flex lg:flex-col items-center gap-2 lg:mt-auto relative">
            <!-- Notifications Dropdown -->
            <div class="relative flex items-center justify-center">
                <button class="w-12 h-12 flex items-center justify-center rounded-2xl text-slate-500 hover:text-primary transition-colors relative" data-dock-trigger title="Notificações">
                    <i class="bi bi-bell text-lg"></i>
                    <!-- Badge (will be toggled by JS) -->
                    <span class="absolute top-3 right-3 w-2 h-2 bg-red-500 rounded-full shadow-[0_0_0_2px_rgba(255,255,255,1)] dark:shadow-[0_0_0_2px_rgba(15,23,42,1)] hidden" data-notifications-badge></span>
                </button>
                
                <div class="absolute left-1/2 -translate-x-1/2 bottom-16 lg:left-14 lg:translate-x-0 lg:top-auto hidden animate-in fade-in slide-in-from-left-2 duration-200 z-[110] w-[300px]" 
                     style="bottom: 100% !important; margin-bottom: 8px !important;"
                     data-dock-menu>
                    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-xl overflow-hidden flex flex-col">
                        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700/50 flex items-center justify-between bg-slate-50/50 dark:bg-slate-800/50">
                            <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-wider">Notificações</h3>
                            <button class="text-[10px] text-primary hover:underline font-bold" data-mark-all-read>Marcar lidas</button>
                        </div>
                        
                        <div class="max-h-[300px] overflow-y-auto custom-scrollbar flex flex-col" data-notifications-list>
                            <!-- Notifications will be injected here via JS -->
                            <div class="px-4 py-8 text-center flex flex-col items-center justify-center text-slate-400">
                                <i class="bi bi-bell-slash text-2xl mb-2 opacity-50"></i>
                                <p class="text-xs font-medium">Nenhuma notificação</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <button type="button" class="w-12 h-12 flex items-center justify-center rounded-2xl text-slate-500 hover:text-primary transition-colors" onclick="toggleTheme()" title="Alternar Tema">
                <i class="bi bi-sun text-lg"></i>
            </button>
            
            <div class="relative flex items-center justify-center">
                <button class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-primary to-blue-400 flex items-center justify-center text-white font-bold cursor-pointer hover:scale-105 transition-transform" data-dock-trigger>
                    <?= $initial ?>
                </button>
                
                <!-- User Profile Dropdown -->
                <div class="absolute left-1/2 -translate-x-1/2 bottom-16 lg:left-14 lg:translate-x-0 lg:top-auto hidden animate-in fade-in slide-in-from-left-2 duration-200 z-[110]" 
                     style="bottom: 100% !important; margin-bottom: 8px !important;"
                     data-dock-menu>
                    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-xl p-2 min-w-[160px]">
                        <div class="px-3 py-2 border-b border-slate-100 dark:border-slate-700/50 mb-1">
                            <p class="text-[10px] font-black text-slate-900 dark:text-white truncate"><?= htmlspecialchars($userName) ?></p>
                            <p class="text-[8px] font-medium text-slate-400 truncate mt-0.5 uppercase tracking-wider"><?= htmlspecialchars($user->role ?? 'Membro') ?></p>
                        </div>
                        
                        <a href="<?= route_path('/profile') ?>" 
                           class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-primary transition-colors">
                            <i class="bi bi-person text-sm"></i>
                            <span>Meu Perfil</span>
                        </a>
                        
                        <div class="h-px bg-slate-100 dark:bg-slate-700/50 my-1"></div>
                        
                        <a href="<?= route_path('/logout') ?>" 
                           class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-bold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/20 transition-colors">
                            <i class="bi bi-box-arrow-right text-sm"></i>
                            <span>Sair</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</aside>

<!-- Top Search -->
<header class="fixed top-4 left-1/2 -translate-x-1/2 lg:left-28 lg:translate-x-0 z-40 w-[90%] sm:w-auto lg:right-6 flex items-center justify-between pointer-events-none">
    <div class="flex-1 max-w-sm pointer-events-auto">
        <form class="h-10 bg-white/70 dark:bg-slate-900/70 backdrop-blur-xl border border-white/20 dark:border-slate-800/50 rounded-xl shadow-xl flex items-center px-4 gap-2 focus-within:ring-2 focus-within:ring-primary/20 transition-all" action="<?= route_path('/search') ?>" data-global-search>
            <i class="bi bi-search text-slate-400 text-xs"></i>
            <input type="text" name="q" class="bg-transparent border-0 ring-0 focus:ring-0 text-xs text-slate-700 dark:text-slate-200 w-full placeholder:text-slate-400 font-bold" placeholder="Buscar..." data-global-search-input>
            <kbd class="hidden sm:block px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-[9px] text-slate-400 font-black border border-slate-200 dark:border-slate-700">K</kbd>
        </form>
    </div>
</header>

<main class="flex-1 min-w-0 lg:pl-28 lg:pt-16 pt-16 pb-28 lg:pb-8 px-4 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between mb-8">
        <div>
            <nav class="flex items-center gap-2 text-[9px] font-black uppercase tracking-tighter text-slate-400 mb-1">
                <a href="<?= route_path('/') ?>" class="hover:text-primary transition-colors">Home</a>
                <?php if (isset($breadcrumbs) && is_array($breadcrumbs)): ?>
                    <?php foreach ($breadcrumbs as $crumb): ?>
                        <i class="bi bi-chevron-right text-[7px] opacity-50"></i>
                        <?php if (isset($crumb['href'])): ?>
                            <a href="<?= htmlspecialchars((string)$crumb['href']) ?>" class="hover:text-primary transition-colors"><?= htmlspecialchars((string)$crumb['label']) ?></a>
                        <?php else: ?>
                            <span class="text-slate-300 dark:text-slate-600"><?= htmlspecialchars((string)$crumb['label']) ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </nav>
            <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight leading-none"><?= htmlspecialchars($pageTitle) ?></h1>
        </div>
    </div>
