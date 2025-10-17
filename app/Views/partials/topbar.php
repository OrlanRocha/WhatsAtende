<?php
$user = auth();
?>
<nav class="navbar navbar-expand-lg border-bottom bg-body-tertiary shadow-sm sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand fw-semibold" href="/tickets">
            <i class="bi bi-chat-square-text text-primary me-2"></i>WhatsAtende
        </a>
        <div class="d-flex align-items-center gap-2">
            <?php if ($user && ($user->role ?? null) === 'admin'): ?>
                <a class="btn btn-outline-primary btn-sm" href="/admin">
                    <i class="bi bi-speedometer2"></i> Admin
                </a>
            <?php endif; ?>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-theme-toggle title="Alternar tema">
                <i class="bi bi-brightness-high"></i>
            </button>
            <?php if ($user): ?>
                <div class="text-end small">
                    <div class="fw-semibold">Olá, <?= htmlspecialchars($user->full_name ?? '') ?></div>
                    <div class="text-muted text-capitalize"><?= htmlspecialchars($user->role ?? '') ?></div>
                </div>
                <form method="POST" action="/logout" class="m-0" data-ajax data-success-redirect="/login">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-box-arrow-right"></i> Sair
                    </button>
                </form>
            <?php else: ?>
                <a class="btn btn-primary btn-sm" href="/login">
                    <i class="bi bi-box-arrow-in-right"></i> Entrar
                </a>
            <?php endif; ?>
        </div>
    </div>
</nav>
