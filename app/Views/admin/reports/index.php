<?php
/** @var array<int, array<string, mixed>> $groups */
/** @var array<int, array<string, mixed>> $summary */
/** @var array<string, mixed> $filters */

$groups = $groups ?? [];
$summary = $summary ?? [];
$filters = $filters ?? [];
$errorMessage = $errorMessage ?? null;

$fromValue = $filters['from'] ?? (new DateTimeImmutable('-7 days'))->format('Y-m-d');
$toValue = $filters['to'] ?? (new DateTimeImmutable())->format('Y-m-d');
$selectedGroups = array_map('intval', $filters['group'] ?? []);

$totalOpened = 0;
$totalClosed = 0;
$queueSamples = [];
$resolutionSamples = [];

foreach ($summary as $row) {
    $totalOpened += (int) ($row['opened'] ?? 0);
    $totalClosed += (int) ($row['closed'] ?? 0);
    if (isset($row['avg_queue_seconds']) && $row['avg_queue_seconds'] !== null) {
        $queueSamples[] = (float) $row['avg_queue_seconds'];
    }
    if (isset($row['avg_resolution_seconds']) && $row['avg_resolution_seconds'] !== null) {
        $resolutionSamples[] = (float) $row['avg_resolution_seconds'];
    }
}

$avgQueueSeconds = $queueSamples !== [] ? array_sum($queueSamples) / count($queueSamples) : null;
$avgResolutionSeconds = $resolutionSamples !== [] ? array_sum($resolutionSamples) / count($resolutionSamples) : null;

$breadcrumbs = [
    ['label' => 'Admin', 'href' => '/admin'],
    ['label' => 'Relatórios'],
];
$pageTitle = 'Relatórios · WhatsAtende';

$formatSeconds = static function (?float $seconds): string {
    if ($seconds === null) {
        return '—';
    }
    $minutes = floor($seconds / 60);
    $remaining = (int) round($seconds % 60);
    if ($minutes <= 0) {
        return $remaining . 's';
    }
    return sprintf('%dmin %02ds', $minutes, $remaining);
};

