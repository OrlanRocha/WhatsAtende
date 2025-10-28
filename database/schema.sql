-- MariaDB schema for WhatsAtende helpdesk system

CREATE DATABASE IF NOT EXISTS whats_atende
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;
USE whats_atende;

CREATE TABLE IF NOT EXISTS roles (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    label VARCHAR(150) NOT NULL,
    description VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (name) VALUES ('admin'), ('agent'), ('supervisor'), ('dev')
    ON DUPLICATE KEY UPDATE name = VALUES(name);

CREATE TABLE IF NOT EXISTS users (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_permissions (
    user_id BIGINT UNSIGNED NOT NULL,
    permission_id SMALLINT UNSIGNED NOT NULL,
    granted_by BIGINT UNSIGNED NULL,
    granted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, permission_id),
    CONSTRAINT fk_user_permissions_user FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_user_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_user_permissions_granted_by FOREIGN KEY (granted_by) REFERENCES users(id)
        ON DELETE SET NULL,
    INDEX idx_user_permissions_permission (permission_id),
    INDEX idx_user_permissions_granted_by (granted_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_status (
    user_id BIGINT UNSIGNED PRIMARY KEY,
    online TINYINT(1) NOT NULL DEFAULT 0,
    last_seen_at DATETIME NULL,
    current_load SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    skills JSON NULL,
    CHECK (skills IS NULL OR JSON_VALID(skills)),
    CONSTRAINT fk_user_status_user FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contacts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    external_id VARCHAR(190) NOT NULL UNIQUE,
    display_name VARCHAR(150) NULL,
    phone VARCHAR(25) NULL,
    email VARCHAR(190) NULL,
    last_interaction_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tickets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contact_id BIGINT UNSIGNED NOT NULL,
    subject VARCHAR(191) NULL,
    status ENUM('open','assigned','resolved','closed') NOT NULL DEFAULT 'open',
    priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    predicted_priority ENUM('low','normal','high','critical') NULL,
    assigned_user_id BIGINT UNSIGNED NULL,
    opened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    closed_at DATETIME NULL,
    sla_due_at DATETIME NULL,
    channel VARCHAR(60) NOT NULL DEFAULT 'whatsapp',
    CONSTRAINT fk_tickets_contact FOREIGN KEY (contact_id) REFERENCES contacts(id)
        ON UPDATE CASCADE,
    CONSTRAINT fk_tickets_assigned_user FOREIGN KEY (assigned_user_id) REFERENCES users(id)
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ticket_metrics (
    ticket_id BIGINT UNSIGNED PRIMARY KEY,
    first_response_at DATETIME NULL,
    last_touch_at DATETIME NULL,
    queue_time_sec INT UNSIGNED NULL,
    resolution_time_sec INT UNSIGNED NULL,
    sla_due_at DATETIME NULL,
    sla_status ENUM('unset','ok','warning','breach') NOT NULL DEFAULT 'unset',
    CONSTRAINT fk_ticket_metrics_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id BIGINT UNSIGNED NOT NULL,
    sender_type ENUM('contact','agent','system') NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    body TEXT NULL,
    media_type ENUM('text','image','audio','video','file') NOT NULL DEFAULT 'text',
    media_url VARCHAR(255) NULL,
    metadata LONGTEXT NULL,
    CHECK (metadata IS NULL OR JSON_VALID(metadata)),
    sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_messages_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_messages_user FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS templates (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_id BIGINT UNSIGNED NULL,
    corr_id VARCHAR(64) NOT NULL,
    level ENUM('debug','info','warning','error','critical') NOT NULL DEFAULT 'info',
    service VARCHAR(80) NOT NULL DEFAULT 'sistema',
    action VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    context LONGTEXT NULL,
    CHECK (context IS NULL OR JSON_VALID(context)),
    ip_address VARCHAR(45) NULL,
    route VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_logs_actor FOREIGN KEY (actor_id) REFERENCES users(id)
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS webhook_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(80) NOT NULL,
    external_message_id VARCHAR(191) NULL,
    payload LONGTEXT NOT NULL,
    CHECK (JSON_VALID(payload)),
    processed TINYINT(1) NOT NULL DEFAULT 0,
    processed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_webhook_provider_message (provider, external_message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    requested_ip VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    UNIQUE KEY uq_password_resets_token (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    last_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    locked_until DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_login_attempts_email_ip (email, ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(120) NOT NULL UNIQUE,
    value TEXT NULL,
    description VARCHAR(255) NULL,
    updated_by BIGINT UNSIGNED NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_settings_updated_by FOREIGN KEY (updated_by) REFERENCES users(id)
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS live_snapshots (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bucket DATETIME NOT NULL,
    payload JSON NOT NULL,
    CHECK (JSON_VALID(payload)),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS capacity_slots (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date_hour DATETIME NOT NULL,
    demand_p50 INT UNSIGNED NOT NULL DEFAULT 0,
    demand_p80 INT UNSIGNED NOT NULL DEFAULT 0,
    demand_p95 INT UNSIGNED NOT NULL DEFAULT 0,
    required_agents_p50 INT UNSIGNED NOT NULL DEFAULT 0,
    required_agents_p80 INT UNSIGNED NOT NULL DEFAULT 0,
    required_agents_p95 INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agent_shift (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    agent_id BIGINT UNSIGNED NOT NULL,
    start_at DATETIME NOT NULL,
    end_at DATETIME NOT NULL,
    channel VARCHAR(60) NOT NULL DEFAULT 'whatsapp',
    notes VARCHAR(255) NULL,
    CONSTRAINT fk_agent_shift_user FOREIGN KEY (agent_id) REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS calendar_overrides (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    factor DECIMAL(5,2) NOT NULL DEFAULT 1.00,
    reason VARCHAR(191) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ticket_ai_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id BIGINT UNSIGNED NOT NULL,
    type ENUM('summary','classify','kb','pii') NOT NULL,
    payload JSON NULL,
    confidence DECIMAL(5,4) NULL,
    model_version VARCHAR(50) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actor_id BIGINT UNSIGNED NULL,
    CHECK (payload IS NULL OR JSON_VALID(payload)),
    CONSTRAINT fk_ticket_ai_events_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_ticket_ai_events_actor FOREIGN KEY (actor_id) REFERENCES users(id)
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ticket_summary (
    ticket_id BIGINT UNSIGNED PRIMARY KEY,
    short TEXT NULL,
    medium TEXT NULL,
    full MEDIUMTEXT NULL,
    model_version VARCHAR(50) NULL,
    confidence DECIMAL(5,4) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NULL,
    CONSTRAINT fk_ticket_summary_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_ticket_summary_user FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kb_articles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(191) NOT NULL,
    body_md MEDIUMTEXT NOT NULL,
    tags JSON NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    CHECK (tags IS NULL OR JSON_VALID(tags))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kb_vectors (
    article_id BIGINT UNSIGNED PRIMARY KEY,
    vector BLOB NOT NULL,
    dim SMALLINT UNSIGNED NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_kb_vectors_article FOREIGN KEY (article_id) REFERENCES kb_articles(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_tickets_status ON tickets(status);
CREATE INDEX idx_tickets_assigned_user ON tickets(assigned_user_id);
CREATE INDEX idx_ticket_sla_due_at ON tickets(sla_due_at);
CREATE INDEX idx_ticket_predicted_priority ON tickets(predicted_priority);
CREATE INDEX idx_messages_ticket_sent_at ON messages(ticket_id, sent_at);
CREATE INDEX idx_messages_created_at ON messages(sent_at);
CREATE INDEX idx_logs_corr ON logs(corr_id);
CREATE INDEX idx_logs_service_level ON logs(service, level);
CREATE INDEX idx_logs_created_at ON logs(created_at);
CREATE INDEX idx_user_status_online_load ON user_status(online, current_load);
CREATE INDEX idx_live_snapshots_bucket ON live_snapshots(bucket);
CREATE UNIQUE INDEX idx_capacity_slots_date_hour ON capacity_slots(date_hour);
CREATE INDEX idx_agent_shift_window ON agent_shift(agent_id, start_at, end_at);
CREATE UNIQUE INDEX idx_calendar_overrides_date ON calendar_overrides(date);
CREATE INDEX idx_ticket_ai_events_ticket ON ticket_ai_events(ticket_id);
CREATE INDEX idx_kb_vectors_article ON kb_vectors(article_id);
CREATE INDEX idx_password_resets_expires_at ON password_resets(expires_at);
