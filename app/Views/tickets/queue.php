<?php
/** @var array<int, array<string, mixed>> $queue */
/** @var array<string, mixed> $nativeChats */
$nativeChats = $nativeChats ?? ['enabled' => false, 'chats' => [], 'error' => null];
$nativeStartEndpoint = route_path($nativeStartEndpoint ?? '/tickets/native/start');
$status = get_flash('auth_status');
$error = get_flash('auth_error');
$pageId = ($pageId ?? 'queue-page');
$queueTitle = $queueTitle ?? 'Fila em tempo real';
$queueDescription = $queueDescription ?? 'Chamados aguardando atribuição atualizam automaticamente a cada 10 segundos.';
$queueEndpoint = route_path($queueEndpoint ?? '/tickets');
$breadcrumbs = $breadcrumbs ?? [
    ['label' => 'Painel', 'href' => route_path('/tickets')],
    ['label' => $queueTitle],
];
$workspaceTabs = $workspaceTabs ?? [
    ['label' => 'Tickets', 'target' => '#queue-pane', 'active' => true],
];
$pageTitle = 'Fila de Chamados · WhatsAtende';
$supportGroups = $supportGroups ?? [];
$userGroups = $userGroups ?? [];
$seriousnessLabels = $seriousnessOptions ?? [
    'information' => 'Informação',
    'low' => 'Baixa',
    'medium' => 'Média',
    'high' => 'Alta',
    'critical' => 'Crítica',
];
include base_path('app/Views/partials/layout-start.php');
include base_path('app/Views/partials/topbar.php');
?>
<div class="workspace" id="<?= htmlspecialchars($pageId) ?>"
     data-support-groups='<?= htmlspecialchars(json_encode($supportGroups, JSON_UNESCAPED_UNICODE)) ?>'
     data-user-groups='<?= htmlspecialchars(json_encode($userGroups, JSON_UNESCAPED_UNICODE)) ?>'
     data-seriousness-options='<?= htmlspecialchars(json_encode($seriousnessLabels, JSON_UNESCAPED_UNICODE)) ?>'>
    <?php if (!empty($status) || !empty($error)): ?>
        <div class="alert-stack" role="status">
            <?php if (!empty($status)): ?>
                <div class="alert alert-success shadow-sm" role="alert"><?= htmlspecialchars($status) ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger shadow-sm" role="alert"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <section class="workspace-header">
        <div>
            <h1 class="workspace-title"><?= htmlspecialchars($queueTitle) ?></h1>
            <p class="workspace-subtitle"><?= htmlspecialchars($queueDescription) ?></p>
        </div>
        <div class="workspace-actions">
            <div class="btn-group" role="group" aria-label="Alternar visualização">
                <button type="button" class="btn btn-outline-secondary active" data-view-toggle="table">
                    <i class="bi bi-list-check"></i><span class="d-none d-md-inline"> Lista</span>
                </button>
                <button type="button" class="btn btn-outline-secondary" data-view-toggle="kanban">
                    <i class="bi bi-columns-gap"></i><span class="d-none d-md-inline"> Kanban</span>
                </button>
            </div>
            <button class="btn btn-outline-secondary" data-refresh-queue>
                <i class="bi bi-arrow-clockwise"></i> Atualizar
            </button>
        </div>
    </section>
    <section class="workspace-toolbar">
        <div class="workspace-toolbar__filters" data-filter-group>
            <span class="label">Filtros salvos:</span>
            <button type="button" class="chip is-active" data-saved-filter='{"status":null,"hide_resolved":true}'>Entrada</button>
            <button type="button" class="chip" data-saved-filter='{"mine":true,"hide_resolved":true}'>Meus</button>
            <button type="button" class="chip" data-saved-filter='{"status":"assigned"}'>Atribuídos</button>
            <button type="button" class="chip" data-saved-filter='{"status":"resolved"}'>Resolvidos</button>
        </div>
        <div class="workspace-toolbar__options">
            <div class="input-icon">
                <i class="bi bi-search"></i>
                <input type="search" placeholder="Pesquisar ticket ou contato" data-queue-search>
            </div>
            <select class="form-select form-select-sm" data-group-filter>
                <option value="">Todos os grupos</option>
                <?php foreach ($supportGroups as $group): ?>
                    <option value="<?= htmlspecialchars((string) ($group['id'] ?? '')) ?>">
                        <?= htmlspecialchars((string) ($group['name'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select class="form-select form-select-sm" data-seriousness-filter>
                <option value="">Todas as seriedades</option>
                <?php foreach ($seriousnessLabels as $key => $label): ?>
                    <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-density-toggle>
                <i class="bi bi-view-stacked"></i><span class="d-none d-lg-inline"> Compactar</span>
            </button>
            <?php if ($showTodayLink ?? false): ?>
                <a class="btn btn-outline-primary btn-sm" href="<?= htmlspecialchars(route_path('/tickets/today'), ENT_QUOTES) ?>">
                    <i class="bi bi-calendar-day"></i> Hoje
                </a>
            <?php endif; ?>
            <?php if ($showAllLink ?? false): ?>
                <a class="btn btn-outline-primary btn-sm" href="<?= htmlspecialchars(route_path('/tickets'), ENT_QUOTES) ?>">
                    <i class="bi bi-list-ul"></i> Fila completa
                </a>
            <?php endif; ?>
        </div>
    </section>
    <section class="workspace-metrics" aria-live="polite">
        <div class="metric-card" data-summary="open">
            <span class="metric-label">Open</span>
            <span class="metric-value">0</span>
        </div>
        <div class="metric-card" data-summary="assigned">
            <span class="metric-label">Assigned</span>
            <span class="metric-value">0</span>
        </div>
        <div class="metric-card" data-summary="waiting">
            <span class="metric-label">Waiting</span>
            <span class="metric-value">0</span>
        </div>
        <div class="metric-card" data-summary="resolved">
            <span class="metric-label">Resolved</span>
            <span class="metric-value">0</span>
        </div>
        <div class="metric-card" data-summary="breach">
            <span class="metric-label">SLA Breach</span>
            <span class="metric-value">0</span>
        </div>
    </section>
    <section class="workspace-panels" id="queue-pane">
        <div class="panel panel--table" data-view="table">
            <div class="table-responsive" data-table-wrapper>
                <table class="table align-middle" id="queue-table" data-table data-density="comfortable">
                    <thead>
                    <tr>
                        <th>Protocolo</th>
                        <th>Contato</th>
                        <th>Canal</th>
                        <th>Grupo</th>
                        <th>Seriedade</th>
                        <th>Status</th>
                        <th>SLA</th>
                        <th>Aberto em</th>
                        <th class="text-end">Ações</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($queue as $ticket): ?>
                        <?php
                        $seriousnessKey = strtolower((string) ($ticket['seriousness'] ?? 'information'));
                        $seriousnessLabel = $seriousnessLabels[$seriousnessKey] ?? ucfirst($seriousnessKey);
                        ?>
                        <tr>
                            <td class="fw-semibold">#<?= htmlspecialchars((string) ($ticket['id'] ?? '')) ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="presence-indicator" data-presence="offline" aria-hidden="true"></span>
                                    <div>
                                        <div class="fw-semibold"><?= htmlspecialchars((string) ($ticket['contact_name'] ?? '')) ?></div>
                                        <small class="text-muted">Ticket <?= htmlspecialchars((string) ($ticket['channel'] ?? 'whatsapp')) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><?= htmlspecialchars((string) ($ticket['channel'] ?? 'whatsapp')) ?></td>
                            <td><?= htmlspecialchars((string) ($ticket['group_name'] ?? 'Sem grupo')) ?></td>
                            <td>
                                <span class="badge bg-secondary-subtle text-secondary" data-seriousness="<?= htmlspecialchars($seriousnessKey) ?>">
                                    <?= htmlspecialchars($seriousnessLabel) ?>
                                </span>
                            </td>
                            <td><span class="status-badge status-badge--<?= htmlspecialchars((string) ($ticket['status'] ?? 'open')) ?>"><?= htmlspecialchars((string) ($ticket['status'] ?? 'open')) ?></span></td>
                            <td><span class="sla-badge" data-sla="ok">Em dia</span></td>
                            <td>
                                <?= htmlspecialchars((string) ($ticket['opened_at'] ?? '')) ?>
                                <?php if (!empty($ticket['opened_today'])): ?>
                                    <span class="badge badge--today">Hoje</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-success btn-sm" data-assign data-ticket="<?= htmlspecialchars((string) ($ticket['id'] ?? '')) ?>">
                                    <i class="bi bi-headset"></i> Iniciar
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="panel panel--kanban" data-view="kanban" hidden>
            <div class="kanban" data-kanban>
                <div class="kanban__column" data-kanban-column="open">
                    <header>Open</header>
                    <div class="kanban__body" data-kanban-list="open"></div>
                </div>
                <div class="kanban__column" data-kanban-column="assigned">
                    <header>Assigned</header>
                    <div class="kanban__body" data-kanban-list="assigned"></div>
                </div>
                <div class="kanban__column" data-kanban-column="waiting">
                    <header>Waiting</header>
                    <div class="kanban__body" data-kanban-list="waiting"></div>
                </div>
                <div class="kanban__column" data-kanban-column="resolved">
                    <header>Resolved</header>
                    <div class="kanban__body" data-kanban-list="resolved"></div>
                </div>
            </div>
        </div>
    </section>
    <?php if (!empty($nativeChats['enabled'])): ?>
        <section class="workspace-native" data-native-chats data-config='<?= htmlspecialchars(json_encode([
            'chats' => $nativeChats['chats'] ?? [],
        ], JSON_UNESCAPED_UNICODE)) ?>'>
            <div class="workspace-native__header">
                <div>
                    <h2>Conversas nativas</h2>
                    <p>Contatos retornados pela Evolution aguardando início de atendimento.</p>
                </div>
                <span class="badge badge--neutral" data-native-count><?= count($nativeChats['chats'] ?? []) ?> pendentes</span>
            </div>
            <div class="workspace-native__body">
                <div class="alert alert-warning<?= empty($nativeChats['error']) ? ' d-none' : '' ?>" role="alert" data-native-error>
                    <?= htmlspecialchars((string) ($nativeChats['error'] ?? '')) ?>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                        <tr>
                            <th>Contato</th>
                            <th>Não lidas</th>
                            <th>Última mensagem</th>
                            <th class="text-end">Ações</th>
                        </tr>
                        </thead>
                        <tbody data-native-body></tbody>
                    </table>
                </div>
            </div>
        </section>
    <?php endif; ?>
</div>
<script type="module">
import { initQueue } from <?= json_encode(route_path('/js/modules/queue.js')) ?>;
const pageSelector = <?= json_encode('#' . $pageId) ?>;
const endpoint = <?= json_encode($queueEndpoint) ?>;
const nativeConfig = <?= json_encode([
    'enabled' => (bool) ($nativeChats['enabled'] ?? false),
    'chats' => $nativeChats['chats'] ?? [],
    'error' => $nativeChats['error'] ?? null,
    'startEndpoint' => $nativeStartEndpoint,
], JSON_UNESCAPED_UNICODE) ?>;
initQueue(pageSelector, endpoint, nativeConfig);
</script>
<?php include base_path('app/Views/partials/layout-end.php'); ?>
