# Antigravity — correções após navegador real

Continue conversa f8e0ec19-d781-4bbe-be17-02f21195fc47 na worktree
D:\Eu\Projetos\2\.worktrees\antigravity, branch agent/antigravity-lastro.
Codex preservou sua entrega e atualizou a base com acesso e importação reais.
Não altere backend/config/rotas/migrations/manifestos. Sem commit/push/deploy.
Escopo frontend resources/{js,css}, testes/frontend e relatório
docs/tasks/antigravity-browser-fixes-result.md.

Use Impeccable Operate e a direção existente paper/ink/copper, SourceSans3/
SourceSerif4. O usuário pediu fugir da cara genérica de IA e delegou decisões.
Codex executou um lote real desktop1440/mobile390 e fluxo completo de criação/
prévia/confirmação. Capturas disponíveis em D:\Eu\Projetos\2\.local\browser-import:
entry-desktop.png, entry-mobile.png, conferences-mobile.png,
conference-mobile.png, import-mobile.png, preview-mobile.png,
preview-desktop.png, confirmed-desktop.png e checks.json. Leia imagens se
ferramenta disponível; evidências precisas a seguir bastam para agir.

Corrija tudo em um lote, preservando apiFetch/GETcsrf fresh em mutações:

1. Mobile: scrollWidth505 na entrada e649 em todas páginas autenticadas
   contra viewport390. Header nav-container/nav-left/nav-right não quebram.
   Reorganize em duas linhas compactas no celular: marca/navegação e ações.
   Usuário/validade não podem forçar largura. Breadcrumb deve quebrar texto.
   Botões Confirmar/Voltar devem wrap/empilhar; white-space:nowrap global não
   pode impor largura maior que seu pai. Corrija causas, não overflow-x:hidden.
   Tabelas de prévia no celular viram registros rotulados; comparação sem
   scroll horizontal. Desktop mantém alinhamento/legibilidade.
2. Axe: file input sem label (critical), dropzone rolebutton contém input e
   botãoTrocar (nested-interactive serious). Use input nativo com label e
   dropzone não interativa contendo controles separados. Preserve drag/drop,
   teclado, File selecionado/remover, desabilitado. Teste RED/GREEN labels/
   ausência de rolebutton envolvendobotão/input e fluxo de selecionar/trocar.
3. Entrada demo mostra login de equipe embora backendrecuse login normal no
   modo demo. Renderizar credenciais somente quando demoEnabledfalse; teste.
   Header entrada não precisa duplicar IniciarDemo e Entrar/Conferências que
   apenas retornam ao login. Priorize uma ação direta no conteúdo.
4. Redesenhe a entrada do modo demo dentro do mundo aprovado: composição
   editorial com título 'Pedidos e repasses, conferidos linha a linha.',
   explicação curta, botão da demo e comparação concreta ao lado no desktop,
   empilhada no celular. Exemplo explicitamente fictício: pedido0002 esperado
   R$200,00, bruto informadoR$190,00, diferença-R$10,00, taxaR$5,00/líquidoR$185,00.
   Mostrar divergência e origem em uma pequena folha/tabela útil, sem um
   dashboard falso. Remova login-card/demo-box aninhados e cópia técnica
   genérica. Não inventar usuários/clientes/adoção nem usar imagens decorativas.
   Uma linha explica demo privada, até5conferências e24h. Os3exemplos serão
   preenchidos por serviços reais antes do deploy; não tratar vazios como demo
   concluída. Sem eyebrow, gradients, íconesunicode, hero de métricas.
5. ConferenceShow 'Substituir arquivo' é falso: fonte é imutável. Use 'Conferir
   arquivo novamente' ou equivalente e explique que para conteúdo diferente
   se cria nova conferência. Confirmar mesmo conteúdo é idempotente.
6. ImportsPreview textos técnicos 'validação de regras de negócio/gravação
   definitiva' devem falar com usuário: conferir linhas/valores antes de
   confirmar. Botão 'Confirmar importação' suficiente. FilePicker explica
   exemplos fixos de maio2026/dadosfictícios e modelos vazios para preencher.
   Limit1048576bytes/100registros, valor com ponto noCSV. Recuperação de404 deve
   explicar expirou/reset e oferecer iniciar nova demo, sem reenvio automático.
7. Não ignorar outros controles ao ajustar responsividade: filtro/resultados/
   reviewPanel/summary/modal precisam manter layout390. Use tokens existentes,
   foco visível e textos reais; nenhum novo framework/dependência.

Execute npm test -- --run, npm run typecheck, npm run build após dot source
scripts/local-env.ps1; Node24.15 já disponível. Registre comandos/pass/falhas
reais no relatório. Codex fará a única rodada de confirmação no browser depois
de integrar; não declare axe/mobile/browser sem executar. Sem novasjanelas.