include base_path('app/Views/partials/layout-start.php');
include base_path('app/Views/partials/topbar.php');
?>
<div class="space-y-8 pb-12" id="reports-page"
     data-report-endpoint="<?= htmlspecialchars(route_path('/admin/reports'), ENT_QUOTES) ?>"
     data-report-export="<?= htmlspecialchars(route_path('/admin/reports/export'), ENT_QUOTES) ?>"
     data-initial-summary='<?= htmlspecialchars(json_encode($summary, JSON_UNESCAPED_UNICODE)) ?>'
     data-initial-error="<?= htmlspecialchars((string) $errorMessage) ?>">
    
    <?php if (!empty($errorMessage)): ?>
        <div class="bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 text-rose-600 dark:text-rose-400 p-4 rounded-xl text-sm font-medium">
            <?= htmlspecialchars($errorMessage) ?>
        </div>
    <?php endif; ?>

    <!-- Header -->
    <section class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-800 dark:text-white tracking-tight mb-1">Relatórios de Atendimento</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Acompanhe volume diário, SLA médio e evolução da fila por grupo.</p>
        </div>
        <div>
            <a href="#" data-report-export-button class="btn-outline-secondary flex items-center gap-2 text-sm bg-white dark:bg-slate-900">
                <i class="bi bi-download"></i> Exportar CSV
            </a>
        </div>
    </section>

    <!-- Filters -->
    <form class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm" data-report-form>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 items-start">
            <div class="space-y-2">
                <label for="report-from" class="form-label">Data Inicial</label>
                <input type="date" id="report-from" name="from" value="<?= htmlspecialchars($fromValue) ?>" class="form-control-compact text-sm">
            </div>
            <div class="space-y-2">
                <label for="report-to" class="form-label">Data Final</label>
                <input type="date" id="report-to" name="to" value="<?= htmlspecialchars($toValue) ?>" class="form-control-compact text-sm">
            </div>
            <div class="space-y-2 md:col-span-2">
                <label for="report-group" class="form-label flex items-center justify-between">
                    Grupos
                    <span class="text-[10px] font-normal normal-case text-slate-400 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded-md">Ctrl/Cmd p/ múltiplos</span>
                </label>
                <select id="report-group" name="group[]" multiple class="form-control-compact text-sm h-[88px] custom-scrollbar">
                    <?php foreach ($groups as $group): ?>
                        <?php $groupId = (int) ($group['id'] ?? 0); ?>
                        <option value="<?= $groupId ?>" <?= in_array($groupId, $selectedGroups, true) ? 'selected' : '' ?> class="py-1 px-2 rounded-md hover:bg-primary/10">
                            <?= htmlspecialchars((string) ($group['name'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="mt-6 pt-6 border-t border-slate-100 dark:border-slate-800 flex items-center justify-end gap-3">
            <button type="reset" class="btn-outline-secondary text-sm px-5 py-2" data-report-reset>Limpar</button>
            <button type="submit" class="btn-primary text-sm px-5 py-2">
                <i class="bi bi-funnel mr-1"></i> Aplicar Filtros
            </button>
        </div>
    </form>

    <!-- Metrics Cards -->
    <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4" data-report-metrics>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 rounded-2xl shadow-sm relative overflow-hidden group">
            <div class="absolute top-0 right-0 p-4 opacity-10 text-primary group-hover:scale-110 transition-transform duration-300">
                <i class="bi bi-inboxes text-6xl"></i>
            </div>
            <p class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-1 relative z-10">Abertos</p>
            <h3 class="text-3xl font-black text-slate-800 dark:text-white relative z-10"><?= number_format($totalOpened) ?></h3>
        </div>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 rounded-2xl shadow-sm relative overflow-hidden group">
            <div class="absolute top-0 right-0 p-4 opacity-10 text-emerald-500 group-hover:scale-110 transition-transform duration-300">
                <i class="bi bi-check-circle text-6xl"></i>
            </div>
            <p class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-1 relative z-10">Encerrados</p>
            <h3 class="text-3xl font-black text-slate-800 dark:text-white relative z-10"><?= number_format($totalClosed) ?></h3>
        </div>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 rounded-2xl shadow-sm relative overflow-hidden group">
            <div class="absolute top-0 right-0 p-4 opacity-10 text-amber-500 group-hover:scale-110 transition-transform duration-300">
                <i class="bi bi-hourglass-split text-6xl"></i>
            </div>
            <p class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-1 relative z-10">Fila Média</p>
            <h3 class="text-3xl font-black text-slate-800 dark:text-white relative z-10"><?= htmlspecialchars($formatSeconds($avgQueueSeconds)) ?></h3>
        </div>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 rounded-2xl shadow-sm relative overflow-hidden group">
            <div class="absolute top-0 right-0 p-4 opacity-10 text-primary group-hover:scale-110 transition-transform duration-300">
                <i class="bi bi-lightning-charge text-6xl"></i>
            </div>
            <p class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-1 relative z-10">Resolução Média</p>
            <h3 class="text-3xl font-black text-slate-800 dark:text-white relative z-10"><?= htmlspecialchars($formatSeconds($avgResolutionSeconds)) ?></h3>
        </div>
    </section>

    <!-- Data Table -->
    <section class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
        <div class="p-6 border-b border-slate-100 dark:border-slate-800">
            <h2 class="text-lg font-bold text-slate-800 dark:text-white">Detalhamento Diário</h2>
        </div>
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse" data-report-table>
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-800/50 text-xs font-bold text-slate-500 uppercase tracking-wider">
                        <th class="p-4 border-b border-slate-200 dark:border-slate-800">Data</th>
                        <th class="p-4 border-b border-slate-200 dark:border-slate-800 text-center">Abertos</th>
                        <th class="p-4 border-b border-slate-200 dark:border-slate-800 text-center">Encerrados</th>
                        <th class="p-4 border-b border-slate-200 dark:border-slate-800">Fila Média</th>
                        <th class="p-4 border-b border-slate-200 dark:border-slate-800">Resolução Média</th>
                    </tr>
                </thead>
                <tbody class="text-sm text-slate-600 dark:text-slate-300 divide-y divide-slate-100 dark:divide-slate-800/50">
                <?php if ($summary === []): ?>
                    <tr>
                        <td colspan="5" class="p-8 text-center text-slate-400">Nenhum dado encontrado para o período selecionado.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($summary as $row): ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/20 transition-colors">
                            <td class="p-4 font-medium"><?= htmlspecialchars((string) ($row['date'] ?? '')) ?></td>
                            <td class="p-4 text-center">
                                <span class="inline-flex items-center justify-center min-w-[2rem] px-2 py-1 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold">
                                    <?= (int) ($row['opened'] ?? 0) ?>
                                </span>
                            </td>
                            <td class="p-4 text-center">
                                <span class="inline-flex items-center justify-center min-w-[2rem] px-2 py-1 rounded-md bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-bold">
                                    <?= (int) ($row['closed'] ?? 0) ?>
                                </span>
                            </td>
                            <td class="p-4"><?= htmlspecialchars($formatSeconds($row['avg_queue_seconds'] ?? null)) ?></td>
                            <td class="p-4"><?= htmlspecialchars($formatSeconds($row['avg_resolution_seconds'] ?? null)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<script type="module">
import { initReports } from <?= json_encode(route_path('/js/modules/reports.js')) ?>;
initReports('#reports-page');
</script>
<?php include base_path('app/Views/partials/layout-end.php'); ?>
