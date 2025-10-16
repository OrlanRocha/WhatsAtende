<?php
/** @var array<int, array<string, mixed>> $users */
/** @var string|null $status */
/** @var string|null $error */
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Usuários · WhatsAtende</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
</head>
<body class="bg-light">
<?php include base_path('app/Views/admin/partials/nav.php'); ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Usuários do Sistema</h1>
        <a href="/admin/users/create" class="btn btn-primary">Novo usuário</a>
    </div>
    <?php if (!empty($status)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($status) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Nome</th>
                        <th>E-mail</th>
                        <th>CPF</th>
                        <th>Perfil</th>
                        <th>Ativo</th>
                        <th>Chamados Ativos</th>
                        <th class="text-end">Ações</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= (int) ($user['id'] ?? 0) ?></td>
                            <td><?= htmlspecialchars((string) ($user['full_name'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($user['email'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($user['cpf'] ?? '')) ?></td>
                            <td><span class="badge bg-secondary text-capitalize"><?= htmlspecialchars((string) ($user['role'] ?? '')) ?></span></td>
                            <td>
                                <?php if (!empty($user['is_active'])): ?>
                                    <span class="badge bg-success">Sim</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Não</span>
                                <?php endif; ?>
                            </td>
                            <td><?= (int) ($user['assigned_tickets'] ?? 0) ?></td>
                            <td class="text-end">
                                <a href="/admin/users/<?= urlencode((string) ($user['id'] ?? '')) ?>/edit" class="btn btn-sm btn-outline-primary">Editar</a>
                                <form method="POST" action="/admin/users/<?= urlencode((string) ($user['id'] ?? '')) ?>/delete" class="d-inline" onsubmit="return confirm('Confirma a exclusão deste usuário?');">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Excluir</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">Nenhum usuário cadastrado.</td>
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
