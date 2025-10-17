<?php
$user = auth();
?>
<nav class="navbar navbar-expand-lg border-bottom sticky-top bg-body-tertiary shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand fw-semibold" href="/admin">
            <i class="bi bi-chat-dots-fill text-primary me-2"></i>WhatsAtende
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar" aria-controls="adminNavbar" aria-expanded="false" aria-label="Alternar navegação">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="adminNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="/admin"><i class="bi bi-graph-up"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="/admin/tickets"><i class="bi bi-inboxes"></i> Solicitações</a></li>
                <li class="nav-item"><a class="nav-link" href="/tickets"><i class="bi bi-people"></i> Fila de Atendimento</a></li>
                <li class="nav-item"><a class="nav-link" href="/admin/templates"><i class="bi bi-stickies"></i> Templates</a></li>
                <li class="nav-item"><a class="nav-link" href="/admin/users"><i class="bi bi-person-gear"></i> Usuários</a></li>
                <li class="nav-item"><a class="nav-link" href="/admin/logs"><i class="bi bi-clipboard-data"></i> Logs</a></li>
                <li class="nav-item"><a class="nav-link" href="/admin/webhook"><i class="bi bi-plug"></i> Integração</a></li>
            </ul>
            <div class="d-flex align-items-center gap-3">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-theme-toggle title="Alternar tema">
                    <i class="bi bi-moon-stars"></i>
                </button>
                <?php if ($user): ?>
                    <div class="text-end small">
                        <div class="fw-semibold">Olá, <?= htmlspecialchars($user->full_name ?? '') ?></div>
                        <div class="text-muted text-capitalize"><?= htmlspecialchars($user->role ?? '') ?></div>
                    </div>
                <?php endif; ?>
                <form method="POST" action="/logout" class="m-0" data-ajax data-success-redirect="/login">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-box-arrow-right"></i> Sair
                    </button>
                </form>
            </div>
        </div>
    </div>
</nav>
