# Copilot — conciliação e revisão do Lastro

Continue NA MESMA conversa e janela do VSCode. Worktree exclusiva
`D:\Eu\Projetos\2\.worktrees\copilot`, branch `agent/copilot-lastro`.
Codex integrou seu parser e a persistência das fontes; a worktree foi atualizada
com os commits verificados. Leia AGENTS.md, plano/spec Lastro e contratos
CSV/HTTP/projections e resources/js/core/contracts.ts.

Execute tarefa4 e depois tarefa5 do plano, com TDD e PostgreSQL real. Você tem
autorização delegada pelo Diogo para implementar classes, testes, migrations e
controllers de Reconciliation/Reviews/Exports. Não altere frontend, manifesto,
scripts, config, contratos ou fontes/acesso já entregues. Não faça commit/push/
deploy; Codex integra rotas e revisa schema/projeções. Se necessitar alteração
fora de escopo, registre a necessidade e prossiga com as partes independentes.

Escopo: app/Domain/{Reconciliation,Reviews,Exports}/*,
app/Http/Controllers/{Reconciliation,Reviews,Exports}/*, migrations novas para
runs/results/run_events/review_events, testes/fixtures desses domínios e
docs/tasks/copilot-{reconciliation,review-export}-result.md.

Preserve signatures do plano e DTOs camelCase. ConfirmSource retorna
SourceProjection com toArray e created; execute(scope,conferenceId,kind,bytes,
expectedHash,expectedVersion,filename). WorkspaceTransaction central bloqueia
workspace e checa clock_timestamp/revogação antes e após callback; serviços
devem usá-lo, depois conference/result lock. FK composta de actor em eventos
precisa DEFERRABLE INITIALLY DEFERRED para coleta integral da geração.
Teste coleta com dados de run/review para não repetir o problema.

Conciliação: join por identificador exato; fixture 0001/0002/0003 vs
0001/0002/0004 produz as quatro classificações, totais600/690/20/670 e
diferença -10.00. Snapshot imutável, idempotente unique conference/run/event,
fontes inexistentes422, estrangeiro404. ResultProjection deve carregar origem
de cada linha e reviewEvents (vazio inicialmente), ausência null, matched sem
reviewStatus, divergência pending. Filtros/paginação máximos20 não alteram totais
globais; prepare serviço de query consumível pelo controller.

Revisão: versão otimista sob workspace→result lock; nota3..1000, reabertura
com motivo, matched422, stale409, eventos append-only, classificação/valores
imutáveis. Progresso da run calculado dos resultados, não contador local.
Teste revisão/reabertura concorrentes e reset versus revisão velha.

Export: materialize sob REPEATABLE READ antes de transmitir. Cuidado: não
trocar isolamento após primeira query da transação. Workspace guard usa lock
e validade real; planeje entrada de transação para definir isolamento antes
da leitura, sem alterar WorkspaceTransaction fora do seu escopo (registre
se precisar extensão do guard pelo Codex). Todas as linhas, último evento,
ausências vazias, proteção de fórmula só texto livre após controles/espaços;
dinheiro negativo permanece decimal. Header/filename fixos no plano.
Teste estados consistentes export/review e novos exports após revogação404.

Use Artisan/Pint. Dot source scripts/local-env.ps1 -Test antes de PHP/testes.
Nunca imprima .env, credenciais, settings ou tokens. Workers reais em processos
independentes com bloqueio observado por pg_blocking_pids; purgar DB e Cache
database se trocar schema, pg_stat_clear_snapshot antes de observar.
Leia tests/Feature/SourceConcurrencyTest.php como harness de referência;
não modifique concurrent-create.php para evitar colisão com Codex.

Não declarar mock/sequência de chamadas como prova de concorrência. Rode RED
antes da implementação, GREEN e registre comandos/resultados e limites reais
nos dois relatórios. Conclua autonomamente; aprovação rotineira já concedida.
