<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class EvolutionService
{
    public function __construct(private SettingService $settings, private LoggerService $logger)
    {
    }

    public function sendText(string $contactExternalId, string $message): bool
    {
        $config = $this->settings->integrationSettings();
        $baseUrl = rtrim((string) ($config['evolution_api_url'] ?? ''), '/');
        $instance = (string) ($config['evolution_instance'] ?? '');
        $token = (string) ($config['evolution_token'] ?? '');

        if ($baseUrl === '' || $instance === '' || $token === '') {
            $this->logger->warning('evolution.missing_configuration', [
                'message' => 'Configurações incompletas para envio nativo.',
            ]);
            return false;
        }

        $endpoint = $baseUrl . '/message/sendText/' . $instance;
        $payload = json_encode([
            'number' => $contactExternalId,
            'text' => $message,
        ], JSON_UNESCAPED_UNICODE);

        if ($payload === false) {
            throw new RuntimeException('Não foi possível serializar o payload para Evolution.');
        }

        $response = $this->postJson($endpoint, $payload, $token);

        if ($response['success'] === false) {
            $this->logger->error('evolution.send_failed', [
                'contact' => $contactExternalId,
                'error' => $response['error'] ?? 'unknown',
            ]);
            return false;
        }

        $this->logger->info('evolution.message_sent', [
            'contact' => $contactExternalId,
        ]);

        return true;
    }

    public function sendDefaultTemplate(string $contactExternalId): void
    {
        $template = $this->settings->get('evolution_default_template');
        if ($template === null || trim($template) === '') {
            return;
        }

        $this->sendText($contactExternalId, $template);
    }

    public function sendTestMessage(string $contact, string $message, int $userId): bool
    {
        $result = $this->sendText($contact, $message);
        if ($result) {
            $this->logger->info('evolution.test_message_sent', [
                'user_id' => $userId,
                'contact' => $contact,
            ]);
        }

        return $result;
    }

    /**
     * @return array{success: bool, body?: string, error?: string}
     */
    private function postJson(string $url, string $payload, string $token): array
    {
        if (function_exists('curl_init')) {
            $headers = [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
            ];

            $ch = curl_init($url);
            if ($ch === false) {
                return ['success' => false, 'error' => 'curl_init_failed'];
            }

            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
            ]);

            $responseBody = curl_exec($ch);
            $error = curl_error($ch);
            $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);

            if ($responseBody === false) {
                return ['success' => false, 'error' => $error ?: 'curl_exec_failed'];
            }

            if ($status < 200 || $status >= 300) {
                return ['success' => false, 'body' => $responseBody, 'error' => 'http_' . $status];
            }

            return ['success' => true, 'body' => $responseBody];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $token,
                ],
                'content' => $payload,
                'timeout' => 15,
            ],
        ]);

        $responseBody = @file_get_contents($url, false, $context);
        if ($responseBody === false) {
            return ['success' => false, 'error' => 'stream_context_failed'];
        }

        $statusLine = $http_response_header[0] ?? 'HTTP/1.1 200';
        if (!preg_match('#\s(\d{3})#', $statusLine, $matches)) {
            return ['success' => true, 'body' => $responseBody];
        }

        $status = (int) $matches[1];
        if ($status < 200 || $status >= 300) {
            return ['success' => false, 'body' => $responseBody, 'error' => 'http_' . $status];
        }

        return ['success' => true, 'body' => $responseBody];
    }
}
