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
<div class="workspace" id="reports-page"
     data-report-endpoint="<?= htmlspecialchars(route_path('/admin/reports'), ENT_QUOTES) ?>"
     data-report-export="<?= htmlspecialchars(route_path('/admin/reports/export'), ENT_QUOTES) ?>"
     data-initial-summary='<?= htmlspecialchars(json_encode($summary, JSON_UNESCAPED_UNICODE)) ?>'
     data-initial-error="<?= htmlspecialchars((string) $errorMessage) ?>">
    <?php if (!empty($errorMessage)): ?>
        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($errorMessage) ?></div>
    <?php endif; ?>
    <section class="workspace-header">
        <div>
            <h1 class="workspace-title">Relatórios de atendimento</h1>
            <p class="workspace-subtitle">Acompanhe volume diário, SLA médio e evolução da fila por grupo.</p>
        </div>
        <div class="workspace-actions">
            <a class="btn btn-outline-secondary" data-report-export-button href="#" aria-disabled="false">
                <i class="bi bi-download"></i> Exportar CSV
            </a>
        </div>
    </section>
    <form class="report-filters" data-report-form>
        <div class="filter-group">
            <label for="report-from">De</label>
            <input type="date" id="report-from" name="from" value="<?= htmlspecialchars($fromValue) ?>">
        </div>
        <div class="filter-group">
            <label for="report-to">Até</label>
            <input type="date" id="report-to" name="to" value="<?= htmlspecialchars($toValue) ?>">
        </div>
        <div class="filter-group">
            <label for="report-group">Grupos</label>
            <select id="report-group" name="group[]" multiple class="form-select">
                <?php foreach ($groups as $group): ?>
                    <?php $groupId = (int) ($group['id'] ?? 0); ?>
                    <option value="<?= $groupId ?>" <?= in_array($groupId, $selectedGroups, true) ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) ($group['name'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="text-muted">Segure Ctrl/Cmd para selecionar múltiplos.</small>
        </div>
        <div class="filter-actions">
            <button class="btn btn-primary" type="submit">Aplicar filtros</button>
            <button class="btn btn-outline-secondary" type="reset" data-report-reset>Limpar</button>
        </div>
    </form>
    <section class="workspace-metrics" data-report-metrics>
        <div class="metric-card" data-metric="opened">
            <span class="metric-label">Abertos</span>
            <span class="metric-value"><?= number_format($totalOpened) ?></span>
        </div>
        <div class="metric-card" data-metric="closed">
            <span class="metric-label">Encerrados</span>
            <span class="metric-value"><?= number_format($totalClosed) ?></span>
        </div>
        <div class="metric-card" data-metric="avg_queue">
            <span class="metric-label">Fila média</span>
            <span class="metric-value"><?= htmlspecialchars($formatSeconds($avgQueueSeconds)) ?></span>
        </div>
        <div class="metric-card" data-metric="avg_resolution">
            <span class="metric-label">Resolução média</span>
            <span class="metric-value"><?= htmlspecialchars($formatSeconds($avgResolutionSeconds)) ?></span>
        </div>
    </section>
    <section class="reports-chart" data-report-chart>
        <div class="insight-card">
            <header>
                <h2>Resumo diário</h2>
                <p class="text-muted mb-0">Total de tickets abertos e encerrados por dia.</p>
            </header>
            <div class="insight-card__body" data-report-timeline>
                <?php if ($summary === []): ?>
                    <p class="text-muted">Nenhum dado encontrado para o período selecionado.</p>
                <?php else: ?>
                    <ul class="report-timeline">
                        <?php foreach ($summary as $row): ?>
                            <li data-date="<?= htmlspecialchars((string) ($row['date'] ?? '')) ?>"
                                data-opened="<?= (int) ($row['opened'] ?? 0) ?>"
                                data-closed="<?= (int) ($row['closed'] ?? 0) ?>">
                                <span class="report-timeline__date"><?= htmlspecialchars((string) ($row['date'] ?? '')) ?></span>
                                <div class="report-timeline__bars">
                                    <span class="bar bar--opened" style="--value: <?= max(1, (int) ($row['opened'] ?? 0)) ?>"></span>
                                    <span class="bar bar--closed" style="--value: <?= max(1, (int) ($row['closed'] ?? 0)) ?>"></span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <section class="reports-table">
        <div class="table-responsive">
            <table class="table align-middle" data-report-table>
                <thead>
                <tr>
                    <th>Data</th>
                    <th>Abertos</th>
                    <th>Encerrados</th>
                    <th>Fila média</th>
                    <th>Resolução média</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($summary as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) ($row['date'] ?? '')) ?></td>
                        <td><?= (int) ($row['opened'] ?? 0) ?></td>
                        <td><?= (int) ($row['closed'] ?? 0) ?></td>
                        <td><?= htmlspecialchars($formatSeconds($row['avg_queue_seconds'] ?? null)) ?></td>
                        <td><?= htmlspecialchars($formatSeconds($row['avg_resolution_seconds'] ?? null)) ?></td>
                    </tr>
                <?php endforeach; ?>
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
