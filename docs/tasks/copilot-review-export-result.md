# Lastro — tarefa 5: revisão e exportação

Data: 10/10/2026. Mesma conversa/janela, worktree exclusiva
`D:\Eu\Projetos\2\.worktrees\copilot`, branch `agent/copilot-lastro`.
Tarefa 5 iniciada após GREEN da tarefa 4. Sem commit/push/deploy.

## Arquivos e contratos

- `app/Domain/Reviews/{ReviewResult,ReviewProjection}.php`
- `app/Domain/Exports/{ExportRun,SafeCsvCell}.php`
- `app/Http/Controllers/Reviews/ReviewController.php`
- `app/Http/Controllers/Exports/ExportController.php`
- `database/migrations/2026_10_11_011028_create_result_review_events.php`
- `tests/Unit/SafeCsvCellTest.php`
- `tests/Feature/{ReviewTest,ConcurrentReviewTest,ExportTest,ConcurrentExportTest}.php`
- Complementos no RunQuery, migration/tests/worker da tarefa 4, todos no escopo.
- Este relatório e o relatório de conciliação.

Assinaturas preservadas:
`ReviewResult::execute(scope,resultId,expectedVersion,action,note): ResultProjection`;
`ExportRun::execute(scope,runId): Response` materializada.
ResultProjection JSON camelCase com `reviewEvents` completos, mais recente
primeiro, sem perder eventos antigos ou alterar classificação/valores.

## Revisão

- WorkspaceTransaction central + result lock, nessa ordem. Versão stale409
  antes de avaliar transição, inclusive comando repetido com nota idêntica.
- matched422. pending→reviewed; reviewed→pending via reopened com motivo.
  Transição inválida422, nota UTF-8 3..1000 caracteres, não whitespace-only.
  Controles/newlines na nota são texto livre preservado, não executado.
- Versão/evento transacionais; state/version só mudam após nota/ator válidos.
- review_events possui workspace/result/actor, nome de ator capturado no evento,
  action/note/result_version/DB clock UTC. Unique result/version.
- FK composta workspace/actor DEFERRABLE INITIALLY DEFERRED, verificada no
  pg_constraint. Append-only trigger bloqueia update/delete direto mas permite
  cascata da coleta. Run/results/eventos também protegidos contra delete direto.
- Progresso vem da consulta aos resultados da run, não contador local.
  Query/page retorna eventos e projeções de origem em bateladas.
- API JSON200; formulário303 para `runs.show`, só depois da persistência.

## Exportação e isolamento

1. Recusa transação ambiente com LogicException explícita; não tenta trocar
   isolamento depois de queries externas.
2. Abre `DB::transaction` externa e executa **SET TRANSACTION ISOLATION LEVEL
   REPEATABLE READ como primeira instrução**, antes de qualquer leitura.
3. Dentro dela chama WorkspaceTransaction::run, que abre savepoint, bloqueia
   workspace e verifica validade real antes/depois do callback. Guard não foi
   alterado e não há autorização duplicada.
4. Consulta run/conference/results/eventos/origens no mesmo snapshot, constrói
   todo CSV em memória (máximo 200 resultados) e cria Response. Guard termina,
   transação externa faz commit; só então execute retorna para transmissão.
5. Até três tentativas do snapshot inteiro em serialização/deadlock. Laravel
   encapsula erro 40001 do savepoint em DeadlockException com code0; catch
   **específico PDOException**, quando causa PDO é 40001, relança a causa para
   o retry transacional padrão reconhecê-la. Outros erros são relançados, sem
   fallback de sucesso e sem catch amplo.
6. Se revisão só modifica results/eventos enquanto export aguarda lock, o
   snapshot iniciado antes dela pode conter o estado antigo, consistentemente.
   Não prometemos “estado mais novo depois do lock”. Revisão do workspace por
   reset causa serialização/retry; após retry namespace revogado404.
