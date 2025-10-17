<?php
/** @var array<string, string|null> $settings */
/** @var string|null $status */
/** @var string|null $error */
/** @var array<string, mixed>|null $apiInfo */
/** @var string|null $apiError */
/** @var array<string, string>|null $instanceInfo */
/** @var string|null $instanceError */
$pageTitle = 'Integrações · WhatsAtende';
include base_path('app/Views/partials/layout-start.php');
include base_path('app/Views/admin/partials/nav.php');
$mode = $settings['integration_mode'] ?? 'webhook';
?>
<div class="container-xxl py-4">
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
                            <h2 class="h6 fw-semibold mt-4">Configuração Evolução Nativa</h2>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="evolution_api_url" class="form-label">Endpoint da API</label>
                                    <input type="url" class="form-control" id="evolution_api_url" name="evolution_api_url" value="<?= htmlspecialchars((string) ($settings['evolution_api_url'] ?? '')) ?>" placeholder="https://evolution.yourdomain.com">
                                </div>
                                <div class="col-md-4">
                                    <label for="evolution_instance" class="form-label">Instância</label>
                                    <input type="text" class="form-control" id="evolution_instance" name="evolution_instance" value="<?= htmlspecialchars((string) ($settings['evolution_instance'] ?? '')) ?>" placeholder="W001">
                                </div>
                                <div class="col-md-4">
                                    <label for="evolution_api_key" class="form-label">API Key</label>
                                    <input type="text" class="form-control" id="evolution_api_key" name="evolution_api_key" value="<?= htmlspecialchars((string) ($settings['evolution_api_key'] ?? '')) ?>" placeholder="Chave fornecida pela Evolution">
                                    <div class="form-text">Enviada no cabeçalho <code>apikey</code>.</div>
                                </div>
                                <div class="col-md-4">
                                    <label for="evolution_token" class="form-label">Token Bearer (opcional)</label>
                                    <input type="text" class="form-control" id="evolution_token" name="evolution_token" value="<?= htmlspecialchars((string) ($settings['evolution_token'] ?? '')) ?>" placeholder="Seu token Evolution">
                                    <div class="form-text">Usado em integrações legadas que exigem <code>Authorization: Bearer</code>.</div>
                                </div>
                                <div class="col-12">
                                    <label for="evolution_default_template" class="form-label">Mensagem automática de boas-vindas</label>
                                    <textarea class="form-control" id="evolution_default_template" name="evolution_default_template" rows="3" placeholder="Olá, somos o WhatsAtende! Como podemos te ajudar?"><?= htmlspecialchars((string) ($settings['evolution_default_template'] ?? '')) ?></textarea>
                                </div>
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
            <?php if ($mode === 'native'): ?>
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="h6 mb-1 fw-semibold">Status Evolution API</h2>
                            <p class="text-muted small mb-0">Informações retornadas pela API v2 e pela instância configurada.</p>
                        </div>
                        <i class="bi bi-diagram-3 text-primary fs-4"></i>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($apiError)): ?>
                            <div class="alert alert-warning shadow-sm" role="alert">
                                <?= htmlspecialchars($apiError) ?>
                            </div>
                        <?php elseif (is_array($apiInfo) && $apiInfo !== []): ?>
                            <dl class="row small mb-0">
                                <?php if (!empty($apiInfo['message'])): ?>
                                    <dt class="col-5 text-muted">Mensagem</dt>
                                    <dd class="col-7"><?= htmlspecialchars((string) $apiInfo['message']) ?></dd>
                                <?php endif; ?>
                                <?php if (!empty($apiInfo['version'])): ?>
                                    <dt class="col-5 text-muted">Versão</dt>
                                    <dd class="col-7"><?= htmlspecialchars((string) $apiInfo['version']) ?></dd>
                                <?php endif; ?>
                                <?php if (!empty($apiInfo['documentation'])): ?>
                                    <dt class="col-5 text-muted">Documentação</dt>
                                    <dd class="col-7">
                                        <a href="<?= htmlspecialchars((string) $apiInfo['documentation']) ?>" target="_blank" rel="noopener">Abrir docs</a>
                                    </dd>
                                <?php endif; ?>
                                <?php if (!empty($apiInfo['swagger'])): ?>
                                    <dt class="col-5 text-muted">Swagger</dt>
                                    <dd class="col-7">
                                        <a href="<?= htmlspecialchars((string) $apiInfo['swagger']) ?>" target="_blank" rel="noopener">Explorar endpoints</a>
                                    </dd>
                                <?php endif; ?>
                                <?php if (!empty($apiInfo['manager'])): ?>
                                    <dt class="col-5 text-muted">Manager</dt>
                                    <dd class="col-7">
                                        <a href="<?= htmlspecialchars((string) $apiInfo['manager']) ?>" target="_blank" rel="noopener">Abrir console</a>
                                    </dd>
                                <?php endif; ?>
                            </dl>
                        <?php else: ?>
                            <p class="text-muted small mb-0">Salve as credenciais para consultar o status do servidor Evolution.</p>
                        <?php endif; ?>

                        <hr class="my-4">

                        <?php if (!empty($instanceError)): ?>
                            <div class="alert alert-warning shadow-sm" role="alert">
                                <?= htmlspecialchars($instanceError) ?>
                            </div>
                        <?php elseif (is_array($instanceInfo) && $instanceInfo !== []): ?>
                            <dl class="row small mb-0">
                                <?php if (!empty($instanceInfo['instanceName'])): ?>
                                    <dt class="col-5 text-muted">Instância</dt>
                                    <dd class="col-7"><?= htmlspecialchars($instanceInfo['instanceName']) ?></dd>
                                <?php endif; ?>
                                <?php if (!empty($instanceInfo['status'])):
                                    $statusValue = strtolower($instanceInfo['status']);
                                    $isOpen = in_array($statusValue, ['open', 'connected', 'online'], true);
                                    $statusBadge = $isOpen ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary';
                                ?>
                                    <dt class="col-5 text-muted">Status</dt>
                                    <dd class="col-7"><span class="badge <?= $statusBadge ?> fw-semibold text-uppercase"><?= htmlspecialchars($instanceInfo['status']) ?></span></dd>
                                <?php endif; ?>
                                <?php if (!empty($instanceInfo['owner'])): ?>
                                    <dt class="col-5 text-muted">Owner</dt>
                                    <dd class="col-7"><?= htmlspecialchars($instanceInfo['owner']) ?></dd>
                                <?php endif; ?>
                                <?php if (!empty($instanceInfo['profileName'])): ?>
                                    <dt class="col-5 text-muted">Nome do perfil</dt>
                                    <dd class="col-7"><?= htmlspecialchars($instanceInfo['profileName']) ?></dd>
                                <?php endif; ?>
                                <?php if (!empty($instanceInfo['instanceId'])): ?>
                                    <dt class="col-5 text-muted">Instance ID</dt>
                                    <dd class="col-7"><code><?= htmlspecialchars($instanceInfo['instanceId']) ?></code></dd>
                                <?php endif; ?>
                                <?php if (!empty($instanceInfo['integrationEngine'])): ?>
                                    <dt class="col-5 text-muted">Engine</dt>
                                    <dd class="col-7"><?= htmlspecialchars($instanceInfo['integrationEngine']) ?></dd>
                                <?php endif; ?>
                                <?php if (!empty($instanceInfo['serverUrl'])): ?>
                                    <dt class="col-5 text-muted">Server URL</dt>
                                    <dd class="col-7 text-truncate" title="<?= htmlspecialchars($instanceInfo['serverUrl']) ?>">
                                        <a href="<?= htmlspecialchars($instanceInfo['serverUrl']) ?>" target="_blank" rel="noopener">Abrir servidor</a>
                                    </dd>
                                <?php endif; ?>
                                <?php if (!empty($instanceInfo['updatedAt'])): ?>
                                    <dt class="col-5 text-muted">Atualizado em</dt>
                                    <dd class="col-7"><?= htmlspecialchars($instanceInfo['updatedAt']) ?></dd>
                                <?php endif; ?>
                            </dl>
                        <?php else: ?>
                            <p class="text-muted small mb-0">Informe uma instância válida para visualizar o status de conexão.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
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
import { initIntegrationForm } from '/js/modules/integration.js';
initIntegrationForm('#integrationForm');
</script>
<?php include base_path('app/Views/partials/layout-end.php'); ?>
