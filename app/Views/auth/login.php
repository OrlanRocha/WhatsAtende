<?php
/** @var string|null $status */
/** @var string|null $error */
$pageTitle = 'Entrar · WhatsAtende';
include base_path('app/Views/partials/layout-start.php');
?>
<div class="auth-hero d-flex align-items-center justify-content-center py-5">
    <div class="auth-card shadow-lg border-0 w-100" style="max-width: 440px;">
        <div class="card-body p-4 p-lg-5">
            <div class="text-center mb-4">
                <i class="bi bi-chat-dots-fill text-primary fs-1"></i>
                <h1 class="h3 fw-semibold mt-2">Bem-vindo de volta</h1>
                <p class="text-muted">Acesse o painel para gerenciar atendimentos em tempo real.</p>
            </div>
            <div class="alert-stack mb-3">
                <?php if (!empty($status)): ?>
                    <div class="alert alert-success shadow-sm" role="alert"><?= htmlspecialchars($status) ?></div>
                <?php endif; ?>
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger shadow-sm" role="alert"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
            </div>
            <form method="POST" action="/login" class="vstack gap-3" data-ajax data-success-redirect="/tickets" data-success-message="Autenticado com sucesso.">
                <div>
                    <label for="email" class="form-label">E-mail</label>
                    <input type="email" class="form-control form-control-lg" id="email" name="email" required autofocus>
                </div>
                <div>
                    <label for="password" class="form-label">Senha</label>
                    <input type="password" class="form-control form-control-lg" id="password" name="password" required minlength="8">
                </div>
                <button type="submit" class="btn btn-primary btn-lg w-100">
                    <i class="bi bi-box-arrow-in-right"></i> Entrar
                </button>
            </form>
            <div class="d-flex justify-content-between align-items-center mt-4">
                <a href="/forgot-password" class="small">Esqueceu a senha?</a>
                <a href="/register" class="small">Criar uma conta</a>
            </div>
        </div>
    </div>
</div>
<?php include base_path('app/Views/partials/layout-end.php'); ?>
