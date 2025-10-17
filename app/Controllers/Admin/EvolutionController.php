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
        if (($result['success'] ?? false) && is_array($result['data'] ?? null)) {
            $chats = array_map([$this, 'normalizeChat'], $result['data']);
            $statusCode = 200;
        } else {
            $error = $result['error_detail'] ?? $result['error'] ?? 'Falha ao consultar Evolution API.';
        }

        if (is_ajax()) {
            json_response([
                'status' => $statusCode,
                'chats' => $chats,
                'error' => $error,
            ], $statusCode);
        }

        view('admin/evolution/chats', [
            'chats' => $chats,
            'error' => $error,
        ]);
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
        $lastMessage = $this->extractTimestamp($chat['conversationTimestamp'] ?? $chat['lastMessageAt'] ?? $chat['last_message_at'] ?? null);
        $createdAt = $this->extractTimestamp($chat['createdAt'] ?? $chat['created_at'] ?? null);

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
