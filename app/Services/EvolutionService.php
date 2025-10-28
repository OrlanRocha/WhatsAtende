<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\FileLogger;
use DateTimeImmutable;
use RuntimeException;
use function app_logger;

class EvolutionService
{
    private const PROFILE_CACHE_SECONDS = 3600; // 1 hour
    private const MEDIA_CACHE_SECONDS = 86400;  // 24 hours

    private FileLogger $httpLogger;

    public function __construct(
        private SettingService $settings,
        private LoggerService $logger,
        ?FileLogger $httpLogger = null
    ) {
        $this->httpLogger = $httpLogger ?? app_logger();
    }

    public function isNativeEnabled(): bool
    {
        return $this->getNativeConfig() !== null;
    }

    /**
     * @return array{status:int, success:bool, data:mixed, error:?string, error_detail:?string, content_type:?string, body:mixed}
     */
    public function getChats(): array
    {
        $config = $this->getNativeConfig();
        if ($config === null) {
            return $this->notConfiguredResponse();
        }

        $response = $this->makeRequest(
            $config,
            'POST',
            '/chat/findChats/' . $config['instance'],
            [],
            [200, 204, 404]
        );

        if (($response['success'] ?? false) && in_array($response['status'], [204, 404], true)) {
            $response['status'] = 200;
        }

        if (($response['success'] ?? false) && !is_array($response['data'])) {
            $response['data'] = [];
        }

        return $response;
    }

    /**
     * @return array{status:int, success:bool, chats:array<int, array<string, mixed>>, error:?string}
     */
    public function fetchChatsOverview(): array
    {
        $response = $this->getChats();
        if (!($response['success'] ?? false)) {
            return [
                'status' => (int) ($response['status'] ?? 500),
                'success' => false,
                'chats' => [],
                'error' => $response['error_detail'] ?? $response['error'] ?? 'Falha ao consultar Evolution API.',
            ];
        }

        $rawChats = $this->extractChats($response['data'] ?? null);
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

        return [
            'status' => 200,
            'success' => true,
            'chats' => $chats,
            'error' => null,
        ];
    }

    /**
     * @return array{status:int, success:bool, data:mixed, error:?string}
     */
    public function getMessages(string $remoteJid, int $page = 1, int $limit = 50): array
    {
        $config = $this->getNativeConfig();
        if ($config === null) {
            return $this->notConfiguredResponse();
        }

        $payload = [
            'where' => ['key' => ['remoteJid' => $remoteJid]],
            'page' => max(1, $page),
            'limit' => max(1, $limit),
        ];

        return $this->makeRequest($config, 'POST', '/chat/findMessages/' . $config['instance'], $payload);
    }

    /**
     * @return array{status:int, success:bool, messages:array<int, array<string, mixed>>, error:?string}
     */
    public function fetchConversationMessages(string $remoteJid, int $limit = 100): array
    {
        $remoteJid = trim($remoteJid);
        if ($remoteJid === '') {
            return [
                'status' => 400,
                'success' => false,
                'messages' => [],
                'error' => 'remoteJid inválido fornecido para consulta de mensagens.',
            ];
        }

        $response = $this->getMessages($remoteJid, 1, max(1, $limit));
        if (!($response['success'] ?? false)) {
            return [
                'status' => (int) ($response['status'] ?? 500),
                'success' => false,
                'messages' => [],
                'error' => $response['error_detail'] ?? $response['error'] ?? 'Falha ao buscar mensagens no Evolution.',
            ];
        }

        $payload = $response['data'] ?? [];
        $rawMessages = [];
        if (is_array($payload)) {
            $rawMessages = $this->collectMessages($payload);
        }

        if ($rawMessages === []) {
            return [
                'status' => 200,
                'success' => true,
                'messages' => [],
                'error' => null,
            ];
        }

        $normalized = [];
        foreach ($rawMessages as $message) {
            if (!is_array($message)) {
                continue;
            }

            $normalizedMessage = $this->normalizeConversationMessage($message, $remoteJid);
            if ($normalizedMessage === null) {
                continue;
            }

            $messageId = $normalizedMessage['id'];
            $normalized[$messageId] = $normalizedMessage;
        }

        if ($normalized === []) {
            return [
                'status' => 200,
                'success' => true,
                'messages' => [],
                'error' => null,
            ];
        }

        $messages = array_values($normalized);
        usort($messages, static function (array $a, array $b): int {
            $aTime = $a['sent_at'] ?? '';
            $bTime = $b['sent_at'] ?? '';

            if ($aTime === $bTime) {
                return strcmp($a['id'] ?? '', $b['id'] ?? '');
            }

            return strcmp($aTime, $bTime);
        });

        return [
            'status' => 200,
            'success' => true,
            'messages' => $messages,
            'error' => null,
        ];
    }

