<?php
/** @var array<int, array<string, mixed>> $roles */
/** @var array<int, string> $errors */
/** @var array<string, mixed> $old */
/** @var string $action */
/** @var int|null $userId */
$old = $old ?? [];
$isEdit = ($action ?? '') === 'edit';
$target = $isEdit ? '/admin/users/' . urlencode((string) ($userId ?? 0)) : '/admin/users';
$breadcrumbs = [
    ['label' => 'Admin', 'href' => '/admin'],
    ['label' => 'Usuários', 'href' => '/admin/users'],
    ['label' => $isEdit ? 'Editar usuário' : 'Novo usuário'],
];
$pageTitle = ($isEdit ? 'Editar usuário' : 'Novo usuário') . ' · WhatsAtende';
include base_path('app/Views/partials/layout-start.php');
include base_path('app/Views/partials/topbar.php');
?>
<div class="workspace workspace--narrow" id="user-form">
    <div class="card shadow-sm border-0">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h1 class="h4 mb-0"><?= $isEdit ? 'Editar usuário' : 'Novo usuário' ?></h1>
            <a href="/admin/users" class="btn btn-outline-secondary btn-sm">Voltar</a>
        </div>
        <div class="card-body">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger" role="alert">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <form method="POST" action="<?= $target ?>">
                <div class="mb-3">
                    <label class="form-label" for="full_name">Nome completo</label>
                    <input type="text" class="form-control" id="full_name" name="full_name" required value="<?= htmlspecialchars((string) ($old['full_name'] ?? '')) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="email">E-mail</label>
                    <input type="email" class="form-control" id="email" name="email" required value="<?= htmlspecialchars((string) ($old['email'] ?? '')) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="role">Perfil</label>
                    <select class="form-select" id="role" name="role" required>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= htmlspecialchars((string) ($role['name'] ?? '')) ?>" <?= (($old['role'] ?? '') === ($role['name'] ?? '')) ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) ($role['label'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="password">Senha</label>
                        <input type="password" class="form-control" id="password" name="password" <?= $isEdit ? '' : 'required' ?>>
                        <small class="text-muted"><?= $isEdit ? 'Preencha apenas para alterar.' : 'Defina uma senha inicial segura.' ?></small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="password_confirmation">Confirmar senha</label>
                        <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" <?= $isEdit ? '' : 'required' ?>>
                    </div>
                </div>
                <div class="mt-4 d-flex justify-content-end gap-2">
                    <a href="/admin/users" class="btn btn-outline-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include base_path('app/Views/partials/layout-end.php'); ?>
