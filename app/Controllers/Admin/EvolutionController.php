<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\EvolutionService;
use DateTimeImmutable;

class EvolutionController
{
    public function __construct(private EvolutionService $evolution)
    {
    }

    public function chats(): void
    {
        require_role('admin');

        $result = $this->evolution->getChats();
        $statusCode = (int) ($result['status'] ?? 500);

        $chats = [];
        $error = null;
        if (($result['success'] ?? false)) {
            $rawChats = $this->extractChats($result['data'] ?? null);
            $chats = array_map([$this, 'normalizeChat'], $rawChats);
            usort($chats, static function (array $a, array $b): int {
                $aTime = $a['last_message_at'] ?? null;
                $bTime = $b['last_message_at'] ?? null;

                if ($aTime === $bTime) {
                    return 0;
                }

                if ($bTime === null) {
                    return -1;
                }

                if ($aTime === null) {
                    return 1;
                }

                return strcmp($bTime, $aTime);
            });
            $statusCode = 200;
        } else {
            $error = $result['error_detail'] ?? $result['error'] ?? 'Falha ao consultar Evolution API.';
        }

        if (is_ajax()) {
            json_response([
                'status' => $statusCode,
                'chats' => $chats,
                'error' => $error,
                'fetched_at' => date('Y-m-d H:i:s'),
            ], $statusCode);
        }

        view('admin/evolution/chats', [
            'chats' => $chats,
            'error' => $error,
        ]);
    }

    /**
     * @param mixed $data
     * @return array<int, array<string, mixed>>
     */
    private function extractChats(mixed $data): array
    {
        if ($data === null) {
            return [];
        }

        if (is_array($data)) {
            if (array_is_list($data)) {
                return $data;
            }

            $candidates = [$data];
            foreach (['chats', 'data', 'items', 'rows', 'response'] as $key) {
                if (isset($data[$key])) {
                    $value = $data[$key];
                    if (is_array($value)) {
                        $candidates[] = $value;
                    }
                }
            }

            foreach ($candidates as $candidate) {
                if (!is_array($candidate)) {
                    continue;
                }
                if (array_is_list($candidate)) {
                    return $candidate;
                }
                $values = array_values($candidate);
                if ($values !== [] && is_array($values[0])) {
                    return $values;
                }
            }
        }

        return [];
    }

    /**
     * @param array<string, mixed> $chat
     * @return array<string, mixed>
     */
    private function normalizeChat(array $chat): array
    {
        $id = (string) ($chat['remoteJid'] ?? $chat['id'] ?? $chat['wid'] ?? '');
        $name = (string) ($chat['name'] ?? $chat['pushName'] ?? $chat['contact'] ?? '');
        $unread = (int) ($chat['unreadCount'] ?? $chat['unread'] ?? 0);
        $lastMessage = $this->extractTimestamp(
            $chat['conversationTimestamp']
                ?? $chat['lastMessageAt']
                ?? $chat['last_message_at']
                ?? $chat['last_message']
                ?? $chat['lastMessage']
                ?? null
        );
        $createdAt = $this->extractTimestamp(
            $chat['createdAt']
                ?? $chat['created_at']
                ?? $chat['firstSeen']
                ?? $chat['startAt']
                ?? $chat['started_at']
                ?? null
        );

        static $today = null;
        if ($today === null) {
            $today = (new DateTimeImmutable('today'))->format('Y-m-d');
        }
        $openedToday = $createdAt !== null && str_starts_with($createdAt, $today);

        return [
            'id' => $id,
            'name' => $name,
            'unread' => $unread,
            'last_message_at' => $lastMessage,
            'created_at' => $createdAt,
            'opened_today' => $openedToday,
            'raw' => $chat,
        ];
    }

    private function extractTimestamp(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            $timestamp = (int) $value;
            if ($timestamp > 9999999999) {
                $timestamp = (int) round($timestamp / 1000);
            }
            if ($timestamp <= 0) {
                return null;
            }

            return date('Y-m-d H:i:s', $timestamp);
        }

        if (is_string($value) && $value !== '') {
            $time = strtotime($value);
            if ($time !== false) {
                return date('Y-m-d H:i:s', $time);
            }
        }

        return null;
    }
}