    /**
     * @return array{status:int, path?:string, cached?:bool, content_type?:string, error?:string}
     */
    public function getProfilePicture(string $remoteJid): array
    {
        $config = $this->getNativeConfig();
        if ($config === null) {
            return ['status' => 400, 'error' => 'Integração nativa não está configurada.'];
        }

        $cachePath = $this->cachePath('profile', $remoteJid, '.jpg');
        if (is_file($cachePath) && (time() - filemtime($cachePath)) < self::PROFILE_CACHE_SECONDS) {
            return ['status' => 200, 'path' => $cachePath, 'cached' => true, 'content_type' => 'image/jpeg'];
        }

        $response = $this->makeRequest($config, 'POST', '/chat/fetchProfilePictureUrl/' . $config['instance'], [
            'number' => $remoteJid,
        ]);

        if (!$response['success']) {
            return ['status' => $response['status'], 'error' => $response['error'] ?? 'Erro ao consultar foto de perfil'];
        }

        $url = $response['data']['profilePictureUrl'] ?? null;
        if (!is_string($url) || $url === '') {
            return ['status' => 404, 'error' => 'Foto de perfil não disponível.'];
        }

        $contentType = $this->downloadToPath($url, $cachePath);
        if ($contentType === null) {
            return ['status' => 502, 'error' => 'Falha ao baixar a foto de perfil.'];
        }

        return ['status' => 200, 'path' => $cachePath, 'cached' => false, 'content_type' => $contentType];
    }

    /**
     * @return array{status:int, path?:string, content_type?:string, error?:string}
     */
    public function getMedia(string $messageId): array
    {
        $config = $this->getNativeConfig();
        if ($config === null) {
            return ['status' => 400, 'error' => 'Integração nativa não está configurada.'];
        }

        $cacheBase = $this->cachePath('media', $messageId);
        $existing = $this->findExistingCache($cacheBase);
        if ($existing !== null && (time() - filemtime($existing)) < self::MEDIA_CACHE_SECONDS) {
            $mime = $this->detectMime($existing) ?? 'application/octet-stream';
            return ['status' => 200, 'path' => $existing, 'content_type' => $mime];
        }

        $response = $this->makeRequest($config, 'POST', '/message/downloadMedia/' . $config['instance'], [
            'messageId' => $messageId,
        ]);

        if (!($response['success'] ?? false)) {
            return [
                'status' => (int) ($response['status'] ?? 500),
                'error' => $response['error_detail'] ?? $response['error'] ?? 'Erro ao baixar mídia.',
            ];
        }

        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $mime = (string) ($data['mimetype']
            ?? $data['mimeType']
            ?? $data['mime_type']
            ?? $response['content_type']
            ?? 'application/octet-stream');

        $payload = $this->extractMediaPayload($response, $config);
        if ($payload === null) {
            $this->logger->error('evolution.media_payload_missing', [
                'message_id' => $messageId,
                'status' => $response['status'] ?? null,
                'keys' => is_array($response['data'] ?? null) ? implode(',', array_keys($response['data'])) : gettype($response['data'] ?? null),
            ]);

            return [
                'status' => 502,
                'error' => 'Conteúdo da mídia não retornado pela Evolution.',
            ];
        }

        [$contents, $detectedMime] = $payload;
        if (is_string($detectedMime) && $detectedMime !== '') {
            $mime = $detectedMime;
        }

        $extension = $this->extensionFromMime($mime) ?? '';
        $cachePath = $cacheBase . $extension;

        $this->ensureDirectory(dirname($cachePath));
        $bytes = @file_put_contents($cachePath, $contents);
        if ($bytes === false) {
            $this->logger->error('evolution.media_cache_failed', [
                'message_id' => $messageId,
                'path' => $cachePath,
            ]);

            return [
                'status' => 500,
                'error' => 'Falha ao salvar mídia no cache local.',
            ];
        }

        return ['status' => 200, 'path' => $cachePath, 'content_type' => $mime];
    }

