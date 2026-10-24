<?php
/** @var array<int, array<string, mixed>> $entries */
/** @var array<int, array<string, mixed>> $groups */
/** @var array<string, mixed> $filters */
/** @var array<string, mixed> $summary */
/** @var string|null $errorMessage */

$entries = $entries ?? [];
$groups = $groups ?? [];
$filters = $filters ?? [];
$summary = $summary ?? [];
$errorMessage = $errorMessage ?? null;

$fromValue = $filters['from'] ?? (new DateTimeImmutable('-30 days'))->format('Y-m-d');
$toValue = $filters['to'] ?? (new DateTimeImmutable())->format('Y-m-d');
$selectedGroups = array_map('intval', $filters['group'] ?? []);
$ratingFilter = $filters['rating'] ?? null;
$average = $summary['average'] ?? null;
$total = (int) ($summary['total'] ?? count($entries));

$breadcrumbs = [
    ['label' => 'Admin', 'href' => '/admin'],
    ['label' => 'Avaliações'],
];
$pageTitle = 'Avaliações de atendimento · WhatsAtende';

$renderRating = static function (?int $rating): string {
    if ($rating === null) {
        return '<span class="text-muted">—</span>';
    }
    $output = '<span class="feedback-stars" aria-label="' . $rating . ' estrelas">';
    for ($i = 1; $i <= 5; $i++) {
        $icon = $i <= $rating ? 'bi-star-fill' : 'bi-star';
        $output .= '<i class="bi ' . $icon . '" aria-hidden="true"></i>';
    }
    $output .= '</span>';

    return $output;
};

