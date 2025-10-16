<?php
/** @var array<int, array<string, mixed>> $roles */
/** @var array<int, string> $errors */
/** @var array<string, mixed> $old */
/** @var string $action */
/** @var int|null $userId */
$old = $old ?? [];
$isEdit = ($action ?? '') === 'edit';
$target = $isEdit ? '/admin/users/' . urlencode((string) ($userId ?? 0)) : '/admin/users';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $isEdit ? 'Editar usuário' : 'Novo usuário' ?> · WhatsAtende</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
</head>
<body class="bg-light">
<?php include base_path('app/Views/admin/partials/nav.php'); ?>
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-6">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h1 class="h4 mb-0"><?= $isEdit ? 'Editar usuário' : 'Novo usuário' ?></h1>
                    <a href="/admin/users" class="btn btn-sm btn-outline-secondary">Voltar</a>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0 ps-3">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <form method="POST" action="<?= $target ?>">
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Nome completo</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required value="<?= htmlspecialchars((string) ($old['full_name'] ?? '')) ?>">
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">E-mail</label>
                            <input type="email" class="form-control" id="email" name="email" required value="<?= htmlspecialchars((string) ($old['email'] ?? '')) ?>">
                        </div>
                        <div class="mb-3">
                            <label for="cpf" class="form-label">CPF</label>
                            <input type="text" class="form-control" id="cpf" name="cpf" minlength="11" maxlength="11" pattern="[0-9]{11}" required value="<?= htmlspecialchars((string) ($old['cpf'] ?? '')) ?>">
                            <div class="form-text">Somente números.</div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="role_id" class="form-label">Perfil</label>
                                <select id="role_id" name="role_id" class="form-select" required>
                                    <option value="">Selecione</option>
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?= (int) ($role['id'] ?? 0) ?>" <?= ((int) ($old['role_id'] ?? 0) === (int) ($role['id'] ?? 0)) ? 'selected' : '' ?>><?= htmlspecialchars((string) ($role['name'] ?? '')) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="is_active" class="form-label">Status</label>
                                <select id="is_active" name="is_active" class="form-select">
                                    <option value="1" <?= !isset($old['is_active']) || (int) $old['is_active'] === 1 ? 'selected' : '' ?>>Ativo</option>
                                    <option value="0" <?= isset($old['is_active']) && (int) $old['is_active'] === 0 ? 'selected' : '' ?>>Inativo</option>
                                </select>
                            </div>
                        </div>
                        <hr>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="password" class="form-label">Senha <?= $isEdit ? '<span class="text-muted small">(deixe em branco para manter)</span>' : '' ?></label>
                                <input type="password" class="form-control" id="password" name="password" <?= $isEdit ? '' : 'required minlength="8"' ?>>
                            </div>
                            <div class="col-md-6">
                                <label for="password_confirmation" class="form-label">Confirmar senha</label>
                                <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" <?= $isEdit ? '' : 'required minlength="8"' ?>>
                            </div>
                        </div>
                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">Salvar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
