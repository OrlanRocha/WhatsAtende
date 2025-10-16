-- Seed data for WhatsAtende helpdesk system
USE whats_atende;

-- Ensure base roles exist with consistent identifiers
INSERT INTO roles (id, name) VALUES
    (1, 'admin'),
    (2, 'agent')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Seed administrative and helpdesk users
INSERT INTO users (
    id,
    role_id,
    full_name,
    email,
    cpf,
    password_hash,
    is_active,
    last_login_at,
    created_at,
    updated_at
) VALUES
    (1, 1, 'Administrador Master', 'admin@example.com', '00000000000', '$2y$12$eAezT0UJq1OxJfo7cud.zulzFO4/nOWRLaeiwt6obglGLF4CHmjly', 1, NULL, NOW(), NOW()),
    (2, 2, 'Atendente Padrão', 'agent@example.com', '11111111111', '$2y$12$4708I1nxNggebtpoUQ8TEeM1jVrjGxGgHc1Shre7nIypixM41MoSC', 1, NULL, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    full_name = VALUES(full_name),
    role_id = VALUES(role_id),
    is_active = VALUES(is_active),
    password_hash = VALUES(password_hash);

-- Seed default message templates
INSERT INTO templates (
    id,
    title,
    body,
    category,
    created_by,
    updated_by,
    created_at,
    updated_at
) VALUES
    (1, 'Saudação inicial', 'Olá! Obrigado por entrar em contato com o suporte. Como posso ajudar você hoje?', 'Saudações', 1, 1, NOW(), NOW()),
    (2, 'Encerramento', 'Seu atendimento foi finalizado. Caso precise de algo mais, estamos à disposição!', 'Finalização', 1, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    body = VALUES(body),
    category = VALUES(category),
    updated_by = VALUES(updated_by),
    updated_at = NOW();

-- Seed contacts used in sample tickets
INSERT INTO contacts (
    id,
    external_id,
    display_name,
    phone,
    email,
    last_interaction_at,
    created_at,
    updated_at
) VALUES
    (1, 'whatsapp:+5500000000000', 'Cliente Teste', '+55 00 00000-0000', 'cliente@example.com', NOW(), NOW(), NOW())
ON DUPLICATE KEY UPDATE
    display_name = VALUES(display_name),
    phone = VALUES(phone),
    email = VALUES(email),
    last_interaction_at = VALUES(last_interaction_at),
    updated_at = NOW();

-- Seed tickets with metrics and messages
INSERT INTO tickets (
    id,
    contact_id,
    subject,
    status,
    priority,
    assigned_user_id,
    opened_at,
    closed_at,
    sla_due_at,
    channel
) VALUES
    (1, 1, 'Dúvida sobre faturamento', 'assigned', 'normal', 2, NOW() - INTERVAL 2 HOUR, NULL, NOW() + INTERVAL 2 HOUR, 'whatsapp')
ON DUPLICATE KEY UPDATE
    status = VALUES(status),
    priority = VALUES(priority),
    assigned_user_id = VALUES(assigned_user_id),
    sla_due_at = VALUES(sla_due_at);

INSERT INTO ticket_metrics (
    ticket_id,
    first_response_at,
    last_response_at,
    resolution_time_seconds
) VALUES
    (1, NOW() - INTERVAL 90 MINUTE, NOW() - INTERVAL 15 MINUTE, NULL)
ON DUPLICATE KEY UPDATE
    first_response_at = VALUES(first_response_at),
    last_response_at = VALUES(last_response_at);

INSERT INTO messages (
    id,
    ticket_id,
    sender_type,
    user_id,
    body,
    media_type,
    media_url,
    metadata,
    sent_at
) VALUES
    (1, 1, 'contact', NULL, 'Olá, preciso de ajuda com a fatura deste mês.', 'text', NULL, NULL, NOW() - INTERVAL 2 HOUR),
    (2, 1, 'agent', 2, 'Olá! Claro, posso ajudar. Você poderia informar o número da fatura?', 'text', NULL, NULL, NOW() - INTERVAL 90 MINUTE)
ON DUPLICATE KEY UPDATE
    body = VALUES(body),
    sender_type = VALUES(sender_type),
    user_id = VALUES(user_id),
    sent_at = VALUES(sent_at);

-- Seed a log entry to illustrate auditing
INSERT INTO logs (
    id,
    user_id,
    level,
    action,
    message,
    context,
    ip_address,
    created_at
) VALUES
    (1, 1, 'info', 'seed_import', 'Dados de exemplo carregados para demonstração.', NULL, '127.0.0.1', NOW())
ON DUPLICATE KEY UPDATE
    message = VALUES(message),
    created_at = NOW();

INSERT INTO settings (
    id,
    `key`,
    value,
    description,
    updated_by,
    updated_at
) VALUES
    (1, 'webhook_url', 'https://seu-dominio.com/api/webhook', 'Endpoint exposto para Evolution API ou n8n.', 1, NOW()),
    (2, 'webhook_token', 'changeme-token', 'Token utilizado para validar chamadas recebidas.', 1, NOW())
ON DUPLICATE KEY UPDATE
    value = VALUES(value),
    description = VALUES(description),
    updated_by = VALUES(updated_by),
    updated_at = NOW();
