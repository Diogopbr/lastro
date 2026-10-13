# Publicação do Lastro — 13/10/2026

- Repositório público: https://github.com/Diogopbr/lastro, branch `main`.
- Demonstração: https://lastro-red.vercel.app.
- CI: https://github.com/Diogopbr/lastro/actions/runs/38326609233,
  success em `9e2814e8a2a482e91770c4b88e07625e184d6253`.
- Deployment verificado: `dpl_741zNwfadAmFRcwTEJrHYsoSeYRh`, Ready,
  container de216,45MB emgru1. GitHub conectado ao projeto Vercel `lastro`.
- Neon Free independente, role de runtime sem superuser/criação de objetos,
  TLS verify-full. Nenhum plano pago contratado nesta entrega.

## Prova local e CI

Verificação completa passou: 284 testes PHP/1319 assertions em PostgreSQL real,
36 frontend em16 suítes, typecheck/build/Composer/Pint/diff e4 E2E.
No CI Linux:284PHP/1319assertions,36frontend e4E2E em9,1s.
Após corrigir o host explícito do teste de ingress e preparar `.env.example`
no CI, a suíte PHP não apresentou os111warnings da primeira execução.

## Prova no domínio público

Playwright real com `E2E_BASE_URL=https://lastro-red.vercel.app`: **4passed em50,8s**.

1. Tab/Enter inicia demo; diálogo de reset mantém foco e Escape o devolve.
2. Importar dois CSVs, confirmar, conciliar, revisar0002, recarregar, exportar
   todas as linhas e reabrir. Auditoria axe e largura desktop/mobile passaram.
3. Prévia inválida mantém fonte ausente; arquivo corrigido funciona em390px.
4. Visitantes distintos não acessam a execução alheia; reset recusa criação
   pela aba antiga sem descartar seu rascunho e preserva o outro visitante.

Prova adicional em contexto privado próprio: cookies `lastro_visitor` e
`lastro_session` ambos Secure/HttpOnly/SameSiteLax; CSRF inválido419.
`/.env`, `/vendor/autoload.php`, `/storage/logs/laravel.log` e
`/docs/contracts/http.md` retornaram404. Nenhum pageerror registrado.
Foi criada uma conferência fictícia e guardado o contexto privado para verificar
sessão/dados após o deploy seguinte; essa checagem será registrada ao concluir.

Quinto cenário acrescentado ao final: fluxo sem clicks/fill, usando Tab/Enter
para abrir a conferência vazia, selecionar fontes, gerar prévia, confirmar,
conciliar, selecionar0002, digitar nota, salvar e baixar. O diálogo nativo de
arquivo é aberto pelo teclado e recebe o CSV pela API FileChooser da automação.
Passou local em7,3s e no domínio público em16,8s; os quatro testes anteriores
passaram em lote separado. A próxima CI executará os cinco juntos.

## Revisão e limites da evidência

Revisão de código inteira estática marcou seu achado importante resolvido após
regressões. A revisão visual fresca pediu três ajustes; o verdict pass marcou
os três resolved, `ship` no escopo desse lote. DESIGN.md e sidecar schema2
foram extraídos do código. Nenhum comp/seed/card aprovado foi inventado.

Primeira tentativa de deploy falhou por detecção Vite; serviço explícito corrigiu.
A imagem inicial Ready ainda falhava no pool Neon, SQLSTATE25P02; diagnóstico
de leitura RED/GREEN e configuração PDO corrigiram. Esses erros não são ocultados
nem considerados sucesso. A evidência hospedada pertence ao deployment corrigido.

Dados são fictícios. Excel real, uso comercial, uptime garantido, auditoria externa
e publicação no LinkedIn não foram realizados. O rascunho social está separado.
