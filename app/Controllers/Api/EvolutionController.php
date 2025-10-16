<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Services\EvolutionService;

class EvolutionController
{
    public function __construct(private EvolutionService $evolution)
    {
    }

    public function handle(): void
    {
        $action = $_GET['action'] ?? $_POST['action'] ?? '';
        switch ($action) {
            case 'getChats':
                $this->chats();
                return;
            case 'getMessages':
                $this->messages();
                return;
            case 'getProfile':
                $this->profile();
                return;
            case 'getMedia':
                $this->media();
                return;
            case 'sendMessage':
                $this->send();
                return;
            case 'markAsRead':
                $this->markRead();
                return;
            default:
                json_response(['status' => 400, 'error' => 'Ação inválida.'], 400);
        }
    }

    public function chats(): void
    {
        $this->ensureAuthenticated();
        $this->guardNative();

        $result = $this->evolution->getChats();
        $this->respondJson($result);
    }

    public function messages(): void
    {
        $this->ensureAuthenticated();
        $this->guardNative();

        $remoteJid = trim((string) ($_GET['remoteJid'] ?? ''));
        if ($remoteJid === '') {
            json_response(['status' => 400, 'error' => 'remoteJid é obrigatório.'], 400);
        }

        $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
        $result = $this->evolution->getMessages($remoteJid, $page, $limit);
        $this->respondJson($result);
    }

    public function profile(): void
    {
        $this->ensureAuthenticated();
        $this->guardNative();

        $remoteJid = trim((string) ($_GET['remoteJid'] ?? ''));
        if ($remoteJid === '') {
            json_response(['status' => 400, 'error' => 'remoteJid é obrigatório.'], 400);
        }

        $result = $this->evolution->getProfilePicture($remoteJid);
        if (($result['status'] ?? 500) === 200 && isset($result['path'])) {
            header('Content-Type: ' . ($result['content_type'] ?? 'image/jpeg'));
            readfile($result['path']);
            exit;
        }

        json_response([
            'status' => $result['status'] ?? 500,
            'error' => $result['error'] ?? 'Foto não encontrada',
        ], $result['status'] ?? 500);
    }

    public function media(): void
    {
        $this->ensureAuthenticated();
        $this->guardNative();

        $messageId = trim((string) ($_GET['messageId'] ?? ''));
        if ($messageId === '') {
            json_response(['status' => 400, 'error' => 'messageId é obrigatório.'], 400);
        }

        $result = $this->evolution->getMedia($messageId);
        if (($result['status'] ?? 500) === 200 && isset($result['path'])) {
            header('Content-Type: ' . ($result['content_type'] ?? 'application/octet-stream'));
            header('Content-Disposition: inline');
            readfile($result['path']);
            exit;
        }

        json_response([
            'status' => $result['status'] ?? 500,
            'error' => $result['error'] ?? 'Mídia não encontrada',
        ], $result['status'] ?? 500);
    }

    public function send(): void
    {
        $user = $this->ensureAuthenticated();
        $this->guardNative();

        $payload = $this->decodeJsonBody();
        $number = trim((string) ($payload['number'] ?? ''));
        $text = (string) ($payload['text'] ?? '');
        if ($number === '' || $text === '') {
            json_response(['status' => 400, 'error' => 'Número e texto são obrigatórios.'], 400);
        }

        $result = $this->evolution->sendMessage([
            'number' => $number,
            'text' => $text,
            'options' => $payload['options'] ?? [
                'delay' => 1200,
                'presence' => 'composing',
            ],
        ]);

        if ($result['success'] ?? false) {
            json_response([
                'status' => $result['status'],
                'data' => $result['data'],
                'message' => 'Mensagem enviada com sucesso.',
                'user_id' => $user->id ?? null,
            ], $result['status']);
        }

        json_response([
            'status' => $result['status'],
            'error' => $result['error_detail'] ?? $result['error'] ?? 'Falha ao enviar mensagem.',
        ], $result['status']);
    }

    public function markRead(): void
    {
        $this->ensureAuthenticated();
        $this->guardNative();

        $payload = $this->decodeJsonBody();
        $remoteJid = trim((string) ($payload['remoteJid'] ?? ''));
        $messageId = trim((string) ($payload['messageId'] ?? ''));
        if ($remoteJid === '' || $messageId === '') {
            json_response(['status' => 400, 'error' => 'remoteJid e messageId são obrigatórios.'], 400);
        }

        $result = $this->evolution->markAsRead($remoteJid, $messageId);
        $this->respondJson($result);
    }

    private function ensureAuthenticated(): object
    {
        $user = auth();
        if ($user === null) {
            json_response(['status' => 401, 'error' => 'Acesso não autorizado.'], 401);
        }

        return $user;
    }

    private function guardNative(): void
    {
        if (!$this->evolution->isNativeEnabled()) {
            json_response(['status' => 409, 'error' => 'Modo nativo da Evolution não está habilitado.'], 409);
        }
    }

    /**
     * @param array{status:int, success?:bool, data:mixed, error:?string} $result
     */
    private function respondJson(array $result): void
    {
        if (($result['success'] ?? false) === true) {
            json_response([
                'status' => $result['status'],
                'data' => $result['data'],
            ], $result['status']);
        }

        json_response([
            'status' => $result['status'],
            'error' => $result['error_detail'] ?? $result['error'] ?? 'Falha ao consultar Evolution API.',
        ], $result['status']);
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeJsonBody(): array
    {
        $input = file_get_contents('php://input');
        if ($input === false || trim($input) === '') {
            return [];
        }

        $decoded = json_decode($input, true);
        if (!is_array($decoded)) {
            json_response(['status' => 400, 'error' => 'JSON inválido fornecido no corpo da requisição.'], 400);
        }

        return $decoded;
    }
}
