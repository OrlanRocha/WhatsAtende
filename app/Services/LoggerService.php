<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use DateTimeInterface;
use PDO;
use PDOException;
use PDOStatement;
use Throwable;

use function app_logger;

class LoggerService
{
    public function __construct(private PDO $connection)
    {
    }

    public function info(string $action, array $context = []): void
    {
        $this->write('info', $action, $context);
    }

    public function warning(string $action, array $context = []): void
    {
        $this->write('warning', $action, $context);
    }

    public function error(string $action, array $context = []): void
    {
        $this->write('error', $action, $context);
    }

    private function write(string $level, string $action, array $context): void
    {
        $actorId = $context['actor_id'] ?? $context['user_id'] ?? null;
        if ($actorId === null && function_exists('auth')) {
            $actor = auth();
            if ($actor !== null && isset($actor->id)) {
                $actorId = (int) $actor->id;
            }
        }

        $ip = $context['ip_address'] ?? $context['ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? null);
        $route = $context['route'] ?? (function_exists('current_route_path') ? current_route_path() : ($_SERVER['REQUEST_URI'] ?? '/'));
        $message = (string) ($context['message'] ?? $action);
        $service = $context['service'] ?? (str_contains($action, '.') ? explode('.', $action, 2)[0] : $action);
        $corrId = $context['corr_id'] ?? null;
        if ($corrId === null) {
            if (function_exists('request_correlation_id')) {
                $corrId = request_correlation_id();
            } else {
                $corrId = $this->generateCorrelationId();
            }
        }

        $extraContext = $context;
        unset(
            $extraContext['actor_id'],
            $extraContext['user_id'],
            $extraContext['ip_address'],
            $extraContext['ip'],
            $extraContext['route'],
            $extraContext['service'],
            $extraContext['corr_id'],
            $extraContext['message']
        );

        $options = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $options |= JSON_INVALID_UTF8_SUBSTITUTE;
        }

        $encodedContext = json_encode($extraContext, $options);

        if ($encodedContext === false) {
            $encodedContext = json_encode([
                'encoding_error' => true,
                'original' => array_keys($extraContext),
            ], $options);
        }

        $sql = 'INSERT INTO logs (actor_id, level, action, message, context, ip_address, route, service, corr_id)'
            . ' VALUES (:actor_id, :level, :action, :message, :context, :ip_address, :route, :service, :corr_id)';

        $params = [
            'actor_id' => $actorId,
            'level' => $level,
            'action' => $action,
            'message' => $message,
            'context' => $encodedContext,
            'ip_address' => $ip,
            'route' => $route,
            'service' => $service,
            'corr_id' => $corrId,
        ];

        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);

