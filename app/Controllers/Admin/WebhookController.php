<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\EvolutionService;
use App\Services\SettingService;
use function array_is_list;

class WebhookController
{
    public function __construct(private SettingService $settingService, private EvolutionService $evolutionService)
    {
    }

    public function index(): void
    {
        require_role('admin');
        $settings = $this->settingService->integrationSettings();

        $apiInfo = null;
        $instanceInfo = null;
        $apiError = null;
        $instanceError = null;

        if (($settings['integration_mode'] ?? 'webhook') === 'native') {
            $infoResponse = $this->evolutionService->getApiInformation();
            if (($infoResponse['success'] ?? false) === true && is_array($infoResponse['data'])) {
                $apiInfo = $infoResponse['data'];
            } else {
                $apiError = $infoResponse['error_detail'] ?? $infoResponse['error'] ?? null;
            }

            $instanceName = (string) ($settings['evolution_instance'] ?? '');
            if ($instanceName !== '') {
                $instanceResponse = $this->evolutionService->fetchInstances($instanceName);
                if (($instanceResponse['success'] ?? false) === true) {
                    $instanceInfo = $this->resolveInstanceInfo($instanceResponse['data'], $instanceName);
                    if ($instanceInfo === null) {
                        $instanceError = 'Instância não encontrada na Evolution API.';
                    }
                } else {
                    $instanceError = $instanceResponse['error_detail'] ?? $instanceResponse['error'] ?? null;
                }
            }
        }

        if (is_ajax()) {
            json_response([
                'settings' => $settings,
                'apiInfo' => $apiInfo,
                'instance' => $instanceInfo,
                'errors' => array_filter([
                    'api' => $apiError,
                    'instance' => $instanceError,
                ]),
            ]);
        }

        view('admin/webhook/index', [
            'settings' => $settings,
            'status' => get_flash('admin_status'),
            'error' => get_flash('admin_error'),
            'apiInfo' => $apiInfo,
            'apiError' => $apiError,
            'instanceInfo' => $instanceInfo,
            'instanceError' => $instanceError,
        ]);
    }

    public function update(): void
    {
        $admin = require_role('admin');
        $isAjax = is_ajax();

        $mode = $_POST['integration_mode'] ?? 'webhook';
        $data = [
            'integration_mode' => $mode,
            'webhook_url' => trim($_POST['webhook_url'] ?? ''),
            'webhook_token' => trim($_POST['webhook_token'] ?? ''),
            'evolution_api_url' => trim($_POST['evolution_api_url'] ?? ''),
            'evolution_instance' => trim($_POST['evolution_instance'] ?? ''),
            'evolution_api_key' => trim($_POST['evolution_api_key'] ?? ''),
            'evolution_token' => trim($_POST['evolution_token'] ?? ''),
            'evolution_default_template' => trim($_POST['evolution_default_template'] ?? ''),
        ];

        $errors = $this->validateSettings($mode, $data);
        if ($errors !== []) {
            if ($isAjax) {
                json_response(['errors' => $errors], 422);
            }
            set_flash('admin_error', implode(' ', $errors));
            redirect('/admin/webhook');
        }

        $this->settingService->updateIntegrationSettings((int) $admin->id, $data);

        if ($isAjax) {
            json_response([
                'message' => 'Configurações salvas com sucesso.',
                'settings' => $this->settingService->integrationSettings(),
            ]);
        }

        set_flash('admin_status', 'Configurações salvas com sucesso.');
        redirect('/admin/webhook');
    }

    public function test(): void
    {
        $admin = require_role('admin');
        $isAjax = is_ajax();

        $contact = trim($_POST['contact'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if ($contact === '' || $message === '') {
            $error = 'Informe o contato e a mensagem para testar.';
            if ($isAjax) {
                json_response(['errors' => [$error]], 422);
            }
            set_flash('admin_error', $error);
            redirect('/admin/webhook');
        }

        $success = $this->evolutionService->sendTestMessage($contact, $message, (int) $admin->id);

        if ($isAjax) {
            if ($success) {
                json_response(['message' => 'Mensagem de teste enviada.']);
            }
            json_response(['errors' => ['Não foi possível enviar a mensagem de teste.']], 422);
        }

        if ($success) {
            set_flash('admin_status', 'Mensagem de teste enviada com sucesso.');
        } else {
            set_flash('admin_error', 'Não foi possível enviar a mensagem de teste. Verifique as credenciais.');
        }

        redirect('/admin/webhook');
    }

    private function validateSettings(string $mode, array $data): array
    {
        $errors = [];
        if ($mode === 'webhook') {
            if (($data['webhook_url'] ?? '') === '' || ($data['webhook_token'] ?? '') === '') {
                $errors[] = 'Informe a URL e o token do webhook.';
            }
        } else {
            $hasCredentials = ($data['evolution_token'] ?? '') !== '' || ($data['evolution_api_key'] ?? '') !== '';
            if (($data['evolution_api_url'] ?? '') === '' || ($data['evolution_instance'] ?? '') === '' || !$hasCredentials) {
                $errors[] = 'Informe endpoint, instância e ao menos uma credencial (API Key ou token) da Evolution API.';
            }
        }

        return $errors;
    }

    private function resolveInstanceInfo(mixed $payload, string $instanceName): ?array
    {
        if (!is_array($payload)) {
            return null;
        }

        if (isset($payload['instances']) && is_array($payload['instances'])) {
            return $this->resolveInstanceInfo($payload['instances'], $instanceName);
        }

        if (isset($payload['data']) && is_array($payload['data'])) {
            return $this->resolveInstanceInfo($payload['data'], $instanceName);
        }

        if (!array_is_list($payload)) {
            return $this->normalizeInstanceEntry($payload, $instanceName);
        }

        foreach ($payload as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $normalized = $this->normalizeInstanceEntry($entry, $instanceName);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    private function normalizeInstanceEntry(array $entry, string $expectedName): ?array
    {
        if (isset($entry['instance']) && is_array($entry['instance'])) {
            $entry = array_merge($entry, $entry['instance']);
        }

        $name = (string) ($entry['instanceName'] ?? $entry['name'] ?? $entry['instance'] ?? '');
        if ($expectedName !== '' && !hash_equals(strtolower($expectedName), strtolower($name))) {
            return null;
        }

        return [
            'instanceName' => $name,
            'instanceId' => (string) ($entry['instanceId'] ?? ''),
            'status' => (string) ($entry['status'] ?? ''),
            'owner' => (string) ($entry['owner'] ?? ''),
            'profileName' => (string) ($entry['profileName'] ?? ''),
            'serverUrl' => (string) ($entry['serverUrl'] ?? ''),
            'integrationEngine' => is_array($entry['integration'] ?? null)
                ? (string) ($entry['integration']['engine'] ?? $entry['integration']['type'] ?? '')
                : '',
            'updatedAt' => (string) ($entry['updatedAt'] ?? ''),
        ];
    }
}
