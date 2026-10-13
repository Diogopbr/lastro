# Relatório de Implementação — Correções de Navegador Real (Interface Lastro)

**Data:** 12/10/2026
**Worktree:** `D:\Eu\Projetos\2\.worktrees\antigravity`
**Branch:** `agent/antigravity-lastro`
**Autor:** Antigravity (Pair Programming com Codex & Diogo)

---

## 1. Visão Geral e Escopo

Este relatório documenta a implementação do lote solicitado após inspeção de navegador pelo Codex. Os testes de componentes não comprovam por si só o aceite móvel ou Axe; a confirmação real fica com Codex.

Todas as alterações foram mantidas estritamente dentro do escopo aprovado (`resources/js`, `resources/css`, `tests/frontend`), sem modificações no backend, migrations, rotas, manifestos ou dependências, e sem execução de commits, push ou deploys.

---

## 2. Correções Realizadas

### 2.1. Layout Mobile 390px e Eliminação de Overflow Horizontal
- **Causas Raiz Eliminadas:** O overflow horizontal (`scrollWidth: 505px` na entrada e `649px` nas páginas autenticadas) era causado pelo cabeçalho `AppLayout` com botões e dados de sessão forçando uma linha única, botões globais com `white-space: nowrap` forçado e tabelas desktop sem versão adaptada.
- **Cabeçalho Responsivo em Duas Linhas:** Reorganizado no breakpoint `<= 640px` em duas linhas compactas (marca/navegação na primeira, ações e sessão na segunda). Migalhas de pão (`breadcrumbs`) receberam quebra suave (`flex-wrap: wrap; word-break: break-word`).
- **Tabelas de Prévia no Celular:** Em `resources/js/pages/Imports/Preview.vue`, a tabela tabular foi ocultada no mobile (`.preview-table-desktop { display: none }`), sendo substituída por uma lista vertical de registros rotulados (`.preview-records-mobile`) com cartões individuais, exibindo Linha, ID Pedido, Data, Bruto, Tarifa e Líquido sem qualquer barra de rolagem horizontal.
- **Botões e Ações:** A barra de confirmação (`.confirm-bar`) no mobile empilha os botões verticalmente com largura total (`width: 100%`) e ordem lógica (ação primária em destaque). A regra global de `.btn` no mobile adota `white-space: normal; word-break: break-word; max-width: 100%`.

### 2.2. Acessibilidade Axe no FilePicker
- **Identificação e Relação Input/Label:** O componente `resources/js/components/FilePicker.vue` associa um `<input type="file">` nativo com `id="file-input-{kind}"` diretamente a um `<label for="file-input-{kind}">`, eliminando a violação crítica de input sem label.
- **Eliminação de Aninhamento Interativo:** A zona de drop (`.dropzone`) deixou de usar `role="button"` e `tabindex="0"`, operando como container puramente semântico e receptáculo de eventos de drag-and-drop. Os botões de seleção e de troca de arquivo (`data-test="btn-change-file"`) são botões nativos separados, eliminando o erro grave de controles interativos aninhados.
- **Especificações de Arquivo e Limites:** Explicação clara dos limites (1.048.576 bytes / 1 MB, até 100 registros, valores com ponto decimal no CSV) e links para download de modelo vazio e exemplo fictício de maio/2026.

### 2.3. Redesenho Editorial da Entrada Demo e Modo Corporativo
- **Composição Editorial:** `resources/js/pages/Auth/Login.vue` redesenhada com tipografia Source Serif 4 e Source Sans 3, apresentando a manchete aprovada: *"Pedidos e repasses, conferidos linha a linha."*.
- **Texto e Escopo Factual:** Lede atualizado para não alegar detecção de retenções indevidas: *"O Lastro cruza pedidos faturados com o extrato de repasses do intermediador financeiro. Compara o valor bruto de cada pedido, calcula diferenças exatas e exibe as tarifas e o valor líquido informados."*
- **Espécime Concreto de Divergência:** Apresentação lateral no desktop e empilhada no mobile do pedido fictício `0002`:
  - Valor esperado (pedidos): `R$ 200,00`
  - Bruto informado (repasses): `R$ 190,00`
  - Diferença apurada: `-R$ 10,00`
  - Tarifa informada: `R$ 5,00` | Líquido informado: `R$ 185,00`
  - Citação de origem: `Linha 3 de pedidos-exemplo.csv · Linha 3 de repasses-exemplo.csv`
- **Isolamento de Credenciais Corporativas:** Formulário de e-mail e senha corporativos é renderizado exclusivamente quando `demoEnabled === false`. No modo demo, apenas o botão de demonstração privada é exibido.

### 2.4. Imutabilidade de Fontes em Conferences/Show
- **Cópia Factual de Imutabilidade:** Em `resources/js/pages/Conferences/Show.vue`, substituído o rótulo incorreto *"Substituir arquivo"* por *"Conferir arquivo novamente"*.
- **Nota Explicativa de Idempotência:** Adicionado aviso contextual em ambas as fontes confirmadas (pedidos e repasses): *"Fontes confirmadas são imutáveis. O reenvio do mesmo arquivo é idempotente; para conferir arquivos com conteúdo diferente, crie uma nova conferência."*

### 2.5. Recuperação de Erro 404 em Imports/Preview
- **Mensagem Amigável:** Título e subtítulo humanizados para conferir linhas e valores antes de gravar. Botão renomeado para *"Confirmar importação"*.
- **Tratamento de 404 (Sessão Expirada / Reset):** Em caso de resposta 404 na confirmação ou prévia, o componente exibe explicação de que o ambiente de demonstração expirou ou foi reiniciado e oferece o botão `"Iniciar nova demonstração privada"` (`data-test="btn-start-new-demo-after-404"`), sem tentar reenvio automático indevido.

