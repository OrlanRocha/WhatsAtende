<?php
/** @var array<int, array<string, mixed>> $chats */
/** @var string|null $error */
$breadcrumbs = [
    ['label' => 'Admin', 'href' => '/admin'],
    ['label' => 'Evolution']
];
$pageTitle = 'Evolution · Chats · WhatsAtende';
include base_path('app/Views/partials/layout-start.php');
include base_path('app/Views/partials/topbar.php');
?>
<div class="workspace" id="evolution-chats-page">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-3 mb-4">
        <div>
            <h1 class="h3 fw-semibold mb-1">Chats da Evolution</h1>
            <p class="text-muted mb-0">Visualize os contatos retornados pela API nativa e identifique novos atendimentos iniciados hoje.</p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="badge text-bg-secondary">Total: <?= count($chats) ?></span>
            <span class="text-muted small" data-last-updated>
                Atualizado em <?= htmlspecialchars(date('d/m/Y H:i:s')) ?>
            </span>
            <button class="btn btn-outline-secondary" data-refresh-chats>
                <i class="bi bi-arrow-repeat"></i> Atualizar agora
            </button>
        </div>
    </div>
    <div class="alert-stack mb-3">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger shadow-sm" role="alert">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0" id="evolution-chats-table" data-table>
                    <thead>
                    <tr>
                        <th>Contato</th>
                        <th>Não lidas</th>
                        <th>Iniciado em</th>
                        <th>Última mensagem</th>
                        <th>Detalhes</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($chats as $chat): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($chat['name'] !== '' ? $chat['name'] : $chat['id']) ?></strong>
                                <div class="text-muted small">ID: <?= htmlspecialchars($chat['id']) ?></div>
                                <?php if (!empty($chat['opened_today'])): ?>
                                    <span class="badge bg-success-subtle text-success mt-1">Iniciado hoje</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (($chat['unread'] ?? 0) > 0): ?>
                                    <span class="badge text-bg-warning text-dark"><?= (int) $chat['unread'] ?></span>
                                <?php else: ?>
                                    <span class="text-muted">0</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($chat['created_at'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($chat['last_message_at'] ?? '—') ?></td>
                            <td>
                                <details>
                                    <summary class="text-primary">Ver JSON</summary>
                                    <pre class="small bg-body-secondary p-3 rounded border mt-2 text-break"><?= htmlspecialchars(json_encode($chat['raw'] ?? new stdClass(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                                </details>
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
import { initEvolutionChats } from <?= json_encode(route_path('/js/modules/evolution-chats.js')) ?>;
initEvolutionChats('#evolution-chats-page', <?= json_encode(route_path('/admin/evolution/chats')) ?>);
</script>
<?php include base_path('app/Views/partials/layout-end.php'); ?>
