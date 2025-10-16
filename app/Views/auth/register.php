<?php
/** @var array<int, string> $errors */
/** @var array<string, string> $old */
$pageTitle = 'Cadastro · WhatsAtende';
include base_path('app/Views/partials/layout-start.php');
?>
<div class="auth-hero d-flex align-items-center justify-content-center py-5">
    <div class="auth-card shadow-lg border-0 w-100" style="max-width: 520px;">
        <div class="card-body p-4 p-lg-5">
            <div class="text-center mb-4">
                <i class="bi bi-stars text-success fs-1"></i>
                <h1 class="h3 fw-semibold mt-2">Criar conta de atendente</h1>
                <p class="text-muted">Habilite um novo agente para atuar nas filas e acompanhar métricas.</p>
            </div>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger shadow-sm">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $message): ?>
                            <li><?= htmlspecialchars($message) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <form method="POST" action="/register" class="row g-3" data-ajax data-success-redirect="/tickets" data-success-message="Cadastro realizado com sucesso.">
                <div class="col-12">
                    <label for="full_name" class="form-label">Nome completo</label>
                    <input type="text" class="form-control form-control-lg" id="full_name" name="full_name" required value="<?= htmlspecialchars($old['full_name'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label">E-mail corporativo</label>
                    <input type="email" class="form-control form-control-lg" id="email" name="email" required value="<?= htmlspecialchars($old['email'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label for="cpf" class="form-label">CPF</label>
                    <input type="text" class="form-control form-control-lg" id="cpf" name="cpf" minlength="11" maxlength="14" required value="<?= htmlspecialchars($old['cpf'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label for="password" class="form-label">Senha</label>
                    <input type="password" class="form-control form-control-lg" id="password" name="password" minlength="8" required>
                </div>
                <div class="col-md-6">
                    <label for="password_confirmation" class="form-label">Confirmar senha</label>
                    <input type="password" class="form-control form-control-lg" id="password_confirmation" name="password_confirmation" minlength="8" required>
                </div>
                <div class="col-12 d-grid">
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="bi bi-person-plus"></i> Criar conta
                    </button>
                </div>
            </form>
            <p class="text-center mt-3 mb-0">
                <a href="/login" class="small">Já possui acesso? Entre aqui</a>
            </p>
        </div>
    </div>
</div>
<?php include base_path('app/Views/partials/layout-end.php'); ?>
