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
        require_role('admin', 'dev');

        $status = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: null;
        $tickets = $this->ticketService->listTickets($status);

        if (is_ajax()) {
            json_response(['tickets' => $tickets]);
        }

        view('admin/tickets/index', [
            'tickets' => $tickets,
            'statusFilter' => $status,
        ]);
    }
}
