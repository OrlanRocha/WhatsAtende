<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\DashboardService;
use App\Services\TicketService;

class DashboardController
{
    public function __construct(
        private DashboardService $dashboardService,
        private TicketService $ticketService
    ) {
    }

    public function index(): void
    {
        require_role('admin');

        $summary = $this->dashboardService->summary();
       $recentTickets = $this->dashboardService->recentTickets();
       $channels = $this->dashboardService->channelBreakdown();
       $leaderboard = $this->dashboardService->agentLeaderboard();
       $queue = $this->ticketService->getOpenQueue();

        if (is_ajax()) {
            json_response([
                'summary' => $summary,
                'recentTickets' => $recentTickets,
                'channels' => $channels,
                'leaderboard' => $leaderboard,
                'queue' => $queue,
            ]);
        }

        view('admin/dashboard', [
            'summary' => $summary,
            'recentTickets' => $recentTickets,
            'channels' => $channels,
            'leaderboard' => $leaderboard,
            'queue' => $queue,
        ]);
    }
}
