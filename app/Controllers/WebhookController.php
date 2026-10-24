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

        $payload = json_decode(file_get_contents('php://input') ?: '[]', true);

        // Debug logging for Evolution v2 migration
        file_put_contents(
            dirname(__DIR__, 2) . '/storage/logs/webhook_debug.log', 
            '[' . date('Y-m-d H:i:s') . '] Request: ' . $_SERVER['REQUEST_URI'] . ' - Payload: ' . json_encode($payload, JSON_UNESCAPED_UNICODE) . PHP_EOL, 
            FILE_APPEND
        );

        $expectedToken = $this->settingService->get('webhook_token');
        if ($expectedToken) {
            $providedToken = $_SERVER['HTTP_X_WEBHOOK_TOKEN'] ?? ($_GET['token'] ?? '');
            if ($providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
                file_put_contents(dirname(__DIR__, 2) . '/storage/logs/webhook_debug.log', '[' . date('Y-m-d H:i:s') . '] 401 Unauthorized' . PHP_EOL, FILE_APPEND);
                http_response_code(401);
                echo json_encode(['error' => 'Token inválido.']);
                return;
            }
        }
        
        file_put_contents(dirname(__DIR__, 2) . '/storage/logs/webhook_debug.log', '[' . date('Y-m-d H:i:s') . '] Processing message...' . PHP_EOL, FILE_APPEND);

        try {
            $ticketId = $this->webhookService->processIncomingMessage($payload);
            file_put_contents(dirname(__DIR__, 2) . '/storage/logs/webhook_debug.log', '[' . date('Y-m-d H:i:s') . '] Processed successfully, Ticket ID: ' . $ticketId . PHP_EOL, FILE_APPEND);
        } catch (Throwable $exception) {
            file_put_contents(
                dirname(__DIR__, 2) . '/storage/logs/webhook_debug.log', 
                '[' . date('Y-m-d H:i:s') . '] Exception: ' . $exception->getMessage() . PHP_EOL . $exception->getTraceAsString() . PHP_EOL, 
                FILE_APPEND
            );
            http_response_code(500);
            echo json_encode(['error' => 'Unable to process webhook.']);
            return;
        }

        echo json_encode(['status' => 'processed', 'ticket_id' => $ticketId]);
    }
}
