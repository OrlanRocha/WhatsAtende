<?php
use App\Models\Ticket;
/** @var array<string, mixed> $ticket */
/** @var array<int, array<string, mixed>> $templates */
$user = auth();
$status = get_flash('auth_status');
$contactName = trim((string) ($ticket['contact_name'] ?? 'Cliente'));
$ticketId = (int) ($ticket['id'] ?? 0);
$statusLabels = [
    Ticket::STATUS_OPEN => 'Aberto',
    Ticket::STATUS_ASSIGNED => 'Em atendimento',
    Ticket::STATUS_RESOLVED => 'Resolvido',
    Ticket::STATUS_CLOSED => 'Encerrado',
];
$ticketStatusKey = strtolower((string) ($ticket['status'] ?? Ticket::STATUS_OPEN));
$ticketStatusLabel = $statusLabels[$ticketStatusKey] ?? ucfirst($ticketStatusKey);
$statusTimeline = [
    Ticket::STATUS_OPEN => [
        'label' => 'Aberto',
        'hint' => 'Ticket criado e aguardando triagem.',
        'enabled' => true,
    ],
    Ticket::STATUS_ASSIGNED => [
        'label' => 'Em atendimento',
        'hint' => 'Agente em conversação ativa.',
        'enabled' => true,
    ],
    Ticket::STATUS_RESOLVED => [
        'label' => 'Resolvido',
        'hint' => 'Atendimento concluído para o cliente.',
        'enabled' => true,
    ],
    Ticket::STATUS_CLOSED => [
        'label' => 'Encerrado',
        'hint' => 'Ticket arquivado após revisão.',
        'enabled' => false,
    ],
];
$statusOrder = array_keys($statusTimeline);
$currentStatusIndex = array_search($ticketStatusKey, $statusOrder, true);
if ($currentStatusIndex === false) {
    $currentStatusIndex = 0;
}
$formattedNumber = !empty($ticket['contact_external_id']) ? format_whatsapp($ticket['contact_external_id']) : null;
if ($contactName === ($ticket['contact_external_id'] ?? '') || str_replace('@s.whatsapp.net', '', $ticket['contact_external_id'] ?? '') === $contactName) {
    if ($formattedNumber) {
        $contactName = $formattedNumber;
    }
}

$isNameNumeric = preg_match('/^\(?\+?\d+[\d\s\-\(\)]+$/', $contactName);
$contactInitial = ($isNameNumeric || empty($contactName)) 
    ? null 
    : (function_exists('mb_substr') ? mb_strtoupper(mb_substr($contactName, 0, 1)) : strtoupper(substr($contactName, 0, 1)));
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
$supportGroups = $supportGroups ?? [];
$userGroups = $userGroups ?? [];
$seriousnessLabels = $seriousnessOptions ?? [
    Ticket::SERIOUSNESS_INFORMATION => 'Informação',
    Ticket::SERIOUSNESS_LOW => 'Baixa',
    Ticket::SERIOUSNESS_MEDIUM => 'Média',
    Ticket::SERIOUSNESS_HIGH => 'Alta',
    Ticket::SERIOUSNESS_CRITICAL => 'Crítica',
];
$seriousnessKey = strtolower((string) ($ticket['seriousness'] ?? Ticket::SERIOUSNESS_INFORMATION));
$seriousnessLabel = $seriousnessLabels[$seriousnessKey] ?? ucfirst($seriousnessKey);
include base_path('app/Views/partials/layout-start.php');
include base_path('app/Views/partials/topbar.php');
?>
<?php if (!empty($status)): ?>
    <div class="p-4 m-4 text-emerald-800 rounded-lg bg-emerald-50 dark:bg-emerald-950/30 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800 shadow-sm" role="alert"><?= htmlspecialchars($status) ?></div>
<?php endif; ?>