include base_path('app/Views/partials/layout-start.php');
include base_path('app/Views/partials/topbar.php');
?>
<div class="space-y-10 pb-12">
    <!-- Header Section -->
    <header class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Avaliações de atendimento</h1>
            <p class="text-slate-500 dark:text-slate-400 mt-1">Acompanhe notas e comentários enviados pelos clientes após o encerramento dos tickets.</p>
        </div>
    </header>

    <?php if ($errorMessage): ?>
        <div class="px-6 py-4 rounded-xl bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-400 font-medium flex items-center gap-3">
            <i class="bi bi-exclamation-octagon text-lg"></i>
            <?= htmlspecialchars($errorMessage) ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <!-- Sidebar Filters & Summary -->
        <div class="lg:col-span-1 space-y-6">
            <!-- Summary Stats -->
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-indigo-50 dark:bg-indigo-950/20 rounded-2xl p-6 border border-indigo-100 dark:border-indigo-900/30">
                    <span class="text-[10px] font-bold text-indigo-500 uppercase tracking-widest block mb-2">Recebidas</span>
                    <h3 class="text-3xl font-bold text-indigo-700 dark:text-indigo-400 font-['Outfit']"><?= number_format($total) ?></h3>
                </div>
                <div class="bg-amber-50 dark:bg-amber-950/20 rounded-2xl p-6 border border-amber-100 dark:border-amber-900/30">
                    <span class="text-[10px] font-bold text-amber-500 uppercase tracking-widest block mb-2">Média Geral</span>
                    <h3 class="text-3xl font-bold text-amber-700 dark:text-amber-400 font-['Outfit']">
                        <?= $average !== null ? number_format((float) $average, 1) : '—' ?>
                    </h3>
                </div>
            </div>

            <!-- Filters Card -->
            <form class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm p-6" method="GET">
                <h6 class="text-sm font-bold text-slate-800 dark:text-white mb-5 flex items-center gap-2">
                    <i class="bi bi-funnel text-primary"></i> Filtros
                </h6>
                <div class="space-y-4">
                    <div>
                        <label for="feedback-from" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">De</label>
                        <input type="date" id="feedback-from" name="from" value="<?= htmlspecialchars($fromValue) ?>" 
                            class="w-full px-4 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all">
                    </div>
                    <div>
                        <label for="feedback-to" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Até</label>
                        <input type="date" id="feedback-to" name="to" value="<?= htmlspecialchars($toValue) ?>" 
                            class="w-full px-4 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all">
                    </div>
                    <div>
                        <label for="feedback-group" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Grupos</label>
                        <select id="feedback-group" name="group[]" multiple 
                            class="w-full px-4 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all h-32">
                            <?php foreach ($groups as $group): ?>
                                <?php $groupId = (int) ($group['id'] ?? 0); ?>
                                <option value="<?= $groupId ?>" <?= in_array($groupId, $selectedGroups, true) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string) ($group['name'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-[10px] text-slate-400 mt-2 font-medium">Segure Ctrl/Cmd para múltipla seleção.</p>
                    </div>
                    <div>
                        <label for="feedback-rating" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Nota</label>
                        <select id="feedback-rating" name="rating" 
                            class="w-full px-4 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all">
                            <option value="">Todas</option>
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <option value="<?= $i ?>" <?= (int) $ratingFilter === $i ? 'selected' : '' ?>><?= $i ?> estrela<?= $i > 1 ? 's' : '' ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="pt-4 flex flex-col gap-2">
                        <button type="submit" class="w-full py-2.5 bg-primary hover:bg-primary-hover text-white rounded-xl text-sm font-bold transition-colors shadow-lg shadow-primary/20">
                            Aplicar filtros
                        </button>
                        <a href="<?= htmlspecialchars(route_path('/admin/avaliacoes')) ?>" class="w-full py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-xl text-sm font-bold transition-colors text-center">
                            Limpar
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Main Content Area -->
        <div class="lg:col-span-3">
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
                <div class="px-8 py-5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-950/50">
                    <h5 class="text-sm font-bold text-slate-700 dark:text-slate-200">Resultados da pesquisa</h5>
                </div>
                
                <?php if ($entries === []): ?>
                    <div class="flex flex-col items-center justify-center p-16 text-center">
                        <div class="w-20 h-20 bg-slate-50 dark:bg-slate-800 rounded-full flex items-center justify-center mb-6">
                            <i class="bi bi-emoji-neutral text-4xl text-slate-400"></i>
                        </div>
                        <h2 class="text-xl font-bold text-slate-800 dark:text-white mb-2">Nenhuma avaliação encontrada</h2>
                        <p class="text-slate-500 max-w-sm">Ajuste os filtros ou aguarde novas avaliações dos clientes.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse" data-table>
                            <thead>
                                <tr class="bg-slate-50/50 dark:bg-slate-900/50">
                                    <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 dark:border-slate-800">Ticket</th>
                                    <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 dark:border-slate-800">Contato</th>
                                    <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 dark:border-slate-800 w-32">Nota</th>
                                    <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 dark:border-slate-800 min-w-[250px]">Comentário</th>
                                    <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 dark:border-slate-800">Grupo</th>
                                    <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 dark:border-slate-800">Data</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <?php foreach ($entries as $entry): ?>
                                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors group">
                                        <td class="px-6 py-4">
                                            <a href="<?= route_path('/tickets/' . ($entry['ticket_id'] ?? '')) ?>" class="text-sm font-bold text-primary hover:underline">
                                                #<?= htmlspecialchars((string) ($entry['ticket_id'] ?? '—')) ?>
                                            </a>
                                            <?php if (!empty($entry['subject'])): ?>
                                                <div class="text-xs text-slate-500 mt-1 truncate max-w-[150px]"><?= htmlspecialchars((string) $entry['subject']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-bold text-slate-800 dark:text-slate-200"><?= htmlspecialchars((string) ($entry['contact_name'] ?? '—')) ?></div>
                                            <div class="text-[11px] text-slate-500 mt-0.5"><?= htmlspecialchars((string) ($entry['contact_external_id'] ?? '')) ?></div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex text-amber-400 text-sm">
                                                <?php
                                                $rating = isset($entry['rating']) ? (int) $entry['rating'] : 0;
                                                for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="bi <?= $i <= $rating ? 'bi-star-fill' : 'bi-star text-slate-200 dark:text-slate-700' ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php if (!empty($entry['note'])): ?>
                                                <div class="text-sm text-slate-600 dark:text-slate-400 bg-slate-50 dark:bg-slate-950/50 p-3 rounded-xl border border-slate-100 dark:border-slate-800 leading-relaxed italic">
                                                    "<?= nl2br(htmlspecialchars((string) $entry['note'])) ?>"
                                                </div>
                                            <?php else: ?>
                                                <span class="text-slate-300 dark:text-slate-600 italic text-sm">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-[10px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-widest">
                                                <?= htmlspecialchars((string) ($entry['group_name'] ?? '—')) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-slate-500">
                                            <?php if (!empty($entry['submitted_at'])): ?>
                                                <?= htmlspecialchars((new DateTimeImmutable((string) $entry['submitted_at']))->format('d/m/Y H:i')) ?>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php include base_path('app/Views/partials/layout-end.php'); ?>
