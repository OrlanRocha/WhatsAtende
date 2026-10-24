<?php include base_path('app/Views/partials/layout-start.php'); ?>
<?php include base_path('app/Views/partials/topbar.php'); ?>

<div class="space-y-10 pb-12" id="integrations-page">
    <div class="flex flex-col lg:flex-row gap-6">
        
        <!-- List Panel -->
        <div class="flex-1 space-y-4">
            <div class="flex items-center justify-between mb-2">
                <h2 class="text-sm font-bold text-slate-400 uppercase tracking-widest">Conexões Ativas</h2>
                <button class="btn btn-primary px-4 py-1.5 rounded-xl text-xs font-bold" data-modal-target="#modal-add-instance">
                    <i class="bi bi-plus-lg mr-1"></i> Nova
                </button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" id="instances-list">
                <?php foreach ($instances as $instance): ?>
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 rounded-2xl flex items-center justify-between group transition-all hover:border-primary/50 <?= $instance['is_default'] ? 'ring-1 ring-primary ring-offset-2 dark:ring-offset-slate-950' : '' ?>">
                        <div class="flex items-center gap-3">
                            <div class="relative">
                                <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
                                    <i class="bi bi-lightning-charge text-lg"></i>
                                </div>
                                <div class="absolute -bottom-1 -right-1 w-4 h-4 rounded-full border-2 border-white dark:border-slate-900 status-indicator" 
                                     data-instance-id="<?= $instance['id'] ?>" title="Verificando status...">
                                    <span class="absolute inset-0 rounded-full bg-slate-400 animate-pulse"></span>
                                </div>
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-800 dark:text-slate-100 text-sm leading-tight"><?= htmlspecialchars($instance['name']) ?></h3>
                                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider"><?= htmlspecialchars($instance['instance_name']) ?></p>
                            </div>
                        </div>
                        <div class="flex items-center gap-1">
                            <button class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-primary transition-colors action-connect" 
                                    data-id="<?= $instance['id'] ?>" title="Conectar (QR Code)">
                                <i class="bi bi-qr-code-scan text-sm"></i>
                            </button>
                            <button class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-emerald-500 transition-colors action-sync" 
                                    data-id="<?= $instance['id'] ?>" title="Sincronizar Webhooks">
                                <i class="bi bi-cloud-check text-sm"></i>
                            </button>
                            <button class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-amber-500 transition-colors action-restart" 
                                    data-id="<?= $instance['id'] ?>" title="Reiniciar Instância">
                                <i class="bi bi-arrow-clockwise text-sm"></i>
                            </button>
                            <button class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-rose-500 transition-colors action-logout" 
                                    data-id="<?= $instance['id'] ?>" title="Desconectar WhatsApp">
                                <i class="bi bi-box-arrow-right text-sm"></i>
                            </button>
                            <div class="w-px h-4 bg-slate-200 dark:bg-slate-800 mx-1"></div>
                            <button class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-primary transition-colors" 
                                    data-modal-target="#modal-edit-<?= $instance['id'] ?>" title="Editar Configurações">
                                <i class="bi bi-gear text-sm"></i>
                            </button>
                            <?php if (!$instance['is_default']): ?>
                                <form action="<?= route_path('/admin/integrations/' . $instance['id'] . '/delete') ?>" method="POST" data-ajax data-confirm="Remover esta instância?" data-success-message="Removida.">
                                    <button type="submit" class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:bg-rose-500 hover:text-white transition-colors">
                                        <i class="bi bi-trash text-sm"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Edit Modal (Vanilla implementation) -->
                    <div id="modal-edit-<?= $instance['id'] ?>" class="modal-vanilla fixed inset-0 z-[100] hidden items-center justify-center p-4">
                        <form action="<?= route_path('/admin/integrations/' . $instance['id']) ?>" 
                              method="POST" data-ajax data-success-message="Salvo." data-hide-modal="#modal-edit-<?= $instance['id'] ?>"
                              class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-2xl w-full max-w-sm overflow-hidden animate-in zoom-in-95 duration-200">
                            <div class="p-6 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                                <h3 class="font-bold text-slate-900 dark:text-white">Ajustar Instância</h3>
                                <button type="button" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" data-modal-dismiss><i class="bi bi-x-lg"></i></button>
                            </div>
                            <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto custom-scrollbar">
                                <div class="space-y-1">
                                    <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1">Nome amigável</label>
                                    <input type="text" name="name" class="form-control-compact" value="<?= htmlspecialchars($instance['name']) ?>" required>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="space-y-1">
                                        <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1">URL API</label>
                                        <input type="url" name="api_url" class="form-control-compact font-mono text-xs" value="<?= htmlspecialchars($instance['api_url']) ?>" required>
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1">Instância</label>
                                        <input type="text" name="instance_name" class="form-control-compact font-mono text-xs" value="<?= htmlspecialchars($instance['instance_name']) ?>" required>
                                    </div>
                                </div>
                                <div class="space-y-1">
                                    <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1">API Key</label>
                                    <input type="text" name="api_key" class="form-control-compact font-mono text-xs" value="<?= htmlspecialchars($instance['api_key']) ?>" required>
                                </div>
                                <div class="space-y-1">
                                    <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1">Bearer Token (Opcional)</label>
                                    <input type="text" name="token" class="form-control-compact font-mono text-xs" value="<?= htmlspecialchars((string)$instance['token']) ?>">
                                </div>
                                <div class="space-y-1">
                                    <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1">Template de Boas-vindas</label>
                                    <textarea name="default_template" class="form-control-compact text-[11px]" rows="2"><?= htmlspecialchars((string)$instance['default_template']) ?></textarea>
                                </div>
                                <label class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 cursor-pointer transition-colors hover:bg-slate-100 dark:hover:bg-slate-800">
                                    <span class="text-xs font-bold text-slate-600 dark:text-slate-300">Conexão Ativa</span>
                                    <input type="checkbox" name="is_active" class="form-checkbox text-primary rounded" <?= $instance['is_active'] ? 'checked' : '' ?>>
                                </label>
                            </div>
                            <div class="p-6 bg-slate-50 dark:bg-slate-800/20 border-t border-slate-100 dark:border-slate-800 flex gap-2">
                                <button type="button" class="btn-compact-secondary flex-1" data-modal-dismiss>Cancelar</button>
                                <button type="submit" class="btn-compact-primary flex-1">Salvar</button>
                            </div>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Right Panel - Info (Visible on Desktop) -->
        <div class="hidden lg:block w-72 space-y-4">
            <h2 class="text-sm font-bold text-slate-400 uppercase tracking-widest mb-2">Orientações</h2>
            <div class="bg-primary/5 border border-primary/20 p-5 rounded-xl space-y-4">
                <div class="flex items-start gap-3">
                    <div class="w-6 h-6 rounded-full bg-primary text-white flex items-center justify-center flex-shrink-0 text-xs font-bold">1</div>
                    <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">Defina uma instância como <b>Padrão</b> para ser usada em operações automáticas.</p>
                </div>
                <div class="flex items-start gap-3">
                    <div class="w-6 h-6 rounded-full bg-primary text-white flex items-center justify-center flex-shrink-0 text-xs font-bold">2</div>
                    <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">O <b>Bearer Token</b> é opcional e usado apenas em versões legadas da API.</p>
                </div>
                <div class="flex items-start gap-3">
                    <div class="w-6 h-6 rounded-full bg-primary text-white flex items-center justify-center flex-shrink-0 text-xs font-bold">3</div>
                    <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">Inative uma instância para pausar o processamento de suas mensagens.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Modal (Vanilla) -->
