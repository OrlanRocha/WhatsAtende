<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\TicketService;

class TicketController
{
    public function __construct(private TicketService $ticketService)
    {
    }

    public function index(): void
    {
        require_role('admin');

        $status = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: null;
        $tickets = $this->ticketService->listTickets($status);

        view('admin/tickets/index', [
            'tickets' => $tickets,
            'statusFilter' => $status,
        ]);
    }
}
