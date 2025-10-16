<?php
/** @var array<string, mixed> $ticket */
/** @var array<int, array<string, mixed>> $templates */
$user = auth();
$status = get_flash('auth_status');
$pageTitle = 'Atendimento · WhatsAtende';
include base_path('app/Views/partials/layout-start.php');
include base_path('app/Views/partials/topbar.php');
?>
<div class="container-fluid py-4" id="chat-page" data-ticket-id="<?= (int) ($ticket['id'] ?? 0) ?>">
    <div class="alert-stack mb-3">
        <?php if (!empty($status)): ?>
            <div class="alert alert-success shadow-sm" role="alert"><?= htmlspecialchars($status) ?></div>
        <?php endif; ?>
    </div>
    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="mb-0 fw-semibold"><?= htmlspecialchars($ticket['contact_name'] ?? 'Cliente') ?></h5>
                        <small class="text-muted">Ticket #<?= htmlspecialchars((string) ($ticket['id'] ?? '')) ?> · Canal <?= htmlspecialchars($ticket['channel'] ?? 'whatsapp') ?></small>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button class="btn btn-outline-success btn-sm" data-resolve-ticket>
                            <i class="bi bi-check2-circle"></i> Finalizar atendimento
                        </button>
                        <button class="btn btn-outline-secondary btn-sm" data-refresh-chat>
                            <i class="bi bi-arrow-repeat"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body chat-window" data-chat-window>
                    <?php foreach ($ticket['messages'] ?? [] as $message): ?>
                        <div class="message <?= $message['sender_type'] === 'agent' ? 'agent' : 'contact' ?>">
                            <div class="bubble">
                                <?php if ($message['media_type'] === 'image' && !empty($message['media_url'])): ?>
                                    <img src="<?= htmlspecialchars($message['media_url']) ?>" class="img-fluid rounded" alt="Imagem recebida">
                                <?php elseif ($message['media_type'] === 'audio' && !empty($message['media_url'])): ?>
                                    <audio controls src="<?= htmlspecialchars($message['media_url']) ?>"></audio>
                                <?php else: ?>
                                    <?= nl2br(htmlspecialchars($message['body'] ?? '')) ?>
                                <?php endif; ?>
                                <div class="small text-muted mt-1">
                                    <?= htmlspecialchars(date('H:i', strtotime($message['sent_at']))) ?>
                                    <?php if (!empty($message['agent_name'])): ?> · <?= htmlspecialchars($message['agent_name']) ?><?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="card-footer">
                    <form id="message-form" data-chat-form>
                        <div class="input-group">
                            <textarea class="form-control" rows="2" placeholder="Digite sua mensagem..." required data-chat-input></textarea>
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-send"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-semibold">Templates rápidos</h6>
                    <button class="btn btn-sm btn-outline-primary" data-open-templates><i class="bi bi-stickies"></i></button>
                </div>
                <div class="card-body template-list" data-template-list>
                    <?php foreach ($templates as $template): ?>
                        <div class="template-card" data-template-body="<?= htmlspecialchars($template['body']) ?>">
                            <div class="d-flex justify-content-between align-items-start gap-3">
                                <div>
                                    <strong><?= htmlspecialchars($template['title']) ?></strong>
                                    <p class="mb-1 small text-muted"><?= nl2br(htmlspecialchars($template['body'])) ?></p>
                                </div>
                                <button class="btn btn-outline-secondary btn-sm" data-insert-template>
                                    <i class="bi bi-arrow-down"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($templates)): ?>
                        <p class="text-muted mb-0">Nenhum template cadastrado.</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card shadow-sm border-0">
                <div class="card-header">
                    <h6 class="mb-0 fw-semibold">Detalhes do chamado</h6>
                </div>
                <div class="card-body d-flex flex-column gap-2">
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Status atual</span>
                        <span class="badge bg-info text-dark text-capitalize" data-ticket-status><?= htmlspecialchars($ticket['status'] ?? '') ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Aberto em</span>
                        <span><?= htmlspecialchars(date('d/m/Y H:i', strtotime($ticket['opened_at'] ?? 'now'))) ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Atendente</span>
                        <span><?= htmlspecialchars($ticket['agent_name'] ?? ($user->full_name ?? '')) ?></span>
                    </div>
                    <div class="mt-3">
                        <span class="text-muted d-block">ID externo</span>
                        <code><?= htmlspecialchars($ticket['contact_external_id'] ?? '-') ?></code>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script type="module">
import { initChat } from '/js/modules/chat.js';
initChat('#chat-page');
</script>
<?php include base_path('app/Views/partials/layout-end.php'); ?>
