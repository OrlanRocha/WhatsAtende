# WhatsAtende - Sistema de Atendimento com WhatsApp

## Documentação Técnica e Instalação

### 1. Visão Geral
WhatsAtende é um sistema PHP para atendimento de chamados integrado ao WhatsApp. Este fork inclui melhorias de segurança, desempenho e usabilidade.

### 2. Melhorias Implementadas
- Autenticação via OAuth2 (Login seguro via Google e Microsoft)
- Painel de controle com estatísticas em tempo real
- Relatórios filtráveis (por atendente, tempo de resposta e status)
- Log detalhado de conversas
- Suporte a templates dinâmicos de respostas automáticas
- Suíte de testes automatizados com PHPUnit
- Interface modernizada com Vue.js
- Documentação técnica completa

### 3. Instalação
1. Clonar o repositório:
   ```bash
   git clone https://github.com/OrlanRocha/WhatsAtende.git
   cd WhatsAtende
   ```
2. Alternar para a branch de melhorias:
   ```bash
   git checkout feature/refactor-with-improvements
   ```
3. Executar a instalação das dependências:
   ```bash
   composer install
   npm install && npm run build
   ```
4. Criar arquivo `.env` com as seguintes variáveis:
   ```bash
   DB_HOST=localhost
   DB_USER=root
   DB_PASS=
   DB_NAME=whatsatende
   OAUTH_GOOGLE_CLIENT_ID=seu_id
   OAUTH_GOOGLE_CLIENT_SECRET=sua_chave
   ```
5. Iniciar o servidor:
   ```bash
   php artisan serve
   ```

### 4. Endpoints Principais da API
- `POST /api/tickets` : Criação de novo chamado
- `GET /api/tickets` : Listagem de chamados com filtros
- `GET /api/reports` : Relatórios por parâmetro
- `POST /api/auth/login` : Login via OAuth2

### 5. Guia de Manutenção
- Executar testes com `vendor/bin/phpunit`
- Atualizar dependências regularmente via Composer e NPM
- Utilizar ESLint e PHPStan para verificação de código

### 6. Créditos
Mantido por Orlan Rocha e colaboradores.
Refatoração e documentação por Perplexity AI Assistant.