7. Código persistido LT-numérico verificado antes de construir filename.
   Filename fixo `lastro-{conferenceCode}-{runUuid}.csv`, nunca nome arbitrário
   de conferência/source. Content-Type UTF-8, no-store e nosniff.

Header exato do contrato:

```text
conference_code,run_id,order_id,classification,order_gross,payout_gross,fee,net,difference,review_status,review_note,reviewed_at
```

Todas as linhas independentemente de filtros; último evento fornece nota/data,
inclusive motivo de reabertura. Sem evento, células vazias. Ausência monetária
é vazia, não zero. Money decimal confiável, difference negativa não recebe
apóstrofo. SafeCsvCell aplica apóstrofo somente a texto livre cujo primeiro
caractere efetivo após Cc/Cf/separadores/espaços seja `=,+,-,@`; preserva
original após prefixo. League Writer escape vazio, RFC doubled quotes, CRLF;
testes leem o CSV de volta, inclusive vírgula/aspas/multiline.

**Divergência documental a integrar:** a frase antiga do plano que exige
“fonte/hash e totais” no arquivo não corresponde ao header fixo detalhado no
mesmo plano/contrato CSV. Mantido o header aprovado sem adicionar colunas,
rodapés ou linhas sintéticas. Origem/hash permanecem nas ResultProjection;
totais são RunSummary. Codex deve harmonizar essa frase, ou despachar nova
mudança contratual se desejar colunas adicionais. Contratos não alterados aqui.

## TDD e resultados reais

Prefixo antes de cada execução PHP:

```powershell
Set-Location 'D:\Eu\Projetos\2\.worktrees\copilot'
. .\scripts\local-env.ps1 -Test
```

Testes gerados antes do domínio via Artisan:

```powershell
php artisan make:test ReviewTest --phpunit --no-interaction
php artisan make:test ConcurrentReviewTest --phpunit --no-interaction
php artisan make:test ExportTest --phpunit --no-interaction
php artisan make:test ConcurrentExportTest --phpunit --no-interaction
php artisan make:test SafeCsvCellTest --unit --phpunit --no-interaction
php artisan test --compact --filter 'SafeCsvCellTest|ReviewTest|ConcurrentReviewTest|ExportTest|ConcurrentExportTest'
```

Houve três tentativas inválidas de carga antes do RED comportamental
(chaves do teste ReviewTest, depois colisão do helper result com método final
PHPUnit). Reparadas antes da implementação; **não contam como prova TDD**.

RED comportamental: **39 testes, 0 passed, 4 failures, 35 errors,
77 assertions, saída2** por classes ausentes, inclusive worker que não podia
alcançar bloqueio. Só então foram geradas/implementadas:

```powershell
php artisan make:class Domain/Reviews/ReviewResult --no-interaction
php artisan make:class Domain/Reviews/ReviewProjection --no-interaction
php artisan make:class Domain/Exports/ExportRun --no-interaction
php artisan make:class Domain/Exports/SafeCsvCell --no-interaction
php artisan make:controller Reviews/ReviewController --no-interaction
php artisan make:controller Exports/ExportController --invokable --no-interaction
php artisan make:migration create_result_review_events --no-interaction
```

Iterações registradas:

- 54 casos combinados: 53 passed/332 assertions; uma expectativa incorreta
  exigia estado novo após espera RR. Corrigida para snapshot antigo consistente.
- 57 casos: erro de retry40001 encapsulado e teste de expiração por listener
  que não observava insert. Retry corrigido; expiração passou a ser induzida
  por trigger real pós-insert no schema do teste.
- 57 passed/352 assertions após correções.
- Ampliação para coleta de geração substituída e rollback/reapply: helper de
  fixture buscava resultado sem escopo e selecionava geração antiga; corrigido
  no teste, sem mudança de acesso/fonte.
- 59 passed/374 assertions.
- Form/Inertia: name lookup de rotas registradas só em testes precisava
  refreshNameLookups; corrigido nos testes, routes de produção não alteradas.
