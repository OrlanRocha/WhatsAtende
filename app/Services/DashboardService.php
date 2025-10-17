<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

class DashboardService
{
    public function __construct(private PDO $connection)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $summary = [
            'open' => 0,
            'assigned' => 0,
            'resolved_today' => 0,
            'average_resolution_minutes' => null,
        ];

        $stmt = $this->connection->query(
            "SELECT status, COUNT(*) AS total FROM tickets GROUP BY status"
        );
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $summary[$row['status']] = (int) $row['total'];
        }

        $summary['open'] = $summary['open'] ?? 0;
        $summary['assigned'] = $summary['assigned'] ?? 0;

        $stmtResolved = $this->connection->query(
            "SELECT COUNT(*) FROM tickets WHERE status IN ('resolved','closed') AND DATE(closed_at) = CURRENT_DATE()"
        );
        $summary['resolved_today'] = (int) $stmtResolved->fetchColumn();

        $stmtAverage = $this->connection->query(
            'SELECT AVG(resolution_time_seconds) FROM ticket_metrics WHERE resolution_time_seconds IS NOT NULL'
        );
        $avg = $stmtAverage->fetchColumn();
        $summary['average_resolution_minutes'] = $avg ? round(((int) $avg) / 60, 1) : null;

        return $summary;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recentTickets(int $limit = 6): array
    {
        $stmt = $this->connection->prepare(
            'SELECT t.id, c.display_name AS contact_name, t.status, t.channel, t.opened_at, t.closed_at '
            . 'FROM tickets t INNER JOIN contacts c ON c.id = t.contact_id '
            . 'ORDER BY t.opened_at DESC LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function channelBreakdown(): array
    {
        $stmt = $this->connection->query(
            'SELECT channel, COUNT(*) AS total FROM tickets GROUP BY channel ORDER BY total DESC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function agentLeaderboard(int $limit = 5): array
    {
        $stmt = $this->connection->prepare(
            'SELECT u.full_name, COUNT(t.id) AS resolved FROM tickets t '
            . 'INNER JOIN users u ON u.id = t.assigned_user_id '
            . "WHERE t.status IN ('resolved','closed') AND t.assigned_user_id IS NOT NULL "
            . 'GROUP BY u.id, u.full_name ORDER BY resolved DESC LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
