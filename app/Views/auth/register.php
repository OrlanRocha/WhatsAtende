<?php
/** @var array<int, string> $errors */
/** @var array<string, string> $old */
$pageTitle = 'Cadastro · WhatsAtende';
include base_path('app/Views/partials/layout-auth-start.php');
?>
<div class="auth-wrapper">
    <section class="auth-showcase" aria-label="Benefícios para novos atendentes">
        <span class="auth-badge auth-badge--success">Equipe em crescimento</span>
        <h1 class="auth-title">Crie acesso para novos atendentes</h1>
        <p class="auth-subtitle">
            Garanta que cada agente tenha perfil, preferências e métricas personalizadas com governança completa.
        </p>
        <ul class="auth-feature-list">
            <li><i class="bi bi-person-vcard" aria-hidden="true"></i> Perfis individuais com preferências de tema e densidade</li>
            <li><i class="bi bi-diagram-3" aria-hidden="true"></i> Atribuição inteligente e métricas por skill</li>
            <li><i class="bi bi-journal-check" aria-hidden="true"></i> Auditoria de ações e acessos em tempo real</li>
        </ul>
    </section>
    <section class="auth-content" aria-label="Formulário de cadastro">
        <div class="auth-card">
            <div class="auth-card__header">
                <i class="bi bi-stars" aria-hidden="true"></i>
                <div>
                    <h2 class="auth-card__title">Criar conta de atendente</h2>
                    <p class="auth-card__subtitle">Preencha os dados do novo agente para liberar o painel.</p>
                </div>
            </div>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger shadow-sm" role="alert">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $message): ?>
                            <li><?= htmlspecialchars($message) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <form method="POST" action="<?= htmlspecialchars(route_path('/register'), ENT_QUOTES) ?>" class="row g-3" data-ajax data-success-redirect="<?= htmlspecialchars(route_path('/tickets'), ENT_QUOTES) ?>" data-success-message="Cadastro realizado com sucesso.">
                <div class="col-12">
                    <label for="full_name" class="form-label">Nome completo</label>
                    <input type="text" class="form-control form-control-lg" id="full_name" name="full_name" required value="<?= htmlspecialchars($old['full_name'] ?? '') ?>" autocomplete="name">
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label">E-mail corporativo</label>
                    <input type="email" class="form-control form-control-lg" id="email" name="email" required value="<?= htmlspecialchars($old['email'] ?? '') ?>" autocomplete="email">
                </div>
                <div class="col-md-6">
                    <label for="cpf" class="form-label">CPF</label>
                    <input type="text" class="form-control form-control-lg" id="cpf" name="cpf" minlength="11" maxlength="14" required value="<?= htmlspecialchars($old['cpf'] ?? '') ?>" autocomplete="off">
                </div>
                <div class="col-md-6">
                    <label for="password" class="form-label">Senha</label>
                    <input type="password" class="form-control form-control-lg" id="password" name="password" minlength="8" required autocomplete="new-password">
                </div>
                <div class="col-md-6">
                    <label for="password_confirmation" class="form-label">Confirmar senha</label>
                    <input type="password" class="form-control form-control-lg" id="password_confirmation" name="password_confirmation" minlength="8" required autocomplete="new-password">
                </div>
                <div class="col-12 d-grid">
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="bi bi-person-plus"></i> Criar conta
                    </button>
                </div>
            </form>
            <div class="auth-links">
                <a href="<?= htmlspecialchars(route_path('/login'), ENT_QUOTES) ?>" class="link-secondary">Já possui acesso? Entre aqui</a>
            </div>
        </div>
    </section>
</div>
<?php include base_path('app/Views/partials/layout-auth-end.php'); ?>
