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
<div class="workspace feedback-admin">
    <section class="workspace-header">
        <div>
            <h1 class="workspace-title">Avaliações de atendimento</h1>
            <p class="workspace-subtitle">Acompanhe notas e comentários enviados pelos clientes após o encerramento dos tickets.</p>
        </div>
    </section>
    <form class="feedback-filters" method="GET">
        <div class="filter-group">
            <label for="feedback-from">De</label>
            <input type="date" id="feedback-from" name="from" value="<?= htmlspecialchars($fromValue) ?>">
        </div>
        <div class="filter-group">
            <label for="feedback-to">Até</label>
            <input type="date" id="feedback-to" name="to" value="<?= htmlspecialchars($toValue) ?>">
        </div>
        <div class="filter-group">
            <label for="feedback-group">Grupos</label>
            <select id="feedback-group" name="group[]" multiple class="form-select">
                <?php foreach ($groups as $group): ?>
                    <?php $groupId = (int) ($group['id'] ?? 0); ?>
                    <option value="<?= $groupId ?>" <?= in_array($groupId, $selectedGroups, true) ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) ($group['name'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="text-muted">Segure Ctrl/Cmd para selecionar múltiplos grupos.</small>
        </div>
        <div class="filter-group">
            <label for="feedback-rating">Nota</label>
            <select id="feedback-rating" name="rating" class="form-select">
                <option value="">Todas</option>
                <?php for ($i = 5; $i >= 1; $i--): ?>
                    <option value="<?= $i ?>" <?= (int) $ratingFilter === $i ? 'selected' : '' ?>><?= $i ?> estrela<?= $i > 1 ? 's' : '' ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary">Aplicar filtros</button>
            <a href="<?= htmlspecialchars(route_path('/admin/avaliacoes')) ?>" class="btn btn-outline-secondary">Limpar</a>
        </div>
    </form>
    <?php if ($errorMessage): ?>
        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($errorMessage) ?></div>
    <?php endif; ?>
    <section class="feedback-summary">
        <div class="feedback-summary__card">
            <span class="feedback-summary__label">Avaliações recebidas</span>
            <span class="feedback-summary__value"><?= number_format($total) ?></span>
        </div>
        <div class="feedback-summary__card">
            <span class="feedback-summary__label">Média geral</span>
            <span class="feedback-summary__value">
                <?= $average !== null ? number_format((float) $average, 2) : '—' ?>
            </span>
        </div>
    </section>
    <?php if ($entries === []): ?>
        <div class="empty-state">
            <i class="bi bi-emoji-neutral" aria-hidden="true"></i>
            <h2>Nenhuma avaliação encontrada</h2>
            <p>Ajuste os filtros ou aguarde novas avaliações dos clientes.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive feedback-table-wrapper">
            <table class="table feedback-table">
                <thead>
                    <tr>
                        <th scope="col">Ticket</th>
                        <th scope="col">Contato</th>
                        <th scope="col">Nota</th>
                        <th scope="col">Comentário</th>
                        <th scope="col">Data</th>
                        <th scope="col">Grupo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $entry): ?>
                        <tr>
                            <td>
                                <span class="table-label">#<?= htmlspecialchars((string) ($entry['ticket_id'] ?? '—')) ?></span>
                                <?php if (!empty($entry['subject'])): ?>
                                    <div class="text-muted small"><?= htmlspecialchars((string) $entry['subject']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= htmlspecialchars((string) ($entry['contact_name'] ?? '—')) ?><br>
                                <small class="text-muted"><?= htmlspecialchars((string) ($entry['contact_external_id'] ?? '')) ?></small>
                            </td>
                            <td><?= $renderRating(isset($entry['rating']) ? (int) $entry['rating'] : null) ?></td>
                            <td>
                                <?php if (!empty($entry['note'])): ?>
                                    <div class="feedback-note-inline"><?= nl2br(htmlspecialchars((string) $entry['note'])) ?></div>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($entry['submitted_at'])): ?>
                                    <?= htmlspecialchars((new DateTimeImmutable((string) $entry['submitted_at']))->format('d/m/Y H:i')) ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars((string) ($entry['group_name'] ?? '—')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php include base_path('app/Views/partials/layout-end.php'); ?>
