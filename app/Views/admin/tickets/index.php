<?php
/** @var array<int, array<string, mixed>> $tickets */
/** @var string|null $statusFilter */
$breadcrumbs = [
    ['label' => 'Admin', 'href' => '/admin'],
    ['label' => 'Solicitações'],
];
$pageTitle = 'Solicitações · WhatsAtende';
include base_path('app/Views/partials/layout-start.php');
include base_path('app/Views/partials/topbar.php');
?>
<div class="space-y-8 pb-12" id="tickets-page">
    <!-- Header Section -->
    <header class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Solicitações</h1>
            <p class="text-slate-500 dark:text-slate-400 mt-1">Visualize todos os chamados com filtros instantâneos.</p>
        </div>
        <div class="flex items-center gap-3 bg-white dark:bg-slate-800 p-1.5 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm">
            <label for="ticket-status" class="pl-3 pr-2 text-xs font-bold text-slate-400 uppercase tracking-widest border-r border-slate-100 dark:border-slate-700">Status</label>
            <div class="relative">
                <select id="ticket-status" class="appearance-none bg-transparent pl-2 pr-8 py-1 text-sm font-bold text-slate-700 dark:text-white outline-none cursor-pointer" data-filter>
                    <option value="">Todos</option>
                    <option value="open" <?= $statusFilter === 'open' ? 'selected' : '' ?>>Aguardando</option>
                    <option value="assigned" <?= $statusFilter === 'assigned' ? 'selected' : '' ?>>Em atendimento</option>
                    <option value="resolved" <?= $statusFilter === 'resolved' ? 'selected' : '' ?>>Resolvidos</option>
                    <option value="closed" <?= $statusFilter === 'closed' ? 'selected' : '' ?>>Encerrados</option>
                </select>
                <i class="bi bi-chevron-down absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-[10px]"></i>
            </div>
        </div>
    </header>

    <!-- Tickets Table Card -->
    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden transition-all duration-300">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse" id="tickets-table" data-table>
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-950/50">
                        <th class="px-3 py-3 text-[11px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 dark:border-slate-800">#</th>
                        <th class="px-3 py-3 text-[11px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 dark:border-slate-800">Contato</th>
                        <th class="px-3 py-3 text-[11px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 dark:border-slate-800">Status</th>
                        <th class="px-3 py-3 text-[11px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 dark:border-slate-800">Responsável</th>
                        <th class="px-3 py-3 text-[11px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 dark:border-slate-800">Canal</th>
                        <th class="px-3 py-3 text-[11px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 dark:border-slate-800">Aberto</th>
                        <th class="px-3 py-3 text-[11px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 dark:border-slate-800">Prioridade</th>
                        <th class="px-3 py-3 text-[11px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 dark:border-slate-800 text-end">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <?php foreach ($tickets as $ticket): ?>
                        <tr class="group hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                            <td class="px-3 py-3 text-sm font-bold text-slate-400">#<?= htmlspecialchars((string) ($ticket['id'] ?? '')) ?></td>
                            <td class="px-3 py-3">
                                <span class="text-sm font-bold text-slate-900 dark:text-white"><?= htmlspecialchars((string) ($ticket['contact_name'] ?? '')) ?></span>
                            </td>
                            <td class="px-3 py-3">
                                <span class="inline-flex px-3 py-1  bg-slate-100 dark:bg-slate-800 text-[10px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-widest">
                                    <?= htmlspecialchars((string) ($ticket['status'] ?? '')) ?>
                                </span>
                            </td>
                            <td class="px-3 py-3 text-sm text-slate-500 dark:text-slate-400 italic">
                                <?= htmlspecialchars((string) ($ticket['agent_name'] ?? 'Livre')) ?>
                            </td>
                            <td class="px-3 py-3 text-sm text-slate-500 dark:text-slate-400 capitalize">
                                <?= htmlspecialchars((string) ($ticket['channel'] ?? 'whatsapp')) ?>
                            </td>
                            <td class="px-3 py-3 text-sm text-slate-500 dark:text-slate-400">
                                <?= htmlspecialchars(date('d/m H:i', strtotime($ticket['opened_at'] ?? 'now'))) ?>
                            </td>
                            <td class="px-3 py-3">
                                <span class="px-1 py-1 rounded-md bg-blue-50 dark:bg-blue-900/20 text-[10px] font-bold text-blue-600 dark:text-blue-400 uppercase">
                                    <?= htmlspecialchars((string) ($ticket['priority'] ?? 'normal')) ?>
                                </span>
                            </td>
                            <td class="px-3 py-3 text-end">
                                <a href="<?= htmlspecialchars(route_path('/tickets/' . rawurlencode((string) ($ticket['id'] ?? ''))), ENT_QUOTES) ?>" 
                                   class="inline-flex items-center gap-2 px-3 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-primary hover:text-white rounded-xl text-xs font-bold text-slate-600 dark:text-slate-400 transition-all active:scale-95">
                                    <i class="bi bi-chat-dots"></i> Atender
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include base_path('app/Views/partials/layout-end.php'); ?>
