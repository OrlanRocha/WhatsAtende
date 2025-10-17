<?php
/** @var array<int, array<string, mixed>> $users */
/** @var string|null $status */
/** @var string|null $error */
$pageTitle = 'Usuários · WhatsAtende';
include base_path('app/Views/partials/layout-start.php');
include base_path('app/Views/admin/partials/nav.php');
?>
<div class="container-xxl py-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1 fw-semibold">Usuários do Sistema</h1>
            <p class="text-muted mb-0">Gerencie acessos de administradores e atendentes com respostas em tempo real.</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" data-mode="create">
            <i class="bi bi-plus-circle"></i> Novo usuário
        </button>
    </div>
    <div class="alert-stack">
        <?php if (!empty($status)): ?>
            <div class="alert alert-success shadow-sm" role="alert"><?= htmlspecialchars($status) ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger shadow-sm" role="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
    </div>
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="users-table" data-table>
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Nome</th>
                        <th>E-mail</th>
                        <th>CPF</th>
                        <th>Perfil</th>
                        <th>Ativo</th>
                        <th>Atribuídos</th>
                        <th class="text-end">Ações</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr data-user-row data-user='<?= json_encode($user, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>'>
                            <td><?= (int) ($user['id'] ?? 0) ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars((string) ($user['full_name'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($user['email'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($user['cpf'] ?? '')) ?></td>
                            <td><span class="badge bg-gradient text-capitalize"><?= htmlspecialchars((string) ($user['role'] ?? '')) ?></span></td>
                            <td>
                                <?php if (!empty($user['is_active'])): ?>
                                    <span class="badge rounded-pill text-bg-success"><i class="bi bi-check-circle"></i> Sim</span>
                                <?php else: ?>
                                    <span class="badge rounded-pill text-bg-danger"><i class="bi bi-x-circle"></i> Não</span>
                                <?php endif; ?>
                            </td>
                            <td><?= (int) ($user['assigned_tickets'] ?? 0) ?></td>
                            <td class="text-end">
                                <div class="btn-group" role="group">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#userModal" data-mode="edit">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <form method="POST" action="/admin/users/<?= urlencode((string) ($user['id'] ?? '')) ?>/delete" class="d-inline" data-ajax data-confirm="Remover este usuário?" data-success-event="users:refresh">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header">
                <h5 class="modal-title" id="userModalLabel">Novo usuário</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form method="POST" action="/admin/users" data-ajax data-hide-modal="#userModal" data-success-event="users:refresh" data-reset="true" id="userForm">
                <div class="modal-body">
                    <input type="hidden" name="_method" value="create" id="user-form-method">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="user_full_name">Nome completo</label>
                            <input type="text" class="form-control" id="user_full_name" name="full_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="user_email">E-mail</label>
                            <input type="email" class="form-control" id="user_email" name="email" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="user_cpf">CPF</label>
                            <input type="text" class="form-control" id="user_cpf" name="cpf" required inputmode="numeric" maxlength="14">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="user_role">Perfil</label>
                            <select class="form-select" id="user_role" name="role_id" required>
                                <?php foreach ($roles ?? [] as $role): ?>
                                    <option value="<?= (int) ($role['id'] ?? 0) ?>" data-role-name="<?= htmlspecialchars((string) ($role['name'] ?? '')) ?>">
                                        <?= htmlspecialchars((string) ($role['display_name'] ?? $role['name'] ?? '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="user_password">Senha</label>
                            <input type="password" class="form-control" id="user_password" name="password" minlength="8">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="user_password_confirmation">Confirmar senha</label>
                            <input type="password" class="form-control" id="user_password_confirmation" name="password_confirmation" minlength="8">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Status</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="user_is_active" name="is_active" value="1" checked>
                                <label class="form-check-label" for="user_is_active">Usuário ativo</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-floppy"></i> Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script type="module">
import { initUserModal, refreshUserTable } from '/js/modules/users.js';
initUserModal('#userModal', '#userForm');
window.addEventListener('users:refresh', () => refreshUserTable('#users-table', '/admin/users'));
</script>
<?php include base_path('app/Views/partials/layout-end.php'); ?>