- Regresso de `migrate:fresh` expôs funções sobreviventes: migrations novas
  passaram a usar CREATE OR REPLACE; rollback/reapply também continua válido.

GREEN final ampliado, após Pint:

```powershell
php artisan test --compact --filter 'SafeCsvCellTest|ReviewTest|ConcurrentReviewTest|ExportTest|ConcurrentExportTest|ReconciliationRulesTest|ReconciliationTest|ConcurrentRunTest|MoneyTest|CsvStructureValidatorTest|CsvImportValidatorTest|SourceConcurrencyTest|CleanupWorkspacesTest|WorkspaceTransactionTest'
```

**227 passed, 951 assertions, saída0**.
Última reexecução exclusiva das tarefas4/5: **61 passed, 403 assertions,
19881 ms, saída0**.
SourceImportTest separado excluindo somente a página dependente de assets:
**11 passed / 94 assertions, saída0**.
Pint final `php vendor\bin\pint --dirty --format agent`: passed.
`php -l`: todos os **24 arquivos PHP novos** sem erros.
Nenhum scaffold/percentual de cobertura declarado. Não executado browser/Excel.

Uma execução anterior que incluía toda SourceImportTest teve
237 testes/236 passed/1017 assertions e falha legada ViteManifestNotFound.
Não foi corrigida por alteração fora de escopo. Ver relatório tarefa4.

## PostgreSQL real e concorrência

Schema exclusivo por teste, migrations reais, purges DB/Cache database,
pg_stat_clear_snapshot e bloqueio observado por pg_blocking_pids. Worker
próprio em processo/conexão independentes; nenhum mock/sequência usado como
prova. Concurrent-create.php preservado.

Casos efetivamente executados:

- Duas revisões mesma versão: uma vencedora200, outra409, um evento.
- Duas reaberturas; revisão×reabertura nos dois sentidos: só uma versão/evento
  avança, nota vencedora preservada e progresso consistente.
- Reset antes de revisão velha:404, sem evento.
- Export com guard aberto: worker reabre e fica bloqueado; export devolve
  estado antigo inteiro, worker200 após commit e DB passa a pending.
  `SHOW transaction_isolation` observado **repeatable read** durante results.
- Export atrás de review: snapshot antigo state/event é consistente enquanto
  DB passa a reviewed; sem misturar status novo com nota antiga.
- Export atrás de reset: bloqueio real, retry40001,404 final.
- Export já materializado pode completar depois de reset; nova chamada404.
- Expiração induzida no banco durante review reverte result/event pela checagem
  pós-callback. Sem clock PHP como autoridade.
- Coleta com run/review apaga toda geração sem erro de FK; coleta de revogada
  preserva run/review da geração substituta. Deferred actor FK observado.
- Rollback das duas migrations novas e reapply preservam fundação/source,
  reaplicam functions/triggers e permitem revisão.

## Integração e limites restantes

Sem alterações em frontend, routes, manifests, scripts, config, contratos,
fontes ou acesso. Guard central **não necessita extensão** nesta implementação,
mas callers devem chamar ExportRun fora de transação ambiente.

Codex deve integrar com web/ResolveWorkspace/whereUuid e CSRF:

- POST `/resultados/{id}/revisoes` → ReviewController::store.
- GET `/execucoes/{id}/exportar` → ExportController invokable.
- Nome `runs.show` necessário para redirects303 das tarefas4/5.
- Atualizar ConferenceProjection (fora deste escopo) para run/progress em
  listagem/detalhe de conferências; aqui Runs/Show já recebe esses campos.

Schemas de teste removidos e workers encerrados. Sem deploy/CI/live/full
frontend build comprovados. Não foram lidos/impressos .env, tokens ou
credenciais. Skills testing/plano citadas não existem localmente; continuidade
independente concluída sem alterar customizações.
