<?php
/** @var array<string, mixed> $ticket */
/** @var array<int, array<string, mixed>> $templates */
$user = auth();
$status = get_flash('auth_status');
$contactName = trim((string) ($ticket['contact_name'] ?? 'Cliente'));
$ticketId = (int) ($ticket['id'] ?? 0);
$contactInitial = function_exists('mb_substr')
    ? mb_strtoupper(mb_substr($contactName, 0, 1))
    : strtoupper(substr($contactName, 0, 1));
$profileUrl = !empty($ticket['contact_external_id'])
    ? '/api/evolution/profile?remoteJid=' . rawurlencode((string) $ticket['contact_external_id'])
    : null;
$breadcrumbs = [
    ['label' => 'Tickets', 'href' => '/tickets'],
    ['label' => 'Ticket #' . $ticketId],
];
$workspaceTabs = [
    ['label' => 'Ticket #' . $ticketId, 'target' => '#ticket-workspace', 'active' => true, 'closable' => false],
];
$pageTitle = 'Atendimento · Ticket #' . $ticketId;
include base_path('app/Views/partials/layout-start.php');
include base_path('app/Views/partials/topbar.php');
?>
<div class="workspace workspace--split" id="ticket-workspace" data-ticket-id="<?= $ticketId ?>" data-sla-due="<?= htmlspecialchars((string) ($ticket['sla_due_at'] ?? '')) ?>">
    <?php if (!empty($status)): ?>
        <div class="alert alert-success shadow-sm" role="alert"><?= htmlspecialchars($status) ?></div>
    <?php endif; ?>
    <aside class="ticket-pane ticket-pane--list" data-ticket-sidebar>
        <header>
            <h2>Atendimentos</h2>
            <p>Tickets atribuídos e recentes são carregados em tempo real.</p>
        </header>
        <div class="ticket-pane__controls">
            <div class="input-icon">
                <i class="bi bi-search"></i>
                <input type="search" placeholder="Buscar protocolo, contato ou tag" data-sidebar-search>
            </div>
            <div class="chip-group" role="group">
                <button type="button" class="chip is-active" data-sidebar-filter='{"mine":true,"hide_resolved":true}'>Meus</button>
                <button type="button" class="chip" data-sidebar-filter='{"status":"open"}'>Open</button>
                <button type="button" class="chip" data-sidebar-filter='{"status":"assigned"}'>Assigned</button>
                <button type="button" class="chip" data-sidebar-filter='{"status":"resolved"}'>Resolved</button>
            </div>
        </div>
        <div class="ticket-pane__feedback alert alert-danger d-none" role="alert" data-sidebar-error aria-live="assertive">
            <div class="ticket-pane__feedback-message" data-sidebar-error-message>
                Não foi possível carregar a lista de tickets.
            </div>
            <div class="ticket-pane__feedback-actions">
                <button type="button" class="btn btn-link btn-sm p-0" data-sidebar-retry>
                    <i class="bi bi-arrow-clockwise"></i> Tentar novamente agora
                </button>
                <small class="text-muted d-none" data-sidebar-error-retry></small>
            </div>
        </div>
        <div class="ticket-pane__list list-group" data-sidebar-list>
            <div class="skeleton-list" aria-hidden="true">
                <div class="skeleton-row"></div>
                <div class="skeleton-row"></div>
                <div class="skeleton-row"></div>
            </div>
        </div>
    </aside>
    <section class="ticket-pane ticket-pane--conversation" data-conversation>
        <header class="conversation-header">
            <div class="conversation-header__identity">
                <div class="avatar avatar-lg" data-profile-avatar<?= $profileUrl ? ' data-profile-url="' . htmlspecialchars($profileUrl) . '"' : '' ?>>
                    <span data-profile-fallback><?= htmlspecialchars($contactInitial ?: '#') ?></span>
                </div>
                <div>
                    <h2><?= htmlspecialchars($contactName) ?></h2>
                    <div class="conversation-header__meta">
                        <span class="status-badge status-badge--<?= htmlspecialchars((string) ($ticket['status'] ?? 'open')) ?>" data-ticket-status><?= htmlspecialchars((string) ($ticket['status'] ?? 'open')) ?></span>
                        <span class="dot" aria-hidden="true"></span>
                        <span>Ticket #<?= htmlspecialchars((string) $ticketId) ?></span>
                        <span class="dot" aria-hidden="true"></span>
                        <span>Canal <?= htmlspecialchars((string) ($ticket['channel'] ?? 'whatsapp')) ?></span>
                    </div>
                </div>
            </div>
            <div class="conversation-header__actions">
                <button class="btn btn-outline-success btn-sm" data-resolve-ticket>
                    <i class="bi bi-check2-circle"></i> Finalizar
                </button>
                <button class="btn btn-outline-secondary btn-sm" data-refresh-chat>
                    <i class="bi bi-arrow-repeat"></i>
                </button>
            </div>
        </header>
        <div class="chat-window" data-chat-window>
            <div class="skeleton-chat" aria-hidden="true">
                <div class="skeleton-bubble"></div>
                <div class="skeleton-bubble"></div>
                <div class="skeleton-bubble"></div>
            </div>
            <?php foreach ($ticket['messages'] ?? [] as $message): ?>
                <?php
                $mediaType = $message['media_type'] ?? 'text';
                $mediaUrl = $message['media_url'] ?? null;
                $bodyText = trim((string) ($message['body'] ?? ''));
                $hasMedia = is_string($mediaUrl)
                    && $mediaUrl !== ''
                    && in_array($mediaType, ['image', 'audio', 'video', 'file'], true);
                $isAgent = ($message['sender_type'] ?? '') === 'agent';
                ?>
                <article class="chat-message <?= $isAgent ? 'chat-message--agent' : 'chat-message--contact' ?>">
                    <div class="chat-bubble" data-message-type="<?= htmlspecialchars($mediaType) ?>">
                        <?php if ($hasMedia): ?>
                            <?php if ($mediaType === 'image'): ?>
                                <img src="<?= htmlspecialchars($mediaUrl) ?>" class="chat-media chat-media--image" alt="Mídia recebida">
                            <?php elseif ($mediaType === 'audio'): ?>
                                <audio controls class="chat-media chat-media--audio" src="<?= htmlspecialchars($mediaUrl) ?>"></audio>
                            <?php elseif ($mediaType === 'video'): ?>
                                <video controls class="chat-media chat-media--video" src="<?= htmlspecialchars($mediaUrl) ?>"></video>
                            <?php elseif ($mediaType === 'file'): ?>
                                <a href="<?= htmlspecialchars($mediaUrl) ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-paperclip"></i> Baixar arquivo
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if ($bodyText !== ''): ?>
                            <p><?= nl2br(htmlspecialchars($bodyText)) ?></p>
                        <?php elseif (!$hasMedia): ?>
                            <p class="text-muted fst-italic">Mensagem sem conteúdo.</p>
                        <?php endif; ?>
                        <footer>
                            <time datetime="<?= htmlspecialchars(date('c', strtotime((string) ($message['sent_at'] ?? 'now')))) ?>">
                                <?= htmlspecialchars(date('H:i', strtotime((string) ($message['sent_at'] ?? 'now')))) ?>
                            </time>
                            <?php if (!empty($message['agent_name'])): ?>
                                <span aria-hidden="true">·</span>
                                <span><?= htmlspecialchars((string) $message['agent_name']) ?></span>
                            <?php endif; ?>
                        </footer>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <div class="chat-composer" data-chat-composer>
            <div class="quick-suggestions" data-quick-suggestions>
                <button type="button" class="chip" data-suggestion>Obrigado pelo contato!</button>
                <button type="button" class="chip" data-suggestion>Estamos analisando seu chamado.</button>
                <button type="button" class="chip" data-suggestion>Podemos encerrar o atendimento?</button>
            </div>
            <form id="message-form" data-chat-form enctype="multipart/form-data">
                <div class="composer-field" data-dropzone>
                    <input type="file" class="visually-hidden" name="attachment" accept="image/*,audio/*,video/*" data-chat-attachment>
                    <button class="btn btn-icon btn-outline-secondary" type="button" data-attach-trigger title="Anexar mídia">
                        <i class="bi bi-paperclip"></i>
                    </button>
                    <textarea class="form-control" rows="2" placeholder="Digite sua mensagem... (Ctrl/Cmd + Enter para enviar)" name="message" data-chat-input></textarea>
                    <div class="composer-shortcuts">
                        <span><kbd>/</kbd> templates</span>
                        <span><kbd>@</kbd> menções</span>
                    </div>
                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-send"></i>
                    </button>
                </div>
            </form>
        </div>
    </section>
    <aside class="ticket-pane ticket-pane--context">
        <section class="context-card">
            <header>
                <h3>Contato</h3>
            </header>
            <dl>
                <div>
                    <dt>Nome</dt>
                    <dd><?= htmlspecialchars($contactName) ?></dd>
                </div>
                <div>
                    <dt>ID externo</dt>
                    <dd><code><?= htmlspecialchars((string) ($ticket['contact_external_id'] ?? '-')) ?></code></dd>
                </div>
                <div>
                    <dt>Aberto em</dt>
                    <dd><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) ($ticket['opened_at'] ?? 'now')))) ?></dd>
                </div>
                <div>
                    <dt>Atendente</dt>
                    <dd><?= htmlspecialchars($ticket['agent_name'] ?? ($user->full_name ?? '')) ?></dd>
                </div>
            </dl>
        </section>
        <section class="context-card" data-sla-panel>
            <header>
                <h3>SLA</h3>
            </header>
            <p class="sla-countdown" data-sla-countdown>Calculando SLA...</p>
            <div class="progress" role="presentation">
                <div class="progress-bar" data-sla-progress style="width: 0%"></div>
            </div>
        </section>
        <section class="context-card">
            <header>
                <h3>Templates rápidos</h3>
            </header>
            <div class="template-list" data-template-list>
                <?php foreach ($templates as $template): ?>
                    <article class="template-card" data-template-body="<?= htmlspecialchars($template['body']) ?>">
                        <div class="template-card__content">
                            <h4><?= htmlspecialchars($template['title']) ?></h4>
                            <p><?= nl2br(htmlspecialchars($template['body'])) ?></p>
                        </div>
                        <div class="template-card__actions">
                            <button class="btn btn-outline-secondary btn-sm" data-send-template>
                                <i class="bi bi-send-fill"></i>
                            </button>
                        </div>
                    </article>
                <?php endforeach; ?>
                <?php if (empty($templates)): ?>
                    <p class="text-muted">Nenhum template cadastrado.</p>
                <?php endif; ?>
            </div>
        </section>
        <section class="context-card">
            <header>
                <h3>Notas internas</h3>
            </header>
            <textarea class="form-control" rows="4" placeholder="Registrar observações visíveis apenas para a equipe." data-internal-notes></textarea>
            <small class="text-muted">Notas são salvas automaticamente no navegador.</small>
        </section>
    </aside>
</div>
<script type="module">
import { initChat } from '/js/modules/chat.js';
initChat('#ticket-workspace');
</script>
<?php include base_path('app/Views/partials/layout-end.php'); ?>
