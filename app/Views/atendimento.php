<?php
/** @var array<string, mixed> $ticket */
/** @var array<int, array<string, mixed>> $templates */

$user = auth();
$status = get_flash('auth_status');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Painel de Atendimento</title>
    <link href="/public/css/bootstrap.min.css" rel="stylesheet">
    <script src="/public/js/bootstrap.bundle.min.js" defer></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <style>
        body { background-color: #f4f6f9; }
        .chat-window { height: 65vh; overflow-y: auto; }
        .message { margin-bottom: 1rem; }
        .message.agent { text-align: right; }
        .message .bubble { display: inline-block; padding: .75rem 1rem; border-radius: 1rem; max-width: 75%; }
        .message.contact .bubble { background-color: #ffffff; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
        .message.agent .bubble { background-color: #0d6efd; color: #fff; }
        .template-list { max-height: 20rem; overflow-y: auto; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="/tickets">WhatsAtende</a>
        <div class="d-flex align-items-center gap-3">
            <?php if ($user): ?>
                <span class="text-light small"><?= htmlspecialchars($user->full_name ?? '') ?></span>
                <form method="POST" action="/logout" class="m-0">
                    <button type="submit" class="btn btn-outline-light btn-sm">Sair</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</nav>
<div class="container-fluid py-4">
    <?php if (!empty($status)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($status) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    <?php endif; ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0"><?= htmlspecialchars($ticket['contact_name'] ?? 'Cliente') ?></h5>
                        <small class="text-muted">Ticket #<?= htmlspecialchars((string)$ticket['id']) ?> · Canal: <?= htmlspecialchars($ticket['channel'] ?? 'whatsapp') ?></small>
                    </div>
                    <button class="btn btn-outline-success btn-sm" id="resolve-ticket">Finalizar Atendimento</button>
                </div>
                <div class="card-body chat-window" id="chat-window">
                    <?php foreach ($ticket['messages'] ?? [] as $message): ?>
                        <div class="message <?= $message['sender_type'] === 'agent' ? 'agent' : 'contact' ?>">
                            <div class="bubble">
                                <?php if ($message['media_type'] === 'image' && !empty($message['media_url'])): ?>
                                    <img src="<?= htmlspecialchars($message['media_url']) ?>" class="img-fluid rounded" alt="Imagem enviada">
                                <?php elseif ($message['media_type'] === 'audio' && !empty($message['media_url'])): ?>
                                    <audio controls src="<?= htmlspecialchars($message['media_url']) ?>"></audio>
                                <?php else: ?>
                                    <?= nl2br(htmlspecialchars($message['body'] ?? '')) ?>
                                <?php endif; ?>
                                <div class="small text-muted mt-1">
                                    <?= htmlspecialchars(date('H:i', strtotime($message['sent_at']))) ?>
                                    <?php if (!empty($message['agent_name'])): ?> · <?= htmlspecialchars($message['agent_name']) ?><?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="card-footer">
                    <form id="message-form" class="d-flex gap-2">
                        <textarea class="form-control" id="message" rows="2" placeholder="Digite sua mensagem..." required></textarea>
                        <button class="btn btn-primary align-self-end" type="submit">Enviar</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Templates de Mensagens</h6>
                </div>
                <div class="card-body template-list">
                    <?php foreach ($templates as $template): ?>
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <strong><?= htmlspecialchars($template['title']) ?></strong>
                                <p class="mb-1 small text-muted"><?= nl2br(htmlspecialchars($template['body'])) ?></p>
                            </div>
                            <button class="btn btn-sm btn-outline-primary" data-template="<?= htmlspecialchars($template['body']) ?>">Usar</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="card shadow-sm">
                <div class="card-header">
                    <h6 class="mb-0">Detalhes do Chamado</h6>
                </div>
                <div class="card-body">
                    <p class="mb-1"><strong>Status:</strong> <span class="badge bg-info text-dark" id="ticket-status"><?= htmlspecialchars($ticket['status'] ?? '') ?></span></p>
                    <p class="mb-1"><strong>Aberto em:</strong> <?= htmlspecialchars(date('d/m/Y H:i', strtotime($ticket['opened_at'] ?? 'now'))) ?></p>
                    <p class="mb-0"><strong>Responsável:</strong> <?= htmlspecialchars($ticket['agent_name'] ?? 'Você') ?></p>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    const ticketId = <?= (int)($ticket['id'] ?? 0) ?>;

    $(function() {
        const $chatWindow = $('#chat-window');
        $chatWindow.scrollTop($chatWindow.prop('scrollHeight'));

        $('#message-form').on('submit', function(event) {
            event.preventDefault();
            const message = $('#message').val().trim();
            if (!message) { return; }

            $.ajax({
                url: `/tickets/${ticketId}/messages`,
                method: 'POST',
                data: { message },
                success: function() {
                    $('#message').val('');
                    fetchMessages();
                },
                error: function(xhr) {
                    alert(xhr.responseJSON?.error || 'Não foi possível enviar a mensagem.');
                }
            });
        });

        $('.template-list button').on('click', function() {
            const template = $(this).data('template');
            $('#message').val(template);
            $('#message').focus();
        });

        $('#resolve-ticket').on('click', function() {
            if (!confirm('Deseja finalizar este atendimento?')) { return; }
            $.post(`/tickets/${ticketId}/resolve`).done(function() {
                $('#ticket-status').text('resolved').removeClass().addClass('badge bg-success');
            });
        });

        function fetchMessages() {
            $.getJSON(`/tickets/${ticketId}/messages`).done(function(response) {
                if (!Array.isArray(response)) { return; }
                $chatWindow.empty();
                response.forEach(renderMessage);
                $chatWindow.scrollTop($chatWindow.prop('scrollHeight'));
            });
        }

        function renderMessage(message) {
            const isAgent = message.sender_type === 'agent';
            let bodyHtml = '';
            if (message.media_type === 'image' && message.media_url) {
                bodyHtml = `<img src="${message.media_url}" class="img-fluid rounded" alt="Imagem">`;
            } else if (message.media_type === 'audio' && message.media_url) {
                bodyHtml = `<audio controls src="${message.media_url}"></audio>`;
            } else {
                bodyHtml = $('<div>').text(message.body || '').html().replace(/\n/g, '<br>');
            }

            const time = message.sent_at ? new Date(message.sent_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) : '';
            const agentName = message.agent_name ? ` · ${message.agent_name}` : '';
            const bubble = `
                <div class="message ${isAgent ? 'agent' : 'contact'}">
                    <div class="bubble">
                        ${bodyHtml}
                        <div class="small text-muted mt-1">${time}${agentName}</div>
                    </div>
                </div>`;
            $chatWindow.append(bubble);
        }

        setInterval(fetchMessages, 5000);
    });
</script>
</body>
</html>
