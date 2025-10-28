<?php
/** @var array<int, array<string, mixed>> $tickets */
/** @var string|null $statusFilter */
$breadcrumbs = [
    ['label' => 'Admin', 'href' => '/admin'],
    ['label' => 'Solicitações'],
];
$pageTitle = 'Solicitações · WhatsAtende';
include base_path('app/Views/partials/layout-start.php');
include base_path('app/Views/partials/topbar.php');
?>
<div class="workspace" id="tickets-page">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-3 mb-4">
        <div>
            <h1 class="h3 fw-semibold mb-1">Solicitações</h1>
            <p class="text-muted mb-0">Visualize todos os chamados com filtros instantâneos e ações rápidas.</p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <label for="ticket-status" class="form-label mb-0">Status</label>
            <select id="ticket-status" class="form-select form-select-sm" data-filter>
                <option value="">Todos</option>
                <option value="open" <?= $statusFilter === 'open' ? 'selected' : '' ?>>Aguardando</option>
                <option value="assigned" <?= $statusFilter === 'assigned' ? 'selected' : '' ?>>Em atendimento</option>
                <option value="resolved" <?= $statusFilter === 'resolved' ? 'selected' : '' ?>>Resolvidos</option>
                <option value="closed" <?= $statusFilter === 'closed' ? 'selected' : '' ?>>Encerrados</option>
            </select>
        </div>
    </div>
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0" id="tickets-table" data-table>
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Contato</th>
                        <th>Status</th>
                        <th>Responsável</th>
                        <th>Canal</th>
                        <th>Aberto em</th>
                        <th>Encerrado em</th>
                        <th>Prioridade</th>
                        <th class="text-end">Ações</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($tickets as $ticket): ?>
                        <tr>
                            <td>#<?= htmlspecialchars((string) ($ticket['id'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($ticket['contact_name'] ?? '')) ?></td>
                            <td><span class="badge bg-secondary text-capitalize"><?= htmlspecialchars((string) ($ticket['status'] ?? '')) ?></span></td>
                            <td><?= htmlspecialchars((string) ($ticket['agent_name'] ?? 'Não atribuído')) ?></td>
                            <td><?= htmlspecialchars((string) ($ticket['channel'] ?? 'whatsapp')) ?></td>
                            <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($ticket['opened_at'] ?? 'now'))) ?></td>
                            <td><?= !empty($ticket['closed_at']) ? htmlspecialchars(date('d/m/Y H:i', strtotime($ticket['closed_at']))) : '—' ?></td>
                            <td><span class="badge bg-info-subtle text-info text-capitalize fw-semibold"><?= htmlspecialchars((string) ($ticket['priority'] ?? 'normal')) ?></span></td>
                            <td class="text-end">
                                <a href="<?= htmlspecialchars(route_path('/tickets/' . rawurlencode((string) ($ticket['id'] ?? ''))), ENT_QUOTES) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-chat-dots"></i> Abrir chat</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script type="module">
import { initTicketList } from <?= json_encode(route_path('/js/modules/tickets.js')) ?>;
initTicketList('#tickets-page', <?= json_encode(route_path('/admin/tickets')) ?>);
</script>
<?php include base_path('app/Views/partials/layout-end.php'); ?>
