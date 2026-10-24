<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Services\TicketService;
use Exception;

class TicketController
{
    public function __construct(private TicketService $ticketService)
    {
    }

    public function index(): void
    {
        $this->ensureAuthenticated();

        try {
            $limit = (int) ($_GET['limit'] ?? 50);
            $offset = (int) ($_GET['offset'] ?? 0);
            // Rotas complexas de listagem podem ser expostas se necessitar
            json_response(['message' => 'Not fully implemented. Use /open to see open queue.'], 200);
        } catch (Exception $e) {
            json_response(['error' => $e->getMessage()], 500);
        }
    }

    public function getOpenQueue(): void
    {
        $user = $this->ensureAuthenticated();
        
        try {
            $userId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : (int) ($user->id ?? 0);
            $tickets = $this->ticketService->getOpenQueue(null, $userId > 0 ? $userId : null);
            json_response(['status' => 200, 'data' => $tickets], 200);
        } catch (Exception $e) {
            json_response(['status' => 500, 'error' => 'Falha ao obter fila: ' . $e->getMessage()], 500);
        }
    }

    public function show(int $id): void
    {
        $this->ensureAuthenticated();

        try {
            $ticket = $this->ticketService->getTicketWithMessages($id);
            if (!$ticket) {
                json_response(['status' => 404, 'error' => 'Ticket não encontrado.'], 404);
            }
            json_response(['status' => 200, 'data' => $ticket], 200);
        } catch (Exception $e) {
            json_response(['status' => 500, 'error' => $e->getMessage()], 500);
        }
    }

    public function store(): void
    {
        $user = $this->ensureAuthenticated();
        $payload = $this->decodeJsonBody();

        $remoteJid = trim((string) ($payload['remoteJid'] ?? ''));
        $contactName = isset($payload['contactName']) ? trim((string) $payload['contactName']) : null;
        $userId = isset($payload['user_id']) ? (int) $payload['user_id'] : (int) ($user->id ?? 0);

        if ($remoteJid === '') {
            json_response(['status' => 400, 'error' => 'remoteJid é obrigatório para iniciar um atendimento.'], 400);
        }

        try {
            $ticketId = $this->ticketService->startNativeConversation($remoteJid, $contactName, $userId);
            json_response(['status' => 201, 'data' => ['ticket_id' => $ticketId], 'message' => 'Ticket iniciado.'], 201);
        } catch (Exception $e) {
            json_response(['status' => 500, 'error' => $e->getMessage()], 500);
        }
    }

    public function updateStatus(int $id): void
    {
        $user = $this->ensureAuthenticated();
        $payload = $this->decodeJsonBody();

        $status = trim((string) ($payload['status'] ?? ''));
        if ($status === '') {
            json_response(['status' => 400, 'error' => 'status é obrigatório.'], 400);
        }

        try {
            $ticket = $this->ticketService->updateTicketStatus($id, $status, (int) ($user->id ?? 0));
            json_response(['status' => 200, 'message' => 'Status atualizado.', 'data' => $ticket], 200);
        } catch (Exception $e) {
            json_response(['status' => 500, 'error' => $e->getMessage()], 500);
        }
    }

    public function assign(int $id): void
    {
        $user = $this->ensureAuthenticated();
        
        try {
            $payload = $this->decodeJsonBody();
            $assigneeId = isset($payload['user_id']) ? (int) $payload['user_id'] : (int) ($user->id ?? 0);
            
            $this->ticketService->assignToUser($id, $assigneeId);
            json_response(['status' => 200, 'message' => 'Ticket atribuído.'], 200);
        } catch (Exception $e) {
            json_response(['status' => 500, 'error' => $e->getMessage()], 500);
        }
    }

    public function sendMessage(int $id): void
    {
        $user = $this->ensureAuthenticated();
        $payload = $this->decodeJsonBody();

        $body = trim((string) ($payload['body'] ?? ''));

        if ($body === '') {
            json_response(['status' => 400, 'error' => 'body é obrigatório.'], 400);
        }

        try {
            $this->ticketService->appendAgentMessage($id, (int) ($user->id ?? 0), $body, null);
            json_response(['status' => 201, 'message' => 'Mensagem adicionada e enviada.'], 201);
        } catch (Exception $e) {
            json_response(['status' => 500, 'error' => $e->getMessage()], 500);
        }
    }

    private function ensureAuthenticated(): object
    {
        $headers = getallheaders();
        $apiKey = $headers['x-api-key'] ?? $headers['X-API-Key'] ?? $headers['X-Api-Key'] ?? null;
        if ($apiKey !== null && isset($_ENV['API_KEY']) && $apiKey === $_ENV['API_KEY']) {
            return (object) ['id' => 1, 'role' => 'api']; 
        }

        $user = auth();
        if ($user === null) {
            json_response(['status' => 401, 'error' => 'Acesso não autorizado. Informe x-api-key no header ou use a sessão web.'], 401);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        return $user;
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
