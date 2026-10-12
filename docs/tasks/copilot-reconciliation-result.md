# Lastro — tarefa 4: conciliação

Data: 10/10/2026. Worktree exclusiva `D:\Eu\Projetos\2\.worktrees\copilot`,
branch conferida `agent/copilot-lastro`; estado inicial limpo. Sem commit,
push, deploy ou outra janela. Tarefa 4 implementada e testada antes da tarefa 5.

## Arquivos e interfaces

- `app/Domain/Reconciliation/{ReconciliationRules,ReconcileConference,RunProjection,ResultProjection,RunQuery}.php`
- `app/Http/Controllers/Reconciliation/RunController.php`
- `database/migrations/2026_10_11_010551_create_reconciliation_snapshot_tables.php`
- `tests/Unit/ReconciliationRulesTest.php`
- `tests/Feature/{ReconciliationTest,ConcurrentRunTest}.php`
- `tests/Fixtures/{ReconciliationDatabase,concurrent-reconciliation}.php`
- `tests/Fixtures/csv/reconciliation-{orders,payouts}.csv`
- Este relatório; a tarefa 5 tem relatório separado.

Lidos AGENTS, plano/spec Lastro, contratos CSV/HTTP/projections, TypeScript,
WorkspaceTransaction, ConfirmSource, schema existente, projeção de conferências,
controllers, limpeza/reset e o harness SourceConcurrencyTest.
Versões conferidas por `composer show --direct`: Laravel 13.35.0,
League CSV 9.28.0, PHPUnit 12.5.38, Pint 1.32.1; PHP portátil 8.5.11.
Não foram alteradas dependências.

`ReconcileConference::execute(CurrentWorkspace,string): RunProjection`.
DTO expõe `toArray`, `JsonSerializable` e `created` fora do JSON, como na fonte.
RunProjection materializa sua projeção dentro do guard; não consulta banco na
serialização posterior. ResultProjection preserva origem completa e eventos.

`RunQuery::show(scope,runId,filters=[])` retorna
`{conference,run,results,filters}`. `results(scope,runId,filters=[])` retorna
`{items,total,page,pageSize}`. Ambos usam WorkspaceTransaction. `project`/`run`
são helpers internos consumidos pelos serviços já sob guard/transação;
não são endpoints de autorização.

## Regras e schema

- Join PHP por chave textual prefixada, sem coerção numérica, fuzzy, mudança de
  caixa ou normalização Unicode. `0001` não casa com `1`.
- Fixture aprovada: matched/mismatch/missingPayout/missingOrder uma de cada,
  totals `600.00 / 690.00 / 20.00 / 670.00`, difference de 0002 `-10.00`.
- Ausência é null; matched reviewStatus null, demais pending inicialmente.
- Guard central bloqueia workspace e verifica DB clock antes/depois; depois
  serviço bloqueia conference. Repetição retorna mesma run/event/resultados.
- UUIDs, unique conference/run e run/event. FKs compostas protegem workspace,
  conference e fonte/kind. Trigger de resultados verifica identidade exata,
  fonte e workspace das linhas antes de inserir, sem alterar schema de fontes.
  FKs simples das linhas são complementadas por esse trigger porque a tabela
  existente não oferece a chave composta necessária.
- Snapshot não admite update/delete direto; campos de resultado imutáveis
  exceto review_status/version com avanço exato de uma versão. Delete por
  cascata permite coleta integral. Eventos são append-only por trigger e
  ausência de endpoints de alteração.
- FK de ator em run_events é DEFERRABLE INITIALLY DEFERRED.
- `CREATE OR REPLACE FUNCTION` permite `migrate:fresh`, que apaga tabelas mas
  não funções PostgreSQL. Down remove as funções; rollback/reapply foi testado.
- Summary global calculado do snapshot/fontes e estado atual dos resultados,
  nunca de uma página nem contador em memória. Até 200 resultados possíveis
  (100 pedidos + 100 repasses sem correspondência), pages de no máximo 20.
  Search usa substring literal case-sensitive, sem wildcard SQL.
- Consulta retorna reviewEvents em ordem decrescente de result_version após
  tarefa 5; inicialmente vazio. Valores são strings Money::format.

## TDD e comandos reais

Prefixo usado antes de comandos PHP:

```powershell
Set-Location 'D:\Eu\Projetos\2\.worktrees\copilot'
. .\scripts\local-env.ps1 -Test
```

Artisan gerou os três testes antes do domínio:

```powershell
php artisan make:test ReconciliationRulesTest --unit --phpunit --no-interaction
php artisan make:test ReconciliationTest --phpunit --no-interaction
php artisan make:test ConcurrentRunTest --phpunit --no-interaction
php artisan test --compact --filter 'ReconciliationRulesTest|ReconciliationTest|ConcurrentRunTest'
```

RED real: **11 testes, 0 passed, 3 failures, 8 errors, 23 assertions, saída 2**.
Classes de domínio/controller ausentes; worker ausente não bloqueou, corretamente
falhando a exigência de bloqueio real. Nenhuma alegação de concorrência no RED.

Depois do RED:

