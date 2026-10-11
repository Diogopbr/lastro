# Lastro — progresso de implementação

Especificação/plano aprovados em 10/10/2026. Repositório local separado, branch codex/lastro. Nenhum deploy ou repositório GitHub público do Lastro foi criado nesta etapa.

## Fundação

- Readiness RED: rota ausente retornou 404 em vez de 503.
- Readiness GREEN: 1 teste/9 assertions com PostgreSQL real, banco indisponível, schema vazio, migrations aplicadas e registro de migration pendente.
- Fundação: 2 testes/19 assertions passaram (prontidão e página Inertia); exemplos do scaffold não entram na cobertura.
- composer install, validate e check-platform-reqs passaram.
- npm ci dos locks, typecheck e build passaram após ajustes de compatibilidade documentados.
- HTTP real: /health/ready 200 com status ready; /entrar 200 com raiz Inertia e referência a assets compilados.
- Bancos de desenvolvimento e teste separados; roles sem superuser; segredos ignorados.

A página atual é uma fundação declarada em implementação, não uma demonstração de conciliação. Acesso privado, CSV, conciliação, revisão/exportação, interface final e publicação ainda dependem das tarefas seguintes. Não contar os testes de exemplo do scaffold como cobertura de produto.

## Acesso e conferências — implementação parcial

A suite atual passou com 28 testes e 105 assertions em PostgreSQL real. Há login/logout, rejeição de CSRF, conferências isoladas por workspace, quota e versão, validade antes/depois de operações, start/reset privados e coleta limitada de dados vencidos. Expiração é verificada com o relógio do banco. Os testes foram vistos falhar antes das implementações, com logs no ledger privado.

A tarefa2 ainda não foi concluída: falta a prova entre processos/hosts independentes e finalizar o comando de drenagem. Os três exemplos de demo são conferências vazias de identidade; somente os serviços de importação/conciliação criarão fontes e resultados depois. Interface final externa ainda não foi integrada. Não há novo commit de conclusão, GitHub ou deploy do Lastro.
## Provas adicionais e interface

Quota com duas conexões/processos independentes: segunda criação bloqueada observada no PostgreSQL, depois conflito e cinco conferências; controle negativo sem proteção aceita seis. Dois resets serializados resultam sucesso/conflito de limite; uma mutação aguardando geração antiga é recusada após revogação. Sessão com cookies e CSRF reais funciona em duas instâncias HTTP separadas; reset invalida cookies antigos na outra instância e mantém o limite entre instâncias. Coleta pula workspace bloqueado e recolhe após liberação.

Frontend do Antigravity integrado após revisão e correções de CSRF/props/preview/versão. Codex repetiu 18 testes de componentes/helpers, typecheck e build com sucesso. Isto não é prova do fluxo completo no navegador: importação/conciliação/revisão ainda estão em implementação, e fontes/exemplos serão criados pelos serviços reais. Copilot passou a executar o parser na conversa existente via interface de agentes do próprio VSCode; relatório final ainda pendente.

Verificação final do núcleo da tarefa2: 39 testes/163 assertions passaram no PostgreSQL real; Pint e git diff --check passaram. Testes específicos contra importação/revisão serão adicionados quando os respectivos serviços existirem, antes da publicação.