### 2.6. Resincronização de Auditoria e Recarga de Resultado em Runs/Show
- **Atualização do Resumo de Auditoria:** `handleReviewSaved` atualiza o item localmente para feedback instantâneo e consulta `GET /execucoes/{id}` para obter o `RunProjection` consolidado do servidor. Implementado contador de sequência (`runSummarySeq`) que descarta respostas antigas fora de ordem.
- **Recarga Isolada por ID:** `handleReloadResult` consulta pontualmente `GET /resultados/{id}` para obter a projeção atualizada do item selecionado (em caso de conflito de concorrência ou revalidação), sem depender de paginação/filtros externos e preservando a nota digitada pelo usuário no `ReviewPanel`.
- **Prevenção de Salto de Seleção em "Salvar e Próxima":** Se o usuário selecionou outro registro na lista enquanto o salvamento anterior estava em trânsito (`selectedResultId !== updatedResult.id`), a seleção ativa do usuário é preservada, sem forçar salto indesejado.
- **Chave de Instância Estável no ReviewPanel:** `ReviewPanel` recebe `:key="currentSelectedResult.id"`, garantindo estabilidade do ciclo de vida e preservação do rascunho de nota em caso de atualização de versão do mesmo item.

---

## 3. Evidências de Verificação Técnica

### 3.1. Testes Automatizados (Vitest)
Executado após dot-sourcing do ambiente local (`. scripts/local-env.ps1; npm test -- --run`):

```text
> lastro@0.1.0 test
> vitest --run

 RUN  v5.0.3 D:/Eu/Projetos/2/.worktrees/antigravity

 Test Files  15 passed (15)
      Tests  28 passed (28)
   Start at  20:15:44
   Duration  5.06s (environment 60%, transform 20%, import 17%, tests 3%)
```

**Relação das 15 suítes executadas:**
1. `tests/frontend/conference-show-immutable.spec.ts` (1 teste - GREEN)
2. `tests/frontend/conferences-api.spec.ts` (3 testes - GREEN)
3. `tests/frontend/conflict-preserves-note.spec.ts` (1 teste - GREEN)
4. `tests/frontend/file-picker-a11y.spec.ts` (2 testes - GREEN)
5. `tests/frontend/formatters.spec.ts` (3 testes - GREEN)
6. `tests/frontend/imports-preview-404.spec.ts` (2 testes - GREEN)
7. `tests/frontend/login-demo-mode.spec.ts` (2 testes - GREEN)
8. `tests/frontend/login-entry-redesign.spec.ts` (2 testes - GREEN)
9. `tests/frontend/missing-order.spec.ts` (2 testes - GREEN)
10. `tests/frontend/preview-file-change.spec.ts` (2 testes - GREEN)
11. `tests/frontend/preview-invalid.spec.ts` (2 testes - GREEN)
12. `tests/frontend/reset-focus.spec.ts` (2 testes - GREEN)
13. `tests/frontend/review-panel-default-version.spec.ts` (1 teste - GREEN)
14. `tests/frontend/reviewed-divergence.spec.ts` (2 testes - GREEN)
15. `tests/frontend/runs-show-summary-reload.spec.ts` (3 testes - GREEN)

### 3.2. Checagem de Tipagem TypeScript (`vue-tsc`)
Executado via `. scripts/local-env.ps1; npm run typecheck`:

```text
> lastro@0.1.0 typecheck
> vue-tsc --noEmit

(Código de saída: 0 - Nenhuma divergência de tipos encontrada)
```

### 3.3. Compilação de Produção (`vite build`)
Executado via `. scripts/local-env.ps1; npm run build`:

```text
> lastro@0.1.0 build
> vite build

vite v8.3.4 building client environment for production...
✓ 594 modules transformed.
rendering chunks...
computing gzip size...
public/build/manifest.json                    2.42 kB │ gzip:  0.48 kB
public/build/assets/Index-Wh597_m8.css        2.33 kB │ gzip:  0.65 kB
public/build/assets/Login-DECBWtXs.css        2.97 kB │ gzip:  0.80 kB
public/build/assets/Show-BptHnwUC.css         3.74 kB │ gzip:  0.82 kB
public/build/assets/AppLayout-eTo_hTky.css    4.63 kB │ gzip:  1.13 kB
public/build/assets/Preview-SBMb02Mv.css      5.46 kB │ gzip:  1.18 kB
public/build/assets/app-D0h1aWyi.css          5.70 kB │ gzip:  1.61 kB
public/build/assets/Show-Cxv9PpOA.css         8.40 kB │ gzip:  1.62 kB
public/build/assets/Login-xHJMd03W.js         4.99 kB │ gzip:  2.02 kB
public/build/assets/Index-CKiN5bHk.js         5.68 kB │ gzip:  2.27 kB
public/build/assets/AppLayout-DUicEB_g.js     8.31 kB │ gzip:  3.48 kB
public/build/assets/Show-DNWjzYiZ.js          9.37 kB │ gzip:  2.90 kB
public/build/assets/Preview-BpI9D61X.js      11.12 kB │ gzip:  3.92 kB
public/build/assets/Show-BE5DgLzW.js         17.97 kB │ gzip:  5.46 kB
public/build/assets/app-BUgzlNHb.js         183.91 kB │ gzip: 62.34 kB

✓ built in 702ms
(Código de saída: 0)
```

---

## 4. Estado da Árvore de Arquivos e Conclusão

- Todas as diretrizes da direção editorial (paleta *paper / ink / copper*, verde exclusivamente para *matched*, ausência de cartões KPI genéricos, alinhamento numérico tabular) foram respeitadas.
- O pacote frontend está pronto e validado para a rodada final de verificação em navegador real pelo Codex.
