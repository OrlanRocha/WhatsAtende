<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\IntegrationModel;
use App\Services\AuthService;
use App\Services\LoggerService;

use App\Services\EvolutionService;

class IntegrationController
{
    public function __construct(
        private IntegrationModel $model,
        private LoggerService $logger,
        private EvolutionService $evolution
    ) {
    }

    public function index(): void
    {
        require_permission('webhook.manage');
        
        $instances = $this->model->getAll();
        $pageTitle = 'Instâncias Evolution · WhatsAtende';
        
        view('admin/integrations/index', [
            'instances' => $instances,
            'pageTitle' => $pageTitle,
            'breadcrumbs' => [
                ['label' => 'Administração'],
                ['label' => 'Integrações']
            ],
            'status' => get_flash('admin_status'),
            'error' => get_flash('admin_error'),
        ]);
    }

    public function store(): void
    {
        $operator = require_permission('webhook.manage');

        $data = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'api_url' => trim((string) ($_POST['api_url'] ?? '')),
            'instance_name' => trim((string) ($_POST['instance_name'] ?? '')),
            'api_key' => trim((string) ($_POST['api_key'] ?? '')),
            'token' => trim((string) ($_POST['token'] ?? '')) ?: null,
            'default_template' => trim((string) ($_POST['default_template'] ?? '')) ?: null,
            'is_default' => isset($_POST['is_default']) ? 1 : 0,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'updated_by' => $operator->id,
        ];

        if ($data['name'] === '' || $data['api_url'] === '' || $data['instance_name'] === '') {
            set_flash('admin_error', 'Preencha os campos obrigatórios.');
            redirect('/admin/integrations');
        }

        if ($data['is_default']) {
            $this->model->setDefault(0); // This logic might need refinement if I wanted to set a new one
        }

        $success = $this->model->create($data);
        
        if ($success) {
            $this->logger->info('admin.integration_created', [
                'user_id' => $operator->id,
                'name' => $data['name'],
            ]);
            
            if (is_ajax()) {
                json_response(['success' => true, 'message' => 'Instância cadastrada com sucesso.']);
            }
            
            set_flash('admin_status', 'Instância cadastrada com sucesso.');
        } else {
            if (is_ajax()) {
                json_response(['success' => false, 'message' => 'Falha ao cadastrar instância.'], 500);
            }
            set_flash('admin_error', 'Falha ao cadastrar instância.');
        }

        redirect('/admin/integrations');
    }

    public function update(int $id): void
    {
        $operator = require_permission('webhook.manage');

        $instance = $this->model->find($id);
        if (!$instance) {
            set_flash('admin_error', 'Instância não encontrada.');
            redirect('/admin/integrations');
        }

        $data = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'api_url' => trim((string) ($_POST['api_url'] ?? '')),
            'instance_name' => trim((string) ($_POST['instance_name'] ?? '')),
            'api_key' => trim((string) ($_POST['api_key'] ?? '')),
            'token' => trim((string) ($_POST['token'] ?? '')) ?: null,
            'default_template' => trim((string) ($_POST['default_template'] ?? '')) ?: null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'updated_by' => $operator->id,
        ];

        $success = $this->model->update($id, $data);
        
        if ($success) {
            if (is_ajax()) {
                json_response(['success' => true, 'message' => 'Instância atualizada com sucesso.']);
            }
            set_flash('admin_status', 'Instância atualizada com sucesso.');
        } else {
            if (is_ajax()) {
                json_response(['success' => false, 'message' => 'Falha ao atualizar instância.'], 500);
            }
            set_flash('admin_error', 'Falha ao atualizar instância.');
        }

        redirect('/admin/integrations');
    }

    public function makeDefault(int $id): void
    {
        require_permission('webhook.manage');
        
        try {
            $this->model->setDefault($id);
            if (is_ajax()) {
                json_response(['success' => true, 'message' => 'Instância definida como padrão.']);
            }
            set_flash('admin_status', 'Instância definida como padrão.');
        } catch (\Throwable $e) {
            if (is_ajax()) {
                json_response(['success' => false, 'message' => 'Falha ao definir padrão.'], 500);
            }
            set_flash('admin_error', 'Falha ao definir instância como padrão.');
        }

        redirect('/admin/integrations');
    }

    public function destroy(int $id): void
    {
        require_permission('webhook.manage');
        
        $instance = $this->model->find($id);
        if ($instance && $instance['is_default']) {
            set_flash('admin_error', 'Não é possível remover a instância padrão.');
            redirect('/admin/integrations');
        }

        // Tenta remover na Evolution API primeiro
        $evoResponse = $this->evolution->instanceDelete($id);
        if (isset($evoResponse['success']) && !$evoResponse['success'] && ($evoResponse['status'] ?? 200) !== 404) {
             // Ignora erro 404 caso a instância já não exista lá
             $this->logger->warning('admin.integration_delete_evo_failed', ['id' => $id, 'response' => $evoResponse]);
        }

        if ($this->model->delete($id)) {
            if (is_ajax()) {
                json_response(['success' => true, 'message' => 'Instância removida.']);
            }
            set_flash('admin_status', 'Instância removida.');
        } else {
            if (is_ajax()) {
                json_response(['success' => false, 'message' => 'Falha ao remover do banco de dados.'], 500);
            }
            set_flash('admin_error', 'Falha ao remover instância.');
        }

        redirect('/admin/integrations');
    }

    public function connect(int $id): void
    {
        require_permission('webhook.manage');
        $response = $this->evolution->instanceConnect($id);
        json_response($response);
    }

    public function restart(int $id): void
    {
        require_permission('webhook.manage');
        $response = $this->evolution->instanceRestart($id);
        json_response($response);
    }

    public function logout(int $id): void
    {
        require_permission('webhook.manage');
        $response = $this->evolution->instanceLogout($id);
        json_response($response);
    }

    public function status(int $id): void
    {
        require_permission('webhook.manage');
        $response = $this->evolution->connectionState($id);
        json_response($response);
    }

    public function sync(int $id): void
    {
        require_permission('webhook.manage');
        
        $instance = $this->model->find($id);
        if (!$instance) {
            json_response(['success' => false, 'message' => 'Instância não encontrada.'], 404);
        }

        // Deactivated webhook sync as per user request to use "Normal API" only
        // $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
        // $webhookUrl = $baseUrl . route_path('/api/webhook');
        // $response = $this->evolution->setWebhook($id, $webhookUrl);
        
        // Instead, we verify the connection state
        $response = $this->evolution->connectionState($id);
        
        if ($response['success']) {
            $this->logger->info('admin.integration_health_check', ['id' => $id, 'status' => $response['data']['instance']['state'] ?? 'unknown']);
            json_response([
                'success' => true, 
                'message' => 'Conexão com API validada com sucesso (Modo: API Direta).',
                'data' => $response['data']
            ]);
        } else {
            json_response($response);
        }
    }
}
