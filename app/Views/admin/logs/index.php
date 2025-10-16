<?php
/** @var array<int, array<string, mixed>> $logs */
/** @var string|null $level */
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Logs · WhatsAtende</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
</head>
<body class="bg-light">
<?php include base_path('app/Views/admin/partials/nav.php'); ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Logs do sistema</h1>
        <form class="d-flex align-items-center gap-2" method="GET" action="/admin/logs">
            <label for="level" class="form-label mb-0">Filtro:</label>
            <select name="level" id="level" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Todos</option>
                <option value="info" <?= $level === 'info' ? 'selected' : '' ?>>Info</option>
                <option value="warning" <?= $level === 'warning' ? 'selected' : '' ?>>Alerta</option>
                <option value="error" <?= $level === 'error' ? 'selected' : '' ?>>Erro</option>
                <option value="critical" <?= $level === 'critical' ? 'selected' : '' ?>>Crítico</option>
            </select>
        </form>
    </div>
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
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
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">Nenhum registro encontrado.</td>
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
