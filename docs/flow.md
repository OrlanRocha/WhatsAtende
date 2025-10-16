# Fluxo de Dados do Webhook até o Painel de Atendimento

1. **Recepção do Webhook**
   - A Evolution API realiza um `POST` no endpoint `/api/webhook` enviando o payload da mensagem.
   - O `WebhookController` valida o JSON e delega o processamento ao `WebhookService`.

2. **Persistência do Evento**
   - O `WebhookService` armazena o payload bruto na tabela `webhook_events` para fins de auditoria e prevenção de duplicidades.
   - Qualquer erro gera um log na tabela `logs` via `LoggerService`.

3. **Identificação do Contato**
   - O serviço busca o contato pelo `external_id`. Se não existir, cria um novo registro em `contacts` e atualiza `last_interaction_at`.

4. **Abertura de Chamado**
   - O sistema procura um ticket aberto (status `open` ou `assigned`) para o mesmo contato no dia corrente.
   - Caso não exista, cria um novo ticket na tabela `tickets` e inicializa seus indicadores em `ticket_metrics`.

5. **Registro da Mensagem**
   - A mensagem é inserida em `messages` como enviada pelo `contact`, incluindo metadados como mídia e horário.

6. **Atualização da Fila**
   - Tickets recém-criados permanecem com status `open` e aparecem na fila geral listada pelo `TicketController::index()`.
   - Atendentes visualizam a fila em tempo real e podem clicar em **Iniciar Atendimento** para assumir o chamado.

7. **Painel de Atendimento**
   - Ao abrir um ticket (`TicketController::show()`), o sistema carrega os dados do chamado e o histórico de mensagens.
   - A view `app/Views/atendimento.php` usa Bootstrap para renderizar o chat e jQuery AJAX para:
     - Enviar novas mensagens (`/tickets/{id}/messages`).
     - Atualizar o histórico a cada 5 segundos (`/tickets/{id}/messages`).
   - O atendente pode aplicar templates pré-cadastrados pelo administrador para agilizar respostas.

8. **Finalização**
   - Ao encerrar o atendimento, `TicketController::resolve()` altera o status do ticket para `resolved` e registra métricas de tempo.
   - A ação é logada pelo `LoggerService`, mantendo rastreabilidade completa.

9. **Autenticação e Recuperação de Acesso**
   - Todo acesso ao painel passa por `AuthController`, que expõe rotas para login, cadastro e logout utilizando o `AuthService`.
   - Tokens de redefinição são gerados e armazenados em `password_resets`, permitindo o fluxo "Esqueci minha senha" com verificação de expiração e auditoria via `LoggerService`.
