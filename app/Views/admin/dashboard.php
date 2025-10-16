<?php
/** @var array<string, mixed> $summary */
/** @var array<int, array<string, mixed>> $recentTickets */
/** @var array<int, array<string, mixed>> $channels */
/** @var array<int, array<string, mixed>> $leaderboard */
/** @var array<int, array<string, mixed>> $queue */
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard · WhatsAtende</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
</head>
<body class="bg-light">
<?php include base_path('app/Views/admin/partials/nav.php'); ?>
<div class="container-fluid py-4">
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h6 class="text-muted">Chamados em aberto</h6>
                    <p class="display-6 fw-bold mb-0"><?= (int) ($summary['open'] ?? 0) ?></p>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h6 class="text-muted">Em atendimento</h6>
                    <p class="display-6 fw-bold mb-0 text-primary"><?= (int) ($summary['assigned'] ?? 0) ?></p>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h6 class="text-muted">Resolvidos hoje</h6>
                    <p class="display-6 fw-bold mb-0 text-success"><?= (int) ($summary['resolved_today'] ?? 0) ?></p>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h6 class="text-muted">Tempo médio (min)</h6>
                    <p class="display-6 fw-bold mb-0 text-warning">
                        <?= $summary['average_resolution_minutes'] !== null ? htmlspecialchars((string) $summary['average_resolution_minutes']) : '--' ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card shadow-sm h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Chamados Recentes</h5>
                    <a href="/admin/tickets" class="btn btn-sm btn-outline-primary">Ver todos</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
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
                            <?php if (empty($recentTickets)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Nenhuma solicitação encontrada.</td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Canais Ativos</h6>
                </div>
                <div class="card-body">
                    <?php foreach ($channels as $channel): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-capitalize"><?= htmlspecialchars((string) ($channel['channel'] ?? '')) ?></span>
                            <span class="badge bg-primary"><?= (int) ($channel['total'] ?? 0) ?></span>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($channels)): ?>
                        <p class="text-muted mb-0">Nenhum dado disponível.</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card shadow-sm">
                <div class="card-header">
                    <h6 class="mb-0">Ranking de Atendentes</h6>
                </div>
                <div class="card-body">
                    <ol class="mb-0 ps-3">
                        <?php foreach ($leaderboard as $agent): ?>
                            <li class="mb-2">
                                <strong><?= htmlspecialchars((string) ($agent['full_name'] ?? '')) ?></strong>
                                <span class="badge bg-success ms-2"><?= (int) ($agent['resolved'] ?? 0) ?> resolvidos</span>
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

    <div class="card shadow-sm mt-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Fila em tempo real</h5>
            <a href="/tickets" class="btn btn-sm btn-outline-secondary">Abrir fila</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
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
                    <?php if (empty($queue)): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">Nenhum chamado aguardando atendimento.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>
