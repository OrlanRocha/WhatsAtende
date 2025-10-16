# WhatsAtende - Estrutura Inicial

Este repositório contém a proposta de arquitetura para o sistema de atendimento de chamados via chat, construído em PHP 8.4+, seguindo o padrão MVC com serviços especializados e integração por webhook com a Evolution API.

## Estrutura de Diretórios

```
app/
  helpers.php
  Controllers/
    AuthController.php
    TicketController.php
    WebhookController.php
  Models/
    Ticket.php
  Services/
    AuthService.php
    LoggerService.php
    TicketService.php
    WebhookService.php
  Views/
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

O arquivo [`database/schema.sql`](database/schema.sql) traz o SQL completo para criação das tabelas `users`, `roles`, `contacts`, `tickets`, `ticket_metrics`, `messages`, `templates`, `logs` e `webhook_events`, com índices e relacionamentos adequados ao MariaDB.

## Pontos de Destaque

- **MVC + Services:** Controllers finos que delegam lógica de negócio para serviços (`TicketService`, `WebhookService`).
- **Webhook Resiliente:** Armazenamento do payload bruto, idempotência via chave única e transação para garantir consistência.
- **Painel Responsivo:** View `atendimento.php` usa Bootstrap 5 e jQuery para chat em tempo real com suporte a mídias.
- **Autenticação Completa:** Fluxo de login, cadastro, logout e recuperação de senha com tokens de redefinição persistidos no banco.
- **Logs Centralizados:** `LoggerService` grava eventos críticos na tabela `logs`, permitindo auditoria completa.
- **Preparado para Métricas:** A tabela `ticket_metrics` facilita cálculo de tempos médios e indicadores do dashboard administrativo.

## Fluxo de Dados

Consulte [`docs/flow.md`](docs/flow.md) para o passo a passo de como uma mensagem recebida no webhook é transformada em chamado e apresentada ao atendente.