<div class="workspace workspace--split" id="ticket-workspace"
     data-ticket-id="<?= $ticketId ?>"
     data-sla-due="<?= htmlspecialchars((string) ($ticket['sla_due_at'] ?? '')) ?>"
     data-support-groups='<?= htmlspecialchars(json_encode($supportGroups, JSON_UNESCAPED_UNICODE)) ?>'
     data-user-groups='<?= htmlspecialchars(json_encode($userGroups, JSON_UNESCAPED_UNICODE)) ?>'
     data-seriousness-options='<?= htmlspecialchars(json_encode($seriousnessLabels, JSON_UNESCAPED_UNICODE)) ?>'
     data-current-seriousness="<?= htmlspecialchars($seriousnessKey) ?>">
    
    <section class="ticket-pane ticket-pane--conversation" data-conversation>
        <header class="conversation-header">
            <div class="conversation-header__identity">
                <?php 
                $initials = strtoupper(substr(preg_replace('/[^\w]/', '', $contactName ?? 'U'), 0, 1) ?: 'U');
                ?>
                <div class="avatar avatar-lg" data-profile-avatar <?= $profileUrl ? 'data-profile-url="' . htmlspecialchars($profileUrl) . '"' : '' ?>>
                    <span data-profile-fallback><?= htmlspecialchars($initials) ?></span>
                </div>
                <div>
                    <h2><?= htmlspecialchars($contactName ?? 'Usuário') ?></h2>
                    <div class="conversation-header__meta">
                        <span class="status-badge status-badge--<?= htmlspecialchars($ticketStatusKey) ?>" data-ticket-status data-status-key="<?= htmlspecialchars($ticketStatusKey) ?>"><?= htmlspecialchars($ticketStatusLabel) ?></span>
                        <span class="header-pill">
                            <i class="bi bi-hash"></i>
                            <span><?= htmlspecialchars((string) $ticketId) ?></span>
                        </span>
                        <span class="header-pill">
                            <i class="bi bi-whatsapp"></i>
                            <span><?= htmlspecialchars((string) ($ticket['channel'] ?? 'whatsapp')) ?></span>
                        </span>
                        <span class="header-pill" data-ticket-seriousness>
                            <i class="bi bi-shield-check"></i>
                            <span><?= htmlspecialchars($seriousnessLabel) ?></span>
                        </span>
                    </div>
                </div>
            </div>
            <div class="conversation-header__actions">
                <button class="btn btn-premium btn-primary flex items-center gap-2" data-resolve-ticket>
                    <i class="bi bi-check2-circle"></i>
                    <span>Finalizar Atendimento</span>
                </button>
                <button class="btn btn-icon btn-premium" data-refresh-chat title="Atualizar Conversa">
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
                            <p class="text-slate-500 italic">Mensagem sem conteúdo.</p>
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
            <div class="quick-suggestions custom-scrollbar" data-quick-suggestions>
                <button type="button" class="chip" data-suggestion><i class="bi bi-chat-heart"></i> Obrigado!</button>
                <button type="button" class="chip" data-suggestion><i class="bi bi-search"></i> Analisando...</button>
                <button type="button" class="chip" data-suggestion><i class="bi bi-check2"></i> Concluído?</button>
            </div>
            <form id="message-form" data-chat-form enctype="multipart/form-data">
                <div class="composer-field">
                    <textarea class="form-control" rows="2" placeholder="Digite sua mensagem... (Ctrl/Cmd + Enter para enviar)" name="message" data-chat-input></textarea>
                    <div class="composer-shortcuts">
                        <span><kbd>/</kbd> templates</span>
                        <span><kbd>@</kbd> menções</span>
                    </div>
                    <button class="btn btn-premium btn-primary" type="submit">
                        <i class="bi bi-send-fill"></i>
                    </button>
                </div>
            </form>
        </div>
    </section>
    <aside class="ticket-pane ticket-pane--context flex flex-col gap-3 p-3" id="ticketSidebarAccordion">
        <style>
            [aria-expanded="true"] .bi-chevron-down {
                transform: rotate(180deg);
            }
        </style>
        <section class="context-card !p-0 overflow-hidden shrink-0" data-ticket-meta>
            <header class="px-3 py-3 flex items-center cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapseDetails" aria-expanded="true">
                <h3 class="!mb-0 flex-1 w-full">Detalhes do ticket</h3>
                <i class="bi bi-chevron-down transition-transform duration-300 ms-3 text-slate-400"></i>
            </header>
            <div id="collapseDetails" class="accordion-content" data-bs-parent="#ticketSidebarAccordion">
                <div class="px-3 pb-3 pt-1 flex flex-col gap-3">
                    <div>
                        <label for="ticket-subject" class="form-label">Assunto</label>
                        <input type="text" class="form-control" id="ticket-subject" name="subject" value="<?= htmlspecialchars((string) ($ticket['subject'] ?? '')) ?>" placeholder="Defina um assunto para o atendimento" data-meta-subject>
                    </div>
                    <div>
                        <label for="ticket-seriousness" class="form-label">Seriedade</label>
                        <select id="ticket-seriousness" class="form-select" name="seriousness" data-meta-seriousness>
                            <?php foreach ($seriousnessLabels as $key => $label): ?>
                                <option value="<?= htmlspecialchars($key) ?>"<?= $key === $seriousnessKey ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="ticket-group" class="form-label">Grupo</label>
                        <select id="ticket-group" class="form-select" name="group_id" data-meta-group>
                            <option value="">Sem grupo definido</option>
                            <?php foreach ($supportGroups as $group): ?>
                                <?php $groupId = (int) ($group['id'] ?? 0); ?>
                                <option value="<?= htmlspecialchars((string) $groupId) ?>"<?= $groupId === (int) ($ticket['group_id'] ?? 0) ? ' selected' : '' ?>>
                                    <?= htmlspecialchars((string) ($group['name'] ?? 'Grupo')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex justify-between items-center mt-2">
                        <span class="text-slate-500 text-sm" data-meta-feedback></span>
                        <button type="button" class="btn btn-outline-primary btn-sm" data-meta-save>
                            <i class="bi bi-save"></i> Salvar
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <section class="context-card !p-0 overflow-hidden shrink-0">
            <header class="px-3 py-3 flex items-center cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapseStatus" aria-expanded="false">
                <h3 class="!mb-0 flex-1 w-full">Status do ticket</h3>
                <i class="bi bi-chevron-down transition-transform duration-300 ms-3 text-slate-400"></i>
            </header>
            <div id="collapseStatus" class="accordion-content hidden" data-bs-parent="#ticketSidebarAccordion">
                <div class="px-3 pb-5 pt-1">
                    <div class="ticket-status-track" data-status-track data-current-status="<?= htmlspecialchars($ticketStatusKey) ?>">
                        <?php foreach ($statusTimeline as $statusKey => $meta): ?>
                            <?php
                            $position = array_search($statusKey, $statusOrder, true);
                            $isActive = $position !== false && $position === $currentStatusIndex;
                            $isComplete = $position !== false && $position < $currentStatusIndex;
                            $classes = 'ticket-status-step';
                            if ($isActive) {
                                $classes .= ' is-active';
                            } elseif ($isComplete) {
                                $classes .= ' is-complete';
                            }
                            ?>
                            <button type="button"
                                    class="<?= $classes ?>"
                                    data-status-step="<?= htmlspecialchars($statusKey) ?>"
                                    data-status-label="<?= htmlspecialchars($meta['label']) ?>"
                                    <?= $meta['enabled'] ? '' : 'disabled aria-disabled="true"' ?>>
                                <span><?= htmlspecialchars($meta['label']) ?></span>
                                <small><?= htmlspecialchars($meta['hint']) ?></small>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>

        <section class="context-card !p-0 overflow-hidden shrink-0">
            <header class="px-3 py-3 flex items-center cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapseContato" aria-expanded="false">
                <h3 class="text-primary font-bold !mb-0 flex-1 w-full"><i class="bi bi-person-badge me-2"></i>Contato</h3>
                <i class="bi bi-chevron-down transition-transform duration-300 ms-3 text-slate-400"></i>
            </header>
            <div id="collapseContato" class="accordion-content hidden" data-bs-parent="#ticketSidebarAccordion">
                <div class="px-3 pb-3 pt-1">
                    <dl class="space-y-4 m-0">
                        <div class="flex flex-col">
                            <dt class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Nome do Cliente</dt>
                            <dd class="text-sm font-semibold text-slate-800 dark:text-slate-100"><?= htmlspecialchars($contactName) ?></dd>
                        </div>
                        <div class="flex flex-col">
                            <dt class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">WhatsApp / ID</dt>
                            <dd class="text-sm font-mono font-medium text-slate-600 dark:text-slate-400 bg-slate-100 dark:bg-slate-800 px-2 py-1 rounded-md w-fit">
                                <?= htmlspecialchars($formattedNumber ?: ($ticket['contact_external_id'] ?? '-')) ?>
                            </dd>
                        </div>
                        <div class="flex flex-col">
                            <dt class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Data de Abertura</dt>
                            <dd class="text-sm font-medium text-slate-700 dark:text-slate-300">
                                <i class="bi bi-calendar-event me-2"></i><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) ($ticket['opened_at'] ?? 'now')))) ?>
                            </dd>
                        </div>
                        <div class="flex flex-col">
                            <dt class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Atendente Responsável</dt>
                            <dd class="text-sm font-medium text-slate-700 dark:text-slate-300">
                                <?php if (!empty($agents)): ?>
                                    <form action="<?= route_path('/tickets/' . $ticketId . '/assign') ?>" method="POST" data-ajax data-success-message="Atendente alterado.">
                                        <select name="user_id" class="form-select form-select-sm" onchange="this.form.requestSubmit()">
                                            <option value="">Selecione um atendente</option>
                                            <?php foreach ($agents as $agent): ?>
                                                <option value="<?= $agent['id'] ?>" <?= (int)$agent['id'] === (int)($ticket['assigned_user_id'] ?? 0) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($agent['full_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                <?php else: ?>
                                    <i class="bi bi-person-circle me-2"></i><?= htmlspecialchars($ticket['agent_name'] ?? 'Não atribuído') ?>
                                <?php endif; ?>
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </section>

        <section class="context-card !p-0 overflow-hidden shrink-0" data-sla-panel>
            <header class="px-3 py-3 flex items-center cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapseSLA" aria-expanded="false">
                <h3 class="!mb-0 flex-1 w-full">SLA</h3>
                <i class="bi bi-chevron-down transition-transform duration-300 ms-3 text-slate-400"></i>
            </header>
            <div id="collapseSLA" class="accordion-content hidden" data-bs-parent="#ticketSidebarAccordion">
                <div class="px-3 pb-5 pt-1">
                    <p class="sla-countdown mb-2" data-sla-countdown>Calculando SLA...</p>
                    <div class="progress" role="presentation">
                        <div class="progress-bar" data-sla-progress style="width: 0%"></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="context-card !p-0 overflow-hidden shrink-0">
            <header class="px-3 py-3 flex items-center cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapseTemplates" aria-expanded="false">
                <h3 class="!mb-0 flex-1 w-full">Templates rápidos</h3>
                <i class="bi bi-chevron-down transition-transform duration-300 ms-3 text-slate-400"></i>
            </header>
            <div id="collapseTemplates" class="accordion-content hidden" data-bs-parent="#ticketSidebarAccordion">
                <div class="px-3 pb-5 pt-1">
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
                            <p class="text-slate-500">Nenhum template cadastrado.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <section class="context-card !p-0 overflow-hidden shrink-0">
            <header class="px-3 py-3 flex items-center cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapseNotes" aria-expanded="false">
                <h3 class="!mb-0 flex-1 w-full">Notas internas</h3>
                <i class="bi bi-chevron-down transition-transform duration-300 ms-3 text-slate-400"></i>
            </header>
            <div id="collapseNotes" class="accordion-content hidden" data-bs-parent="#ticketSidebarAccordion">
                <div class="px-3 pb-3 pt-1">
                    <textarea class="form-control mb-2" rows="4" placeholder="Registrar observações visíveis apenas para a equipe." data-internal-notes></textarea>
                    <small class="text-slate-500">Notas são salvas automaticamente no navegador.</small>
                </div>
            </div>
        </section>
    </aside>
</div>
<script type="module">
import { initChat } from <?= json_encode(route_path('/js/modules/chat.js')) ?>;
initChat('#ticket-workspace');
</script>
<?php include base_path('app/Views/partials/layout-end.php'); ?>
