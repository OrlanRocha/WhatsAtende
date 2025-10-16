<?php
/** @var array<int, array<string, mixed>> $queue */
$status = get_flash('auth_status');
$error = get_flash('auth_error');
$pageTitle = 'Fila de Chamados · WhatsAtende';
include base_path('app/Views/partials/layout-start.php');
include base_path('app/Views/partials/topbar.php');
?>
<div class="container-xxl py-4" id="queue-page">
    <div class="alert-stack mb-3">
        <?php if (!empty($status)): ?>
            <div class="alert alert-success shadow-sm" role="alert"><?= htmlspecialchars($status) ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger shadow-sm" role="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
    </div>
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-3 mb-4">
        <div>
            <h1 class="h3 fw-semibold mb-1">Fila em tempo real</h1>
            <p class="text-muted mb-0">Chamados aguardando atribuição atualizam automaticamente a cada 10 segundos.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-outline-secondary" data-refresh-queue>
                <i class="bi bi-arrow-clockwise"></i> Atualizar agora
            </button>
        </div>
    </div>
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0" id="queue-table" data-table>
                    <thead>
                    <tr>
                        <th>Protocolo</th>
                        <th>Contato</th>
                        <th>Canal</th>
                        <th>Status</th>
                        <th>Aberto em</th>
                        <th class="text-end">Ações</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($queue as $ticket): ?>
                        <tr>
                            <td>#<?= htmlspecialchars((string) ($ticket['id'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($ticket['contact_name'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($ticket['channel'] ?? 'whatsapp')) ?></td>
                            <td><span class="badge bg-secondary text-capitalize"><?= htmlspecialchars((string) ($ticket['status'] ?? 'open')) ?></span></td>
                            <td><?= htmlspecialchars((string) ($ticket['opened_at'] ?? '')) ?></td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-success" data-assign data-ticket="<?= htmlspecialchars((string) ($ticket['id'] ?? '')) ?>">
                                    <i class="bi bi-headset"></i> Iniciar atendimento
                                </button>
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
import { initQueue } from '/js/modules/queue.js';
initQueue('#queue-page', '/tickets');
</script>
<?php include base_path('app/Views/partials/layout-end.php'); ?>
