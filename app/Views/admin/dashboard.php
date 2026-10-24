<?php
/** @var array<string, mixed> $summary */
/** @var array<int, array<string, mixed>> $recentTickets */
/** @var array<int, array<string, mixed>> $channels */
/** @var array<int, array<string, mixed>> $leaderboard */
/** @var array<int, array<string, mixed>> $queue */
$breadcrumbs = [
    ['label' => 'Admin', 'href' => '/admin'],
    ['label' => 'Dashboard'],
];
$pageTitle = 'Dashboard · WhatsAtende';
include base_path('app/Views/partials/layout-start.php');
include base_path('app/Views/partials/topbar.php');
?>
<div class="space-y-10 pb-12" id="dashboard" data-dashboard>
    <!-- Header Section -->
    <header class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Visão operacional</h1>
            <p class="text-slate-500 dark:text-slate-400 mt-1">Acompanhe indicadores chave, canais ativos e desempenho por equipe.</p>
        </div>
        <div class="flex items-center gap-3">
            <button type="button" 
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl text-sm font-bold text-slate-700 dark:text-slate-200 hover:border-primary transition-all shadow-sm" 
                data-bs-toggle="modal" data-bs-target="#groupTargetsModal" data-group-targets-trigger>
                <i class="bi bi-sliders text-primary"></i> 
                <span>Metas TMA/TME</span>
            </button>
        </div>
    </header>

    <!-- KPI Cards Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Open Tickets -->
        <div class="group relative overflow-hidden rounded-[2rem] bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-blue-950/20 dark:to-indigo-950/20 p-8 border border-blue-100 dark:border-blue-900/30 transition-all hover:shadow-xl hover:shadow-blue-500/10">
            <div class="absolute top-0 right-0 -m-4 w-24 h-24 bg-blue-500/10 blur-3xl rounded-full group-hover:bg-blue-500/20 transition-all"></div>
            <div class="flex items-center justify-between mb-6">
                <span class="text-xs font-bold text-blue-600 dark:text-blue-400 uppercase tracking-widest">Abertos</span>
                <div class="w-10 h-10 rounded-xl bg-white dark:bg-slate-800 flex items-center justify-center shadow-sm text-blue-600">
                    <i class="bi bi-inbox text-xl"></i>
                </div>
            </div>
            <div class="flex items-baseline gap-2">
                <h2 class="text-5xl font-bold text-blue-700 dark:text-blue-300 tracking-tighter" style="font-family: 'Outfit';"><?= (int) ($summary['open'] ?? 0) ?></h2>
                <span class="text-sm font-bold text-blue-600/60 dark:text-blue-400/60 uppercase tracking-widest">Ativos</span>
            </div>
        </div>

        <!-- Assigned Tickets -->
        <div class="group relative overflow-hidden rounded-[2rem] bg-gradient-to-br from-amber-50 to-orange-50 dark:from-amber-950/20 dark:to-orange-950/20 p-8 border border-amber-100 dark:border-amber-900/30 transition-all hover:shadow-xl hover:shadow-amber-500/10">
            <div class="absolute top-0 right-0 -m-4 w-24 h-24 bg-amber-500/10 blur-3xl rounded-full group-hover:bg-amber-500/20 transition-all"></div>
            <div class="flex items-center justify-between mb-6">
                <span class="text-xs font-bold text-amber-600 dark:text-amber-400 uppercase tracking-widest">Em atendimento</span>
                <div class="w-10 h-10 rounded-xl bg-white dark:bg-slate-800 flex items-center justify-center shadow-sm text-amber-600">
                    <i class="bi bi-lightning text-xl"></i>
                </div>
            </div>
            <div class="flex items-baseline gap-2">
                <h2 class="text-5xl font-bold text-amber-700 dark:text-amber-300 tracking-tighter" style="font-family: 'Outfit';"><?= (int) ($summary['assigned'] ?? 0) ?></h2>
                <span class="text-sm font-bold text-amber-600/60 dark:text-amber-400/60 uppercase tracking-widest">Agentes</span>
            </div>
        </div>

        <!-- Resolved Today -->
        <div class="group relative overflow-hidden rounded-[2rem] bg-gradient-to-br from-emerald-50 to-teal-50 dark:from-emerald-950/20 dark:to-teal-950/20 p-8 border border-emerald-100 dark:border-emerald-900/30 transition-all hover:shadow-xl hover:shadow-emerald-500/10">
            <div class="absolute top-0 right-0 -m-4 w-24 h-24 bg-emerald-500/10 blur-3xl rounded-full group-hover:bg-emerald-500/20 transition-all"></div>
            <div class="flex items-center justify-between mb-6">
                <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-widest">Resolvidos hoje</span>
                <div class="w-10 h-10 rounded-xl bg-white dark:bg-slate-800 flex items-center justify-center shadow-sm text-emerald-600">
                    <i class="bi bi-check2-circle text-xl"></i>
                </div>
            </div>
            <div class="flex items-baseline gap-2">
                <h2 class="text-5xl font-bold text-emerald-700 dark:text-emerald-300 tracking-tighter" style="font-family: 'Outfit';"><?= (int) ($summary['resolved_today'] ?? 0) ?></h2>
                <span class="text-sm font-bold text-emerald-600/60 dark:text-emerald-400/60 uppercase tracking-widest">Pronto</span>
            </div>
        </div>

        <!-- Average Time -->
        <div class="group relative overflow-hidden rounded-[2rem] bg-gradient-to-br from-indigo-50 to-purple-50 dark:from-indigo-950/20 dark:to-purple-950/20 p-8 border border-indigo-100 dark:border-indigo-900/30 transition-all hover:shadow-xl hover:shadow-indigo-500/10">
            <div class="absolute top-0 right-0 -m-4 w-24 h-24 bg-indigo-500/10 blur-3xl rounded-full group-hover:bg-indigo-500/20 transition-all"></div>
            <div class="flex items-center justify-between mb-6">
                <span class="text-xs font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-widest">TMA Médio</span>
                <div class="w-10 h-10 rounded-xl bg-white dark:bg-slate-800 flex items-center justify-center shadow-sm text-indigo-600">
                    <i class="bi bi-clock-history text-xl"></i>
                </div>
            </div>
            <div class="flex items-baseline gap-2">
                <h2 class="text-5xl font-bold text-indigo-700 dark:text-indigo-300 tracking-tighter" style="font-family: 'Outfit';"><?= $summary['average_resolution_minutes'] !== null ? htmlspecialchars((string) $summary['average_resolution_minutes']) : '--' ?></h2>
                <span class="text-sm font-bold text-indigo-600/60 dark:text-indigo-400/60 uppercase tracking-widest">Minutos</span>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Recent Tickets Table -->
        <div class="lg:col-span-2 flex flex-col bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
            <div class="flex items-center justify-between px-8 py-6 border-b border-slate-100 dark:border-slate-800">
                <h5 class="text-lg font-bold text-slate-800 dark:text-white">Chamados Recentes</h5>
                <a href="<?= route_path('/admin/tickets') ?>" class="inline-flex items-center gap-2 text-sm font-bold text-primary hover:underline">
                    Ver todos <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse" id="recent-tickets" data-table>
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-950/50">
                            <th class="px-8 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-widest">#</th>
                            <th class="px-6 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-widest">Contato</th>
                            <th class="px-6 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-widest">Status</th>
                            <th class="px-6 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-widest">Canal</th>
                            <th class="px-8 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-widest">Aberto em</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <?php foreach ($recentTickets as $ticket): ?>
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                                <td class="px-8 py-4 text-sm font-bold text-slate-400">#<?= htmlspecialchars((string) $ticket['id']) ?></td>
                                <td class="px-6 py-4 text-sm font-bold text-slate-900 dark:text-slate-200"><?= htmlspecialchars((string) ($ticket['contact_name'] ?? '')) ?></td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex px-3 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-[10px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-widest">
                                        <?= htmlspecialchars((string) ($ticket['status'] ?? '')) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-500 dark:text-slate-400 capitalize"><?= htmlspecialchars((string) ($ticket['channel'] ?? 'whatsapp')) ?></td>
                                <td class="px-8 py-4 text-sm text-slate-500 dark:text-slate-400"><?= htmlspecialchars(date('d/m H:i', strtotime($ticket['opened_at'] ?? 'now'))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Sidebar Widgets -->
        <div class="space-y-8">
            <!-- Active Channels -->
            <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm p-8">
                <h6 class="text-sm font-bold text-slate-400 uppercase tracking-widest mb-6 border-b border-slate-100 dark:border-slate-800 pb-4">Canais Ativos</h6>
                <div class="space-y-4" id="channel-list">
                    <?php foreach ($channels as $channel): ?>
                        <div class="flex items-center justify-between p-4 rounded-2xl bg-slate-50 dark:bg-slate-950/50 border border-slate-100 dark:border-slate-800/30">
                            <span class="text-sm font-bold text-slate-700 dark:text-slate-300 capitalize flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center"><i class="bi bi-broadcast"></i></div>
                                <?= htmlspecialchars((string) ($channel['channel'] ?? '')) ?>
                            </span>
                            <span class="px-3 py-1 rounded-lg bg-primary text-white text-xs font-bold italic"><?= (int) ($channel['total'] ?? 0) ?></span>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($channels)): ?>
                        <p class="text-sm text-slate-400 text-center py-4">Nenhum canal ativo no momento.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Leaderboard -->
            <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm p-8">
                <h6 class="text-sm font-bold text-slate-400 uppercase tracking-widest mb-6 border-b border-slate-100 dark:border-slate-800 pb-4">Ranking de Atendentes</h6>
                <div class="space-y-4" id="leaderboard">
                    <?php foreach ($leaderboard as $index => $agent): ?>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-xs font-bold text-slate-500"><?= $index + 1 ?></div>
                                <span class="text-sm font-bold text-slate-700 dark:text-slate-300"><?= htmlspecialchars((string) ($agent['full_name'] ?? '')) ?></span>
                            </div>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-emerald-50 dark:bg-emerald-950/30 text-emerald-600 dark:text-emerald-400 text-xs font-bold underline">
                                <i class="bi bi-award"></i> <?= (int) ($agent['resolved'] ?? 0) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($leaderboard)): ?>
                        <p class="text-sm text-slate-400 text-center py-4 italic">Aguardando atendimentos...</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- RT Queue Table -->
    <div class="flex flex-col bg-slate-950 text-white rounded-xl shadow-2xl overflow-hidden border border-slate-800">
        <div class="flex items-center justify-between px-8 py-6 border-b border-white/5 bg-white/5 backdrop-blur-md">
            <div>
                <h5 class="text-lg font-bold">Fila em tempo real</h5>
                <p class="text-[10px] text-slate-500 font-bold uppercase tracking-widest mt-1">Atualização automática</p>
            </div>
            <a href="<?= route_path('/tickets') ?>" class="inline-flex items-center gap-2 px-5 py-2 bg-white/10 hover:bg-white/20 rounded-xl text-xs font-bold transition-all">
                <i class="bi bi-people"></i> Abrir fila
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left" id="queue-table" data-table data-refresh="/tickets">
                <thead>
                    <tr>
                        <th class="px-8 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">#</th>
                        <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Contato</th>
                        <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Canal</th>
                        <th class="px-8 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Aberto em</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    <?php foreach ($queue as $ticket): ?>
                        <tr class="hover:bg-white/[0.02] transition-colors">
                            <td class="px-8 py-4 text-sm font-bold text-slate-500">#<?= htmlspecialchars((string) ($ticket['id'] ?? '')) ?></td>
                            <td class="px-6 py-4 text-sm font-bold text-slate-100"><?= htmlspecialchars((string) ($ticket['contact_name'] ?? '')) ?></td>
                            <td class="px-6 py-4 text-sm text-slate-400 uppercase tracking-wider italic"><?= htmlspecialchars((string) ($ticket['channel'] ?? 'whatsapp')) ?></td>
                            <td class="px-8 py-4 text-sm text-slate-500"><?= htmlspecialchars(date('d/m H:i', strtotime($ticket['opened_at'] ?? 'now'))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Refactor (Hidden by default, used as a Tailwind-powered popup) -->
<div id="groupTargetsModal" class="fixed inset-0 z-[100] hidden overflow-y-auto" role="dialog" aria-modal="true">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm transition-opacity" data-bs-dismiss="modal"></div>
        <div class="relative bg-white dark:bg-slate-900 w-full max-w-5xl rounded-xl shadow-2xl border border-slate-200 dark:border-slate-800 overflow-hidden transform transition-all translate-y-0">
            <div class="flex items-center justify-between px-8 py-6 border-b border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/50">
                <div>
                    <h5 class="text-xl font-bold text-slate-800 dark:text-white">Metas por grupo</h5>
                    <p class="text-xs text-slate-500 font-medium mt-1">Defina objetivos de TMA e TME por fila.</p>
                </div>
                <button type="button" class="w-10 h-10 flex items-center justify-center rounded-xl hover:bg-slate-200 dark:hover:bg-slate-800 transition-colors" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="p-8">
                <div class="hidden mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-100 text-rose-700 text-sm font-medium" data-group-feedback></div>
                <div class="flex justify-center py-10 hidden" data-group-loading>
                    <div class="w-10 h-10 border-4 border-primary border-t-transparent rounded-full animate-spin"></div>
                </div>
                <div class="overflow-x-auto" data-group-table>
                    <table class="w-full text-left border-collapse table-auto">
                        <thead>
                            <tr class="text-[10px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 dark:border-slate-800">
                                <th class="pb-4 pr-4">Grupo</th>
                                <th class="pb-4 px-4 whitespace-nowrap" width="120">TMA alvo (min)</th>
                                <th class="pb-4 px-4 whitespace-nowrap" width="120">TME alvo (min)</th>
                                <th class="pb-4 px-4 whitespace-nowrap" width="200">Atualizado</th>
                                <th class="pb-4 pl-4 text-end text-primary underline italic">Ação</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800" data-group-list>
                            <tr data-group-empty>
                                <td colspan="5" class="py-10 text-center text-slate-400 font-medium italic">Obtendo dados dos grupos...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="px-8 py-6 bg-slate-50 dark:bg-slate-950/50 flex justify-end gap-3">
                <button type="button" class="px-6 py-2.5 rounded-xl bg-slate-200 dark:bg-slate-800 text-sm font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-300 transition-all shadow-sm shadow-blue-500/10" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<script type="module">
import { initDashboard } from <?= json_encode(route_path('/js/modules/dashboard.js')) ?>;
initDashboard('#dashboard');
</script>
<?php include base_path('app/Views/partials/layout-end.php'); ?>
