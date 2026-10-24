<?php
/** @var array<int, array<string, mixed>> $roles */
/** @var array<int, array<string, mixed>> $permissions */
/** @var array<int, array<string, mixed>> $groups */
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
$selectedPermissions = array_map('strval', $old['permissions'] ?? []);
$customPermissions = (string) ($old['custom_permissions'] ?? '');
$selectedRoleId = (int) ($old['role_id'] ?? ($old['role'] ?? 0));
$isActiveValue = array_key_exists('is_active', $old) ? (bool) (int) $old['is_active'] : true;
$availableGroups = $groups ?? [];
$selectedGroups = [];
if (!empty($old['group_ids']) && is_array($old['group_ids'])) {
    $selectedGroups = array_map('intval', $old['group_ids']);
} elseif (!empty($old['groups']) && is_array($old['groups'])) {
    foreach ($old['groups'] as $group) {
        if (is_array($group) && isset($group['id'])) {
            $selectedGroups[] = (int) $group['id'];
        } elseif (is_scalar($group)) {
            $selectedGroups[] = (int) $group;
        }
    }
}
$selectedGroups = array_values(array_unique(array_filter($selectedGroups, static fn ($value) => $value > 0)));

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
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="full_name">Nome completo</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required value="<?= htmlspecialchars((string) ($old['full_name'] ?? '')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="email">E-mail</label>
                        <input type="email" class="form-control" id="email" name="email" required value="<?= htmlspecialchars((string) ($old['email'] ?? '')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="cpf">CPF</label>
                        <input type="text" class="form-control" id="cpf" name="cpf" required maxlength="14" inputmode="numeric" value="<?= htmlspecialchars((string) ($old['cpf'] ?? '')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="role_id">Perfil</label>
                        <select class="form-select" id="role_id" name="role_id" required>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= (int) ($role['id'] ?? 0) ?>" <?= $selectedRoleId === (int) ($role['id'] ?? 0) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string) ($role['display_name'] ?? $role['name'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="password">Senha</label>
                        <input type="password" class="form-control" id="password" name="password" <?= $isEdit ? '' : 'required' ?> minlength="8">
                        <small class="text-muted"><?= $isEdit ? 'Preencha apenas para alterar.' : 'Defina uma senha inicial segura.' ?></small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="password_confirmation">Confirmar senha</label>
                        <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" <?= $isEdit ? '' : 'required' ?> minlength="8">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Status</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" <?= $isActiveValue ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_active">Usuário ativo</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Permissões individuais</label>
                        <div class="d-flex flex-wrap gap-3">
                            <?php foreach ($permissions as $permission): ?>
                                <?php $permissionName = (string) ($permission['name'] ?? ''); ?>
                                <div class="form-check form-check-inline align-items-start">
                                    <input class="form-check-input" type="checkbox" value="<?= htmlspecialchars($permissionName) ?>" id="perm-form-<?= (int) ($permission['id'] ?? 0) ?>" name="permissions[]" <?= in_array($permissionName, $selectedPermissions, true) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="perm-form-<?= (int) ($permission['id'] ?? 0) ?>">
                                        <span class="fw-semibold d-block"><?= htmlspecialchars((string) ($permission['label'] ?? $permissionName)) ?></span>
                                        <?php if (!empty($permission['description'])): ?>
                                            <small class="text-muted"><?= htmlspecialchars((string) $permission['description']) ?></small>
                                        <?php endif; ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <small class="text-muted d-block mt-1">Selecione permissões adicionais específicas para este usuário.</small>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Grupos de atendimento</label>
                        <div class="d-flex flex-wrap gap-3">
                            <?php foreach ($availableGroups as $group): ?>
                                <?php $groupId = (int) ($group['id'] ?? 0); ?>
                                <div class="form-check form-check-inline align-items-start">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        value="<?= $groupId ?>"
                                        id="group-form-<?= $groupId ?>"
                                        name="groups[]"
                                        <?= in_array($groupId, $selectedGroups, true) ? 'checked' : '' ?>
                                    >
                                    <label class="form-check-label" for="group-form-<?= $groupId ?>">
                                        <span class="fw-semibold d-block"><?= htmlspecialchars((string) ($group['name'] ?? '')) ?></span>
                                        <?php if (!empty($group['description'])): ?>
                                            <small class="text-muted"><?= htmlspecialchars((string) $group['description']) ?></small>
                                        <?php endif; ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <small class="text-muted d-block mt-1">Defina as filas e grupos em que este usuário pode atuar.</small>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="custom_permissions">Permissões personalizadas</label>
                        <input type="text" class="form-control" id="custom_permissions" name="custom_permissions" value="<?= htmlspecialchars($customPermissions) ?>" placeholder="Ex.: reports.export, ai.override">
                        <small class="text-muted">Separe múltiplas permissões por vírgula, espaço ou quebra de linha.</small>
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
