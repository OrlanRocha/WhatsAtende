<?php
/** @var array<int, array<string, mixed>> $users */
/** @var string|null $status */
/** @var string|null $error */
/** @var array<int, array<string, mixed>> $groups */
$breadcrumbs = [
    ['label' => 'Admin', 'href' => '/admin'],
    ['label' => 'Usuários'],
];
$pageTitle = 'Usuários · WhatsAtende';
include base_path('app/Views/partials/layout-start.php');
include base_path('app/Views/partials/topbar.php');
?>
<div class="space-y-8 pb-12">
    <!-- Header Section -->
    <header class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Usuários do Sistema</h1>
            <p class="text-slate-500 dark:text-slate-400 mt-1">Gerencie acessos de administradores e atendentes em tempo real.</p>
        </div>
        <button class="inline-flex items-center gap-2 px-6 py-3 bg-primary hover:bg-primary-dark text-white rounded-2xl font-bold shadow-lg shadow-primary/25 transition-all active:scale-[0.98]" 
                data-bs-toggle="modal" data-bs-target="#userModal" data-mode="create">
            <i class="bi bi-plus-circle"></i> Novo usuário
        </button>
    </header>

    <div class="alert-stack">
        <?php if (!empty($status)): ?>
            <div class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-100 text-emerald-700 dark:bg-emerald-900/20 dark:border-emerald-800/30 dark:text-emerald-400 flex items-center gap-3">
                <i class="bi bi-check-circle-fill"></i>
                <span class="font-medium"><?= htmlspecialchars($status) ?></span>
            </div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-100 text-rose-700 dark:bg-rose-900/20 dark:border-rose-800/30 dark:text-rose-400 flex items-center gap-3">
                <i class="bi bi-exclamation-circle-fill"></i>
                <span class="font-medium"><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Users Table Card -->
    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden transition-all duration-300">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse" id="users-table" data-table>
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-950/50">
                        <th class="px-8 py-5 text-[11px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 dark:border-slate-800">#</th>
                        <th class="px-6 py-5 text-[11px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 dark:border-slate-800">Nome</th>
                        <th class="px-6 py-5 text-[11px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 dark:border-slate-800">E-mail</th>
                        <th class="px-6 py-5 text-[11px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 dark:border-slate-800">Perfil</th>
                        <th class="px-6 py-5 text-[11px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 dark:border-slate-800">Ativo</th>
                        <th class="px-6 py-5 text-[11px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 dark:border-slate-800">Grupos</th>
                        <th class="px-8 py-5 text-[11px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 dark:border-slate-800 text-end">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <?php foreach ($users as $user): ?>
                        <tr class="group hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors" 
                            data-user-row data-user='<?= json_encode($user, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>'>
                            <td class="px-8 py-5 text-sm font-bold text-slate-400">#<?= (int) ($user['id'] ?? 0) ?></td>
                            <td class="px-6 py-5">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-xs font-bold text-slate-500 uppercase">
                                        <?= substr($user['full_name'] ?? 'U', 0, 1) ?>
                                    </div>
                                    <span class="text-sm font-bold text-slate-900 dark:text-white capitalize"><?= htmlspecialchars((string) ($user['full_name'] ?? '')) ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-5 text-sm text-slate-500 dark:text-slate-400"><?= htmlspecialchars((string) ($user['email'] ?? '')) ?></td>
                            <td class="px-6 py-5">
                                <span class="inline-flex px-3 py-1 rounded-lg bg-primary/10 text-primary text-[10px] font-bold uppercase tracking-widest">
                                    <?= htmlspecialchars((string) ($user['role'] ?? '')) ?>
                                </span>
                            </td>
                            <td class="px-6 py-5 text-sm">
                                <?php if (!empty($user['is_active'])): ?>
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400 text-[10px] font-bold uppercase tracking-wider">
                                        <div class="w-1.5 h-1.5 rounded-full bg-emerald-500"></div> Sim
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-rose-50 dark:bg-rose-900/20 text-rose-600 dark:text-rose-400 text-[10px] font-bold uppercase tracking-wider">
                                        <div class="w-1.5 h-1.5 rounded-full bg-rose-500"></div> Não
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-5">
                                <div class="flex flex-wrap gap-1.5">
                                    <?php if (!empty($user['groups'])): ?>
                                        <?php foreach ($user['groups'] as $group): ?>
                                            <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-[10px] font-bold text-slate-500 uppercase tracking-tighter">
                                                <?= htmlspecialchars((string) ($group['name'] ?? $group['slug'] ?? $group['id'] ?? 'Fila')) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-slate-300 dark:text-slate-600 text-xs">—</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-8 py-5 text-end">
                                <div class="inline-flex items-center gap-1 p-1 bg-slate-100 dark:bg-slate-800 rounded-xl">
                                    <button class="p-2 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-white dark:hover:bg-slate-700 hover:text-primary transition-all" 
                                            data-bs-toggle="modal" data-bs-target="#userModal" data-mode="edit">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <form method="POST" action="/admin/users/<?= urlencode((string) ($user['id'] ?? '')) ?>/delete" class="inline" data-ajax data-confirm="Remover este usuário?" data-success-event="users:refresh">
                                        <button type="submit" class="p-2 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-white dark:hover:bg-slate-700 hover:text-rose-500 transition-all">
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

