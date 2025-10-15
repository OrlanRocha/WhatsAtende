<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\LoggerService;
use App\Services\TicketService;
use Throwable;

class TicketController
{
    public function __construct(
        private TicketService $ticketService,
        private LoggerService $logger
    ) {
    }

    public function index(): void
    {
        $queue = $this->ticketService->getOpenQueue();

        view('tickets/queue', [
            'queue' => $queue,
        ]);
    }

    public function show(int $ticketId): void
    {
        try {
            $ticket = $this->ticketService->getTicketWithMessages($ticketId);
        } catch (Throwable $exception) {
            $this->logger->error('ticket.show_failed', [
                'ticket_id' => $ticketId,
                'message' => $exception->getMessage(),
            ]);

            http_response_code(404);
            echo 'Ticket not found';
            return;
        }

        view('tickets/show', [
            'ticket' => $ticket,
        ]);
    }

    public function assign(int $ticketId): void
    {
        $userId = auth()->id();
        $this->ticketService->assignToUser($ticketId, $userId);

        redirect('/tickets/' . $ticketId);
    }

    public function storeMessage(int $ticketId): void
    {
        $body = trim($_POST['message'] ?? '');
        if ($body === '') {
            http_response_code(422);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Message body is required.']);
            return;
        }

        $userId = auth()->id();
        $this->ticketService->appendAgentMessage($ticketId, $userId, $body);

        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok']);
    }


    public function messages(int $ticketId): void
    {
        try {
            $ticket = $this->ticketService->getTicketWithMessages($ticketId);
        } catch (\Throwable $exception) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode([]);
            return;
        }

        header('Content-Type: application/json');
        echo json_encode($ticket['messages'] ?? []);
    }

    public function resolve(int $ticketId): void
    {
        $this->ticketService->resolveTicket($ticketId);
        redirect('/tickets');
    }
}
