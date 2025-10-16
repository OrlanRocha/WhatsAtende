<?php
$user = auth();
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="/admin">WhatsAtende</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar" aria-controls="adminNavbar" aria-expanded="false" aria-label="Alternar navegação">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="adminNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="/admin">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="/admin/tickets">Solicitações</a></li>
                <li class="nav-item"><a class="nav-link" href="/tickets">Fila de Atendimento</a></li>
                <li class="nav-item"><a class="nav-link" href="/admin/templates">Templates</a></li>
                <li class="nav-item"><a class="nav-link" href="/admin/users">Usuários</a></li>
                <li class="nav-item"><a class="nav-link" href="/admin/logs">Logs</a></li>
                <li class="nav-item"><a class="nav-link" href="/admin/webhook">Webhook</a></li>
            </ul>
            <div class="d-flex align-items-center gap-3">
                <?php if ($user): ?>
                    <span class="text-light small">Olá, <?= htmlspecialchars($user->full_name ?? '') ?></span>
                <?php endif; ?>
                <form method="POST" action="/logout" class="m-0">
                    <button type="submit" class="btn btn-outline-light btn-sm">Sair</button>
                </form>
            </div>
        </div>
    </div>
</nav>
