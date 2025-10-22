<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Services\LoggerService;
use DateTimeImmutable;

class LogStreamController
{
    public function __construct(private LoggerService $logger)
    {
    }

    public function live(): void
    {
        require_role('admin', 'dev');

        ignore_user_abort(true);
        set_time_limit(0);

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache, no-transform');
        header('Connection: keep-alive');

        $level = filter_input(INPUT_GET, 'level', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: null;
        $service = filter_input(INPUT_GET, 'service', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: null;
        $userId = filter_input(INPUT_GET, 'user', FILTER_VALIDATE_INT);
        $search = filter_input(INPUT_GET, 'q', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: null;
        $from = filter_input(INPUT_GET, 'from', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: null;
        $to = filter_input(INPUT_GET, 'to', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: null;
        $lastId = filter_input(INPUT_GET, 'last_id', FILTER_VALIDATE_INT);
        if ($lastId === false || $lastId === null) {
            $lastId = filter_input(INPUT_GET, 'since', FILTER_VALIDATE_INT);
        }

        $headerLastId = null;
        if (isset($_SERVER['HTTP_LAST_EVENT_ID'])) {
            $headerLastId = filter_var($_SERVER['HTTP_LAST_EVENT_ID'], FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 0],
            ]);
        }

        if ($lastId === false || $lastId === null) {
            $lastId = $headerLastId !== false && $headerLastId !== null ? $headerLastId : 0;
        } elseif ($headerLastId !== false && $headerLastId !== null) {
            $lastId = max($lastId, $headerLastId);
        }

        $lastId ??= 0;

        $filters = array_filter([
            'service' => $service,
            'user' => $userId !== false ? $userId : null,
            'search' => $search,
            'from' => $from,
            'to' => $to,
        ], static fn ($value) => $value !== null && $value !== '' && $value !== 0);

        $startedAt = time();
        $lastMetrics = 0;
        $keepAliveInterval = 10;
        $metricsInterval = 30;

        $this->emitRetry(5000);

        while (!connection_aborted()) {
            $logs = $this->logger->streamSince($lastId ?? 0, $level, $filters, 100);
            if ($logs !== []) {
                $normalized = $this->normalizeLogs($logs);
                $lastId = max($lastId ?? 0, $normalized['last_id']);
                $this->emit('log', [
                    'logs' => $normalized['logs'],
                    'last_id' => $lastId,
                ], $lastId);
            }

            $now = time();
            if (($now - $lastMetrics) >= $metricsInterval) {
                $aggregates = $this->logger->aggregates($level, $filters);
                $this->emit('aggregates', $aggregates);
                $lastMetrics = $now;
            }

            if (($now - $startedAt) >= 300) {
                break;
            }

            $this->emitComment('ping');
            sleep($keepAliveInterval);
        }

        $this->emit('close', ['last_id' => $lastId ?? 0]);
    }

    /**
     * @param array<int, array<string, mixed>> $logs
     * @return array{logs:array<int, array<string, mixed>>, last_id:int}
     */
    private function normalizeLogs(array $logs): array
    {
        $lastId = 0;
        $normalized = array_map(static function (array $log) use (&$lastId): array {
            $lastId = max($lastId, (int) ($log['id'] ?? 0));
            $raw = $log['context'] ?? null;
            $decoded = null;
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $decoded = $raw;
                }
            }

            $log['context'] = $decoded;
            $log['corr_id'] = (string) ($log['corr_id'] ?? '');
            $log['route'] = (string) ($log['route'] ?? '');
            $log['service'] = (string) ($log['service'] ?? '');
            $log['actor_id'] = isset($log['actor_id']) ? (int) $log['actor_id'] : null;
            $log['created_at'] = (string) ($log['created_at'] ?? (new DateTimeImmutable())->format('Y-m-d H:i:s'));

            unset($log['context_raw']);

            return $log;
        }, $logs);

        return [
            'logs' => $normalized,
            'last_id' => $lastId,
        ];
    }

    private function emit(string $event, array $data, ?int $id = null): void
    {
        if ($id !== null) {
            echo 'id: ' . $id . "\n";
        }
        echo 'event: ' . $event . "\n";
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            $encoded = json_encode(['error' => 'unable_to_encode'], JSON_UNESCAPED_UNICODE);
        }
        echo 'data: ' . $encoded . "\n\n";
        $this->flush();
    }

    private function emitComment(string $message): void
    {
        echo ': ' . $message . "\n\n";
        $this->flush();
    }

    private function emitRetry(int $milliseconds): void
    {
        $delay = max(0, $milliseconds);
        echo 'retry: ' . $delay . "\n\n";
        $this->flush();
    }

    private function flush(): void
    {
        if (function_exists('fastcgi_finish_request')) {
            @ob_flush();
            flush();
            return;
        }

        while (ob_get_level() > 0) {
            $status = ob_get_status(true);
            $flags = $status['flags'] ?? 0;
            if (($flags & PHP_OUTPUT_HANDLER_FLUSHABLE) === 0) {
                break;
            }
            ob_end_flush();
        }
        flush();
    }
}
