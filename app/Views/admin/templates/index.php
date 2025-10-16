<?php
/** @var array<int, array<string, mixed>> $templates */
/** @var string|null $status */
/** @var string|null $error */
/** @var array<int, string> $formErrors */
/** @var array<string, mixed> $old */
$old = $old ?? [];
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Templates · WhatsAtende</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
</head>
<body class="bg-light">
<?php include base_path('app/Views/admin/partials/nav.php'); ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Templates de Mensagens</h1>
    </div>
    <?php if (!empty($status)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($status) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0">Novo template</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($formErrors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0 ps-3">
                                <?php foreach ($formErrors as $formError): ?>
                                    <li><?= htmlspecialchars($formError) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <form method="POST" action="/admin/templates">
                        <div class="mb-3">
                            <label for="title" class="form-label">Título</label>
                            <input type="text" class="form-control" id="title" name="title" required value="<?= htmlspecialchars((string) ($old['title'] ?? '')) ?>">
                        </div>
                        <div class="mb-3">
                            <label for="category" class="form-label">Categoria</label>
                            <input type="text" class="form-control" id="category" name="category" value="<?= htmlspecialchars((string) ($old['category'] ?? '')) ?>">
                        </div>
                        <div class="mb-3">
                            <label for="body" class="form-label">Mensagem</label>
                            <textarea class="form-control" id="body" name="body" rows="5" required><?= htmlspecialchars((string) ($old['body'] ?? '')) ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Salvar template</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <?php if (empty($templates)): ?>
                <div class="alert alert-info">Nenhum template cadastrado.</div>
            <?php endif; ?>
            <?php foreach ($templates as $template): ?>
                <div class="card shadow-sm mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0"><?= htmlspecialchars((string) ($template['title'] ?? '')) ?></h5>
                            <small class="text-muted text-capitalize">Categoria: <?= htmlspecialchars((string) ($template['category'] ?? 'Geral')) ?></small>
                        </div>
                        <form method="POST" action="/admin/templates/<?= urlencode((string) ($template['id'] ?? '')) ?>/delete" onsubmit="return confirm('Deseja remover este template?');">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Excluir</button>
                        </form>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="/admin/templates/<?= urlencode((string) ($template['id'] ?? '')) ?>" class="row g-3 align-items-end">
                            <div class="col-md-6">
                                <label class="form-label">Título</label>
                                <input type="text" name="title" class="form-control" required value="<?= htmlspecialchars((string) ($template['title'] ?? '')) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Categoria</label>
                                <input type="text" name="category" class="form-control" value="<?= htmlspecialchars((string) ($template['category'] ?? '')) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Mensagem</label>
                                <textarea name="body" class="form-control" rows="4" required><?= htmlspecialchars((string) ($template['body'] ?? '')) ?></textarea>
                            </div>
                            <div class="col-12 text-end">
                                <button type="submit" class="btn btn-outline-primary">Atualizar</button>
                            </div>
                        </form>
                        <?php if (!empty($template['author'])): ?>
                            <p class="text-muted small mt-3 mb-0">Criado por <?= htmlspecialchars((string) $template['author']) ?> em <?= htmlspecialchars(date('d/m/Y H:i', strtotime($template['created_at'] ?? 'now'))) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
</body>
</html>
