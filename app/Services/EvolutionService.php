<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\FileLogger;
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

        if (!$response['success']) {
            return ['status' => $response['status'], 'error' => $response['error'] ?? 'Erro ao baixar mídia.'];
        }

        $data = $response['data'] ?? [];
        $mime = (string) ($data['mimetype'] ?? $response['content_type'] ?? 'application/octet-stream');
        $contents = null;
        if (isset($data['data']) && is_string($data['data'])) {
            $contents = base64_decode($data['data'], true);
        } elseif (isset($data['fileBase64']) && is_string($data['fileBase64'])) {
            $contents = base64_decode($data['fileBase64'], true);
        } elseif (isset($data['url']) && is_string($data['url'])) {
            $downloaded = $this->downloadBinary($data['url']);
            if ($downloaded !== null) {
                [$contents, $mime] = $downloaded;
            }
        }

        if (!is_string($contents)) {
            return ['status' => 502, 'error' => 'Conteúdo da mídia não retornado pela Evolution.'];
        }

        $extension = $this->extensionFromMime($mime) ?? '';
        $cachePath = $cacheBase . $extension;

        $this->ensureDirectory(dirname($cachePath));
        file_put_contents($cachePath, $contents);

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

        return $this->makeRequest($config, 'PUT', '/chat/markMessageAsRead/' . $config['instance'], $payload);
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
        $digitsOnly = preg_replace('/\D+/', '', $number);
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
