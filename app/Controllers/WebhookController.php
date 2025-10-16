<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\SettingService;
use App\Services\WebhookService;
use Throwable;

class WebhookController
{
    public function __construct(
        private WebhookService $webhookService,
        private SettingService $settingService
    ) {
    }

    public function handle(): void
    {
        header('Content-Type: application/json');

        $expectedToken = $this->settingService->get('webhook_token');
        if ($expectedToken) {
            $providedToken = $_SERVER['HTTP_X_WEBHOOK_TOKEN'] ?? ($_GET['token'] ?? '');
            if ($providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
                http_response_code(401);
                echo json_encode(['error' => 'Token inválido.']);
                return;
            }
        }

        $payload = json_decode(file_get_contents('php://input') ?: '[]', true);

        if (!is_array($payload)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid payload.']);
            return;
        }

        try {
            $ticketId = $this->webhookService->processIncomingMessage($payload);
        } catch (Throwable $exception) {
            http_response_code(500);
            echo json_encode(['error' => 'Unable to process webhook.']);
            return;
        }

        echo json_encode(['status' => 'processed', 'ticket_id' => $ticketId]);
    }
}
