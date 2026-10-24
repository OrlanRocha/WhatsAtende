<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\GroupService;
use App\Services\SettingService;
use Throwable;

class SettingController
{
    public function __construct(
        private SettingService $settings,
        private GroupService $groups
    ) {
    }

    public function index(): void
    {
        $user = require_role('admin', 'dev');

        $businessRules = [
            'default_tma_minutes' => $this->settings->get('default_tma_minutes', '15'),
            'default_group_id' => $this->settings->get('default_group_id'),
            'auto_assign_on_open' => $this->settings->get('auto_assign_on_open', '1'),
            'webhook_token' => $this->settings->get('webhook_token', ''),
        ];

        $groups = $this->groups->listGroups();

        view('admin/settings/index', [
            'rules' => $businessRules,
            'groups' => $groups,
            'pageTitle' => 'Regras de Negócio · WhatsAtende',
            'breadcrumbs' => [
                ['label' => 'Administração'],
                ['label' => 'Configurações']
            ]
        ]);
    }

    public function store(): void
    {
        $user = require_role('admin', 'dev');
        $userId = (int) $user->id;

        try {
            $tma = trim((string) ($_POST['default_tma_minutes'] ?? '15'));
            $defaultGroup = trim((string) ($_POST['default_group_id'] ?? ''));
            $autoAssign = isset($_POST['auto_assign_on_open']) ? '1' : '0';
            $webhookToken = trim((string) ($_POST['webhook_token'] ?? ''));

            $this->settings->set('default_tma_minutes', $tma, $userId, 'Tempo Médio de Atendimento padrão (minutos).');
            $this->settings->set('default_group_id', $defaultGroup !== '' ? $defaultGroup : null, $userId, 'Grupo inicial para novos chamados.');
            $this->settings->set('auto_assign_on_open', $autoAssign, $userId, 'Atribuir automaticamente ao abrir chat (1=sim, 0=não).');
            $this->settings->set('webhook_token', $webhookToken, $userId, 'Token utilizado para validar chamadas recebidas.');

            if (is_ajax()) {
                json_response(['message' => 'Configurações atualizadas com sucesso.']);
            }
            redirect('/admin/settings');
        } catch (Throwable $e) {
            if (is_ajax()) {
                json_response(['message' => 'Erro ao salvar configurações.', 'error' => $e->getMessage()], 500);
            }
            redirect('/admin/settings');
        }
    }
}
