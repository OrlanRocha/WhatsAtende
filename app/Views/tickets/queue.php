<?php

declare(strict_types=1);

$status = get_flash('auth_status');
$error = get_flash('auth_error');
$user = auth();

?><!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fila de Chamados</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="/tickets">WhatsAtende</a>
        <div class="d-flex align-items-center gap-3">
            <?php if ($user && ($user->role ?? null) === 'admin'): ?>
                <a class="btn btn-outline-light btn-sm" href="/admin">Dashboard</a>
            <?php endif; ?>
            <?php if ($user): ?>
                <span class="text-light small">Olá, <?= htmlspecialchars($user->full_name ?? '') ?></span>
                <form method="POST" action="/logout" class="m-0">
                    <button type="submit" class="btn btn-outline-light btn-sm">Sair</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</nav>
<div class="container py-4">
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
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Fila de Chamados</h1>
        <a class="btn btn-primary" href="/tickets">Atualizar</a>
    </div>
    <?php if (empty($queue)): ?>
        <div class="alert alert-info">Nenhum chamado aguardando atendimento.</div>
    <?php else: ?>
        <div class="table-responsive shadow-sm rounded">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    <th>Protocolo</th>
                    <th>Contato</th>
                    <th>Canal</th>
                    <th>Status</th>
                    <th>Aberto em</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($queue as $ticket): ?>
                    <tr>
                        <td>#<?= htmlspecialchars((string) ($ticket['id'] ?? '')); ?></td>
                        <td><?= htmlspecialchars((string) ($ticket['contact_name'] ?? '')); ?></td>
                        <td><?= htmlspecialchars((string) ($ticket['channel'] ?? 'whatsapp')); ?></td>
                        <td>
                            <span class="badge text-bg-secondary text-capitalize">
                                <?= htmlspecialchars((string) ($ticket['status'] ?? 'open')); ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars((string) ($ticket['opened_at'] ?? '')); ?></td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-success" href="/tickets/<?= urlencode((string) ($ticket['id'] ?? '')); ?>">Iniciar atendimento</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
