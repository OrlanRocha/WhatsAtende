<?php
/** @var array<int, array<string, mixed>> $queue */
/** @var array<string, mixed> $nativeChats */
$nativeChats = $nativeChats ?? ['enabled' => false, 'chats' => [], 'error' => null];
$nativeStartEndpoint = route_path($nativeStartEndpoint ?? '/tickets/native/start');
$status = get_flash('auth_status');
$error = get_flash('auth_error');
$pageId = ($pageId ?? 'queue-page');
$queueTitle = $queueTitle ?? 'Fila em tempo real';
$queueDescription = $queueDescription ?? 'Chamados aguardando atribuição atualizam automaticamente a cada 10 segundos.';
$queueEndpoint = route_path($queueEndpoint ?? '/tickets');
$breadcrumbs = $breadcrumbs ?? [
    ['label' => 'Painel', 'href' => route_path('/tickets')],
    ['label' => $queueTitle],
];
$workspaceTabs = $workspaceTabs ?? [
    ['label' => 'Tickets', 'target' => '#queue-pane', 'active' => true],
];
$pageTitle = 'Fila de Chamados · WhatsAtende';
$supportGroups = $supportGroups ?? [];
$userGroups = $userGroups ?? [];
$seriousnessLabels = $seriousnessOptions ?? [
    'information' => 'Informação',
    'low' => 'Baixa',
    'medium' => 'Média',
    'high' => 'Alta',
    'critical' => 'Crítica',
];
include base_path('app/Views/partials/layout-start.php');
include base_path('app/Views/partials/topbar.php');
?>
<div class="space-y-8 pb-12 p-6 rounded-xl bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 shadow-sm" id="<?= htmlspecialchars($pageId) ?>"
     data-support-groups='<?= htmlspecialchars(json_encode($supportGroups, JSON_UNESCAPED_UNICODE)) ?>'
     data-user-groups='<?= htmlspecialchars(json_encode($userGroups, JSON_UNESCAPED_UNICODE)) ?>'
     data-seriousness-options='<?= htmlspecialchars(json_encode($seriousnessLabels, JSON_UNESCAPED_UNICODE)) ?>'>
    
    <?php if (!empty($status) || !empty($error)): ?>
        <div class="alert-stack space-y-3">
            <?php if (!empty($status)): ?>
                <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-100 text-emerald-700 dark:bg-emerald-900/20 dark:border-emerald-800/30 dark:text-emerald-400 flex items-center gap-3">
                    <i class="bi bi-check-circle-fill"></i>
                    <span class="text-sm font-medium"><?= htmlspecialchars($status) ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="p-4 rounded-2xl bg-rose-50 border border-rose-100 text-rose-700 dark:bg-rose-900/20 dark:border-rose-800/30 dark:text-rose-400 flex items-center gap-3">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    <span class="text-sm font-medium"><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Header & Actions -->
    <header class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-black text-slate-900 dark:text-white tracking-tight uppercase leading-none"><?= htmlspecialchars($queueTitle) ?></h1>
            <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest mt-1">Status: Online · Atualizando agora</p>
        </div>
        <div class="flex items-center gap-3">
            <div class="inline-flex p-1 bg-slate-100 dark:bg-slate-800 rounded-2xl shadow-inner">
                <button type="button" class="px-5 py-2 rounded-xl text-sm font-bold transition-all bg-white dark:bg-slate-700 text-primary shadow-sm" data-view-toggle="table">
                    <i class="bi bi-list-check"></i> <span class="hidden md:inline ml-1">Lista</span>
                </button>
                <button type="button" class="px-5 py-2 rounded-xl text-sm font-bold transition-all text-slate-500 hover:text-slate-700 dark:hover:text-slate-300" data-view-toggle="kanban">
                    <i class="bi bi-columns-gap"></i> <span class="hidden md:inline ml-1">Kanban</span>
                </button>
            </div>
            <button class="w-10 h-10 flex items-center justify-center bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl text-slate-600 dark:text-slate-300 hover:border-primary transition-all shadow-sm" data-refresh-queue>
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
    </header>

    <!-- Toolbar & Filters -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 bg-white dark:bg-slate-900 p-6 rounded-[2rem] border border-slate-200 dark:border-slate-800 shadow-sm">
        <div class="flex flex-wrap items-center gap-2" data-filter-group>
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mr-2">Filtros:</span>
            <button type="button" class="px-4 py-1.5 rounded-full text-xs font-bold transition-all bg-primary text-white shadow-lg shadow-primary/20" data-saved-filter='{"status":null,"hide_resolved":true}'>Entrada</button>
            <button type="button" class="px-4 py-1.5 rounded-full text-xs font-bold transition-all bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200" data-saved-filter='{"mine":true,"hide_resolved":true}'>Meus</button>
            <button type="button" class="px-4 py-1.5 rounded-full text-xs font-bold transition-all bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200" data-saved-filter='{"status":"assigned"}'>Ativos</button>
            <button type="button" class="px-4 py-1.5 rounded-full text-xs font-bold transition-all bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200" data-saved-filter='{"status":"resolved"}'>Resolvidos</button>
        </div>
        
        <div class="flex flex-wrap items-center gap-3">
            <div class="relative group min-w-[200px]">
                <i class="bi bi-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-primary transition-colors"></i>
                <input type="search" placeholder="Buscar ticket ou contato..." data-queue-search
                    class="w-full pl-11 pr-4 py-2.5 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-100 dark:border-slate-800 outline-none focus:ring-2 focus:ring-primary/20 transition-all text-sm">
            </div>
            <div class="relative min-w-[160px]">
                <select class="w-full appearance-none pl-4 pr-10 py-2.5 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-100 dark:border-slate-800 outline-none focus:ring-2 focus:ring-primary/20 transition-all text-sm font-medium cursor-pointer" data-group-filter>
                    <option value="">Filas</option>
                    <?php foreach ($supportGroups as $group): ?>
                        <option value="<?= htmlspecialchars((string) ($group['id'] ?? '')) ?>"><?= htmlspecialchars((string) ($group['name'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
                <i class="bi bi-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-[10px]"></i>
            </div>
            <button type="button" class="px-4 py-2.5 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-100 dark:border-slate-800 text-slate-500 hover:text-primary transition-all text-sm font-medium" data-density-toggle>
                <i class="bi bi-view-stacked"></i>
            </button>
        </div>
    </div>

    <!-- Metrics Grid -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 workspace-metrics" aria-live="polite">
        <div class="bg-blue-50/50 dark:bg-blue-900/10 p-5 rounded-xl border border-blue-100/50 dark:border-blue-900/20" data-summary="open">
            <span class="block text-[10px] font-bold text-blue-400 uppercase tracking-widest mb-1">Abertos</span>
            <span class="text-2xl font-black text-blue-700 dark:text-blue-300 metric-value">0</span>
        </div>
        <div class="bg-amber-50/50 dark:bg-amber-900/10 p-5 rounded-xl border border-amber-100/50 dark:border-amber-900/20" data-summary="assigned">
            <span class="block text-[10px] font-bold text-amber-400 uppercase tracking-widest mb-1">Em curso</span>
            <span class="text-2xl font-black text-amber-700 dark:text-amber-300 metric-value">0</span>
        </div>
        <div class="bg-purple-50/50 dark:bg-purple-900/10 p-5 rounded-xl border border-purple-100/50 dark:border-purple-900/20" data-summary="waiting">
            <span class="block text-[10px] font-bold text-purple-400 uppercase tracking-widest mb-1">Aguardando</span>
            <span class="text-2xl font-black text-purple-700 dark:text-purple-300 metric-value">0</span>
        </div>
        <div class="bg-emerald-50/50 dark:bg-emerald-900/10 p-5 rounded-xl border border-emerald-100/50 dark:border-emerald-900/20" data-summary="resolved">
            <span class="block text-[10px] font-bold text-emerald-400 uppercase tracking-widest mb-1">Resolvidos</span>
            <span class="text-2xl font-black text-emerald-700 dark:text-emerald-300 metric-value">0</span>
        </div>
        <div class="bg-rose-50/50 dark:bg-rose-900/10 p-5 rounded-xl border border-rose-100/50 dark:border-rose-900/20 col-span-2 md:col-span-1" data-summary="breach">
            <span class="block text-[10px] font-bold text-rose-400 uppercase tracking-widest mb-1">SLA Crítico</span>
            <span class="text-2xl font-black text-rose-700 dark:text-rose-300 metric-value">0</span>
        </div>
    </div>

    <!-- Main Views Content -->
    <div id="queue-pane" class="space-y-8">
        <!-- Table View -->
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden" data-view="table">
            <div class="overflow-x-auto">
                <table class="w-full text-left" id="queue-table" data-table data-density="comfortable">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-950/50">
                            <th class="px-8 py-5 text-[11px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 dark:border-slate-800">Protocolo</th>
                            <th class="px-6 py-5 text-[11px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 dark:border-slate-800">Contato</th>
                            <th class="px-6 py-5 text-[11px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 dark:border-slate-800">Status</th>
                            <th class="px-6 py-5 text-[11px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 dark:border-slate-800 whitespace-nowrap">Grupo / SLA</th>
                            <th class="px-6 py-5 text-[11px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 dark:border-slate-800 text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <?php foreach ($queue as $ticket): ?>
                            <?php $serKey = strtolower((string) ($ticket['seriousness'] ?? 'information')); ?>
                            <tr class="group hover:bg-slate-50/50 dark:hover:bg-slate-800/60 transition-colors">
                                <td class="px-8 py-5 text-sm font-bold text-slate-400 group-hover:text-primary transition-colors">#<?= htmlspecialchars((string) ($ticket['id'] ?? '')) ?></td>
                                <td class="px-6 py-5">
                                    <div class="flex items-center gap-3">
                                        <div class="relative">
                                            <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-400">
                                                <i class="bi bi-person text-lg"></i>
                                            </div>
                                            <div class="absolute -bottom-1 -right-1 w-3 h-3 rounded-full border-2 border-white dark:border-slate-900 bg-slate-300" data-presence="offline"></div>
                                        </div>
                                        <div>
                                            <div class="text-sm font-bold text-slate-900 dark:text-white"><?= htmlspecialchars((string) ($ticket['contact_name'] ?? '')) ?></div>
                                            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider"><?= htmlspecialchars((string) ($ticket['channel'] ?? 'whatsapp')) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-5">
                                    <span class="inline-flex px-3 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                                        <?= htmlspecialchars((string) ($ticket['status'] ?? 'open')) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-5">
                                    <div class="flex flex-col gap-1">
                                        <span class="text-xs font-bold text-slate-700 dark:text-slate-300"><?= htmlspecialchars((string) ($ticket['group_name'] ?? 'Padrão')) ?></span>
                                        <span class="text-[10px] text-emerald-600 font-bold uppercase" data-sla="ok">No prazo</span>
                                    </div>
                                </td>
                                <td class="px-8 py-5 text-end">
                                    <button class="inline-flex items-center gap-2 px-5 py-2 bg-emerald-500 hover:bg-emerald-600 dark:bg-emerald-600 hover:scale-105 text-white rounded-xl text-xs font-bold shadow-lg shadow-emerald-500/20 transition-all active:scale-95" 
                                            data-assign data-ticket="<?= htmlspecialchars((string) ($ticket['id'] ?? '')) ?>">
                                        <i class="bi bi-headset"></i> ATENDER
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Kanban View -->
        <div data-view="kanban" hidden>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6" data-kanban>
                <?php foreach (['open', 'assigned', 'waiting', 'resolved'] as $col): ?>
                    <div class="flex flex-col gap-4">
                        <header class="flex items-center justify-between px-2">
                            <h3 class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em]"><?= ucfirst($col) ?></h3>
                            <span class="w-6 h-6 rounded-lg bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-[10px] font-black text-slate-400" data-kanban-count="<?= $col ?>">0</span>
                        </header>
                        <div class="flex-1 min-h-[500px] border-2 border-dashed border-slate-100 dark:border-slate-800/50 rounded-[2rem] p-4 space-y-4" data-kanban-list="<?= $col ?>">
                            <!-- Cards inserted by JS -->
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Native Chats Section -->
    <?php if (!empty($nativeChats['enabled'])): ?>
        <section class="mt-12 space-y-6 pt-12 border-t border-slate-100 dark:border-slate-800" data-native-chats data-config='<?= htmlspecialchars(json_encode(['chats' => $nativeChats['chats'] ?? []], JSON_UNESCAPED_UNICODE)) ?>'>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold text-slate-900 dark:text-white">Conversas Pendentes (Evolution)</h2>
                    <p class="text-sm text-slate-500 mt-1">Sincronização direta com a API nativa.</p>
                </div>
                <span class="px-4 py-1.5 rounded-full bg-slate-100 dark:bg-slate-800 text-[10px] font-bold text-slate-500 uppercase tracking-widest" data-native-count>
                    <?= count($nativeChats['chats'] ?? []) ?> aguardando
                </span>
            </div>
            
            <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
                <div class="alert alert-warning hidden p-6 text-amber-600 bg-amber-50" data-native-error></div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-slate-950/50 border-b border-slate-100 dark:border-slate-800">
                                <th class="px-8 py-5 text-[11px] font-bold text-slate-400 uppercase tracking-widest">Perf.</th>
                                <th class="px-2 py-5 text-[11px] font-bold text-slate-400 uppercase tracking-widest">Contato</th>
                                <th class="px-6 py-5 text-[11px] font-bold text-slate-400 uppercase tracking-widest">Não lidas</th>
                                <th class="px-6 py-5 text-[11px] font-bold text-slate-400 uppercase tracking-widest">Última msg</th>
                                <th class="px-8 py-5 text-[11px] font-bold text-slate-400 uppercase tracking-widest text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody data-native-body class="divide-y divide-slate-100 dark:divide-slate-800"></tbody>
                    </table>
                </div>
            </div>
        </section>
    <?php endif; ?>
</div>

<script type="module">
import { initQueue } from <?= json_encode(route_path('/js/modules/queue.js')) ?>;
const pageSelector = <?= json_encode('#' . $pageId) ?>;
const endpoint = <?= json_encode($queueEndpoint) ?>;
const nativeConfig = <?= json_encode([
    'enabled' => (bool) ($nativeChats['enabled'] ?? false),
    'chats' => $nativeChats['chats'] ?? [],
    'error' => $nativeChats['error'] ?? null,
    'startEndpoint' => $nativeStartEndpoint,
], JSON_UNESCAPED_UNICODE) ?>;
initQueue(pageSelector, endpoint, nativeConfig);
</script>
<?php include base_path('app/Views/partials/layout-end.php'); ?>
