# Changelog

Todas as mudancas relevantes deste projeto serao registradas neste arquivo.

## [0.1.0] - 2026-09-28

### Corrigido

- Remove o caminho `.htaccess` malformado que impedia o checkout no Windows.
- Adiciona regras Apache validas na raiz e no diretorio `public`.
- Alinha o banco padrao `whats_atende` entre schema, configuracao e `.env.example`.
- Evita dominios de cookie invalidos ao executar em `localhost`, IP ou host com porta.

### Documentacao

- Reescreve o README com requisitos, instalacao, execucao, configuracao e cuidados de producao.

[0.1.0]: https://github.com/OrlanRocha/WhatsAtende/releases/tag/v0.1.0
