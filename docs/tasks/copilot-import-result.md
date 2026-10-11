# Lastro — integração da importação

Copilot entregou o parser puro na conversa existente do VSCode, na worktree
`agent/copilot-lastro`. Codex repetiu os 154 testes / 492 assertions e integrou
somente classes, testes, fixtures e relatório do escopo autorizado.
Detalhes do RED/GREEN do parser estão em `copilot-parser-result.md`.

Codex implementou a persistência e HTTP na branch `codex/lastro` após observar
seis falhas reais de `SourceImportTest` antes das rotas/tabelas. A prévia usa
o parser com o período atual e não grava dados. Erros do CSV são uma avaliação
200 com `valid:false`; requisição sem arquivo ou acima de 1048576 bytes recebe
422. A confirmação sempre revalida bytes e período sob locks de workspace e
conferência, recalcula hash e grava fonte, linhas, evento e versão juntos.

Mesmo hash devolve a fonte existente antes de cobrar versão antiga, preservando
filename e evento. Hash diferente, arquivo trocado ou versão obsoleta recebem
409. Fontes têm isolamento por chaves compostas; o nome exibido é somente
basename, sem controles, até 200 caracteres. Não se grava arquivo bruto.
Leitura multipart é limitada a 1048577 bytes depois de verificar seu tamanho.

Durante integração, o rate limiter executava antes do middleware customizado
por prioridade do Laravel; testes falharam 500 com workspace ausente.
A prioridade explícita de ResolveWorkspace antes de ThrottleRequests corrigiu
a causa. O limite de 20 importações/minuto usa identidade persistente do visitante
e foi comprovado após reset, com 429 e Retry-After na nova geração.

Um teste adicional encontrou falha real de coleta: a referência do evento ao ator
impedia cascatas do workspace. A FK do ator agora é DEFERRABLE INITIALLY DEFERRED:
preserva integridade e histórico enquanto o ambiente existe e permite apagar
toda a geração na mesma transação. A coleta passou no teste com fonte e linhas
confirmadas; não se exclui audit trail ao remover só um usuário.

## Evidência executada pelo Codex

- Parser puro: 154 testes, 492 assertions, execução independente.
- SourceImportTest: 12 testes, 108 assertions; prévia inválida, idempotência
  BOM/CRLF/trim/nome, conflito de substituição/hash/versão, bloqueio de período,
  expiração, isolamento, tamanho, página Inertia, taxas/líquido, coleta e limite.
- SourceConcurrencyTest: quatro processos independentes, bloqueio observado por
  pg_blocking_pids: mesmo conteúdo retorna uma fonte/evento; diferente 409;
  período atualizado torna linha inválida 422; reset revoga confirmação 404.
- Falha injetada por trigger após inserir linhas comprova rollback de fonte,
  linhas/evento e manutenção da versão/período anteriores.
- Todos os testes de integração usam PostgreSQL real; cada corrida migra um
  schema vazio próprio. Sem SQLite, sleeps como prova de disputa ou mock da API.

Comandos: dot source `scripts/local-env.ps1 -Test`, `php artisan test --compact`,
`php vendor/bin/pint --dirty --format agent` e `git diff --check`.
Logs privados: `.superpowers/sdd/2026-10-10-lastro/source-import-red.log` e
`task-3-final-suite.log`. O log final registra o total efetivamente executado.

Esta etapa não declara a conciliação, revisão, exportação, navegador ou deploy
concluídos; esses fluxos são as próximas tarefas do plano.
