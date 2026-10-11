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

A aplicação segue em implementação. Acesso privado e importação foram entregues nas etapas seguintes; conciliação, revisão/exportação, integração visual e publicação ainda estão pendentes. Não contar os testes de exemplo do scaffold como cobertura de produto.

## Acesso e conferências — histórico de integração

A suite atual passou com 28 testes e 105 assertions em PostgreSQL real. Há login/logout, rejeição de CSRF, conferências isoladas por workspace, quota e versão, validade antes/depois de operações, start/reset privados e coleta limitada de dados vencidos. Expiração é verificada com o relógio do banco. Os testes foram vistos falhar antes das implementações, com logs no ledger privado.

Naquele ponto ainda faltavam as provas entre processos/hosts e a drenagem, posteriormente concluídas abaixo. Os três exemplos de demo continuam conferências de identidade; serviços reais de importação/conciliação criarão seus dados antes da publicação. Não há GitHub ou deploy do Lastro.
## Provas adicionais e interface

Quota com duas conexões/processos independentes: segunda criação bloqueada observada no PostgreSQL, depois conflito e cinco conferências; controle negativo sem proteção aceita seis. Dois resets serializados resultam sucesso/conflito de limite; uma mutação aguardando geração antiga é recusada após revogação. Sessão com cookies e CSRF reais funciona em duas instâncias HTTP separadas; reset invalida cookies antigos na outra instância e mantém o limite entre instâncias. Coleta pula workspace bloqueado e recolhe após liberação.

Frontend do Antigravity integrado após revisão e correções de CSRF/props/preview/versão. Codex repetiu 18 testes de componentes/helpers, typecheck e build com sucesso. Isto não é prova do fluxo completo no navegador; fontes/exemplos serão criados pelos serviços reais. Copilot executou e entregou o parser na conversa existente via interface de agentes do próprio VSCode, com relatório verificado na etapa3.

Verificação final do núcleo da tarefa2: 39 testes/163 assertions passaram no PostgreSQL real; Pint e git diff --check passaram. Testes específicos contra importação/revisão serão adicionados quando os respectivos serviços existirem, antes da publicação.

## Importação — etapa3 verificada

Copilot entregou parser estrito, precisão monetária e normalização de hash.
Codex repetiu os 154 testes / 492 assertions e implementou persistência e HTTP.
A suíte inteira passou com **209 testes / 797 assertions** em PostgreSQL real.
Pint e diff check passaram; migration aplicada ao banco de desenvolvimento.

Prévia não persiste dados; confirmação revalida arquivo/período/hash sob locks,
grava fonte/linhas/evento/versão atomicamente e fecha o período. Mesmo conteúdo
preserva fonte/filename/evento; substituição, arquivo trocado e versão antiga
recebem409. Sem raw CSV persistido. Limites de bytes/linhas e identidade de
visitante sobrevivendo ao reset foram comprovados.

Quatro disputas em processos independentes foram observadas bloqueadas no
PostgreSQL: mesmo conteúdo retorna uma única fonte; diferente gera409;
mudança de período é revalidada; reset recusa confirmação antiga404.
Falha injetada após gravação das linhas prova rollback integral. Coleta de
workspace com fontes remove corretamente linhas/eventos/atores juntos.
Relatórios: docs/tasks/copilot-parser-result.md e copilot-import-result.md.

Conciliação/revisão/exportação ainda não entregues. Frontend ainda precisa de
QA do fluxo completo e design no navegador; Lastro não está publicado.
