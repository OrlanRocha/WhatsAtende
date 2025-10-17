<?php
/** @var array<string, mixed> $summary */
/** @var array<int, array<string, mixed>> $recentTickets */
/** @var array<int, array<string, mixed>> $channels */
/** @var array<int, array<string, mixed>> $leaderboard */
/** @var array<int, array<string, mixed>> $queue */
$pageTitle = 'Dashboard · WhatsAtende';
include base_path('app/Views/partials/layout-start.php');
include base_path('app/Views/admin/partials/nav.php');
?>
<div class="container-fluid py-4" id="dashboard" data-dashboard>
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card kpi-card shadow-sm border-0" data-kpi="open">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <h6 class="text-muted mb-0">Chamados em aberto</h6>
                        <span class="badge bg-primary-subtle text-primary"><i class="bi bi-life-preserver"></i></span>
                    </div>
                    <p class="display-6 fw-bold mb-0 mt-2"><?= (int) ($summary['open'] ?? 0) ?></p>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card kpi-card shadow-sm border-0" data-kpi="assigned">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <h6 class="text-muted mb-0">Em atendimento</h6>
                        <span class="badge bg-warning-subtle text-warning"><i class="bi bi-lightning"></i></span>
                    </div>
                    <p class="display-6 fw-bold text-warning mb-0 mt-2"><?= (int) ($summary['assigned'] ?? 0) ?></p>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card kpi-card shadow-sm border-0" data-kpi="resolved_today">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <h6 class="text-muted mb-0">Resolvidos hoje</h6>
                        <span class="badge bg-success-subtle text-success"><i class="bi bi-check2-circle"></i></span>
                    </div>
                    <p class="display-6 fw-bold text-success mb-0 mt-2"><?= (int) ($summary['resolved_today'] ?? 0) ?></p>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card kpi-card shadow-sm border-0" data-kpi="average_resolution_minutes">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <h6 class="text-muted mb-0">Tempo médio (min)</h6>
                        <span class="badge bg-info-subtle text-info"><i class="bi bi-stopwatch"></i></span>
                    </div>
                    <p class="display-6 fw-bold text-info mb-0 mt-2">
                        <?= $summary['average_resolution_minutes'] !== null ? htmlspecialchars((string) $summary['average_resolution_minutes']) : '--' ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card shadow-sm h-100 border-0">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-semibold">Chamados Recentes</h5>
                    <a href="/admin/tickets" class="btn btn-sm btn-outline-primary"><i class="bi bi-inboxes"></i> Ver todos</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0" id="recent-tickets" data-table>
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Contato</th>
                                <th>Status</th>
                                <th>Canal</th>
                                <th>Aberto em</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($recentTickets as $ticket): ?>
                                <tr>
                                    <td>#<?= htmlspecialchars((string) $ticket['id']) ?></td>
                                    <td><?= htmlspecialchars((string) ($ticket['contact_name'] ?? '')) ?></td>
                                    <td><span class="badge bg-secondary text-capitalize"><?= htmlspecialchars((string) ($ticket['status'] ?? '')) ?></span></td>
                                    <td><?= htmlspecialchars((string) ($ticket['channel'] ?? 'whatsapp')) ?></td>
                                    <td><?= htmlspecialchars(date('d/m H:i', strtotime($ticket['opened_at'] ?? 'now'))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header">
                    <h6 class="mb-0 fw-semibold">Canais Ativos</h6>
                </div>
                <div class="card-body" id="channel-list">
                    <?php foreach ($channels as $channel): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-capitalize fw-medium"><i class="bi bi-broadcast me-2 text-primary"></i><?= htmlspecialchars((string) ($channel['channel'] ?? '')) ?></span>
                            <span class="badge bg-primary-subtle text-primary fw-semibold"><?= (int) ($channel['total'] ?? 0) ?></span>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($channels)): ?>
                        <p class="text-muted mb-0">Nenhum dado disponível.</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card shadow-sm border-0">
                <div class="card-header">
                    <h6 class="mb-0 fw-semibold">Ranking de Atendentes</h6>
                </div>
                <div class="card-body" id="leaderboard">
                    <ol class="mb-0 ps-3">
                        <?php foreach ($leaderboard as $agent): ?>
                            <li class="mb-2">
                                <strong><?= htmlspecialchars((string) ($agent['full_name'] ?? '')) ?></strong>
                                <span class="badge bg-success-subtle text-success fw-semibold ms-2"><i class="bi bi-award"></i> <?= (int) ($agent['resolved'] ?? 0) ?></span>
                            </li>
                        <?php endforeach; ?>
                        <?php if (empty($leaderboard)): ?>
                            <li class="text-muted">Ainda não há atendentes com chamados encerrados.</li>
                        <?php endif; ?>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mt-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-semibold">Fila em tempo real</h5>
            <a href="/tickets" class="btn btn-sm btn-outline-secondary"><i class="bi bi-people"></i> Abrir fila</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0" id="queue-table" data-table data-refresh="/tickets">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Contato</th>
                        <th>Canal</th>
                        <th>Aberto em</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($queue as $ticket): ?>
                        <tr>
                            <td>#<?= htmlspecialchars((string) ($ticket['id'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($ticket['contact_name'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($ticket['channel'] ?? 'whatsapp')) ?></td>
                            <td><?= htmlspecialchars(date('d/m H:i', strtotime($ticket['opened_at'] ?? 'now'))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script type="module">
import { initDashboard } from '/js/modules/dashboard.js';
initDashboard('#dashboard');
</script>
<?php include base_path('app/Views/partials/layout-end.php'); ?>
