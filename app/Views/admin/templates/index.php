<?php
/** @var array<int, array<string, mixed>> $templates */
/** @var string|null $status */
/** @var string|null $error */
$breadcrumbs = [
    ['label' => 'Admin', 'href' => '/admin'],
    ['label' => 'Templates'],
];
$pageTitle = 'Templates · WhatsAtende';
include base_path('app/Views/partials/layout-start.php');
include base_path('app/Views/partials/topbar.php');
?>
<div class="space-y-8 pb-12">
    <!-- Header Section -->
    <header class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Scripts e Templates</h1>
            <p class="text-slate-500 dark:text-slate-400 mt-1">Prepare respostas rápidas para padronizar o atendimento.</p>
        </div>
        <button class="inline-flex items-center gap-2 px-6 py-3 bg-primary hover:bg-primary-dark text-white rounded-2xl font-bold shadow-lg shadow-primary/25 transition-all active:scale-[0.98]" 
                data-modal-target="#templateModal" data-mode="create">
            <i class="bi bi-plus-circle"></i> Novo template
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

    <!-- Templates Table Card -->
    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden transition-all duration-300">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse" id="templates-table" data-table>
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-950/50">
                        <th class="px-8 py-5 text-[11px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 dark:border-slate-800">#</th>
                        <th class="px-6 py-5 text-[11px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 dark:border-slate-800">Título</th>
                        <th class="px-6 py-5 text-[11px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 dark:border-slate-800">Categoria</th>
                        <th class="px-6 py-5 text-[11px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 dark:border-slate-800 whitespace-nowrap">Atualizado em</th>
                        <th class="px-8 py-5 text-[11px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 dark:border-slate-800 text-end">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <?php foreach ($templates as $template): ?>
                        <tr class="group hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors" 
                            data-template-row data-template='<?= json_encode($template, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>'>
                            <td class="px-8 py-5 text-sm font-bold text-slate-400">#<?= (int) ($template['id'] ?? 0) ?></td>
                            <td class="px-6 py-5">
                                <span class="text-sm font-bold text-slate-900 dark:text-white"><?= htmlspecialchars((string) ($template['title'] ?? '')) ?></span>
                            </td>
                            <td class="px-6 py-5">
                                <span class="inline-flex px-3 py-1 rounded-lg bg-primary/10 text-primary text-[10px] font-bold uppercase tracking-widest">
                                    <?= htmlspecialchars((string) ($template['category'] ?? 'geral')) ?>
                                </span>
                            </td>
                            <td class="px-6 py-5 text-sm text-slate-500 dark:text-slate-400">
                                <?= htmlspecialchars(isset($template['updated_at']) ? date('d/m H:i', strtotime((string) $template['updated_at'])) : '—') ?>
                            </td>
                            <td class="px-8 py-5 text-end">
                                <div class="inline-flex items-center gap-1 p-1 bg-slate-100 dark:bg-slate-800 rounded-xl">
                                    <button class="p-2 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-white dark:hover:bg-slate-700 hover:text-primary transition-all" 
                                            data-modal-target="#templateModal" data-mode="edit">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <form method="POST" action="/admin/templates/<?= urlencode((string) ($template['id'] ?? '')) ?>/delete" class="inline" data-ajax data-confirm="Excluir este template?" data-success-event="templates:refresh">
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

<!-- Refactored Template Modal -->
<div id="templateModal" class="fixed inset-0 z-[100] hidden overflow-y-auto" role="dialog" aria-modal="true">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm transition-opacity" data-modal-dismiss></div>
        <div class="relative bg-white dark:bg-slate-900 w-full max-w-3xl rounded-xl shadow-2xl border border-slate-200 dark:border-slate-800 overflow-hidden">
            <div class="flex items-center justify-between px-8 py-6 border-b border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/50">
                <div>
                    <h5 class="text-xl font-bold text-slate-800 dark:text-white" id="templateModalLabel">Novo template</h5>
                    <p class="text-xs text-slate-500 font-medium mt-1">Configure respostas rápidas.</p>
                </div>
                <button type="button" class="w-10 h-10 flex items-center justify-center rounded-xl hover:bg-slate-200 dark:hover:bg-slate-800 transition-colors" data-modal-dismiss>
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            
            <form method="POST" action="/admin/templates" data-ajax data-hide-modal="#templateModal" data-success-event="templates:refresh" data-reset="true" id="templateForm">
                <div class="p-8 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 ml-1" for="template_title">Título do Script</label>
                            <input type="text" 
                                class="w-full px-5 py-3 rounded-2xl bg-slate-100 dark:bg-slate-800/50 border-0 text-slate-900 dark:text-white focus:ring-2 focus:ring-primary/20 outline-none transition-all placeholder:text-slate-400" 
                                id="template_title" name="title" required placeholder="Ex: Boas-vindas">
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 ml-1" for="template_category">Categoria</label>
                            <input type="text" 
                                class="w-full px-5 py-3 rounded-2xl bg-slate-100 dark:bg-slate-800/50 border-0 text-slate-900 dark:text-white focus:ring-2 focus:ring-primary/20 outline-none transition-all placeholder:text-slate-400" 
                                id="template_category" name="category" placeholder="Suporte, Vendas...">
                        </div>
                    </div>
                    <div class="space-y-2">
                        <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 ml-1" for="template_body">Conteúdo do Template</label>
                        <textarea 
                            class="w-full px-5 py-4 rounded-xl bg-slate-100 dark:bg-slate-800/50 border-0 text-slate-900 dark:text-white focus:ring-2 focus:ring-primary/20 outline-none transition-all placeholder:text-slate-400 min-h-[160px]" 
                            id="template_body" name="body" required placeholder="Escreva aqui sua resposta padrão..."></textarea>
                        <p class="text-[10px] text-slate-400 font-medium ml-2 uppercase tracking-widest">Suporta emojis, links e tags dinâmicas.</p>
                    </div>
                </div>
                
                <div class="px-8 py-6 bg-slate-50 dark:bg-slate-950/50 flex justify-end gap-3">
                    <button type="button" class="px-6 py-2.5 rounded-xl bg-slate-200 dark:bg-slate-800 text-sm font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-300 transition-all" data-modal-dismiss>Cancelar</button>
                    <button type="submit" class="px-8 py-2.5 bg-primary hover:bg-primary-dark text-white rounded-xl font-bold text-sm shadow-lg shadow-primary/20 transition-all active:scale-[0.98]">
                        <i class="bi bi-floppy mr-2"></i> Salvar Template
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script type="module">
import { initTemplateModal, refreshTemplateTable } from <?= json_encode(route_path('/js/modules/templates.js')) ?>;
initTemplateModal('#templateModal', '#templateForm');
window.addEventListener('templates:refresh', () => {
    refreshTemplateTable('[data-table]', '/admin/templates');
});
</script>
<?php include base_path('app/Views/partials/layout-end.php'); ?>
