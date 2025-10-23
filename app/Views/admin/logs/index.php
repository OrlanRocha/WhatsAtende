<?php
/** @var array<int, array<string, mixed>> $logs */
$level = $level ?? null;
$filters = $filters ?? [];
$aggregates = $aggregates ?? ['levels' => [], 'services' => [], 'actions' => [], 'timeline' => []];
$breadcrumbs = [
    ['label' => 'Admin', 'href' => '/admin'],
    ['label' => 'Logs']
];
$pageTitle = 'Logs · WhatsAtende';
include base_path('app/Views/partials/layout-start.php');
include base_path('app/Views/partials/topbar.php');
?>
<div class="workspace" id="logs-page" data-log-level="<?= htmlspecialchars((string) $level) ?>">
    <section class="workspace-header">
        <div>
            <h1 class="workspace-title">Observabilidade do sistema</h1>
            <p class="workspace-subtitle">Monitore integrações, autenticações e atividades críticas em tempo real.</p>
        </div>
        <div class="workspace-actions">
            <button class="btn btn-outline-secondary" data-log-refresh>
                <i class="bi bi-arrow-clockwise"></i> Atualizar
            </button>
            <button class="btn btn-outline-secondary" data-live-tail-toggle>
                <i class="bi bi-broadcast"></i> Live tail
            </button>
        </div>
    </section>
    <form class="log-filters" data-log-filters>
        <div class="filter-group">
            <label for="filter-level">Nível</label>
            <select id="filter-level" name="level" class="form-select">
                <option value="">Todos</option>
                <option value="info" <?= $level === 'info' ? 'selected' : '' ?>>Info</option>
                <option value="warning" <?= $level === 'warning' ? 'selected' : '' ?>>Warning</option>
                <option value="error" <?= $level === 'error' ? 'selected' : '' ?>>Error</option>
                <option value="critical" <?= $level === 'critical' ? 'selected' : '' ?>>Critical</option>
            </select>
        </div>
        <div class="filter-group">
            <label for="filter-service">Serviço</label>
            <input type="text" id="filter-service" name="service" value="<?= htmlspecialchars((string) ($filters['service'] ?? '')) ?>" placeholder="ex: ticket, webhook">
        </div>
        <div class="filter-group">
            <label for="filter-user">Usuário</label>
            <input type="number" id="filter-user" name="user" min="1" value="<?= htmlspecialchars((string) ($filters['user'] ?? '')) ?>" placeholder="ID do usuário">
        </div>
        <div class="filter-group">
            <label for="filter-search">Busca</label>
            <input type="search" id="filter-search" name="q" value="<?= htmlspecialchars((string) ($filters['search'] ?? '')) ?>" placeholder="Texto ou ID do incidente">
        </div>
        <div class="filter-group">
            <label for="filter-from">De</label>
            <input type="datetime-local" id="filter-from" name="from" value="<?= htmlspecialchars((string) ($filters['from'] ?? '')) ?>">
        </div>
        <div class="filter-group">
            <label for="filter-to">Até</label>
            <input type="datetime-local" id="filter-to" name="to" value="<?= htmlspecialchars((string) ($filters['to'] ?? '')) ?>">
        </div>
        <div class="filter-actions">
            <button class="btn btn-primary" type="submit">Aplicar filtros</button>
            <button class="btn btn-outline-secondary" type="reset" data-log-reset>Limpar</button>
        </div>
    </form>
    <section class="workspace-metrics" data-log-metrics>
        <?php foreach ($aggregates['levels'] as $metric): ?>
            <div class="metric-card metric-card--compact" data-metric-level="<?= htmlspecialchars((string) $metric['label']) ?>">
                <span class="metric-label text-uppercase"><?= htmlspecialchars((string) $metric['label']) ?></span>
                <span class="metric-value"><?= htmlspecialchars((string) $metric['total']) ?></span>
            </div>
        <?php endforeach; ?>
    </section>
    <section class="logs-insights">
        <div class="insight-card">
            <header>
                <h2>Timeline (últimas 24h)</h2>
            </header>
            <div class="insight-chart" data-log-timeline>
                <?php if (empty($aggregates['timeline'])): ?>
                    <p class="text-muted">Sem dados recentes.</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($aggregates['timeline'] as $bucket): ?>
                            <li data-bucket="<?= htmlspecialchars((string) $bucket['bucket']) ?>" data-total="<?= (int) $bucket['total'] ?>">
                                <span><?= htmlspecialchars(date('H\h', strtotime((string) $bucket['bucket']))) ?></span>
                                <div class="bar" style="--value: <?= max(1, (int) $bucket['total']) ?>"></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
        <div class="insight-card">
            <header>
                <h2>Top serviços</h2>
            </header>
            <ul class="insight-list" data-log-services>
                <?php foreach ($aggregates['services'] as $service): ?>
                    <li>
                        <span><?= htmlspecialchars((string) $service['label']) ?></span>
                        <span class="badge badge--neutral"><?= (int) $service['total'] ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="insight-card">
            <header>
                <h2>Principais ações</h2>
            </header>
            <ul class="insight-list" data-log-actions>
                <?php foreach ($aggregates['actions'] as $action): ?>
                    <li>
                        <span><?= htmlspecialchars((string) $action['label']) ?></span>
                        <span class="badge badge--neutral"><?= (int) $action['total'] ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
    <section class="logs-table">
        <div class="table-responsive">
            <table class="table align-middle" id="logs-table" data-log-table>
                <thead>
                <tr>
                    <th>Data</th>
                    <th>Nível</th>
                    <th>Serviço</th>
                    <th>Ação</th>
                    <th>Mensagem</th>
                    <th>Rota</th>
                    <th>Corr ID</th>
                    <th>Usuário</th>
                    <th>IP</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $index => $log): ?>
                    <?php $service = $log['service'] !== '' ? $log['service'] : (explode('.', (string) ($log['action'] ?? ''), 2)[0] ?? 'sistema'); ?>
                    <tr data-log-row data-log-index="<?= (int) $index ?>">
                        <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) ($log['created_at'] ?? 'now')))) ?></td>
                        <td><span class="status-badge status-badge--<?= htmlspecialchars((string) ($log['level'] ?? 'info')) ?>"><?= htmlspecialchars((string) ($log['level'] ?? 'info')) ?></span></td>
                        <td><?= htmlspecialchars($service) ?></td>
                        <td><?= htmlspecialchars((string) ($log['action'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) ($log['message'] ?? '')) ?></td>
                        <td><code><?= htmlspecialchars((string) ($log['route'] ?? '')) ?></code></td>
                        <td><code class="text-muted"><?= htmlspecialchars((string) ($log['corr_id'] ?? '')) ?></code></td>
                        <td><?= htmlspecialchars((string) ($log['user_name'] ?? 'Sistema')) ?></td>
                        <td><?= htmlspecialchars((string) ($log['ip_address'] ?? '-')) ?></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-secondary" data-log-details>
                                <i class="bi bi-eye"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <section class="log-detail" data-log-detail hidden>
        <header>
            <h2>Detalhes do log</h2>
            <div class="log-detail__actions">
                <button class="btn btn-outline-secondary btn-sm" data-log-copy>
                    <i class="bi bi-clipboard"></i> Copiar detalhes
                </button>
                <button class="btn btn-icon" data-log-close aria-label="Fechar">
                    <i class="bi bi-x"></i>
                </button>
            </div>
        </header>
        <pre class="log-detail__content" data-log-json></pre>
    </section>
</div>
<script type="module">
import { initLogViewer } from <?= json_encode(route_path('/js/modules/logs.js')) ?>;
initLogViewer('#logs-page', <?= json_encode(route_path('/admin/logs')) ?>);
</script>
<?php include base_path('app/Views/partials/layout-end.php'); ?>
