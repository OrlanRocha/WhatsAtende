<?php
/** @var string|null $status */
$pageTitle = 'Recuperar Senha · WhatsAtende';
include base_path('app/Views/partials/layout-auth-start.php');
?>
<div class="auth-wrapper">
    <section class="auth-showcase" aria-label="Ajuda para recuperar acesso">
        <span class="auth-badge auth-badge--warning">Segurança primeiro</span>
        <h1 class="auth-title">Recupere sua conta com segurança</h1>
        <p class="auth-subtitle">
            Enviaremos um link de redefinição para o e-mail cadastrado. O token expira rapidamente para proteger seus dados.
        </p>
        <ul class="auth-feature-list">
            <li><i class="bi bi-envelope-check" aria-hidden="true"></i> Link enviado apenas para o e-mail registrado</li>
            <li><i class="bi bi-shield-lock" aria-hidden="true"></i> Tokens únicos e automaticamente expirados</li>
            <li><i class="bi bi-life-preserver" aria-hidden="true"></i> Equipe pronta para ajudar se o acesso não chegar</li>
        </ul>
    </section>
    <section class="auth-content" aria-label="Formulário de recuperação">
        <div class="auth-card">
            <div class="auth-card__header">
                <i class="bi bi-shield-lock" aria-hidden="true"></i>
                <div>
                    <h2 class="auth-card__title">Recuperar acesso</h2>
                    <p class="auth-card__subtitle">Informe o e-mail utilizado no cadastro para receber as instruções.</p>
                </div>
            </div>
            <?php if (!empty($status)): ?>
                <div class="alert alert-info shadow-sm" role="alert"><?= htmlspecialchars($status) ?></div>
            <?php endif; ?>
            <form method="POST" action="/forgot-password" class="vstack gap-3" data-ajax data-success-message="Se o e-mail existir, enviaremos instruções.">
                <div>
                    <label for="email" class="form-label">E-mail</label>
                    <input type="email" class="form-control form-control-lg" id="email" name="email" required autocomplete="email">
                </div>
                <button type="submit" class="btn btn-warning btn-lg w-100 text-dark">
                    <i class="bi bi-envelope"></i> Enviar instruções
                </button>
            </form>
            <div class="auth-links">
                <a href="/login" class="link-secondary">Voltar ao login</a>
                <a href="/register" class="link-secondary">Criar conta</a>
            </div>
        </div>
    </section>
</div>
<?php include base_path('app/Views/partials/layout-auth-end.php'); ?>
