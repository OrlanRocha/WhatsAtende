<?php
/** @var string|null $status */
/** @var string|null $error */
$pageTitle = 'Entrar · WhatsAtende';
include base_path('app/Views/partials/layout-auth-start.php');
?>
<div class="auth-wrapper">
    <section class="auth-showcase" aria-label="Destaques do painel">
        <span class="auth-badge">WhatsAtende vNext</span>
        <h1 class="auth-title">Bem-vindo de volta</h1>
        <p class="auth-subtitle">
            Acesse um ambiente de atendimento com Command Palette, indicadores em tempo real e fluxo unificado de tickets.
        </p>
        <ul class="auth-feature-list">
            <li><i class="bi bi-lightning-charge-fill" aria-hidden="true"></i> Atalhos rápidos com <kbd>Ctrl</kbd> + <kbd>K</kbd></li>
            <li><i class="bi bi-activity" aria-hidden="true"></i> Health widget e métricas de SLA em tempo real</li>
            <li><i class="bi bi-shield-check" aria-hidden="true"></i> Segurança com verificação de sessão e logs detalhados</li>
        </ul>
    </section>
    <section class="auth-content" aria-label="Formulário de acesso">
        <div class="auth-card">
            <div class="auth-card__header">
                <i class="bi bi-chat-dots-fill" aria-hidden="true"></i>
                <div>
                    <h2 class="auth-card__title">Entre com suas credenciais</h2>
                    <p class="auth-card__subtitle">Utilize o e-mail corporativo informado pelo administrador.</p>
                </div>
            </div>
            <div class="alert-stack" role="status">
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
                    <input type="email" class="form-control form-control-lg" id="email" name="email" required autofocus autocomplete="email">
                </div>
                <div>
                    <label for="password" class="form-label">Senha</label>
                    <input type="password" class="form-control form-control-lg" id="password" name="password" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn btn-primary btn-lg w-100">
                    <i class="bi bi-box-arrow-in-right"></i> Entrar
                </button>
            </form>
            <div class="auth-links">
                <a href="/forgot-password" class="link-secondary">Esqueceu a senha?</a>
                <a href="/register" class="link-secondary">Criar uma conta</a>
            </div>
        </div>
    </section>
</div>
<?php include base_path('app/Views/partials/layout-auth-end.php'); ?>
