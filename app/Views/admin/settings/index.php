<?php include base_path('app/Views/partials/layout-start.php'); ?>
<?php include base_path('app/Views/partials/topbar.php'); ?>

<div class="space-y-10 pb-12" id="settings-admin-page">
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Regras de Negócio</h1>
        <p class="text-sm text-slate-500">Configure o comportamento automático do sistema e metas globais.</p>
    </div>

    <div class="max-w-4xl">
        <form action="<?= route_path('/admin/settings') ?>" method="POST" data-ajax data-success-message="Configurações salvas!"
              class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden shadow-sm">
            
            <div class="p-8 border-b border-slate-100 dark:border-slate-800">
                <div class="flex items-start gap-6">
                    <div class="w-12 h-12 rounded-2xl bg-primary/10 text-primary flex items-center justify-center flex-shrink-0">
                        <i class="bi bi-clock-history text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-bold text-slate-800 dark:text-white mb-1">SLA Padrão (TMA)</h3>
                        <p class="text-xs text-slate-500 mb-6">Define o tempo limite inicial para novos chamados caírem na fila.</p>
                        
                        <div class="max-w-xs space-y-1">
                            <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1">TMA padrão (minutos)</label>
                            <div class="relative">
                                <input type="number" name="default_tma_minutes" class="form-control-premium pr-12" value="<?= htmlspecialchars($rules['default_tma_minutes'] ?? '15') ?>" placeholder="15">
                                <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[10px] font-bold text-slate-400 uppercase tracking-widest">MIN</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-8 border-b border-slate-100 dark:border-slate-800">
                <div class="flex items-start gap-6">
                    <div class="w-12 h-12 rounded-2xl bg-amber-500/10 text-amber-500 flex items-center justify-center flex-shrink-0">
                        <i class="bi bi-diagram-3-fill text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-bold text-slate-800 dark:text-white mb-1">Direcionamento Inicial</h3>
                        <p class="text-xs text-slate-500 mb-6">Escolha para qual grupo os novos chamados (sem histórico) serão enviados.</p>
                        
                        <div class="max-w-md space-y-1">
                            <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1">Grupo Padrão</label>
                            <div class="relative">
                                <select name="default_group_id" class="form-control-premium appearance-none pr-10">
                                    <option value="">Nenhum (fica na fila geral)</option>
                                    <?php foreach ($groups as $group): ?>
                                        <option value="<?= $group['id'] ?>" <?= (isset($rules['default_group_id']) && (int)$rules['default_group_id'] === $group['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($group['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400">
                                    <i class="bi bi-chevron-down text-xs"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-8">
                <div class="flex items-start gap-6">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center flex-shrink-0">
                        <i class="bi bi-person-check-fill text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center justify-between mb-1">
                            <h3 class="font-bold text-slate-800 dark:text-white">Auto-atribuição ao abrir</h3>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="auto_assign_on_open" class="sr-only peer" <?= ($rules['auto_assign_on_open'] ?? '1') === '1' ? 'checked' : '' ?>>
                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                            </label>
                        </div>
                        <p class="text-xs text-slate-500">Quando um técnico clicar em um chat da fila pela primeira vez, ele será atribuído automaticamente a ele.</p>
                    </div>
                </div>
            </div>

            <div class="p-8 border-t border-slate-100 dark:border-slate-800">
                <div class="flex items-start gap-6">
                    <div class="w-12 h-12 rounded-2xl bg-indigo-500/10 text-indigo-500 flex items-center justify-center flex-shrink-0">
                        <i class="bi bi-shield-lock-fill text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-bold text-slate-800 dark:text-white mb-1">Token de Segurança (Webhook)</h3>
                        <p class="text-xs text-slate-500 mb-6">Token usado para validar as chamadas recebidas da Evolution API. Deixe em branco se não quiser validação (não recomendado).</p>
                        
                        <div class="max-w-md space-y-1">
                            <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1">Webhook Token</label>
                            <div class="relative flex items-center gap-2">
                                <input type="text" name="webhook_token" id="webhook_token_input" class="form-control-premium" value="<?= htmlspecialchars($rules['webhook_token'] ?? '') ?>" placeholder="Ex: meu-token-secreto">
                                <button type="button" onclick="generateToken()" class="px-4 py-3 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-xl font-medium transition-colors text-sm whitespace-nowrap">
                                    <i class="bi bi-arrow-repeat mr-1"></i> Gerar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-8 bg-slate-50 dark:bg-slate-800/20 border-t border-slate-100 dark:border-slate-800 flex justify-end">
                <button type="submit" class="w-full sm:w-auto px-10 py-4 bg-primary text-white rounded-2xl font-bold transition-all hover:scale-[1.02] hover:shadow-xl active:scale-[0.98] shadow-lg shadow-primary/25">
                    Salvar Configurações
                </button>
            </div>
        </form>
    </div>
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
    outline: none;
}
.dark .form-control-premium {
    background-color: #0f172a;
    border-color: #1e293b;
    color: white;
}
.form-control-premium:focus {
    background-color: white;
    border-color: #0061ff;
    box-shadow: 0 0 0 4px rgba(0, 97, 255, 0.1);
}
.dark .form-control-premium:focus {
    background-color: #1e293b;
}
</style>

<script>
function generateToken() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    let token = '';
    for (let i = 0; i < 32; i++) {
        token += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('webhook_token_input').value = token;
}
</script>

<?php include base_path('app/Views/partials/layout-end.php'); ?>
