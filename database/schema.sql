-- MariaDB schema for WhatsAtende helpdesk system

CREATE TABLE roles (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
);

INSERT INTO roles (name) VALUES ('admin'), ('agent');

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id TINYINT UNSIGNED NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    cpf CHAR(11) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
        ON UPDATE CASCADE
);

CREATE TABLE contacts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    external_id VARCHAR(190) NOT NULL UNIQUE,
    display_name VARCHAR(150) NULL,
    phone VARCHAR(25) NULL,
    email VARCHAR(190) NULL,
    last_interaction_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE tickets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contact_id BIGINT UNSIGNED NOT NULL,
    subject VARCHAR(191) NULL,
    status ENUM('open','assigned','resolved','closed') NOT NULL DEFAULT 'open',
    priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    assigned_user_id BIGINT UNSIGNED NULL,
    opened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    closed_at DATETIME NULL,
    sla_due_at DATETIME NULL,
    channel VARCHAR(60) NOT NULL DEFAULT 'whatsapp',
    CONSTRAINT fk_tickets_contact FOREIGN KEY (contact_id) REFERENCES contacts(id)
        ON UPDATE CASCADE,
    CONSTRAINT fk_tickets_assigned_user FOREIGN KEY (assigned_user_id) REFERENCES users(id)
        ON UPDATE CASCADE
);

CREATE TABLE ticket_metrics (
    ticket_id BIGINT UNSIGNED PRIMARY KEY,
    first_response_at DATETIME NULL,
    last_response_at DATETIME NULL,
    resolution_time_seconds INT UNSIGNED NULL,
    CONSTRAINT fk_ticket_metrics_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id)
        ON DELETE CASCADE
);

CREATE TABLE messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id BIGINT UNSIGNED NOT NULL,
    sender_type ENUM('contact','agent','system') NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    body TEXT NULL,
    media_type ENUM('text','image','audio','video','file') NOT NULL DEFAULT 'text',
    media_url VARCHAR(255) NULL,
    metadata JSON NULL,
    sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_messages_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_messages_user FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE
);

CREATE TABLE templates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    body TEXT NOT NULL,
    category VARCHAR(100) NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_templates_created_by FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE,
    CONSTRAINT fk_templates_updated_by FOREIGN KEY (updated_by) REFERENCES users(id)
        ON UPDATE CASCADE
);

CREATE TABLE logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    level ENUM('info','warning','error','critical') NOT NULL DEFAULT 'info',
    action VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    context JSON NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_logs_user FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE
);

CREATE TABLE webhook_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(80) NOT NULL,
    external_message_id VARCHAR(191) NULL,
    payload JSON NOT NULL,
    processed TINYINT(1) NOT NULL DEFAULT 0,
    processed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_webhook_provider_message (provider, external_message_id)
);

CREATE INDEX idx_tickets_status ON tickets(status);
CREATE INDEX idx_tickets_assigned_user ON tickets(assigned_user_id);
CREATE INDEX idx_messages_ticket_sent_at ON messages(ticket_id, sent_at);
CREATE INDEX idx_logs_created_at ON logs(created_at);
