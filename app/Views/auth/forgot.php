<?php
/** @var string|null $status */
$pageTitle = 'Recuperar Senha · WhatsAtende';
include base_path('app/Views/partials/layout-start.php');
?>
<div class="auth-hero d-flex align-items-center justify-content-center py-5">
    <div class="auth-card shadow-lg border-0 w-100" style="max-width: 440px;">
        <div class="card-body p-4 p-lg-5">
            <div class="text-center mb-4">
                <i class="bi bi-shield-lock text-warning fs-1"></i>
                <h1 class="h4 fw-semibold mt-2">Recuperar acesso</h1>
                <p class="text-muted">Informe seu e-mail cadastrado para gerar um token de redefinição.</p>
            </div>
            <?php if (!empty($status)): ?>
                <div class="alert alert-info shadow-sm" role="alert"><?= htmlspecialchars($status) ?></div>
            <?php endif; ?>
            <form method="POST" action="/forgot-password" class="vstack gap-3" data-ajax data-success-message="Se o e-mail existir, enviaremos instruções.">
                <div>
                    <label for="email" class="form-label">E-mail</label>
                    <input type="email" class="form-control form-control-lg" id="email" name="email" required>
                </div>
                <button type="submit" class="btn btn-warning btn-lg w-100 text-dark">
                    <i class="bi bi-envelope"></i> Enviar instruções
                </button>
            </form>
            <div class="d-flex justify-content-between mt-3">
                <a href="/login" class="small">Voltar ao login</a>
                <a href="/register" class="small">Criar conta</a>
            </div>
        </div>
    </div>
</div>
<?php include base_path('app/Views/partials/layout-end.php'); ?>
