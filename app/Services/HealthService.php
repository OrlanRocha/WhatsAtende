<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use PDO;
use Throwable;

class HealthService
{
    public function __construct(
        private EvolutionService $evolution,
        private PDO $connection
    ) {
    }

    /**
     * @return array{
     *     overall:string,
     *     checked_at:string,
     *     timezone:string,
     *     services:array<string, array{status:string,label:string,detail:string,meta:array<string, mixed>}>}
     */
    public function snapshot(): array
    {
        $timezone = new DateTimeZone('America/Sao_Paulo');
        $now = new DateTimeImmutable('now', $timezone);

        $services = [
            'evolution' => $this->checkEvolution(),
            'jobs' => $this->checkJobs(),
            'webhook' => $this->checkWebhook(),
        ];

        $statusOrder = ['down' => 3, 'degraded' => 2, 'ok' => 1];
        $overall = 'ok';
        foreach ($services as $service) {
            $weight = $statusOrder[$service['status']] ?? 1;
            if ($weight > ($statusOrder[$overall] ?? 1)) {
                $overall = $service['status'];
            }
        }

        return [
            'overall' => $overall,
            'checked_at' => $now->format(DateTimeInterface::ATOM),
            'timezone' => $timezone->getName(),
            'services' => $services,
        ];
    }

    /**
     * @return array{status:string,label:string,detail:string,meta:array<string, mixed>}
     */
    private function checkEvolution(): array
    {
        if (!$this->evolution->isNativeEnabled()) {
            return [
                'status' => 'degraded',
                'label' => 'Evolution API',
                'detail' => 'Integração desativada.',
                'meta' => ['enabled' => false, 'unread' => 0, 'chats' => 0],
            ];
        }

        try {
            $overview = $this->evolution->fetchChatsOverview();
        } catch (Throwable $exception) {
            return [
                'status' => 'down',
                'label' => 'Evolution API',
                'detail' => 'Falha ao consultar Evolution: ' . $exception->getMessage(),
                'meta' => ['enabled' => true],
            ];
        }

        if (!($overview['success'] ?? false)) {
            return [
                'status' => 'down',
                'label' => 'Evolution API',
                'detail' => (string) ($overview['error'] ?? 'Erro desconhecido ao consultar Evolution.'),
                'meta' => ['enabled' => true],
            ];
        }

        $chats = $overview['chats'] ?? [];
        $totalChats = is_array($chats) ? count($chats) : 0;
        $unread = 0;

        if (is_array($chats)) {
            foreach ($chats as $chat) {
                if (!is_array($chat)) {
                    continue;
                }
                $unread += (int) ($chat['unread'] ?? 0);
            }
        }

        return [
            'status' => 'ok',
            'label' => 'Evolution API',
            'detail' => $totalChats > 0
                ? sprintf('%d chats monitorados, %d pendentes.', $totalChats, $unread)
                : 'Nenhuma conversa pendente.',
            'meta' => [
                'enabled' => true,
                'unread' => $unread,
                'chats' => $totalChats,
            ],
        ];
    }

    /**
     * @return array{status:string,label:string,detail:string,meta:array<string, mixed>}
     */
    private function checkJobs(): array
    {
        $since = (new DateTimeImmutable('-15 minutes'));

        $stmt = $this->connection->prepare(
            "SELECT COUNT(*) FROM logs WHERE service = :service AND level IN ('error','critical') AND created_at >= :since"
        );
        $stmt->execute([
            'service' => 'queue',
            'since' => $since->format('Y-m-d H:i:s'),
        ]);
        $failures = (int) $stmt->fetchColumn();

        $status = 'ok';
        $detail = 'Fila saudável.';
        if ($failures > 0 && $failures < 5) {
            $status = 'degraded';
            $detail = sprintf('Falhas intermitentes detectadas (%d).', $failures);
        } elseif ($failures >= 5) {
            $status = 'down';
            $detail = sprintf('Falhas críticas na fila (%d).', $failures);
        }

        return [
            'status' => $status,
            'label' => 'Fila de jobs',
            'detail' => $detail,
            'meta' => [
                'recent_failures' => $failures,
                'observed_since' => $since->format(DateTimeInterface::ATOM),
            ],
        ];
    }

    /**
     * @return array{status:string,label:string,detail:string,meta:array<string, mixed>}
     */
    private function checkWebhook(): array
    {
        $pendingStmt = $this->connection->query(
            'SELECT COUNT(*) FROM webhook_events WHERE processed = 0'
        );
        $pending = (int) $pendingStmt->fetchColumn();

        $staleStmt = $this->connection->query(
            'SELECT MAX(created_at) FROM webhook_events'
        );
        $lastEvent = $staleStmt->fetchColumn();

        $status = 'ok';
        $detail = 'Fila de webhook estável.';

        if ($pending > 0) {
            $status = $pending > 10 ? 'down' : 'degraded';
            $detail = sprintf('%d eventos aguardando processamento.', $pending);
        }

        if ($lastEvent) {
            $last = new DateTimeImmutable((string) $lastEvent);
            $threshold = (new DateTimeImmutable('-30 minutes'));
            if ($last < $threshold) {
                $status = $status === 'down' ? 'down' : 'degraded';
                $detail .= ' Sem eventos recentes há mais de 30 minutos.';
            }
        }

        return [
            'status' => $status,
            'label' => 'Webhook',
            'detail' => trim($detail),
            'meta' => [
                'pending' => $pending,
                'last_event_at' => $lastEvent ? (string) $lastEvent : null,
            ],
        ];
    }
}
