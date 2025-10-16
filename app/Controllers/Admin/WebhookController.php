<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\SettingService;

class WebhookController
{
    public function __construct(private SettingService $settingService)
    {
    }

    public function index(): void
    {
        require_role('admin');

        view('admin/webhook/index', [
            'settings' => $this->settingService->webhookSettings(),
            'status' => get_flash('admin_status'),
            'error' => get_flash('admin_error'),
        ]);
    }

    public function update(): void
    {
        $admin = require_role('admin');

        $url = trim($_POST['webhook_url'] ?? '');
        $token = trim($_POST['webhook_token'] ?? '');

        if ($url === '' || $token === '') {
            set_flash('admin_error', 'Informe a URL e o token do webhook.');
            redirect('/admin/webhook');
        }

        $this->settingService->updateWebhookSettings((int) $admin->id, $url, $token);
        set_flash('admin_status', 'Configurações salvas com sucesso.');
        redirect('/admin/webhook');
    }
}