            return;
        } catch (PDOException $exception) {
            if ($this->isDuplicateCorrelationException($exception)) {
                $params['corr_id'] = $this->generateCorrelationId($corrId);

                try {
                    $stmt = $this->connection->prepare($sql);
                    $stmt->execute($params);

                    return;
                } catch (PDOException $retryException) {
                    $this->reportFailure($retryException, $action, $params['corr_id']);

                    return;
                }
            }

            $this->reportFailure($exception, $action, $corrId);
        }
    }

    private function generateCorrelationId(?string $seed = null): string
    {
        $prefix = $seed !== null ? trim(substr($seed, 0, 40)) : '';

        try {
            $random = bin2hex(random_bytes(4));
        } catch (Throwable) {
            $random = str_replace('.', '', uniqid('', true));
        }

        return $prefix !== '' ? $prefix . '-' . $random : $random;
    }

    private function isDuplicateCorrelationException(PDOException $exception): bool
    {
        $errorInfo = $exception->errorInfo;
        if (is_array($errorInfo) && isset($errorInfo[0], $errorInfo[1]) && (int) $errorInfo[1] === 1062) {
            return true;
        }

        $message = strtolower($exception->getMessage());

        return str_contains($message, 'duplicate entry') && str_contains($message, 'idx_logs_corr');
    }

    private function reportFailure(PDOException $exception, string $action, string $corrId): void
    {
        if (!function_exists('app_logger')) {
            return;
        }

        app_logger()->error('logger.database.write_failed', [
            'action' => $action,
            'corr_id' => $corrId,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function latest(int $limit = 50, ?string $level = null, array $filters = []): array
    {
        [$where, $params] = $this->buildFilterClause($level, $filters);

        $sql = 'SELECT l.*, u.full_name AS user_name FROM logs l '
            . 'LEFT JOIN users u ON u.id = l.actor_id'
            . $where
            . ' ORDER BY l.created_at DESC LIMIT :limit';

        $stmt = $this->connection->prepare($sql);
        $this->bindFilterParams($stmt, $params);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{
     *     levels:array<int, array{label:string,total:int}>,
     *     services:array<int, array{label:string,total:int}>,
     *     actions:array<int, array{label:string,total:int}>,
     *     timeline:array<int, array{bucket:string,total:int}>
     * }
     */
    public function aggregates(?string $level = null, array $filters = []): array
    {
        [$where, $params] = $this->buildFilterClause($level, $filters);

        $levelsSql = 'SELECT l.level AS label, COUNT(*) AS total FROM logs l'
            . $where
            . ' GROUP BY l.level ORDER BY total DESC';
        $levelsStmt = $this->connection->prepare($levelsSql);
        $this->bindFilterParams($levelsStmt, $params);
        $levelsStmt->execute();
        $levels = array_map(static function (array $row): array {
            return [
                'label' => (string) ($row['label'] ?? ''),
                'total' => (int) ($row['total'] ?? 0),
            ];
        }, $levelsStmt->fetchAll(PDO::FETCH_ASSOC));

        $serviceSql = 'SELECT COALESCE(NULLIF(l.service, ""), "sistema") AS label,'
            . ' COUNT(*) AS total FROM logs l'
            . $where
            . ' GROUP BY label ORDER BY total DESC LIMIT 6';
        $serviceStmt = $this->connection->prepare($serviceSql);
        $this->bindFilterParams($serviceStmt, $params);
        $serviceStmt->execute();
        $services = array_map(static function (array $row): array {
            return [
                'label' => (string) ($row['label'] ?? ''),
                'total' => (int) ($row['total'] ?? 0),
            ];
        }, $serviceStmt->fetchAll(PDO::FETCH_ASSOC));

        $actionSql = 'SELECT l.action AS label, COUNT(*) AS total FROM logs l'
            . $where
            . ' GROUP BY l.action ORDER BY total DESC LIMIT 5';
        $actionStmt = $this->connection->prepare($actionSql);
        $this->bindFilterParams($actionStmt, $params);
        $actionStmt->execute();
        $actions = array_map(static function (array $row): array {
            return [
                'label' => (string) ($row['label'] ?? ''),
                'total' => (int) ($row['total'] ?? 0),
            ];
        }, $actionStmt->fetchAll(PDO::FETCH_ASSOC));

        $timelineSql = 'SELECT DATE_FORMAT(l.created_at, "%Y-%m-%d %H:00:00") AS bucket, COUNT(*) AS total FROM logs l'
            . $where
            . ' GROUP BY bucket ORDER BY bucket DESC LIMIT 24';
        $timelineStmt = $this->connection->prepare($timelineSql);
        $this->bindFilterParams($timelineStmt, $params);
        $timelineStmt->execute();
        $timeline = array_map(static function (array $row): array {
            return [
                'bucket' => (string) ($row['bucket'] ?? ''),
                'total' => (int) ($row['total'] ?? 0),
            ];
        }, array_reverse($timelineStmt->fetchAll(PDO::FETCH_ASSOC)));

        return [
            'levels' => $levels,
            'services' => $services,
            'actions' => $actions,
            'timeline' => $timeline,
        ];
    }

    /**
     * @return array{0:string,1:array<string, mixed>}
     */
    private function buildFilterClause(?string $level, array $filters): array
    {
        $conditions = [];
        $params = [];

        if ($level !== null && $level !== '') {
            $conditions[] = 'l.level = :level';
            $params['level'] = $level;
        }

        $service = isset($filters['service']) ? trim((string) $filters['service']) : '';
        if ($service !== '') {
            $conditions[] = 'l.service = :service';
            $params['service'] = $service;
        }

        if (!empty($filters['user']) && is_numeric($filters['user'])) {
            $conditions[] = 'l.actor_id = :user_id';
            $params['user_id'] = (int) $filters['user'];
        }

        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
        if ($search !== '') {
            $conditions[] = '(
                l.action LIKE :search
                OR l.message LIKE :search
                OR l.context LIKE :search
                OR l.corr_id LIKE :search_exact
            )';
            $params['search'] = '%' . $search . '%';
            $params['search_exact'] = $search . '%';
        }

        if (!empty($filters['from'])) {
            $conditions[] = 'l.created_at >= :from';
            $params['from'] = $this->normalizeDate($filters['from']);
        }

        if (!empty($filters['to'])) {
            $conditions[] = 'l.created_at <= :to';
            $params['to'] = $this->normalizeDate($filters['to']);
        }

        $where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

        return [$where, $params];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function streamSince(int $lastId, ?string $level = null, array $filters = [], int $limit = 50): array
    {
        [$where, $params] = $this->buildFilterClause($level, $filters);

        $conditions = [];
        if ($where !== '') {
            $conditions[] = substr($where, 7);
        }

        if ($lastId > 0) {
            $conditions[] = 'l.id > :last_id';
            $params['last_id'] = $lastId;
        }

        $finalWhere = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);

        $sql = 'SELECT l.*, u.full_name AS user_name FROM logs l ' .
            'LEFT JOIN users u ON u.id = l.actor_id' .
            $finalWhere .
            ' ORDER BY l.id ASC LIMIT :limit';

        $stmt = $this->connection->prepare($sql);
        $this->bindFilterParams($stmt, $params);
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function bindFilterParams(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $param = ':' . $key;
            if ($key === 'user_id' || $key === 'last_id') {
                $stmt->bindValue($param, (int) $value, PDO::PARAM_INT);
                continue;
            }

            $stmt->bindValue($param, $value);
        }
    }

    private function normalizeDate(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return (new DateTimeImmutable((string) $value))->format('Y-m-d H:i:s');
    }
}
