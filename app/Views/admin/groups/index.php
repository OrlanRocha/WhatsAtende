<?php include base_path('app/Views/partials/layout-start.php'); ?>
<?php include base_path('app/Views/partials/topbar.php'); ?>

<div class="space-y-10 pb-12" id="groups-admin-page">
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Grupos de Atendimento</h1>
        <p class="text-sm text-slate-500">Gerencie as equipes e as metas de SLA (TMA/TME) de cada setor.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <!-- New Group Card -->
        <button class="bg-white dark:bg-slate-900 border-2 border-dashed border-slate-200 dark:border-slate-800 rounded-xl p-6 shadow-sm hover:shadow-md hover:border-primary/50 group transition-all flex flex-col items-center justify-center text-center outline-none h-full min-h-[220px]" data-modal-target="#modal-add-group">
            <div class="w-16 h-16 rounded-full bg-slate-50 dark:bg-slate-800 flex items-center justify-center text-slate-400 group-hover:bg-primary/10 group-hover:text-primary transition-colors mb-4">
                <i class="bi bi-plus-lg text-2xl"></i>
            </div>
            <h3 class="font-bold text-slate-800 dark:text-white mb-1 group-hover:text-primary transition-colors">Criar Novo Grupo</h3>
            <p class="text-xs text-slate-500">Adicionar novo setor de atendimento</p>
        </button>
        <?php foreach ($groups as $group): ?>
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 shadow-sm hover:shadow-md transition-all group border-b-4 <?= ($group['slug'] ?? '') === 'atendimento-inicial' ? 'border-b-primary' : 'border-b-slate-200 dark:border-b-slate-800' ?>">
                <div class="flex items-start justify-between mb-4">
                    <div class="w-12 h-12 rounded-2xl bg-slate-50 dark:bg-slate-800 flex items-center justify-center text-slate-400 group-hover:bg-primary/10 group-hover:text-primary transition-colors">
                        <i class="bi bi-people-fill text-xl"></i>
                    </div>
                    <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-primary" 
                                data-modal-target="#modal-edit-<?= $group['id'] ?? 0 ?>" title="Editar Grupo">
                            <i class="bi bi-pencil-square"></i>
                        </button>
                        <?php if (($group['slug'] ?? '') !== 'atendimento-inicial'): ?>
                            <form action="<?= route_path('/admin/groups/' . ($group['id'] ?? 0) . '/delete') ?>" method="POST" data-ajax data-confirm="Deseja realmente remover este grupo?">
                                <button type="submit" class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:bg-rose-50 dark:hover:bg-rose-900/20 hover:text-rose-500" title="Excluir">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <h3 class="font-bold text-slate-800 dark:text-white mb-1"><?= htmlspecialchars($group['name'] ?? 'Sem nome') ?></h3>
                <p class="text-xs text-slate-500 mb-6 line-clamp-2 h-10"><?= htmlspecialchars($group['description'] ?? 'Sem descrição definida.') ?></p>

                <div class="space-y-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Meta TMA</span>
                        <span class="text-xs font-bold text-slate-700 dark:text-slate-300"><?= ($group['target_tma'] ?? null) ? $group['target_tma'] . ' min' : 'Não definido' ?></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Meta TME</span>
                        <span class="text-xs font-bold text-slate-700 dark:text-slate-300"><?= ($group['target_tme'] ?? null) ? $group['target_tme'] . ' min' : 'Não definido' ?></span>
                    </div>
                    <button class="w-full mt-2 py-2 rounded-xl border border-dashed border-slate-200 dark:border-slate-700 text-[10px] font-bold text-slate-500 hover:border-primary hover:text-primary transition-all uppercase tracking-widest"
                            data-modal-target="#modal-targets-<?= $group['id'] ?? 0 ?>">
                        Ajustar Metas
                    </button>
                </div>
            </div>

            <div id="modal-edit-<?= $group['id'] ?? 0 ?>" class="modal-vanilla fixed inset-0 z-[100] hidden items-center justify-center p-4">
                <form action="<?= route_path('/admin/groups/' . ($group['id'] ?? 0)) ?>" method="POST" data-ajax data-success-message="Grupo atualizado." data-hide-modal="#modal-edit-<?= $group['id'] ?? 0 ?>"
                      class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-2xl w-full max-w-md overflow-hidden animate-in zoom-in-95 duration-200">
                    <div class="p-6 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                        <h3 class="font-bold text-slate-900 dark:text-white">Editar Grupo</h3>
                        <button type="button" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" data-modal-dismiss><i class="bi bi-x-lg"></i></button>
                    </div>
                    <div class="p-6 space-y-4">
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1">Nome do Setor</label>
                            <input type="text" name="name" class="form-control-premium" value="<?= htmlspecialchars($group['name'] ?? '') ?>" required>
                        </div>
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1">Descrição</label>
                            <textarea name="description" class="form-control-premium min-h-[100px]" placeholder="O que este grupo faz?"><?= htmlspecialchars((string)($group['description'] ?? '')) ?></textarea>
                        </div>
                    </div>
                    <div class="p-6 bg-slate-50 dark:bg-slate-800/20 border-t border-slate-100 dark:border-slate-800 flex gap-2">
                        <button type="button" class="btn-secondary flex-1" data-modal-dismiss>Cancelar</button>
                        <button type="submit" class="btn-primary flex-1">Salvar Alterações</button>
                    </div>
                </form>
            </div>

            <!-- Modal Metas -->
            <div id="modal-targets-<?= $group['id'] ?? 0 ?>" class="modal-vanilla fixed inset-0 z-[100] hidden items-center justify-center p-4">
                <form action="<?= route_path('/admin/groups/' . ($group['id'] ?? 0) . '/targets') ?>" method="POST" data-ajax data-success-message="Metas salvas." data-hide-modal="#modal-targets-<?= $group['id'] ?? 0 ?>"
                      class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-2xl w-full max-w-sm overflow-hidden animate-in zoom-in-95 duration-200">
                    <div class="p-6 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                        <h3 class="font-bold text-slate-900 dark:text-white">Metas de SLA: <?= htmlspecialchars($group['name'] ?? '') ?></h3>
                        <button type="button" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" data-modal-dismiss><i class="bi bi-x-lg"></i></button>
                    </div>
                    <div class="p-6 space-y-6">
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1">TMA (Tempo Médio Atend.)</label>
                            <div class="relative">
                                <input type="number" name="target_tma" class="form-control-premium pr-12" value="<?= $group['target_tma'] ?? '' ?>" placeholder="Ex: 15">
                                <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[10px] font-bold text-slate-400 uppercase tracking-widest">MIN</span>
                            </div>
                            <p class="text-[10px] text-slate-400 ml-1">Tempo máximo ideal para concluir um atendimento.</p>
                        </div>
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1">TME (Tempo Médio Espera)</label>
                            <div class="relative">
                                <input type="number" name="target_tme" class="form-control-premium pr-12" value="<?= $group['target_tme'] ?? '' ?>" placeholder="Ex: 5">
                                <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[10px] font-bold text-slate-400 uppercase tracking-widest">MIN</span>
                            </div>
                            <p class="text-[10px] text-slate-400 ml-1">Tempo máximo ideal para o primeiro atendimento.</p>
                        </div>
                    </div>
                    <div class="p-6 bg-slate-50 dark:bg-slate-800/20 border-t border-slate-100 dark:border-slate-800 flex gap-2">
                        <button type="button" class="btn-secondary flex-1" data-modal-dismiss>Cancelar</button>
                        <button type="submit" class="btn-primary flex-1">Salvar Metas</button>
                    </div>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal Adicionar -->
