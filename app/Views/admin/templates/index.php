<?php
/** @var array<int, array<string, mixed>> $templates */
/** @var string|null $status */
/** @var string|null $error */
$pageTitle = 'Templates · WhatsAtende';
include base_path('app/Views/partials/layout-start.php');
include base_path('app/Views/admin/partials/nav.php');
?>
<div class="container-xxl py-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-3 mb-4">
        <div>
            <h1 class="h3 fw-semibold mb-1">Scripts e Templates</h1>
            <p class="text-muted mb-0">Prepare respostas rápidas para padronizar o atendimento omnichannel.</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#templateModal" data-mode="create">
            <i class="bi bi-plus-circle"></i> Novo template
        </button>
    </div>
    <div class="alert-stack">
        <?php if (!empty($status)): ?>
            <div class="alert alert-success shadow-sm" role="alert"><?= htmlspecialchars($status) ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger shadow-sm" role="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
    </div>
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0" id="templates-table" data-table>
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Título</th>
                        <th>Categoria</th>
                        <th>Última atualização</th>
                        <th class="text-end">Ações</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($templates as $template): ?>
                        <tr data-template-row data-template='<?= json_encode($template, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>'>
                            <td><?= (int) ($template['id'] ?? 0) ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars((string) ($template['title'] ?? '')) ?></td>
                            <td><span class="badge rounded-pill bg-secondary-subtle text-dark text-capitalize"><?= htmlspecialchars((string) ($template['category'] ?? 'geral')) ?></span></td>
                            <td><?= htmlspecialchars(isset($template['updated_at']) ? date('d/m/Y H:i', strtotime((string) $template['updated_at'])) : '') ?></td>
                            <td class="text-end">
                                <div class="btn-group" role="group">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#templateModal" data-mode="edit">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <form method="POST" action="/admin/templates/<?= urlencode((string) ($template['id'] ?? '')) ?>/delete" class="d-inline" data-ajax data-confirm="Excluir este template?" data-success-event="templates:refresh">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
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
</div>

<div class="modal fade" id="templateModal" tabindex="-1" aria-labelledby="templateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header">
                <h5 class="modal-title" id="templateModalLabel">Novo template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form method="POST" action="/admin/templates" data-ajax data-hide-modal="#templateModal" data-success-event="templates:refresh" data-reset="true" id="templateForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <label class="form-label" for="template_title">Título</label>
                            <input type="text" class="form-control" id="template_title" name="title" required>
                        </div>
                        <div class="col-lg-6">
                            <label class="form-label" for="template_category">Categoria</label>
                            <input type="text" class="form-control" id="template_category" name="category" placeholder="Boas-vindas, Follow-up, ...">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="template_body">Conteúdo</label>
                            <textarea class="form-control" id="template_body" name="body" rows="5" required></textarea>
                            <div class="form-text">Suporta emojis, links e quebras de linha.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-floppy"></i> Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script type="module">
import { initTemplateModal, refreshTemplateTable } from '/js/modules/templates.js';
initTemplateModal('#templateModal', '#templateForm');
window.addEventListener('templates:refresh', () => refreshTemplateTable('#templates-table', '/admin/templates'));
</script>
<?php include base_path('app/Views/partials/layout-end.php'); ?>
