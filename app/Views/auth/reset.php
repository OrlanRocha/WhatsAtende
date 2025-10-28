<?php
/** @var string $token */
/** @var string $email */
/** @var string|null $status */
/** @var string|null $error */
$pageTitle = 'Definir nova senha · WhatsAtende';
include base_path('app/Views/partials/layout-auth-start.php');
?>
<div class="auth-wrapper">
    <section class="auth-showcase" aria-label="Orientações de redefinição">
        <span class="auth-badge auth-badge--info">Atualize sua credencial</span>
        <h1 class="auth-title">Defina uma nova senha com confiança</h1>
        <p class="auth-subtitle">
            Ao concluir, encerraremos sessões anteriores e enviaremos um alerta de segurança para o administrador.
        </p>
        <ul class="auth-feature-list">
            <li><i class="bi bi-unlock" aria-hidden="true"></i> Senhas criptografadas com padrão moderno</li>
            <li><i class="bi bi-hourglass-split" aria-hidden="true"></i> Token válido por tempo limitado</li>
            <li><i class="bi bi-chat-text" aria-hidden="true"></i> Contato do suporte disponível em caso de dúvidas</li>
        </ul>
    </section>
    <section class="auth-content" aria-label="Formulário de nova senha">
        <div class="auth-card">
            <div class="auth-card__header">
                <i class="bi bi-key" aria-hidden="true"></i>
                <div>
                    <h2 class="auth-card__title">Definir nova senha</h2>
                    <p class="auth-card__subtitle">Redefinindo acesso para <strong><?= htmlspecialchars($email) ?></strong></p>
                </div>
            </div>
            <div class="alert-stack" role="status">
                <?php if (!empty($status)): ?>
                    <div class="alert alert-info shadow-sm" role="alert"><?= htmlspecialchars($status) ?></div>
                <?php endif; ?>
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger shadow-sm" role="alert"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
            </div>
            <form method="POST" action="<?= htmlspecialchars(route_path('/reset-password'), ENT_QUOTES) ?>" class="vstack gap-3" data-ajax data-success-message="Senha redefinida com sucesso." data-success-redirect="<?= htmlspecialchars(route_path('/login'), ENT_QUOTES) ?>">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <div>
                    <label for="password" class="form-label">Nova senha</label>
                    <input type="password" class="form-control form-control-lg" id="password" name="password" minlength="8" required autocomplete="new-password" autofocus>
                </div>
                <div>
                    <label for="password_confirmation" class="form-label">Confirmar nova senha</label>
                    <input type="password" class="form-control form-control-lg" id="password_confirmation" name="password_confirmation" minlength="8" required autocomplete="new-password">
                </div>
                <button type="submit" class="btn btn-info btn-lg w-100 text-dark">
                    <i class="bi bi-unlock"></i> Salvar nova senha
                </button>
            </form>
            <div class="auth-links">
                <a href="<?= htmlspecialchars(route_path('/login'), ENT_QUOTES) ?>" class="link-secondary">Voltar ao login</a>
            </div>
        </div>
    </section>
</div>
<?php include base_path('app/Views/partials/layout-auth-end.php'); ?>
