# Changelog

Todas as mudancas relevantes deste projeto serao registradas neste arquivo.

## [0.2.0] - 2026-10-24

### Adicionado

- Publica a versao local completa, incluindo administracao de grupos, integracoes, configuracoes, relatorios, avaliacoes e perfil.
- Inclui os manifestos e locks do Composer e npm, assets da interface e testes Pest.

### Corrigido

- Corrige SQLSTATE[HY093] ao registrar falhas de login com consultas preparadas nativas do MySQL, usando parametros exclusivos no upsert.
- Adiciona regressao para primeira falha, reinicio apos expiracao e bloqueio apos cinco tentativas.

### Publicacao

- Exclui credenciais locais, dependencias instaladas, uploads, logs e arquivos temporarios do pacote.

## [0.1.0] - 2026-09-28

### Corrigido

- Remove o caminho `.htaccess` malformado que impedia o checkout no Windows.
- Adiciona regras Apache validas na raiz e no diretorio `public`.
- Alinha o banco padrao `whats_atende` entre schema, configuracao e `.env.example`.
- Evita dominios de cookie invalidos ao executar em `localhost`, IP ou host com porta.

### Documentacao

- Reescreve o README com requisitos, instalacao, execucao, configuracao e cuidados de producao.

[0.1.0]: https://github.com/OrlanRocha/WhatsAtende/releases/tag/v0.1.0
[0.2.0]: https://github.com/OrlanRocha/WhatsAtende/releases/tag/v0.2.0
