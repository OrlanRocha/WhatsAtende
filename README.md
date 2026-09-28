# WhatsAtende

Sistema de atendimento de chamados em PHP, com painel administrativo, fila de tickets e integracao com WhatsApp pela Evolution API.

> Status: projeto em desenvolvimento. A release `v0.1.0` corrige a instalacao no Windows/Apache e padroniza a configuracao inicial. Os PRs abertos contem evolucoes que ainda nao fazem parte do branch padrao.

## Recursos atuais

- autenticacao, cadastro e recuperacao de senha;
- fila, atribuicao, mensagens e encerramento de tickets;
- painel administrativo para usuarios, templates, logs e webhook;
- recebimento idempotente de eventos por webhook;
- integracao nativa com a Evolution API;
- schema e dados de exemplo para MariaDB.

## Requisitos

- PHP 8.0 ou superior, com extensoes `pdo` e `pdo_mysql`;
- MariaDB 10.5+ ou MySQL 8+;
- Apache com `mod_rewrite`, ou o servidor embutido do PHP para desenvolvimento;
- uma instancia da Evolution API para os recursos de WhatsApp.

## Instalacao

```bash
git clone https://github.com/OrlanRocha/WhatsAtende.git
cd WhatsAtende
cp .env.example .env
```

Edite `.env` com as credenciais do banco e da Evolution API. O nome padrao do banco e `whats_atende`.

Crie a estrutura e, opcionalmente, os dados de demonstracao:

```bash
mysql -u root -p < database/schema.sql
mysql -u root -p < database/seeds.sql
```

Inicie o ambiente de desenvolvimento:

```bash
php -S localhost:8000 -t public
```

Acesse `http://localhost:8000`. Em Apache, aponte o `DocumentRoot` preferencialmente para `public/`; os arquivos `.htaccess` tambem suportam a instalacao pela raiz do repositorio.

## Configuracao

As principais variaveis estao documentadas em [`.env.example`](.env.example):

- `DB_*`: conexao com MariaDB/MySQL;
- `EVO_API_BASE`, `EVO_INSTANCE` e `EVO_API_KEY`: acesso a Evolution API;
- `WEBHOOK_URL` e `WEBHOOK_TOKEN`: recebimento e validacao do webhook;
- `INTEGRATION_MODE`: `webhook` ou `native`.

Os dados de demonstracao criam as contas abaixo:

| Perfil | Usuario | Senha |
| --- | --- | --- |
| Administrador | `admin@example.com` | `Admin123!` |
| Atendente | `agent@example.com` | `Agent123!` |

Troque essas senhas imediatamente fora de um ambiente local.

## Estrutura

```text
app/        controllers, services, models e views
config/     configuracao do banco
database/   schema e dados de exemplo
docs/       documentacao do fluxo
public/     front controller e arquivos estaticos
routes/     definicao das rotas HTTP
storage/    logs e cache local da integracao
```

Veja [docs/flow.md](docs/flow.md) para o fluxo de mensagens e [CHANGELOG.md](CHANGELOG.md) para o historico de releases.

## Politica de atualizacao

Cada mudanca entregue deve atualizar a documentacao afetada e o `CHANGELOG.md`, receber um commit descritivo, ser enviada ao GitHub e gerar uma release versionada.

## Producao

- nao publique o arquivo `.env`;
- use HTTPS e credenciais exclusivas;
- restrinja a escrita de `storage/` ao usuario do servidor;
- altere as contas de demonstracao ou nao execute `database/seeds.sql`;
- configure backups e rotacao de logs.

## Licenca

Distribuido sob a licenca presente em [LICENSE](LICENSE).
