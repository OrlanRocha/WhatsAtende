<?php
/** @var array<string, string|null> $settings */
/** @var string|null $status */
/** @var string|null $error */
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Webhook · WhatsAtende</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
</head>
<body class="bg-light">
<?php include base_path('app/Views/admin/partials/nav.php'); ?>
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-6">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h1 class="h4 mb-0">Configuração do Webhook</h1>
                </div>
                <div class="card-body">
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
                    <form method="POST" action="/admin/webhook">
                        <div class="mb-3">
                            <label for="webhook_url" class="form-label">URL do webhook</label>
                            <input type="url" class="form-control" id="webhook_url" name="webhook_url" required value="<?= htmlspecialchars((string) ($settings['webhook_url'] ?? '')) ?>">
                            <div class="form-text">Informe a URL pública que receberá chamadas da Evolution API ou do n8n.</div>
                        </div>
                        <div class="mb-3">
                            <label for="webhook_token" class="form-label">Token de segurança</label>
                            <input type="text" class="form-control" id="webhook_token" name="webhook_token" required value="<?= htmlspecialchars((string) ($settings['webhook_token'] ?? '')) ?>">
                            <div class="form-text">Será validado via cabeçalho <code>X-Webhook-Token</code> ou parâmetro <code>token</code>.</div>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">Salvar alterações</button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="card shadow-sm mt-4">
                <div class="card-header">
                    <h2 class="h5 mb-0">Como configurar</h2>
                </div>
                <div class="card-body">
                    <ol class="mb-0 ps-3">
                        <li>Defina a URL de destino no provedor Evolution ou fluxo n8n apontando para <code><?= htmlspecialchars((string) ($settings['webhook_url'] ?? 'https://seu-dominio.com/api/webhook')) ?></code>.</li>
                        <li>Inclua o token acima no cabeçalho <code>X-Webhook-Token</code> ou como parâmetro <code>?token=</code>.</li>
                        <li>Verifique os logs do sistema para monitorar erros de integração.</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
