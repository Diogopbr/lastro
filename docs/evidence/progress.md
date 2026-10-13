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

## Conciliação, revisão e exportação — integração em12/10

Copilot entregou os domínios nas tarefas4/5. Codex executou independentemente
61 testes /403 assertions, integrou rotas reais e projeções de conferências.
O teste HTTP produtivo percorre conciliar/revisar/filtrar/exportar e comprova
progresso atualizado. Dois testes de formulários passaram a usar os caminhos
produtivos depois de detectar conflito entre nomes de rotas das fixtures.

A suíte inteira passou com **275 testes /1275 assertions** em PostgreSQL real.
Inclui disputas run/run, review/reopen, export/review e reset/export, com
processos/conexões independentes e bloqueio observado. Exportação materializa
um único snapshot REPEATABLE READ antes de liberar a resposta; campos textuais
com risco de fórmula recebem proteção, dinheiro negativo permanece decimal.
Não houve teste de fórmulas em Excel, nem afirmação de leitura bancária real.

DemoSeeder agora cria fontes e execuções pelos serviços reais: Marketplace
com quatro classificações, Loja online conciliada e Pedidos avulsos com duas
ausências. Testes específicos verificam6fontes/3runs/8resultados, idempotência,
isolamento de dois visitantes e coleta após reset. Fixtures isoladas de domínio
desativam enriquecimento dos exemplos via LASTRO_DEMO_EXAMPLES=false; o padrão
da aplicação é true, e testes específicos + E2E exercitam o enriquecimento real.

Ruling do formato: o CSV segue o cabeçalho fixo dos contratos, uma linha por
resultado. Origem/hash são consultáveis no detalhe; totais globais na aplicação.
Não inserir rodapés/linhas sintéticas no CSV para cumprir frase genérica anterior
do plano. O arquivo contém todas as linhas mesmo com filtros ou pendências.

Relatórios externos em docs/tasks/copilot-reconciliation-result.md e
copilot-review-export-result.md. Frontend ainda precisa concluir correções e
QA do fluxo completo no navegador; Lastro não está publicado.

## Interface e verificação integrada — 12/10/2026

Antigravity concluiu o lote de responsividade/entrada/seletor de arquivo e
resincronização do resumo. Codex repetiu 28 testes, tipagem e build, integrou o
escopo frontend e corrigiu achados reais do percurso: rótulo do arquivo precisava
continuar presente após seleção; tabela de resultados precisava de registros
rotulados no celular; notas inválidas precisam da mensagem específica do servidor;
correspondências exatas não oferecem revisão; reabertura não pode ter botão
ambíguo de salvar/próxima; falha de recarga precisa de mensagem e recuperação.

Reset em outra aba também expôs rascunho que podia criar na geração nova usando
cookie compartilhado. O cliente captura X-Lastro-Workspace antes de pedir CSRF
fresco; servidor compara apenas como expectativa, sem usar o header para
autorizar. RED aceitou201 indevido; GREEN recusa404 e preserva rascunho, permitindo
criação somente na página da geração atual. Navegador confirmou o caso real.

Verificação por scripts/verify.ps1: **283 testes PHP /1313 assertions**, **34
testes frontend /16suítes**, **3 E2E em12,6s**, Composer validate/platform,
Pint, typecheck, build e diff check passaram. Backend em PostgreSQL real,
sem SQLite ou skipped tests. Foram observadas notas NUL/visualmente vazias
aceitas antes da correção; regressões agora recebem422 sem evento/versão extra.

Navegador: importar dois CSVs, conciliar, revisar, reload, exportar4resultados,
reabrir; corrigir CSV inválido no390px; isolar visitantes e recusar aba antiga
após reset. Auditoria de13estados capturados: Axe WCAG AA zero violações,
scrollWidth=390 no celular e1440 no desktop, sem pageerror. Fontes locais200.
Capturas reais com dados fictícios em docs/screenshots; posição de scroll é
zerada ao fotografar para não duplicar a barra sticky no meio da imagem.

Hospedagem preparada: projeto Vercel Lastro e Neon Free separados, migrations
aplicadas com TLS verificado, role limitada e APP_KEY próprio por stdin.
Não houve deploy, GitHub público ou CI remoto do Lastro até esta verificação.
Revisão independente e validação hospedada continuam como gates de publicação.

## Fechamento da revisão independente — 12/10/2026

A revisão estática inteira encontrou um resumo antigo após filtro/paginação
ou recuperação de conflito em outra aba. Dois testes reproduziram o contador
antigo; a correção sincroniza novas props e invalida respostas de resumo antigas.
Carregar a versão atual também atualiza o andamento. A reinspeção marcou o
achado importante resolvido. A ação sequencial foi delimitada explicitamente
como “Salvar e próxima desta página”. O terceiro exemplo agora aguarda importação,
como previsto no design: quatro fontes, duas execuções e seis resultados reais,
mais uma conferência sem fontes. Idempotência, reset e coleta foram revalidados.

A revisão visual pediu origem completa acessível, nova captura do Buscar em390px
e redução das molduras internas. Hashes completos e nomes quebráveis dispensam
hover; registros e prévia mobile usam regras. O veredicto do lote marcou os três
fixes resolvidos (`ship` nesse escopo), sem alegar aprovação integral de runtime.

Nova verificação completa: **283 PHP /1315 assertions**, **36 frontend /16suítes**,
**4 E2E em14,1s**, Composer/Pint/typecheck/build/diff passaram. O quarto E2E prova
entrada via Tab/Enter e trap/Escape/retorno de foco no reset; não declara toda a
importação/revisão por teclado. Treze estados reais recapturados com scroll0
mantiveram zero violações axe, zero overflow e zero pageerror.
