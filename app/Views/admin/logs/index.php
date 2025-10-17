<?php
/** @var array<int, array<string, mixed>> $logs */
/** @var string|null $level */
$pageTitle = 'Logs · WhatsAtende';
include base_path('app/Views/partials/layout-start.php');
include base_path('app/Views/admin/partials/nav.php');
?>
<div class="container-xxl py-4" id="logs-page">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-3 mb-4">
        <div>
            <h1 class="h3 fw-semibold mb-1">Observabilidade do sistema</h1>
            <p class="text-muted mb-0">Acompanhe autenticações, integrações e ações críticas realizadas pelos usuários.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <label for="log-level" class="form-label mb-0">Nível</label>
            <select id="log-level" class="form-select form-select-sm" data-filter>
                <option value="">Todos</option>
                <option value="info" <?= $level === 'info' ? 'selected' : '' ?>>Informações</option>
                <option value="warning" <?= $level === 'warning' ? 'selected' : '' ?>>Alertas</option>
                <option value="error" <?= $level === 'error' ? 'selected' : '' ?>>Erros</option>
                <option value="critical" <?= $level === 'critical' ? 'selected' : '' ?>>Críticos</option>
            </select>
        </div>
    </div>
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0" id="logs-table" data-table>
                    <thead>
                    <tr>
                        <th>Data</th>
                        <th>Nível</th>
                        <th>Ação</th>
                        <th>Mensagem</th>
                        <th>Usuário</th>
                        <th>IP</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($log['created_at'] ?? 'now'))) ?></td>
                            <td><span class="badge bg-secondary text-uppercase"><?= htmlspecialchars((string) ($log['level'] ?? '')) ?></span></td>
                            <td><?= htmlspecialchars((string) ($log['action'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($log['message'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($log['user_name'] ?? 'Sistema')) ?></td>
                            <td><?= htmlspecialchars((string) ($log['ip_address'] ?? '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script type="module">
import { initLogViewer } from '/js/modules/logs.js';
initLogViewer('#logs-page', '/admin/logs');
</script>
<?php include base_path('app/Views/partials/layout-end.php'); ?>