<div id="modal-add-instance" class="modal-vanilla fixed inset-0 z-[100] hidden items-center justify-center p-4">
    <form action="<?= route_path('/admin/integrations') ?>" 
          method="POST" data-ajax data-success-message="Conectado." data-hide-modal="#modal-add-instance"
          class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-2xl w-full max-w-sm overflow-hidden animate-in zoom-in-95 duration-200">
        <div class="p-6 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
            <h3 class="font-bold text-slate-900 dark:text-white">Conectar Nova Instância</h3>
            <button type="button" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" data-modal-dismiss><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto custom-scrollbar">
            <div class="space-y-1">
                <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1">Nome amigável</label>
                <input type="text" name="name" class="form-control-compact" required placeholder="Ex: Matriz">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1">
                    <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1">URL API</label>
                    <input type="url" name="api_url" class="form-control-compact font-mono text-xs" required placeholder="https://...">
                </div>
                <div class="space-y-1">
                    <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1">Instância</label>
                    <input type="text" name="instance_name" class="form-control-compact font-mono text-xs" required placeholder="W001">
                </div>
            </div>
            <div class="space-y-1">
                <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1">API Key</label>
                <input type="text" name="api_key" class="form-control-compact font-mono text-xs" required>
            </div>
            <label class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 cursor-pointer transition-colors hover:bg-slate-100 dark:hover:bg-slate-800">
                <span class="text-xs font-bold text-slate-600 dark:text-slate-300">Definir como Padrão</span>
                <input type="checkbox" name="is_default" class="form-checkbox text-primary rounded">
            </label>
        </div>
        <div class="p-6 bg-slate-50 dark:bg-slate-800/20 border-t border-slate-100 dark:border-slate-800 flex gap-2">
            <button type="button" class="btn-compact-secondary flex-1" data-modal-dismiss>Cancelar</button>
            <button type="submit" class="btn-compact-primary flex-1">Conectar</button>
        </div>
    </form>