<div id="modal-add-group" class="modal-vanilla fixed inset-0 z-[100] hidden items-center justify-center p-4">
    <form action="<?= route_path('/admin/groups') ?>" method="POST" data-ajax data-success-message="Grupo criado." data-hide-modal="#modal-add-group"
          class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-2xl w-full max-w-md overflow-hidden animate-in zoom-in-95 duration-200">
        <div class="p-6 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
            <h3 class="font-bold text-slate-900 dark:text-white">Novo Grupo de Atendimento</h3>
            <button type="button" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" data-modal-dismiss><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="p-6 space-y-4">
            <div class="space-y-1">
                <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1">Nome do Setor</label>
                <input type="text" name="name" class="form-control-premium" placeholder="Ex: Comercial, Suporte N1..." required>
            </div>
            <div class="space-y-1">
                <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1">Descrição</label>
                <textarea name="description" class="form-control-premium min-h-[100px]" placeholder="O que este grupo faz?"></textarea>
            </div>
        </div>
        <div class="p-6 bg-slate-50 dark:bg-slate-800/20 border-t border-slate-100 dark:border-slate-800 flex gap-2">
            <button type="button" class="btn-secondary flex-1" data-modal-dismiss>Cancelar</button>
            <button type="submit" class="btn-primary flex-1">Criar Grupo</button>
        </div>
    </form>
</div>

<style>
.form-control-premium {
    width: 100%;
    padding: 12px 16px;
    border-radius: 16px;
    background-color: #f8fafc;
    border: 1px solid #e2e8f0;
    font-size: 14px;
    transition: all 0.2s;
}
.dark .form-control-premium {
    background-color: #1e293b;
    border-color: #334155;
    color: white;
}
.form-control-premium:focus {
    outline: none;
    background-color: white;
    border-color: #0061ff;
    box-shadow: 0 0 0 4px rgba(0, 97, 255, 0.1);
}
.dark .form-control-premium:focus {
    background-color: #0f172a;
}
.btn-secondary {
    display: flex; align-items: center; justify-content: center;
    padding: 10px 20px; border-radius: 16px; background: transparent;
    color: #64748b; font-size: 14px; font-weight: 700; transition: all 0.2s;
    border: 1px solid #e2e8f0;
}
.dark .btn-secondary { border-color: #334155; color: #94a3b8; }
.btn-secondary:hover { background: #f1f5f9; color: #1e293b; }
.dark .btn-secondary:hover { background: #334155; color: white; }
</style>

<?php include base_path('app/Views/partials/layout-end.php'); ?>
