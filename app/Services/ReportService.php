<?php

declare(strict_types=1);

namespace App\Services;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use PDO;

class ReportService
{
    public function __construct(private PDO $connection)
    {
    }

    /**
     * @param array<int, int> $groupIds
     *
     * @return array<int, array<string, mixed>>
     */
    public function attendanceSummary(DateTimeImmutable $from, DateTimeImmutable $to, array $groupIds = []): array
    {
        if ($to < $from) {
            [$from, $to] = [$to, $from];
        }

        $params = [
            'from' => $from->setTime(0, 0, 0)->format('Y-m-d H:i:s'),
            'to' => $to->setTime(23, 59, 59)->format('Y-m-d H:i:s'),
        ];

        $filters = '';
        $groupParams = [];
        foreach (array_values($groupIds) as $index => $groupId) {
            $value = (int) $groupId;
            if ($value <= 0) {
                continue;
            }

            $key = 'group_' . $index;
            $groupParams[$key] = $value;
        }

        if ($groupParams !== []) {
            $placeholders = implode(', ', array_map(static fn (string $key): string => ':' . $key, array_keys($groupParams)));
            $filters .= " AND t.group_id IN ({$placeholders})";
            $params += $groupParams;
        }

        $sql = 'SELECT DATE(t.opened_at) AS bucket,'
            . ' COUNT(*) AS total_opened,'
            . " SUM(CASE WHEN t.status IN ('resolved','closed') THEN 1 ELSE 0 END) AS total_closed,"
            . ' AVG(tm.queue_time_sec) AS avg_queue_time,'
            . ' AVG(tm.resolution_time_sec) AS avg_resolution_time'
            . ' FROM tickets t'
            . ' LEFT JOIN ticket_metrics tm ON tm.ticket_id = t.id'
            . ' WHERE t.opened_at BETWEEN :from AND :to'
            . $filters
            . ' GROUP BY bucket'
            . ' ORDER BY bucket ASC';

        $statement = $this->connection->prepare($sql);
        $statement->execute($params);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $data = [];
        foreach ($rows as $row) {
            $bucket = (string) ($row['bucket'] ?? '');
            if ($bucket === '') {
                continue;
            }
            $data[$bucket] = [
                'date' => $bucket,
                'opened' => (int) ($row['total_opened'] ?? 0),
                'closed' => (int) ($row['total_closed'] ?? 0),
                'avg_queue_seconds' => $row['avg_queue_time'] !== null ? (float) $row['avg_queue_time'] : null,
                'avg_resolution_seconds' => $row['avg_resolution_time'] !== null ? (float) $row['avg_resolution_time'] : null,
            ];
        }

        // Ensure the range is continuous even if there are gaps
        $period = new DatePeriod(
            $from->setTime(0, 0),
            new DateInterval('P1D'),
            $to->setTime(0, 0)->add(new DateInterval('P1D'))
        );

        $result = [];
        foreach ($period as $day) {
            $index = $day->format('Y-m-d');
            $result[] = $data[$index] ?? [
                'date' => $index,
                'opened' => 0,
                'closed' => 0,
                'avg_queue_seconds' => null,
                'avg_resolution_seconds' => null,
            ];
        }

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    public function summaryToCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'w+');
        fputcsv($handle, ['Data', 'Abertos', 'Encerrados', 'Fila média (s)', 'Resolução média (s)']);

        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['date'] ?? '',
                (int) ($row['opened'] ?? 0),
                (int) ($row['closed'] ?? 0),
                $row['avg_queue_seconds'] !== null ? round((float) $row['avg_queue_seconds'], 2) : '',
                $row['avg_resolution_seconds'] !== null ? round((float) $row['avg_resolution_seconds'], 2) : '',
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $csv;
    }
}