</div>

<!-- QR Code Modal -->
<div id="modal-qrcode" class="modal-vanilla fixed inset-0 z-[110] hidden items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-2xl w-full max-w-sm overflow-hidden animate-in zoom-in-95 duration-200">
        <div class="p-6 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
            <h3 class="font-bold text-slate-900 dark:text-white">Conectar WhatsApp</h3>
            <button type="button" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" data-modal-dismiss><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="p-8 flex flex-col items-center justify-center space-y-6">
            <div id="qrcode-container" class="w-64 h-64 bg-slate-50 dark:bg-slate-800 rounded-2xl flex items-center justify-center border border-slate-100 dark:border-slate-700 overflow-hidden text-center p-4">
                <div class="flex flex-col items-center text-slate-400 text-xs font-bold gap-2">
                    <div class="w-8 h-8 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
                    Gerando QR Code...
                </div>
            </div>
            <p class="text-xs text-center text-slate-500 leading-relaxed px-4">
                Abra o WhatsApp no seu celular, vá em <b>Aparelhos Conectados</b> e escaneie o código acima.
            </p>
        </div>
        <div class="p-6 bg-slate-50 dark:bg-slate-800/20 border-t border-slate-100 dark:border-slate-800">
            <button type="button" class="btn-compact-secondary w-full" data-modal-dismiss>Fechar</button>
        </div>
    </div>
</div>

<script>
    if (typeof initIntegrationManager === 'function') {
        initIntegrationManager();
    }
</script>

<style>
.workspace { padding-top: 1rem; }
.form-control-compact {
    width: 100%;
    height: 36px;
    padding: 0 12px;
    border-radius: 12px;
    background-color: rgb(248 250 252);
    border: 1px solid rgb(226 232 240);
    font-size: 13px;
    transition: all 0.2s;
}
.dark .form-control-compact {
    background-color: rgba(30, 41, 59, 0.5);
    border-color: rgb(30 41 59);
}
.form-control-compact:focus { outline: none; border-color: #0061ff; box-shadow: 0 0 0 3px rgba(0, 97, 255, 0.1); }
textarea.form-control-compact { height: auto; padding: 10px 12px; }

.btn-compact-primary {
    display: inline-flex; align-items: center; justify-content: center;
    height: 40px; border-radius: 14px; background: #0061ff; color: #fff;
    font-size: 13px; font-weight: 700; transition: all 0.2s;
}
.btn-compact-primary:hover { background: #0056e0; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0, 97, 255, 0.2); }
.btn-compact-primary:active { transform: scale(0.98); }

.btn-compact-secondary {
    display: inline-flex; align-items: center; justify-content: center;
    height: 40px; border-radius: 14px; background: transparent; color: #64748b;
    font-size: 13px; font-weight: 700; transition: all 0.2s;
}
.btn-compact-secondary:hover { background: rgba(100, 116, 139, 0.05); color: #475569; }

.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
.dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #1e293b; }
</style>

<?php include base_path('app/Views/partials/layout-end.php'); ?>