    /**
     * @return array{status:int, success:bool, data:mixed, error:?string}
     */
    public function markAsRead(string $remoteJid, string $messageId): array
    {
        $config = $this->getNativeConfig();
        if ($config === null) {
            return $this->notConfiguredResponse();
        }

        $payload = [
            'read_messages' => [[
                'remoteJid' => $remoteJid,
                'fromMe' => false,
                'id' => $messageId,
            ]],
        ];

        return $this->makeRequest($config, 'POST', '/chat/markMessageAsRead/' . $config['instance'], $payload);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{status:int, success:bool, data:mixed, error:?string}
     */
    public function sendMessage(array $payload): array
    {
        $config = $this->getNativeConfig();
        if ($config === null) {
            return $this->notConfiguredResponse();
        }

        if (!isset($payload['number'])) {
            throw new RuntimeException('Payload de envio deve conter o número do destinatário.');
        }

        $payload['number'] = $this->sanitizeContactNumber((string) $payload['number']);
        if (isset($payload['text'])) {
            $payload['text'] = $this->sanitizeMessageBody((string) $payload['text']);
        }

        return $this->makeRequest($config, 'POST', '/message/sendText/' . $config['instance'], $payload);
    }

    /**
     * @return array{success:bool,status:int,error:?string}
     */
    public function sendMedia(string $contactExternalId, string $filePath, array $options = []): array
    {
        $config = $this->getNativeConfig();
        if ($config === null) {
            return [
                'success' => false,
                'status' => 409,
                'error' => 'Integração nativa não está configurada.',
            ];
        }

        if (!is_file($filePath) || !is_readable($filePath)) {
            return [
                'success' => false,
                'status' => 400,
                'error' => 'Arquivo de mídia indisponível para envio.',
            ];
        }

        $contents = file_get_contents($filePath);
        if ($contents === false) {
            return [
                'success' => false,
                'status' => 500,
                'error' => 'Falha ao ler o arquivo de mídia.',
            ];
        }

        $caption = isset($options['caption']) ? $this->sanitizeMessageBody((string) $options['caption']) : '';
        $fileName = isset($options['filename']) && is_string($options['filename']) && $options['filename'] !== ''
            ? $options['filename']
            : basename($filePath);
        $mime = isset($options['mime_type']) && is_string($options['mime_type']) && $options['mime_type'] !== ''
            ? $options['mime_type']
            : ($this->detectMime($filePath) ?? 'application/octet-stream');
        $mediaType = isset($options['type']) && is_string($options['type']) && $options['type'] !== ''
            ? strtolower($options['type'])
            : 'auto';

        $payload = [
            'number' => $this->sanitizeContactNumber($contactExternalId),
            'mediaData' => base64_encode($contents),
            'mimetype' => $mime,
            'fileName' => $fileName,
            'caption' => $caption,
            'type' => $mediaType,
        ];

        if ($payload['caption'] === '') {
            unset($payload['caption']);
        }

        $response = $this->makeRequest($config, 'POST', '/message/sendMedia/' . $config['instance'], $payload);
        if (!($response['success'] ?? false)) {
            $errorMessage = $this->buildSendErrorMessage($response);

            $this->logger->error('evolution.media_send_failed', [
                'contact' => $contactExternalId,
                'status' => $response['status'] ?? 500,
                'error' => $errorMessage,
            ]);

            return [
                'success' => false,
                'status' => (int) ($response['status'] ?? 500),
                'error' => $errorMessage,
            ];
        }

        $this->logger->info('evolution.media_sent', [
            'contact' => $contactExternalId,
            'filename' => $fileName,
            'mime_type' => $mime,
        ]);

        return [
            'success' => true,
            'status' => (int) ($response['status'] ?? 200),
            'error' => null,
        ];
    }

    /**
     * @return array{success:bool,status:int,error:?string}
     */
    public function sendText(string $contactExternalId, string $message, array $options = []): array
    {
        $payload = array_merge($options, [
            'number' => $contactExternalId,
            'text' => $message,
        ]);

        if (!isset($payload['options'])) {
            $payload['options'] = [
                'delay' => 1200,
                'presence' => 'composing',
            ];
        }

        $response = $this->sendMessage($payload);
        if (!$response['success']) {
            $errorMessage = $this->buildSendErrorMessage($response);

            $this->logger->error('evolution.send_failed', [
                'message' => $errorMessage,
                'contact' => $contactExternalId,
                'status' => $response['status'],
                'error_code' => $response['error'] ?? null,
                'error_detail' => $response['error_detail'] ?? null,
            ]);

            return [
                'success' => false,
                'status' => $response['status'],
                'error' => $errorMessage,
            ];
        }

        $this->logger->info('evolution.message_sent', [
            'message' => 'Mensagem nativa enviada com sucesso.',
            'contact' => $contactExternalId,
        ]);

        return [
            'success' => true,
            'status' => $response['status'],
            'error' => null,
        ];
    }

    public function sendDefaultTemplate(string $contactExternalId): void
    {
        $template = $this->settings->get('evolution_default_template');
        if ($template === null || trim($template) === '') {
            return;
        }

        $result = $this->sendText($contactExternalId, $template);
        if (!$result['success']) {
            $this->logger->warning('evolution.template_send_failed', [
                'contact' => $contactExternalId,
                'message' => $result['error'],
            ]);
        }
    }

    public function sendTestMessage(string $contact, string $message, int $userId): bool
    {
        $result = $this->sendText($contact, $message);
        if ($result['success']) {
            $this->logger->info('evolution.test_message_sent', [
                'user_id' => $userId,
                'contact' => $contact,
            ]);
        }

        return $result['success'];
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

        $messages = $this->extractMessages($chat);
        $calculatedUnread = 0;
        $lastMessageId = null;
        $lastMessageTimestamp = null;
        $lastInboundUnreadId = null;
        $lastInboundUnreadTimestamp = null;

        foreach ($messages as $message) {
            if (!is_array($message)) {
                continue;
            }

            $normalizedMessage = $this->normalizeConversationMessage($message, $id);
            if ($normalizedMessage === null) {
                continue;
            }

            $messageId = (string) ($normalizedMessage['id'] ?? '');
            $messageTimestamp = $normalizedMessage['sent_at'] ?? null;
            if ($messageId !== '' && $messageTimestamp !== null) {
                if ($lastMessageTimestamp === null || strcmp($messageTimestamp, $lastMessageTimestamp) >= 0) {
                    $lastMessageTimestamp = $messageTimestamp;
                    $lastMessageId = $messageId;
                }
            }

            $fromMe = (bool) ($normalizedMessage['from_me'] ?? false);
            if ($fromMe) {
                continue;
            }

            $status = strtoupper((string) ($normalizedMessage['status'] ?? ''));
            if ($status === 'READ') {
                continue;
            }

            $calculatedUnread++;
            if ($messageId !== '' && $messageTimestamp !== null) {
                if ($lastInboundUnreadTimestamp === null || strcmp($messageTimestamp, $lastInboundUnreadTimestamp) >= 0) {
                    $lastInboundUnreadTimestamp = $messageTimestamp;
                    $lastInboundUnreadId = $messageId;
                }
            }
        }

        if ($calculatedUnread > 0) {
            $unread = $calculatedUnread;
        } elseif ($unread === 0 && isset($chat['lastMessage']) && is_array($chat['lastMessage'])) {
            $lastMessage = $this->normalizeConversationMessage($chat['lastMessage'], $id);
            if ($lastMessage !== null) {
                $status = strtoupper((string) ($lastMessage['status'] ?? ''));
                $fromMe = (bool) ($lastMessage['from_me'] ?? false);
                if ($status !== 'READ' && !$fromMe) {
                    $unread = 1;
                    if ($lastInboundUnreadId === null && ($lastMessage['id'] ?? '') !== '') {
                        $lastInboundUnreadId = (string) $lastMessage['id'];
                    }
                }
                if ($lastMessageId === null && ($lastMessage['id'] ?? '') !== '') {
                    $lastMessageId = (string) $lastMessage['id'];
                }
            }
        }

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

        $profileUrl = $id !== '' ? $this->buildProfileUrl($id) : null;

        return [
            'id' => $id,
            'name' => $name,
            'unread' => $unread,
            'last_message_at' => $lastMessage,
            'created_at' => $createdAt,
            'opened_today' => $openedToday,
            'profile_url' => $profileUrl,
            'last_message_id' => $lastMessageId,
            'last_unread_message_id' => $lastInboundUnreadId,
            'raw' => $chat,
        ];
    }

    /**
     * @param array<string, mixed> $chat
     * @return array<int, mixed>
     */
    private function extractMessages(array $chat): array
    {
        $messages = [];
        $seen = [];

        $addMessage = static function (array $message) use (&$messages, &$seen): void {
            $identifier = null;
            if (isset($message['id']) && is_scalar($message['id'])) {
                $identifier = (string) $message['id'];
            } elseif (isset($message['key']['id']) && is_scalar($message['key']['id'])) {
                $identifier = (string) $message['key']['id'];
            }

            if ($identifier !== null) {
                if (isset($seen[$identifier])) {
                    return;
                }
                $seen[$identifier] = true;
            }

            $messages[] = $message;
        };

        foreach (['messages', 'lastMessages', 'history', 'items'] as $key) {
            if (!isset($chat[$key])) {
                continue;
            }

            foreach ($this->collectMessages($chat[$key]) as $message) {
                if (is_array($message)) {
                    $addMessage($message);
                }
            }
        }

        if (isset($chat['lastMessage']) && is_array($chat['lastMessage'])) {
            $addMessage($chat['lastMessage']);
        }

        return $messages;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collectMessages(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        if ($this->looksLikeMessage($value)) {
            return [$value];
        }

        $messages = [];
        if (array_is_list($value)) {
            foreach ($value as $item) {
                foreach ($this->collectMessages($item) as $message) {
                    $messages[] = $message;
                }
            }

            return $messages;
        }

        foreach ($value as $item) {
            foreach ($this->collectMessages($item) as $message) {
                $messages[] = $message;
            }
        }

        return $messages;
    }

    private function looksLikeMessage(array $value): bool
    {
        return isset($value['key'])
            || isset($value['messageType'])
            || isset($value['type'])
            || isset($value['status'])
            || isset($value['message']);
    }

    /**
     * @param array<string, mixed> $message
     */
    private function normalizeConversationMessage(array $message, string $fallbackRemoteJid): ?array
    {
        $id = '';
        if (isset($message['id']) && is_scalar($message['id'])) {
            $id = (string) $message['id'];
        } elseif (isset($message['key']['id']) && is_scalar($message['key']['id'])) {
            $id = (string) $message['key']['id'];
        }

        if ($id === '') {
            return null;
        }

        $key = isset($message['key']) && is_array($message['key']) ? $message['key'] : [];
        $remoteJid = (string) ($key['remoteJid'] ?? $message['remoteJid'] ?? $fallbackRemoteJid);
        if ($remoteJid === '') {
            $remoteJid = $fallbackRemoteJid;
        }

        $fromMe = (bool) ($key['fromMe'] ?? $message['fromMe'] ?? false);

        $timestamp = $this->extractTimestamp(
            $message['messageTimestamp']
                ?? $message['timestamp']
                ?? $message['createdAt']
                ?? $message['created_at']
                ?? $message['sentAt']
                ?? $message['ts']
                ?? null
        );

        if ($timestamp === null) {
            return null;
        }

        $type = strtolower((string) ($message['messageType'] ?? $message['type'] ?? ''));
        if ($type === 'protocolmessage' || $type === 'notification' || $type === 'senderkeydistributionmessage') {
            return null;
        }

        $content = $this->extractMessageContent($message, $id);

        if ($content['body'] === '' && $content['media_url'] === null) {
            return null;
        }

        return [
            'id' => $id,
            'remote_jid' => $remoteJid,
            'from_me' => $fromMe,
            'status' => strtoupper((string) ($message['status'] ?? '')),
            'sent_at' => $timestamp,
            'body' => $content['body'],
            'media_type' => $content['media_type'],
            'media_url' => $content['media_url'],
            'raw' => $message,
        ];
    }

    /**
     * @param array<string, mixed> $message
     * @return array{body:string, media_type:string, media_url:?string}
     */
    private function extractMessageContent(array $message, string $messageId): array
    {
        $body = '';
        $mediaType = 'text';
        $mediaUrl = null;

        if (isset($message['body']) && is_string($message['body'])) {
            $body = $message['body'];
        }

        if (isset($message['text']) && is_string($message['text'])) {
            $body = $message['text'];
        }

        if (isset($message['message']) && is_array($message['message'])) {
            $payload = $message['message'];
            $resolved = $this->resolveMessageBody($payload);
            if ($resolved !== null) {
                $body = $resolved;
            }

            if (isset($payload['imageMessage']) && is_array($payload['imageMessage'])) {
                $mediaType = 'image';
                $mediaUrl = $this->buildMediaUrl($messageId);
                $caption = $payload['imageMessage']['caption'] ?? null;
                if (is_string($caption) && trim($caption) !== '') {
                    $body = $caption;
                }
            } elseif (isset($payload['audioMessage']) && is_array($payload['audioMessage'])) {
                $mediaType = 'audio';
                $mediaUrl = $this->buildMediaUrl($messageId);
                $caption = $payload['audioMessage']['caption'] ?? null;
                if (is_string($caption) && trim($caption) !== '') {
                    $body = $caption;
                }
            } elseif (isset($payload['videoMessage']) && is_array($payload['videoMessage'])) {
                $mediaType = 'video';
                $mediaUrl = $this->buildMediaUrl($messageId);
                $caption = $payload['videoMessage']['caption'] ?? null;
                if (is_string($caption) && trim($caption) !== '') {
                    $body = $caption;
                }
            } elseif (isset($payload['documentMessage']) && is_array($payload['documentMessage'])) {
                $mediaType = 'file';
                $mediaUrl = $this->buildMediaUrl($messageId);
                $fileName = $payload['documentMessage']['fileName'] ?? null;
                if (is_string($fileName) && trim($fileName) !== '') {
                    $body = $fileName;
                }
            } elseif (isset($payload['stickerMessage']) && is_array($payload['stickerMessage'])) {
                $mediaType = 'image';
                $mediaUrl = $this->buildMediaUrl($messageId);
            }
        }

        $body = trim((string) $body);

        $mediaType = $this->normalizeMediaType($mediaType);

        return [
            'body' => $body,
            'media_type' => $mediaType,
            'media_url' => $mediaUrl,
        ];
    }

    private function normalizeMediaType(string $type): string
    {
        $normalized = strtolower($type);

        return match ($normalized) {
            'image', 'photo', 'sticker' => 'image',
            'audio', 'ptt', 'voice' => 'audio',
            'video' => 'video',
            'file', 'document', 'application', 'doc', 'pdf' => 'file',
            default => 'text',
        };
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function resolveMessageBody(array $payload): ?string
    {
        $paths = [
            ['conversation'],
            ['extendedTextMessage', 'text'],
            ['ephemeralMessage', 'message', 'conversation'],
            ['templateMessage', 'hydratedTemplate', 'hydratedContentText'],
            ['buttonsMessage', 'contentText'],
            ['buttonsMessage', 'body'],
            ['listMessage', 'description'],
            ['listMessage', 'body'],
            ['interactiveMessage', 'body', 'text'],
        ];

        foreach ($paths as $path) {
            $value = $this->arrayGet($payload, $path);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $array
     * @param array<int, string> $path
     */
    private function arrayGet(array $array, array $path): mixed
    {
        $value = $array;
        foreach ($path as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $array
     * @param array<int, string> $path
     */
    private function arrayGetString(array $array, array $path): ?string
    {
        $value = $this->arrayGet($array, $path);
        return is_string($value) ? trim($value) : null;
    }

    private function buildMediaUrl(string $messageId): string
    {
        return '/api/evolution/media?messageId=' . rawurlencode($messageId);
    }

    private function buildProfileUrl(string $remoteJid): string
    {
        return '/api/evolution/profile?remoteJid=' . rawurlencode($remoteJid);
    }

    /**
     * @param array<string, mixed> $response
     * @param array<string, mixed> $config
     * @return array{0:string,1:?string}|null
     */
    private function extractMediaPayload(array $response, array $config): ?array
    {
        $contentType = isset($response['content_type']) && is_string($response['content_type'])
            ? $response['content_type']
            : null;

        $body = $response['body'] ?? null;
        if (is_string($body) && $body !== '' && ($contentType === null || !str_contains(strtolower($contentType), 'json'))) {
            return [$body, $contentType];
        }

        $data = $response['data'] ?? null;
        $candidates = [];

        if (is_string($data) && trim($data) !== '') {
            $candidates[] = $data;
        }

        if (is_array($data)) {
            $paths = [
                ['data'],
                ['fileBase64'],
                ['file'],
                ['fileUrl'],
                ['file_url'],
                ['media'],
                ['base64'],
                ['buffer'],
                ['buffer', 'data'],
                ['buffer', 'file'],
                ['buffer', 'base64'],
                ['payload', 'data'],
                ['payload', 'file'],
                ['payload', 'base64'],
                ['url'],
                ['mediaUrl'],
                ['media_url'],
                ['directPath'],
                ['downloadUrl'],
                ['download_url'],
            ];

            foreach ($paths as $path) {
                $value = $this->arrayGetString($data, $path);
                if ($value !== null && $value !== '') {
                    $candidates[] = $value;
                }
            }

            foreach (['imageMessage', 'videoMessage', 'audioMessage', 'documentMessage', 'stickerMessage'] as $mediaKey) {
                if (!isset($data[$mediaKey]) || !is_array($data[$mediaKey])) {
                    continue;
                }

                foreach (['url', 'directPath'] as $path) {
                    $value = $this->arrayGetString($data[$mediaKey], [$path]);
                    if ($value !== null && $value !== '') {
                        $candidates[] = $value;
                    }
                }
            }
        }

        foreach ($candidates as $candidate) {
            $parsed = $this->parseMediaString($candidate, $config);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $config
     * @return array{0:string,1:?string}|null
     */
    private function parseMediaString(string $value, array $config): ?array
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, 'data:')) {
            $parsed = $this->parseDataUrl($value);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://') || str_starts_with($value, '//') || str_starts_with($value, '/')) {
            $url = $this->normalizeMediaUrl($value, $config);
            $downloaded = $this->downloadBinary($url);
            if ($downloaded !== null) {
                return $downloaded;
            }
        }

        $decoded = $this->decodeBase64Media($value);
        if ($decoded !== null) {
            return [$decoded, null];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function normalizeMediaUrl(string $value, array $config): string
    {
        if (str_starts_with($value, '//')) {
            return 'https:' . $value;
        }

        if (str_starts_with($value, '/')) {
            if (preg_match('#^/v/#i', $value) === 1) {
                return 'https://mmg.whatsapp.net' . $value;
            }

            $base = isset($config['base_url']) && is_string($config['base_url']) ? rtrim($config['base_url'], '/') : '';
            if ($base !== '') {
                return $base . $value;
            }
        }

        if (!str_starts_with($value, 'http://') && !str_starts_with($value, 'https://')) {
            $base = isset($config['base_url']) && is_string($config['base_url']) ? rtrim($config['base_url'], '/') : '';
            if ($base !== '') {
                return $base . '/' . ltrim($value, '/');
            }
        }

        return $value;
    }

    private function decodeBase64Media(string $value): ?string
    {
        $clean = preg_replace('/\s+/', '', $value);
        if ($clean === null || $clean === '') {
            return null;
        }

        if (strlen($clean) < 16) {
            return null;
        }

        if (preg_match('/^[A-Za-z0-9\-_/+=]+$/', $clean) !== 1) {
            return null;
        }

        $normalized = strtr($clean, '-_', '+/');
        $padLength = strlen($normalized) % 4;
        if ($padLength !== 0) {
            $normalized .= str_repeat('=', 4 - $padLength);
        }

        $decoded = base64_decode($normalized, true);
        if ($decoded === false) {
            return null;
        }

        return $decoded === '' ? null : $decoded;
    }

    /**
     * @return array{0:string,1:?string}|null
     */
    private function parseDataUrl(string $value): ?array
    {
        if (!preg_match('#^data:(?P<mime>[^;,]+)?(?P<params>(;[^,]+)*)?;base64,(?P<data>.+)$#i', $value, $matches)) {
            return null;
        }

        $mime = isset($matches['mime']) && $matches['mime'] !== '' ? strtolower(trim($matches['mime'])) : null;
        $decoded = $this->decodeBase64Media($matches['data']);
        if ($decoded === null) {
            return null;
        }

        return [$decoded, $mime];
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

    /**
     * @param array<int, int> $expectedStatuses
     * @return array{status:int, success:bool, data:mixed, error:?string, error_detail:?string, content_type:?string, body:mixed}
     */
    private function makeRequest(
        array $config,
        string $method,
        string $endpoint,
        ?array $payload = null,
        array $expectedStatuses = []
    ): array
    {
        $url = $config['base_url'] . $endpoint;
        $method = strtoupper($method);
        $headers = ['Content-Type: application/json'];

        if ($config['token'] !== '') {
            $headers[] = 'Authorization: Bearer ' . $config['token'];
        }
        if ($config['api_key'] !== '') {
            $headers[] = 'apikey: ' . $config['api_key'];
        }

        $body = null;
        if ($payload !== null && $method !== 'GET') {
            $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($body === false) {
                throw new RuntimeException('Falha ao serializar payload para Evolution API.');
            }
        } elseif ($payload !== null && $method === 'GET') {
            $query = http_build_query($payload);
            if ($query !== '') {
                $url .= (str_contains($url, '?') ? '&' : '?') . $query;
            }
        }

        $curlErrno = null;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                return [
                    'status' => 500,
                    'success' => false,
                    'data' => null,
                    'error' => 'curl_init_failed',
                    'error_detail' => 'Falha ao inicializar cURL.',
                    'content_type' => null,
                    'body' => null,
                ];
            }

            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }

            $responseBody = curl_exec($ch);
            $curlError = curl_error($ch);
            $curlErrno = curl_errno($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: null;
            curl_close($ch);
        } else {
            $context = [
                'http' => [
                    'method' => $method,
                    'header' => implode("\r\n", $headers),
                    'timeout' => 30,
                ],
            ];
            if ($body !== null) {
                $context['http']['content'] = $body;
            }

            $contextResource = stream_context_create($context);
            $responseBody = @file_get_contents($url, false, $contextResource);
            $curlError = $responseBody === false ? 'stream_context_failed' : '';
            $statusLine = $http_response_header[0] ?? 'HTTP/1.1 500';
            $status = preg_match('#\s(\d{3})#', $statusLine, $matches) ? (int) $matches[1] : 200;
            $contentType = null;
            foreach ($http_response_header ?? [] as $headerLine) {
                if (stripos($headerLine, 'Content-Type:') === 0) {
                    $contentType = trim(substr($headerLine, strlen('Content-Type:')));
                    break;
                }
            }
        }

        if ($responseBody === false) {
            $this->logger->error('evolution.request_failed', [
                'message' => 'Falha ao executar requisição cURL.',
                'endpoint' => $endpoint,
                'error' => $curlError ?: 'unknown',
                'curl_errno' => $curlErrno,
            ]);

            $this->logHttp($method, $endpoint, $payload, 500, null, $curlError ?: 'curl_exec_failed');

            return [
                'status' => 500,
                'success' => false,
                'data' => null,
                'error' => $curlError ?: 'curl_exec_failed',
                'error_detail' => $curlError ?: ($curlErrno !== null ? 'curl_errno_' . $curlErrno : 'curl_exec_failed'),
                'content_type' => null,
                'body' => null,
            ];
        }

        $decoded = null;
        if (is_string($responseBody) && $contentType !== null && str_contains($contentType, 'application/json')) {
            $decoded = json_decode($responseBody, true);
            if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                $decoded = null;
            }
        }

        $normalizedExpected = array_map('intval', $expectedStatuses);
        $success = $status >= 200 && $status < 300;
        if (!$success && $normalizedExpected !== []) {
            $success = in_array($status, $normalizedExpected, true);
        }
        $errorDetail = null;
        if (!$success) {
            if ($curlError !== '') {
                $errorDetail = $curlError;
            } elseif (is_array($decoded)) {
                foreach (['error', 'message', 'detail', 'details'] as $key) {
                    $value = $decoded[$key] ?? null;
                    if (is_string($value) && trim($value) !== '') {
                        $errorDetail = trim($value);
                        break;
                    }
                }
            } elseif (is_string($responseBody) && trim($responseBody) !== '') {
                $errorDetail = substr(trim($responseBody), 0, 512);
            }
        }
        if (!$success) {
            $this->logger->error('evolution.request_failed', [
                'message' => 'Evolution API respondeu com erro.',
                'endpoint' => $endpoint,
                'status' => $status,
                'response' => $decoded ?? $responseBody,
                'error_detail' => $errorDetail,
            ]);
        } else {
            $this->logger->info('evolution.request_success', [
                'message' => 'Chamada à Evolution concluída com sucesso.',
                'endpoint' => $endpoint,
                'status' => $status,
            ]);
        }

        $this->logHttp(
            $method,
            $endpoint,
            $payload,
            $status,
            $decoded ?? $responseBody,
            $success ? null : ($curlError ?: 'http_' . $status)
        );

        return [
            'status' => $status,
            'success' => $success,
            'data' => $decoded,
            'error' => $success ? null : ($curlError ?: 'http_' . $status),
            'error_detail' => $errorDetail,
            'content_type' => $contentType,
            'body' => $decoded === null ? $responseBody : null,
        ];
    }

    /**
     * @return array{status:int, success:bool, data:null, error:string}
     */
    private function notConfiguredResponse(): array
    {
        return [
            'status' => 400,
            'success' => false,
            'data' => null,
            'error' => 'Integração nativa não configurada.',
            'error_detail' => null,
            'content_type' => null,
            'body' => null,
        ];
    }

    /**
     * @param array{status:int,error:?string,error_detail:?string} $response
     */
    private function buildSendErrorMessage(array $response): string
    {
        $detail = $response['error_detail'] ?? null;
        if (is_string($detail) && trim($detail) !== '') {
            $normalized = trim($detail);
            if (!str_starts_with($normalized, 'curl_errno_') && $normalized !== 'curl_exec_failed') {
                return $normalized;
            }
        }

        $code = $response['error'] ?? null;
        if (is_string($code) && $code !== '') {
            if (str_starts_with($code, 'curl_errno_')) {
                $errno = (int) substr($code, strlen('curl_errno_'));
                return match ($errno) {
                    6 => 'Não foi possível resolver o host configurado para a Evolution API.',
                    7 => 'Evolution API não respondeu à tentativa de conexão.',
                    28 => 'Tempo limite excedido ao contatar a Evolution API.',
                    default => 'Erro de transporte ao contatar a Evolution API (cURL ' . $errno . ').',
                };
            }

            $mapped = match ($code) {
                'curl_init_failed' => 'Falha ao inicializar o cliente HTTP (cURL).',
                'curl_exec_failed' => 'Falha ao executar a requisição HTTP para a Evolution API.',
                default => null,
            };

            if (is_string($mapped) && $mapped !== '') {
                return $mapped;
            }
        }

        return match ($response['status']) {
            401 => 'Evolution API rejeitou as credenciais fornecidas (HTTP 401).',
            403 => 'Evolution API bloqueou a requisição (HTTP 403).',
            404 => 'Recurso solicitado na Evolution API não foi encontrado (HTTP 404).',
            408 => 'Tempo limite atingido ao aguardar resposta da Evolution API (HTTP 408).',
            429 => 'Evolution API limitou o número de requisições (HTTP 429).',
            500, 502, 503, 504 => 'Evolution API retornou um erro interno (HTTP ' . $response['status'] . ').',
            default => 'Evolution API respondeu com erro (HTTP ' . $response['status'] . ').',
        };
    }

    private function sanitizeContactNumber(string $number): string
    {
        $trimmed = trim($number);
        if ($trimmed === '') {
            throw new RuntimeException('Número inválido informado para Evolution API.');
        }

        if (str_contains($trimmed, '@')) {
            $normalized = function_exists('mb_substr')
                ? mb_substr($trimmed, 0, 191)
                : substr($trimmed, 0, 191);
            if (!preg_match('/^[0-9A-Za-z._:@-]+$/', $normalized)) {
                throw new RuntimeException('Número inválido informado para Evolution API.');
            }

            return $normalized;
        }

        $digitsOnly = preg_replace('/\D+/', '', $trimmed);
        if ($digitsOnly === null || $digitsOnly === '') {
            throw new RuntimeException('Número inválido informado para Evolution API.');
        }

        return substr($digitsOnly, 0, 20);
    }

    private function sanitizeMessageBody(string $message): string
    {
        $clean = strip_tags($message);
        $normalized = preg_replace("/[\r\n]+/", "\n", $clean);
        if (is_string($normalized)) {
            $clean = $normalized;
        }
        $clean = trim($clean);

        if (function_exists('mb_substr')) {
            return mb_substr($clean, 0, 1024);
        }

        return substr($clean, 0, 1024);
    }

    private function logHttp(string $method, string $endpoint, ?array $payload, int $status, mixed $response, ?string $error): void
    {
        $context = [
            'method' => $method,
            'endpoint' => $endpoint,
            'status' => $status,
            'payload' => $payload,
        ];

        if ($response !== null) {
            $context['response'] = $this->truncateResponse($response);
        }

        if ($error !== null) {
            $context['error'] = $error;
        }

        if ($error === null && $status >= 200 && $status < 400) {
            $this->httpLogger->info('evolution.http', $context);
        } else {
            $this->httpLogger->error('evolution.http', $context);
        }
    }

    private function truncateResponse(mixed $response): mixed
    {
        if (is_string($response) && strlen($response) > 2048) {
            return substr($response, 0, 2048) . '...';
        }

        if (is_array($response)) {
            $result = [];
            foreach ($response as $key => $value) {
                $result[$key] = $this->truncateResponse($value);
            }

            return $result;
        }

        return $response;
    }

    /**
     * @return array{base_url:string, instance:string, token:string, api_key:string}|null
     */
    private function getNativeConfig(): ?array
    {
        $config = $this->settings->integrationSettings();
        if (($config['integration_mode'] ?? 'webhook') !== 'native') {
            return null;
        }

        $baseUrl = rtrim((string) ($config['evolution_api_url'] ?? ''), '/');
        $instance = (string) ($config['evolution_instance'] ?? '');
        $token = (string) ($config['evolution_token'] ?? '');
        $apiKey = (string) ($config['evolution_api_key'] ?? '');

        if ($baseUrl === '' || $instance === '') {
            return null;
        }

        return [
            'base_url' => $baseUrl,
            'instance' => $instance,
            'token' => $token,
            'api_key' => $apiKey,
        ];
    }

    private function cachePath(string $type, string $key, string $defaultExtension = ''): string
    {
        $directory = base_path('storage/evolution/' . $type);
        $this->ensureDirectory($directory);

        return $directory . '/' . md5($key) . $defaultExtension;
    }

    private function findExistingCache(string $basePath): ?string
    {
        $matches = glob($basePath . '*');
        if ($matches === false || $matches === []) {
            return null;
        }

        return (string) $matches[0];
    }

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }
    }

    private function extensionFromMime(string $mime): ?string
    {
        return match (strtolower($mime)) {
            'image/jpeg', 'image/jpg' => '.jpg',
            'image/png' => '.png',
            'image/gif' => '.gif',
            'audio/mpeg', 'audio/mp3' => '.mp3',
            'audio/ogg' => '.ogg',
            'audio/wav' => '.wav',
            'video/mp4' => '.mp4',
            default => null,
        };
    }

    private function detectMime(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return null;
        }

        $mime = finfo_file($finfo, $path) ?: null;
        finfo_close($finfo);

        return $mime ?: null;
    }

    private function downloadToPath(string $url, string $path): ?string
    {
        $downloaded = $this->downloadBinary($url);
        if ($downloaded === null) {
            return null;
        }

        [$contents, $contentType] = $downloaded;
        $this->ensureDirectory(dirname($path));
        file_put_contents($path, $contents);

        return $contentType;
    }

    /**
     * @return array{0:string,1:string}|null Returns [contents, mime]
     */
    private function downloadBinary(string $url): ?array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                return null;
            }
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            $contents = curl_exec($ch);
            $error = curl_error($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'application/octet-stream';
            curl_close($ch);

            if ($contents === false || $status !== 200) {
                $this->logger->error('evolution.media_download_failed', [
                    'message' => 'Falha ao baixar conteúdo binário.',
                    'url' => $url,
                    'status' => $status,
                    'error' => $error ?: 'unknown',
                ]);

                return null;
            }

            return [$contents, $contentType];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 30,
            ],
        ]);

        $contents = @file_get_contents($url, false, $context);
        if ($contents === false) {
            $this->logger->error('evolution.media_download_failed', [
                'message' => 'Falha ao baixar conteúdo binário (stream).',
                'url' => $url,
            ]);

            return null;
        }

        $contentType = 'application/octet-stream';
        foreach ($http_response_header ?? [] as $headerLine) {
            if (stripos($headerLine, 'Content-Type:') === 0) {
                $contentType = trim(substr($headerLine, strlen('Content-Type:')));
                break;
            }
        }

        return [$contents, $contentType];
    }
}
