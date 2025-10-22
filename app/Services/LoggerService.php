<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use DateTimeInterface;
use PDO;
use PDOStatement;

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
        $stmt = $this->connection->prepare(
            'INSERT INTO logs (user_id, level, action, message, context, ip_address)
             VALUES (:user_id, :level, :action, :message, :context, :ip_address)'
        );

        $options = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $options |= JSON_INVALID_UTF8_SUBSTITUTE;
        }

        $encodedContext = json_encode($context, $options);

        if ($encodedContext === false) {
            $encodedContext = json_encode([
                'encoding_error' => true,
                'original' => array_keys($context),
            ], $options);
        }

        $stmt->execute([
            'user_id' => $context['user_id'] ?? null,
            'level' => $level,
            'action' => $action,
            'message' => $context['message'] ?? '',
            'context' => $encodedContext,
            'ip_address' => $context['ip_address'] ?? null,
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
            . 'LEFT JOIN users u ON u.id = l.user_id'
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

        $serviceSql = 'SELECT COALESCE(NULLIF(SUBSTRING_INDEX(l.action, ".", 1), ""), "sistema") AS label,'
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
            $conditions[] = 'l.action LIKE :service';
            $params['service'] = $service . '.%';
        }

        if (!empty($filters['user']) && is_numeric($filters['user'])) {
            $conditions[] = 'l.user_id = :user_id';
            $params['user_id'] = (int) $filters['user'];
        }

        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
        if ($search !== '') {
            $conditions[] = '(l.action LIKE :search OR l.message LIKE :search OR l.context LIKE :search)';
            $params['search'] = '%' . $search . '%';
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
     * @param array<string, mixed> $params
     */
    private function bindFilterParams(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $param = ':' . $key;
            if ($key === 'user_id') {
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
