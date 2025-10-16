<?php
/** @var array<int, array<string, mixed>> $tickets */
/** @var string|null $statusFilter */
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Solicitações · WhatsAtende</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
</head>
<body class="bg-light">
<?php include base_path('app/Views/admin/partials/nav.php'); ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Solicitações</h1>
        <form class="d-flex align-items-center gap-2" method="GET" action="/admin/tickets">
            <label for="status" class="form-label mb-0">Status:</label>
            <select name="status" id="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Todos</option>
                <option value="open" <?= $statusFilter === 'open' ? 'selected' : '' ?>>Aguardando</option>
                <option value="assigned" <?= $statusFilter === 'assigned' ? 'selected' : '' ?>>Em atendimento</option>
                <option value="resolved" <?= $statusFilter === 'resolved' ? 'selected' : '' ?>>Resolvidos</option>
                <option value="closed" <?= $statusFilter === 'closed' ? 'selected' : '' ?>>Encerrados</option>
            </select>
        </form>
    </div>
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Contato</th>
                        <th>Status</th>
                        <th>Responsável</th>
                        <th>Canal</th>
                        <th>Aberto em</th>
                        <th>Encerrado em</th>
                        <th>Prioridade</th>
                        <th class="text-end">Ações</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($tickets as $ticket): ?>
                        <tr>
                            <td>#<?= htmlspecialchars((string) ($ticket['id'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($ticket['contact_name'] ?? '')) ?></td>
                            <td><span class="badge bg-secondary text-capitalize"><?= htmlspecialchars((string) ($ticket['status'] ?? '')) ?></span></td>
                            <td><?= htmlspecialchars((string) ($ticket['agent_name'] ?? 'Não atribuído')) ?></td>
                            <td><?= htmlspecialchars((string) ($ticket['channel'] ?? 'whatsapp')) ?></td>
                            <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($ticket['opened_at'] ?? 'now'))) ?></td>
                            <td><?= !empty($ticket['closed_at']) ? htmlspecialchars(date('d/m/Y H:i', strtotime($ticket['closed_at']))) : '—' ?></td>
                            <td><span class="badge bg-info text-capitalize"><?= htmlspecialchars((string) ($ticket['priority'] ?? 'normal')) ?></span></td>
                            <td class="text-end">
                                <a href="/tickets/<?= urlencode((string) ($ticket['id'] ?? '')) ?>" class="btn btn-sm btn-outline-primary">Abrir chat</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($tickets)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">Nenhuma solicitação encontrada.</td>
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
