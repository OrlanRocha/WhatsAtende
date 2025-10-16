<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\LoggerService;

class LogController
{
    public function __construct(private LoggerService $logger)
    {
    }

    public function index(): void
    {
        require_role('admin');

        $level = filter_input(INPUT_GET, 'level', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: null;
        $logs = $this->logger->latest(100, $level);

        if (is_ajax()) {
            json_response(['logs' => $logs]);
        }

        view('admin/logs/index', [
            'logs' => $logs,
            'level' => $level,
        ]);
    }
}
