# WhatsAtende - Estrutura Inicial

Este repositório contém a proposta de arquitetura para o sistema de atendimento de chamados via chat, construído em PHP 8.4+, seguindo o padrão MVC com serviços especializados e integração por webhook com a Evolution API.

## Estrutura de Diretórios

```
app/
  helpers.php
  Controllers/
    Admin/
      DashboardController.php
      LogController.php
      TemplateController.php
      TicketController.php
      UserController.php
      WebhookController.php
    AuthController.php
    TicketController.php
    WebhookController.php
  Models/
    Ticket.php
  Services/
    AuthService.php
    DashboardService.php
    LoggerService.php
    SettingService.php
    TicketService.php
    TemplateService.php
    UserService.php
    WebhookService.php
  Views/
    admin/
      dashboard.php
      logs/index.php
      partials/nav.php
      templates/index.php
      tickets/index.php
      users/form.php
      users/index.php
      webhook/index.php
    auth/
      forgot.php
      login.php
      register.php
      reset.php
    atendimento.php
config/
  database.php
database/
  schema.sql
  seeds.sql
docs/
  flow.md
public/
  index.php
  css/
  img/
  js/
routes/
  web.php
```

## Schema do Banco de Dados

O arquivo [`database/schema.sql`](database/schema.sql) traz o SQL completo para criação das tabelas `users`, `roles`, `contacts`, `tickets`, `ticket_metrics`, `messages`, `templates`, `logs`, `password_resets`, `login_attempts`, `settings` e `webhook_events`, com índices e relacionamentos adequados ao MariaDB.

Para popular o ambiente com dados de exemplo (contas iniciais, templates e um ticket demonstrativo), execute também [`database/seeds.sql`](database/seeds.sql) após criar o schema.

> Credenciais iniciais:
> - Admin: `admin@example.com` / `Admin123!`
> - Atendente: `agent@example.com` / `Agent123!`

## Configuração de Ambiente

- Copie o arquivo `.env.example` para `.env` e ajuste as variáveis obrigatórias:
  - `ADMIN_USERNAME` e `ADMIN_PASSWORD` são usados na preparação inicial do ambiente.
  - `EVO_API_BASE`, `EVO_INSTANCE` e `EVO_API_KEY` habilitam a integração nativa com a Evolution API.
  - Caso utilize Webhook, defina também `WEBHOOK_URL` e `WEBHOOK_TOKEN`.
- Os valores ausentes são reportados no arquivo de log `storage/logs/app.log`, permitindo identificar rapidamente inconsistências de configuração.

## Pontos de Destaque

- **MVC + Services:** Controllers finos que delegam lógica de negócio para serviços (`TicketService`, `UserService`, `TemplateService`, `WebhookService`).
- **Webhook Resiliente:** Armazenamento do payload bruto, idempotência via chave única e transação para garantir consistência.
- **Painel Responsivo:** View `atendimento.php` usa Bootstrap 5 e jQuery para chat em tempo real com suporte a mídias.
- **Autenticação Completa:** Fluxo de login, cadastro, logout e recuperação de senha com tokens de redefinição persistidos no banco.
- **Proteção de Autenticação:** Serviço de throttling limita tentativas consecutivas de login, aplicando bloqueio temporário por IP/e-mail e registrando eventos suspeitos.
- **Área Administrativa Completa:** Dashboard, CRUD de usuários, templates de mensagem, lista de solicitações, configuração do webhook Evolution/n8n e visualização de logs ficam disponíveis em `/admin`, acessível apenas para o perfil `admin`.
- **Evolution API Nativa:** Endpoint autenticado em `/api/evolution` expõe ações de chats, mensagens, mídias e envio direto utilizando API Key/token definidos pelo administrador.
- **Logs Centralizados:** `LoggerService` grava eventos críticos na tabela `logs`, permitindo auditoria completa e consulta filtrada diretamente na interface administrativa.
- **Sanitização de Dados:** Entradas de usuários e mensagens de retorno são normalizadas/escapadas para impedir XSS em toasts, cadastros e listagens administrativas.
- **Preparado para Métricas:** A tabela `ticket_metrics` facilita cálculo de tempos médios e indicadores do dashboard administrativo.
- **Configurações Persistentes:** A tabela `settings` guarda URL/token do webhook, garantindo que somente chamadas autorizadas sejam aceitas pelo endpoint `/api/webhook`.

## Fluxo de Dados

Consulte [`docs/flow.md`](docs/flow.md) para o passo a passo de como uma mensagem recebida no webhook é transformada em chamado e apresentada ao atendente.
