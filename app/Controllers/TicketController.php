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
        if (!in_array($user->role ?? null, ['admin', 'agent'], true)) {
            if (is_ajax()) {
                json_response(['message' => 'Acesso restrito a atendentes.'], 403);
            }
            http_response_code(403);
            echo 'Acesso restrito a atendentes.';
            return;
        }
        $userId = (int) $user->id;
        $this->ticketService->assignToUser($ticketId, $userId);

        if (is_ajax()) {
            json_response([
                'message' => 'Chamado atribuído com sucesso.',
                'redirect' => '/tickets/' . $ticketId,
            ]);
        }

        redirect('/tickets/' . $ticketId);
    }

    public function startNativeConversation(): void
    {
        $user = require_auth();
        if (!in_array($user->role ?? null, ['admin', 'agent'], true)) {
            json_response(['error' => 'Acesso restrito a atendentes.'], 403);
            return;
        }

        $payload = $this->getRequestPayload();

        $remoteJid = trim((string) ($payload['remote_jid'] ?? $_POST['remote_jid'] ?? ''));
        $name = trim((string) ($payload['name'] ?? $_POST['name'] ?? ''));

        try {
            $ticketId = $this->ticketService->startNativeConversation(
                $remoteJid,
                $name !== '' ? $name : null,
                (int) $user->id
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
            'redirect' => '/tickets/' . $ticketId,
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
        if (!in_array($user->role ?? null, ['admin', 'agent'], true)) {
            if (is_ajax()) {
                json_response(['message' => 'Acesso restrito a atendentes.'], 403);
            }
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Acesso restrito a atendentes.']);
            return;
        }
        $body = trim($_POST['message'] ?? '');
        if ($body === '') {
            http_response_code(422);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Message body is required.']);
            return;
        }

        $userId = (int) $user->id;

        try {
            $this->ticketService->appendAgentMessage($ticketId, $userId, $body);
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


    public function messages(int $ticketId): void
    {
        require_auth();
        try {
            $ticket = $this->ticketService->getTicketWithMessages($ticketId);
        } catch (\Throwable $exception) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode([]);
            return;
        }

        header('Content-Type: application/json');
        echo json_encode($ticket['messages'] ?? []);
    }

    public function resolve(int $ticketId): void
    {
        $user = require_auth();
        if (!in_array($user->role ?? null, ['admin', 'agent'], true)) {
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
                'redirect' => '/tickets',
            ]);
        }

        redirect('/tickets');
    }
}
