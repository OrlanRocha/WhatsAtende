<?php
/** @var array<string, string|null> $settings */
/** @var string|null $status */
/** @var string|null $error */
$breadcrumbs = [
    ['label' => 'Admin', 'href' => '/admin'],
    ['label' => 'Integrações'],
];
$pageTitle = 'Integrações · WhatsAtende';
include base_path('app/Views/partials/layout-start.php');
include base_path('app/Views/partials/topbar.php');
$mode = $settings['integration_mode'] ?? 'webhook';
?>
<div class="space-y-10 pb-12" id="webhook-admin-page">
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h4 mb-1">Integração com WhatsApp Evolution</h1>
                        <p class="text-muted mb-0">Escolha entre receber mensagens via webhook externo ou enviar direto usando a API nativa.</p>
                    </div>
                    <span class="badge bg-primary-subtle text-primary fw-semibold text-uppercase">Beta</span>
                </div>
                <div class="card-body">
                    <div class="alert-stack mb-3">
                        <?php if (!empty($status)): ?>
                            <div class="alert alert-success shadow-sm" role="alert"><?= htmlspecialchars($status) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger shadow-sm" role="alert"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                    </div>
                    <form method="POST" action="/admin/webhook" data-ajax data-success-message="Configurações salvas com sucesso." data-reset="false" id="integrationForm">
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Modo de integração</label>
                            <div class="btn-group" role="group">
                                <input type="radio" class="btn-check" name="integration_mode" id="mode_webhook" value="webhook" <?= $mode === 'webhook' ? 'checked' : '' ?>>
                                <label class="btn btn-outline-primary" for="mode_webhook"><i class="bi bi-link-45deg"></i> Webhook / n8n</label>
                                <input type="radio" class="btn-check" name="integration_mode" id="mode_native" value="native" <?= $mode === 'native' ? 'checked' : '' ?>>
                                <label class="btn btn-outline-primary" for="mode_native"><i class="bi bi-lightning-charge"></i> Evolução nativa</label>
                            </div>
                        </div>
                        <div class="integration-group" data-integration="webhook" <?= $mode === 'webhook' ? '' : 'hidden' ?>>
                            <h2 class="h6 fw-semibold">Configuração Webhook</h2>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="webhook_url" class="form-label">URL do webhook</label>
                                    <input type="url" class="form-control" id="webhook_url" name="webhook_url" value="<?= htmlspecialchars((string) ($settings['webhook_url'] ?? '')) ?>" placeholder="https://seu-dominio.com/api/webhook">
                                </div>
                                <div class="col-12">
                                    <label for="webhook_token" class="form-label">Token de segurança</label>
                                    <input type="text" class="form-control" id="webhook_token" name="webhook_token" value="<?= htmlspecialchars((string) ($settings['webhook_token'] ?? '')) ?>" placeholder="Informe um segredo forte">
                                    <div class="form-text">Validado no cabeçalho <code>X-Webhook-Token</code> ou parâmetro <code>?token=</code>.</div>
                                </div>
                            </div>
                        </div>
                        <div class="integration-group" data-integration="native" <?= $mode === 'native' ? '' : 'hidden' ?>>
                            <div class="p-8 text-center bg-slate-50 dark:bg-slate-900/50 rounded-xl border border-dashed border-slate-200 dark:border-slate-800">
                                <div class="w-16 h-16 rounded-2xl bg-primary/10 text-primary flex items-center justify-center mx-auto mb-4">
                                    <i class="bi bi-lightning-charge text-3xl"></i>
                                </div>
                                <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2">Configuração via Instâncias</h3>
                                <p class="text-slate-500 dark:text-slate-400 mb-6 max-w-sm mx-auto">
                                    A configuração nativa da Evolution agora é gerenciada de forma centralizada. Você pode cadastrar múltiplas instâncias e definir uma como padrão.
                                </p>
                                <a href="<?= route_path('/admin/integrations') ?>" class="btn btn-primary">
                                    Gerenciar Instâncias
                                </a>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <button type="reset" class="btn btn-outline-secondary">Descartar</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save2"></i> Salvar alterações
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header">
                    <h2 class="h6 mb-0 fw-semibold">Guia rápido</h2>
                </div>
                <div class="card-body">
                    <ol class="ps-3 mb-0">
                        <li><strong>Webhook:</strong> informe a URL pública do seu n8n ou serviço e cole o token neste painel.</li>
                        <li><strong>Nativo:</strong> cadastre o endpoint, instância e token fornecidos pela Evolution para enviar mensagens diretamente.</li>
                        <li>Use o botão <em>Testar entrega</em> abaixo para validar as credenciais antes de publicar.</li>
                    </ol>
                </div>
            </div>
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header">
                    <h2 class="h6 mb-0 fw-semibold">URL de Recebimento</h2>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-2">Configure o painel da Evolution-API (ou n8n) com a URL abaixo para nos enviar os eventos de mensagens:</p>
                    <div class="input-group mb-2">
                        <?php 
                            $whToken = $settings['webhook_token'] ?? '';
                            $endpointUrl = url('/api/webhook') . ($whToken !== '' ? '?token=' . rawurlencode($whToken) : '');
                        ?>
                        <input type="text" class="form-control font-monospace" style="font-size: 0.85rem;" id="my_webhook_url" readonly value="<?= htmlspecialchars($endpointUrl) ?>">
                        <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('my_webhook_url').value); alert('URL copiada!')"><i class="bi bi-clipboard"></i></button>
                    </div>
                    <p class="text-xs text-muted mb-0">No Evolution, ative os eventos: <code>MESSAGES_UPSERT</code>, <code>CALL</code>.</p>
                </div>
            </div>
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h2 class="h6 mb-1 fw-semibold">Teste rápido</h2>
                            <p class="text-muted small mb-0">Envie uma mensagem de validação usando a configuração atual.</p>
                        </div>
                        <i class="bi bi-broadcast-pin fs-3 text-primary"></i>
                    </div>
                    <form method="POST" action="/admin/webhook/test" data-ajax data-success-message="Mensagem de teste enviada." data-reset="true">
                        <div class="mb-3">
                            <label for="test_contact" class="form-label">Número do contato (com DDI)</label>
                            <input type="text" class="form-control" id="test_contact" name="contact" placeholder="5511999999999" required>
                        </div>
                        <div class="mb-3">
                            <label for="test_message" class="form-label">Mensagem</label>
                            <textarea class="form-control" id="test_message" name="message" rows="2" placeholder="Olá! Este é apenas um teste." required></textarea>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="bi bi-play-circle"></i> Testar entrega
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script type="module">
import { initIntegrationForm } from <?= json_encode(route_path('/js/modules/integration.js')) ?>;
initIntegrationForm('#integrationForm');
</script>
<?php include base_path('app/Views/partials/layout-end.php'); ?>