<!-- Refactored User Modal -->
<div id="userModal" class="modal-overlay" role="dialog" aria-modal="true">
    <div class="modal-content">
        <div class="modal-header border-b border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/50">
            <div>
                <h5 class="text-xl font-bold text-slate-800 dark:text-white" id="userModalLabel">Novo usuário</h5>
                <p class="text-xs text-slate-500 font-medium mt-1">Configure permissões e acessos.</p>
            </div>
            <button type="button" class="w-10 h-10 flex items-center justify-center rounded-xl hover:bg-slate-200 dark:hover:bg-slate-800 transition-colors" data-bs-dismiss="modal">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        
        <form method="POST" action="/admin/users" data-ajax data-hide-modal="#userModal" data-success-event="users:refresh" data-reset="true" id="userForm" class="flex flex-col overflow-hidden">
            <div class="modal-body">
                <input type="hidden" name="_method" value="create" id="user-form-method">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 ml-1" for="user_full_name">Nome completo</label>
                            <input type="text" 
                                class="w-full px-5 py-3 rounded-2xl bg-slate-100 dark:bg-slate-800/50 border-0 text-slate-900 dark:text-white focus:ring-2 focus:ring-primary/20 outline-none transition-all placeholder:text-slate-400" 
                                id="user_full_name" name="full_name" required>
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 ml-1" for="user_email">E-mail</label>
                            <input type="email" 
                                class="w-full px-5 py-3 rounded-2xl bg-slate-100 dark:bg-slate-800/50 border-0 text-slate-900 dark:text-white focus:ring-2 focus:ring-primary/20 outline-none transition-all placeholder:text-slate-400" 
                                id="user_email" name="email" required>
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 ml-1" for="user_cpf">CPF</label>
                            <input type="text" 
                                class="w-full px-5 py-3 rounded-2xl bg-slate-100 dark:bg-slate-800/50 border-0 text-slate-900 dark:text-white focus:ring-2 focus:ring-primary/20 outline-none transition-all placeholder:text-slate-400" 
                                id="user_cpf" name="cpf" required inputmode="numeric" maxlength="14" placeholder="000.000.000-00">
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 ml-1" for="user_role">Perfil de Acesso</label>
                            <div class="relative group">
                                <select class="w-full appearance-none px-5 py-3 rounded-2xl bg-slate-100 dark:bg-slate-800/50 border-0 text-slate-900 dark:text-white focus:ring-2 focus:ring-primary/20 outline-none transition-all cursor-pointer" 
                                    id="user_role" name="role_id" required>
                                    <?php foreach ($roles ?? [] as $role): ?>
                                        <option value="<?= (int) ($role['id'] ?? 0) ?>" data-role-name="<?= htmlspecialchars((string) ($role['name'] ?? '')) ?>">
                                            <?= htmlspecialchars((string) ($role['display_name'] ?? $role['name'] ?? '')) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <i class="bi bi-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-xs"></i>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 ml-1" for="user_password">Senha</label>
                            <input type="password" 
                                class="w-full px-5 py-3 rounded-2xl bg-slate-100 dark:bg-slate-800/50 border-0 text-slate-900 dark:text-white focus:ring-2 focus:ring-primary/20 outline-none transition-all placeholder:text-slate-400" 
                                id="user_password" name="password" minlength="8" placeholder="••••••••">
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 ml-1" for="user_password_confirmation">Confirmar senha</label>
                            <input type="password" 
                                class="w-full px-5 py-3 rounded-2xl bg-slate-100 dark:bg-slate-800/50 border-0 text-slate-900 dark:text-white focus:ring-2 focus:ring-primary/20 outline-none transition-all placeholder:text-slate-400" 
                                id="user_password_confirmation" name="password_confirmation" minlength="8" placeholder="••••••••">
                        </div>
                        
                        <div class="col-span-1 md:col-span-2 py-4">
                            <div class="flex items-center gap-4 p-4 rounded-2xl bg-slate-50 dark:bg-slate-950/50 border border-slate-100 dark:border-slate-800">
                                <button type="button" role="switch" id="user_is_active_toggle" 
                                    onclick="document.getElementById('user_is_active').click(); this.classList.toggle('bg-primary'); this.querySelector('div').classList.toggle('translate-x-6')"
                                    class="relative inline-flex h-7 w-12 items-center rounded-full bg-slate-300 dark:bg-slate-700 transition-colors focus:outline-none focus:ring-2 focus:ring-primary/20 bg-primary">
                                    <input type="checkbox" id="user_is_active" name="is_active" value="1" class="hidden" checked>
                                    <div class="h-5 w-5 translate-x-6 rounded-full bg-white transition-transform"></div>
                                </button>
                                <div>
                                    <label class="block text-sm font-bold text-slate-800 dark:text-white" for="user_is_active_toggle">Usuário Ativo</label>
                                    <p class="text-xs text-slate-500">Define se o usuário poderá realizar login no sistema.</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-span-1 md:col-span-2 space-y-4">
                            <label class="block text-sm font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 dark:border-slate-800 pb-2">Acesso por Fila (Grupos)</label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3" data-groups-list>
                                <?php foreach ($groups ?? [] as $group): ?>
                                    <?php $groupId = (int) ($group['id'] ?? 0); ?>
                                    <label class="flex items-start gap-3 p-3 rounded-xl border border-slate-100 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50 cursor-pointer transition-colors group">
                                        <input type="checkbox" value="<?= $groupId ?>" name="groups[]" class="mt-1 h-5 w-5 rounded-md border-slate-300 text-primary focus:ring-primary/20 transition-all cursor-pointer" data-group-checkbox id="modal-group-<?= $groupId ?>">
                                        <div>
                                            <span class="block text-xs font-bold text-slate-700 dark:text-slate-300 group-hover:text-primary transition-colors"><?= htmlspecialchars((string) ($group['name'] ?? $group['slug'] ?? $group['id'] ?? 'Fila')) ?></span>
                                            <?php if (!empty($group['description'])): ?>
                                                <span class="block text-[10px] text-slate-400 line-clamp-1"><?= htmlspecialchars((string) $group['description']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="col-span-1 md:col-span-2 space-y-4">
                            <label class="block text-sm font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 dark:border-slate-800 pb-2">Permissões Específicas</label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3" data-permissions-list>
                                <?php foreach ($permissions ?? [] as $permission): ?>
                                    <?php $permissionValue = (string) ($permission['name'] ?? ''); ?>
                                    <label class="flex items-start gap-3 p-3 rounded-xl border border-slate-100 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50 cursor-pointer transition-colors group">
                                        <input type="checkbox" value="<?= htmlspecialchars($permissionValue) ?>" name="permissions[]" class="mt-1 h-5 w-5 rounded-md border-slate-300 text-primary focus:ring-primary/20 transition-all cursor-pointer" data-permission-checkbox id="permission-<?= (int) ($permission['id'] ?? 0) ?>">
                                        <div>
                                            <span class="block text-xs font-bold text-slate-700 dark:text-slate-300 group-hover:text-primary transition-colors"><?= htmlspecialchars((string) ($permission['label'] ?? $permissionValue)) ?></span>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
            </div>
            
            <div class="modal-footer bg-slate-50 dark:bg-slate-950/50 border-t border-slate-100 dark:border-slate-800">
                <button type="button" class="px-6 py-3 text-sm font-bold text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 transition-colors" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="px-8 py-3 bg-primary text-white rounded-xl text-sm font-black shadow-lg shadow-primary/20 hover:shadow-primary/40 hover:-translate-y-0.5 transition-all">Salvar Alterações</button>
            </div>
        </form>
    </div>
</div>

<?php include base_path('app/Views/partials/layout-end.php'); ?>
