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
        require_role('dev');

        $level = filter_input(INPUT_GET, 'level', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: null;
        $service = filter_input(INPUT_GET, 'service', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: null;
        $search = filter_input(INPUT_GET, 'q', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: null;
        $userId = filter_input(INPUT_GET, 'user', FILTER_SANITIZE_NUMBER_INT);
        $from = filter_input(INPUT_GET, 'from', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: null;
        $to = filter_input(INPUT_GET, 'to', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: null;

        $filters = array_filter([
            'service' => $service,
            'user' => $userId !== null && $userId !== false && $userId !== '' ? (int) $userId : null,
            'search' => $search,
            'from' => $from,
            'to' => $to,
        ], static fn ($value) => $value !== null && $value !== '' && $value !== 0);

        $logs = $this->logger->latest(150, $level, $filters);
        $normalizedLogs = array_map(static function (array $log): array {
            $raw = $log['context'] ?? null;
            $decoded = null;
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $decoded = $raw;
                }
            }
            $log['context_raw'] = $raw;
            $log['context'] = $decoded;
            $log['corr_id'] = (string) ($log['corr_id'] ?? '');
            $log['route'] = (string) ($log['route'] ?? '');
            $log['service'] = (string) ($log['service'] ?? '');
            $log['actor_id'] = isset($log['actor_id']) ? (int) $log['actor_id'] : null;
            return $log;
        }, $logs);
        $aggregates = $this->logger->aggregates($level, $filters);
        $lastId = 0;
        foreach ($logs as $logRow) {
            $lastId = max($lastId, (int) ($logRow['id'] ?? 0));
        }

        if (is_ajax()) {
            json_response([
                'logs' => $normalizedLogs,
                'aggregates' => $aggregates,
                'last_id' => $lastId,
            ]);
        }

        view('admin/logs/index', [
            'logs' => $normalizedLogs,
            'level' => $level,
            'filters' => [
                'service' => $service,
                'user' => $filters['user'] ?? null,
                'search' => $search,
                'from' => $from,
                'to' => $to,
            ],
            'aggregates' => $aggregates,
        ]);
    }
}
