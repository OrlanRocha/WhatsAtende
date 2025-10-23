<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\EvolutionService;
use App\Services\SettingService;

class WebhookController
{
    public function __construct(private SettingService $settingService, private EvolutionService $evolutionService)
    {
    }

    public function index(): void
    {
        require_role('dev');
        $settings = $this->settingService->integrationSettings();

        if (is_ajax()) {
            json_response(['settings' => $settings]);
        }

        view('admin/webhook/index', [
            'settings' => $settings,
            'status' => get_flash('admin_status'),
            'error' => get_flash('admin_error'),
        ]);
    }

    public function update(): void
    {
        $operator = require_role('dev');
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

        $this->settingService->updateIntegrationSettings((int) $operator->id, $data);

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
        $operator = require_role('dev');
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

        $success = $this->evolutionService->sendTestMessage($contact, $message, (int) $operator->id);

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
}
