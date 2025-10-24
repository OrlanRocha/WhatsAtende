<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\LoggerService;
use App\Services\TicketService;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class TicketController
{
    public function __construct(
        private TicketService $ticketService,
        private LoggerService $logger
    ) {
    }

    public function index(): void
    {
        require_auth();
        $queue = $this->ticketService->getOpenQueue();
        $native = $this->ticketService->listNativeChats();

        if (is_ajax()) {
            json_response([
                'queue' => $queue,
                'native' => $native,
            ]);
        }

        view('tickets/queue', [
            'queue' => $queue,
            'pageId' => 'queue-page',
            'queueTitle' => 'Fila em tempo real',
            'queueDescription' => 'Chamados aguardando atribuição atualizam automaticamente a cada 10 segundos.',
            'queueEndpoint' => '/tickets',
            'showTodayLink' => true,
            'showAllLink' => false,
            'nativeChats' => $native,
            'nativeStartEndpoint' => '/tickets/native/start',
        ]);
    }

    public function overview(): void
    {
        $user = require_auth();

        $status = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: null;
        $priority = filter_input(INPUT_GET, 'priority', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: null;
        $search = filter_input(INPUT_GET, 'q', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: null;
        $mine = filter_input(INPUT_GET, 'mine', FILTER_VALIDATE_BOOL);
        $excludeResolved = filter_input(INPUT_GET, 'hide_resolved', FILTER_VALIDATE_BOOL);

        $filters = [];
        if ($status) {
            $filters['status'] = $status;
        }
        if ($priority) {
            $filters['priority'] = $priority;
        }
        if ($search) {
            $filters['search'] = $search;
        }
        if ($mine) {
            $filters['assigned'] = (int) $user->id;
        }
        if ($excludeResolved) {
            $filters['exclude'] = ['resolved', 'closed'];
        }

        $tickets = $this->ticketService->listTickets($filters);

        $statusMap = [
            'open' => 0,
            'assigned' => 0,
            'resolved' => 0,
            'closed' => 0,
            'waiting' => 0,
            'breach' => 0,
        ];

        foreach ($tickets as $ticket) {
            $statusKey = $ticket['status'] ?? 'open';
            if (isset($statusMap[$statusKey])) {
                $statusMap[$statusKey]++;
            }
            if (($ticket['status'] ?? '') === 'open' && empty($ticket['agent_name'])) {
                $statusMap['waiting']++;
            }
            if (($ticket['sla_status'] ?? '') === 'breach') {
                $statusMap['breach']++;
            }
        }

        json_response([
            'tickets' => $tickets,
            'summary' => $statusMap,
        ]);
    }

    public function today(): void
    {
        require_auth();

        $today = new DateTimeImmutable('today');
        $queue = $this->ticketService->getOpenQueue($today);
        $native = $this->ticketService->listNativeChats();

        if (is_ajax()) {
            json_response([
                'queue' => $queue,
                'native' => $native,
            ]);
        }

        view('tickets/queue', [
            'queue' => $queue,
            'pageId' => 'today-queue-page',
            'queueTitle' => 'Chamados iniciados hoje',
            'queueDescription' => 'Acompanhe apenas os atendimentos que começaram na data atual.',
            'queueEndpoint' => '/tickets/today',
            'showTodayLink' => false,
            'showAllLink' => true,
            'nativeChats' => $native,
            'nativeStartEndpoint' => '/tickets/native/start',
        ]);
    }

    public function show(int $ticketId): void
    {
        require_auth();
        try {
            $ticket = $this->ticketService->getTicketWithMessages($ticketId);
        } catch (Throwable $exception) {
            $this->logger->error('ticket.show_failed', [
                'ticket_id' => $ticketId,
                'message' => $exception->getMessage(),
            ]);

            http_response_code(404);
            echo 'Ticket not found';
            return;
        }

        $templates = $this->ticketService->listMessageTemplates();

        view('tickets/show', [
            'ticket' => $ticket,
            'templates' => $templates,
        ]);
    }

    public function assign(int $ticketId): void
    {
        $user = require_auth();
        if (!in_array($user->role ?? null, ['admin', 'agent', 'dev'], true)) {
            if (is_ajax()) {
                json_response(['message' => 'Acesso restrito a atendentes.'], 403);
            }
            http_response_code(403);
            echo 'Acesso restrito a atendentes.';
            return;
        }
        $userId = (int) $user->id;
        $this->ticketService->assignToUser($ticketId, $userId, null);

        if (is_ajax()) {
            json_response([
                'message' => 'Chamado atribuído com sucesso.',
                'redirect' => route_path('/tickets/' . $ticketId),
            ]);
        }

        redirect('/tickets/' . $ticketId);
    }

    public function startNativeConversation(): void
    {
        $user = require_auth();
        if (!in_array($user->role ?? null, ['admin', 'agent', 'dev'], true)) {
            json_response(['error' => 'Acesso restrito a atendentes.'], 403);
            return;
        }

        $payload = $this->getRequestPayload();

        $remoteJid = trim((string) ($payload['remote_jid'] ?? $_POST['remote_jid'] ?? ''));
        $name = trim((string) ($payload['name'] ?? $_POST['name'] ?? ''));
        $lastMessageId = trim((string) ($payload['last_message_id'] ?? $_POST['last_message_id'] ?? ''));

        try {
            $ticketId = $this->ticketService->startNativeConversation(
                $remoteJid,
                $name !== '' ? $name : null,
                (int) $user->id,
                $lastMessageId !== '' ? $lastMessageId : null
            );
        } catch (InvalidArgumentException $exception) {
            $this->logger->warning('ticket.native_start_validation_failed', [
                'user_id' => $user->id ?? null,
                'error' => $exception->getMessage(),
            ]);
            json_response(['error' => $exception->getMessage()], 422);
            return;
        } catch (RuntimeException $exception) {
            $this->logger->error('ticket.native_start_failed', [
                'user_id' => $user->id ?? null,
                'error' => $exception->getMessage(),
            ]);
            json_response(['error' => $exception->getMessage()], 400);
            return;
        } catch (Throwable $exception) {
            $this->logger->error('ticket.native_start_failed', [
                'user_id' => $user->id ?? null,
                'error' => $exception->getMessage(),
            ]);
            json_response(['error' => 'Não foi possível iniciar a conversa.'], 500);
            return;
        }

        json_response([
            'message' => 'Conversa iniciada com sucesso.',
            'ticket_id' => $ticketId,
            'redirect' => route_path('/tickets/' . $ticketId),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function getRequestPayload(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        if (is_string($contentType) && str_contains($contentType, 'application/json')) {
            $rawInput = file_get_contents('php://input');
            if ($rawInput !== false && $rawInput !== '') {
                $decoded = json_decode($rawInput, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        return [];
    }

    public function storeMessage(int $ticketId): void
    {
        $user = require_auth();
        if (!in_array($user->role ?? null, ['admin', 'agent', 'dev'], true)) {
            if (is_ajax()) {
                json_response(['message' => 'Acesso restrito a atendentes.'], 403);
            }
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Acesso restrito a atendentes.']);
            return;
        }
        $body = trim($_POST['message'] ?? '');
        try {
            $attachment = $this->prepareAttachment($ticketId);
        } catch (InvalidArgumentException $exception) {
            http_response_code(422);
            header('Content-Type: application/json');
            echo json_encode(['error' => $exception->getMessage()]);
            return;
        } catch (RuntimeException $exception) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => $exception->getMessage()]);
            return;
        }

        if ($body === '' && $attachment === null) {
            http_response_code(422);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Informe uma mensagem ou selecione um arquivo para envio.']);
            return;
        }

        $userId = (int) $user->id;

        try {
            $this->ticketService->appendAgentMessage($ticketId, $userId, $body, $attachment);
        } catch (RuntimeException $exception) {
            $this->logger->error('ticket.store_message_failed', [
                'ticket_id' => $ticketId,
                'user_id' => $userId,
                'error' => $exception->getMessage(),
            ]);

            json_response(['error' => $exception->getMessage()], 502);
            return;
        } catch (Throwable $exception) {
            $this->logger->error('ticket.store_message_failed', [
                'ticket_id' => $ticketId,
                'user_id' => $userId,
                'error' => $exception->getMessage(),
            ]);

            json_response(['error' => 'Não foi possível registrar a mensagem.'], 500);
            return;
        }

        json_response(['message' => 'Mensagem enviada.']);
    }

    /**
     * @return array{path:string,url:string,mime:string,type:string,name:string}|null
     */
    private function prepareAttachment(int $ticketId): ?array
    {
        if (!isset($_FILES['attachment']) || !is_array($_FILES['attachment'])) {
            return null;
        }

        $file = $_FILES['attachment'];
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Falha ao processar o upload do arquivo.');
        }

        $tmpPath = $file['tmp_name'] ?? '';
        if (!is_string($tmpPath) || $tmpPath === '' || !is_uploaded_file($tmpPath)) {
            throw new RuntimeException('Upload inválido recebido pelo servidor.');
        }

        $mime = mime_content_type($tmpPath) ?: 'application/octet-stream';
        $type = $this->detectMediaType($mime);
        if ($type === null) {
            throw new InvalidArgumentException('Apenas arquivos de imagem, áudio ou vídeo são suportados.');
        }

        $extension = $this->guessExtension($mime, $type);
        $directory = base_path('public/uploads/tickets/' . $ticketId);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Não foi possível preparar o diretório de anexos.');
        }

        try {
            $filename = bin2hex(random_bytes(12)) . ($extension !== '' ? '.' . $extension : '');
        } catch (Throwable $exception) {
            throw new RuntimeException('Não foi possível preparar o arquivo para envio.');
        }
        $destination = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($tmpPath, $destination)) {
            throw new RuntimeException('Falha ao armazenar o anexo enviado.');
        }

        $publicUrl = '/uploads/tickets/' . $ticketId . '/' . $filename;

        return [
            'path' => $destination,
            'url' => $publicUrl,
            'mime' => $mime,
            'type' => $type,
            'name' => is_string($file['name'] ?? null) ? (string) $file['name'] : $filename,
        ];
    }

    private function detectMediaType(string $mime): ?string
    {
        $mime = strtolower($mime);
        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }

        if (str_starts_with($mime, 'audio/')) {
            return 'audio';
        }

        if (str_starts_with($mime, 'video/')) {
            return 'video';
        }

        return null;
    }

    private function guessExtension(string $mime, string $type): string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'audio/mpeg' => 'mp3',
            'audio/ogg' => 'ogg',
            'audio/wav' => 'wav',
            'audio/webm' => 'webm',
            'audio/mp4' => 'm4a',
            'audio/aac' => 'aac',
            'video/mp4' => 'mp4',
            'video/ogg' => 'ogv',
            'video/webm' => 'webm',
            'video/quicktime' => 'mov',
        ];

        if (isset($map[$mime])) {
            return $map[$mime];
        }

        return match ($type) {
            'image' => 'jpg',
            'audio' => 'mp3',
            'video' => 'mp4',
            default => '',
        };
    }


    public function messages(int $ticketId): void
    {
        require_auth();

        try {
            $ticket = $this->ticketService->getTicketWithMessages($ticketId);
            json_response($ticket['messages'] ?? []);
        } catch (\Throwable $exception) {
            $this->logger->error('ticket.messages_fetch_failed', [
                'ticket_id' => $ticketId,
                'error' => $exception->getMessage(),
            ]);

            json_response([
                'messages' => [],
                'status' => 'missing',
                'error' => 'Ticket não encontrado ou inacessível.',
            ]);
        }
    }

    public function resolve(int $ticketId): void
    {
        $user = require_auth();
        if (!in_array($user->role ?? null, ['admin', 'agent', 'dev'], true)) {
            if (is_ajax()) {
                json_response(['message' => 'Acesso restrito a atendentes.'], 403);
            }
            http_response_code(403);
            echo 'Acesso restrito a atendentes.';
            return;
        }
        $this->ticketService->resolveTicket($ticketId);

        if (is_ajax()) {
            json_response([
                'message' => 'Chamado finalizado com sucesso.',
                'redirect' => route_path('/tickets'),
            ]);
        }

        redirect('/tickets');
    }
}
