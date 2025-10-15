<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\WebhookService;
use Throwable;

class WebhookController
{
    public function __construct(private WebhookService $webhookService)
    {
    }

    public function handle(): void
    {
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
