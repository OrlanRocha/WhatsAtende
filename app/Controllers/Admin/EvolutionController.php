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
        if ($name === '' && isset($chat['lastMessage']) && is_array($chat['lastMessage'])) {
            $name = (string) ($chat['lastMessage']['pushName'] ?? $chat['lastMessage']['contact'] ?? $chat['lastMessage']['participant'] ?? '');
        }

        $unread = (int) ($chat['unreadCount'] ?? $chat['unread'] ?? 0);

        $lastMessageSource = $chat['conversationTimestamp']
            ?? $chat['lastMessageAt']
            ?? $chat['last_message_at']
            ?? $chat['last_message']
            ?? null;

        if ($lastMessageSource === null && isset($chat['lastMessage']) && is_array($chat['lastMessage'])) {
            $lastMessageSource = $chat['lastMessage']['messageTimestamp']
                ?? $chat['lastMessage']['timestamp']
                ?? $chat['lastMessage']['createdAt']
                ?? $chat['lastMessage']['created_at']
                ?? null;
        }

        if ($lastMessageSource === null) {
            $lastMessageSource = $chat['updatedAt'] ?? $chat['updated_at'] ?? $chat['last_activity_at'] ?? null;
        }

        $lastMessage = $this->extractTimestamp($lastMessageSource);

        $createdSource = $chat['createdAt']
            ?? $chat['created_at']
            ?? $chat['firstSeen']
            ?? $chat['startAt']
            ?? $chat['started_at']
            ?? $chat['windowStart']
            ?? $chat['window_start']
            ?? null;

        if ($createdSource === null) {
            $createdSource = $chat['updatedAt'] ?? $chat['updated_at'] ?? null;
        }

        $createdAt = $this->extractTimestamp($createdSource);

        if ($createdAt === null) {
            $createdAt = $lastMessage;
        }

        static $today = null;
        if ($today === null) {
            $today = (new DateTimeImmutable('today'))->format('Y-m-d');
        }
        $openedToday = false;
        foreach ([$createdAt, $lastMessage, $this->extractTimestamp($chat['updatedAt'] ?? $chat['updated_at'] ?? null)] as $candidate) {
            if ($candidate !== null && str_starts_with($candidate, $today)) {
                $openedToday = true;
                break;
            }
        }

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