```powershell
php artisan make:class Domain/Reconciliation/ReconciliationRules --no-interaction
php artisan make:class Domain/Reconciliation/ReconcileConference --no-interaction
php artisan make:class Domain/Reconciliation/RunProjection --no-interaction
php artisan make:class Domain/Reconciliation/ResultProjection --no-interaction
php artisan make:class Domain/Reconciliation/RunQuery --no-interaction
php artisan make:controller Reconciliation/RunController --no-interaction
php artisan make:migration create_reconciliation_snapshot_tables --no-interaction
```

Fixture auxiliar também foi gerada via make:class, inicialmente colocada pelo
gerador em `app/Tests/Fixtures`; movida imediatamente para o escopo autorizado
`tests/Fixtures`, namespace Tests\Fixtures. Diretórios vazios gerados foram
removidos especificamente ao fim. Nenhum arquivo permaneceu fora do escopo.

GREEN inicial: **11 passed / 83 assertions**. Depois da ampliação:
**15 passed / 112 assertions** antes de iniciar tarefa 5.
O teste de violação FK deferred no commit precisou capturar PDOException
(o commit não encapsula em QueryException); rollback total foi confirmado.

## PostgreSQL e concorrência verificados

Harness cria schema exclusivo aleatório em lastro_test, migra esquema vazio,
purga DB e Cache database ao trocar search_path, restaura configuração e remove
somente esse schema no finally/tearDown. Sem SQLite/mocks.

Worker `concurrent-reconciliation.php` é separado de `concurrent-create.php`,
que não foi modificado. Usa PHP_BINARY/Symfony Process, processo e conexão
independentes. Parent mantém a transação aberta; worker bloqueia; parent
consulta pg_stat_clear_snapshot e pg_blocking_pids até observar o PID bloqueado,
depois commit e verificação da resposta/DB. Workers são parados ao final.

- Parent/worker retornam mesma run, worker200, uma run/event e quatro results.
- Reset com lock anterior faz worker de conciliação velha404 e nenhuma run.
- Foreign/expired404; fontes faltantes422 sem lote parcial.
- Schema recusa edits/delete direto, wrong origins e dados de ator estrangeiro;
  falha deferred reverte run/results/event em conjunto.
- Pagination até 200 rows, 100 tetos por fonte, totals exatos int64.
- JSON/controller contracts e Inertia Runs/Show/form303 testados com rotas
  registradas **apenas nos testes**, sem tocar routes/web.php.

## Validação final e limites

Após tarefa 5, comando conjunto:

```powershell
php artisan test --compact --filter 'SafeCsvCellTest|ReviewTest|ConcurrentReviewTest|ExportTest|ConcurrentExportTest|ReconciliationRulesTest|ReconciliationTest|ConcurrentRunTest|MoneyTest|CsvStructureValidatorTest|CsvImportValidatorTest|SourceConcurrencyTest|CleanupWorkspacesTest|WorkspaceTransactionTest'
```

**227 passed, 951 assertions, saída 0**, incluindo revisão/export e regressão
parser/fontes/guard/coleta. Selector separado:

```powershell
php artisan test --compact --filter 'SourceImportTest::(?!test_preview_page_uses_the_current_scoped_conference)'
```

**11 passed, 94 assertions, saída 0**.
Última reexecução exclusiva das tarefas4/5: **61 passed, 403 assertions,
19881 ms, saída0**.
Pint final `php vendor\bin\pint --dirty --format agent`: passed.
`php -l` em cada um dos 24 arquivos PHP novos: sem erro.
`git diff --check`: saída 0, mas não verifica arquivos untracked; Pint cobre
formatação PHP. Sem percentual de cobertura medido/alegado.

Uma tentativa ampliada anterior: 237 testes, 236 passed, 1017 assertions,
1 falha no teste legado SourceImportTest de página por ViteManifestNotFound
(public/build/manifest.json ausente). Não foi alterado teste legado/frontend
nem produzido build fora do escopo. Posteriormente todos os casos de fontes
não dependentes de assets passaram pelo selector acima.

## Integração necessária por Codex

Nenhuma mudança em fontes/acesso/frontend/config/scripts/manifestos/contratos.

Rotas de produção ainda **não** integradas:

- POST `/conferencias/{id}/conciliar` → RunController::store.
- GET `/execucoes/{id}` → RunController::show, nome `runs.show`.
- GET `/execucoes/{id}/resultados` → RunController::results.
- Todas com middleware workspace/web/CSRF pertinente e whereUuid.

ConferenceProjection existente continua devolvendo run/progress null em
listagem/detalhe de conferências. RunQuery enriquece esses campos para Runs/Show,
mas Codex precisa atualizar o domínio de conferências fora deste escopo para
manter todos os surfaces consistentes após conciliação/revisão.

Sem prova nesta entrega de rotas produtivas, frontend/build, browser, CI ou
deploy. `.ai/rules`/skills de testing/plano não existem nesta worktree.
Skills de banco disponíveis foram carregadas; nenhuma operação Neon/cloud foi
necessária, feita ou provisionada. PostgreSQL local existente foi preservado.
