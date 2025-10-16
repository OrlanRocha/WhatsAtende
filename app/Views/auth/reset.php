<?php
/** @var string $token */
/** @var string $email */
/** @var string|null $status */
/** @var string|null $error */
$pageTitle = 'Definir nova senha · WhatsAtende';
include base_path('app/Views/partials/layout-start.php');
?>
<div class="auth-hero d-flex align-items-center justify-content-center py-5">
    <div class="auth-card shadow-lg border-0 w-100" style="max-width: 440px;">
        <div class="card-body p-4 p-lg-5">
            <div class="text-center mb-4">
                <i class="bi bi-key text-info fs-1"></i>
                <h1 class="h4 fw-semibold mt-2">Definir nova senha</h1>
                <p class="text-muted">Redefinindo acesso para <strong><?= htmlspecialchars($email) ?></strong></p>
            </div>
            <div class="alert-stack mb-3">
                <?php if (!empty($status)): ?>
                    <div class="alert alert-info shadow-sm" role="alert"><?= htmlspecialchars($status) ?></div>
                <?php endif; ?>
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger shadow-sm" role="alert"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
            </div>
            <form method="POST" action="/reset-password" class="vstack gap-3" data-ajax data-success-message="Senha redefinida com sucesso." data-success-redirect="/login">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <div>
                    <label for="password" class="form-label">Nova senha</label>
                    <input type="password" class="form-control form-control-lg" id="password" name="password" minlength="8" required autofocus>
                </div>
                <div>
                    <label for="password_confirmation" class="form-label">Confirmar nova senha</label>
                    <input type="password" class="form-control form-control-lg" id="password_confirmation" name="password_confirmation" minlength="8" required>
                </div>
                <button type="submit" class="btn btn-info btn-lg w-100 text-dark">
                    <i class="bi bi-unlock"></i> Salvar nova senha
                </button>
            </form>
            <div class="text-center mt-3">
                <a href="/login" class="small">Voltar ao login</a>
            </div>
        </div>
    </div>
</div>
<?php include base_path('app/Views/partials/layout-end.php'); ?>
