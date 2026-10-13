---
name: Lastro
description: Mesa de conferência de pedidos pagos e repasses fictícios.
colors:
  page: "#f8fafb"
  surface: "#ffffff"
  ink: "#18324a"
  muted: "#526579"
  border: "#d7e0e6"
  border-subtle: "#e8eef2"
  copper: "#9b4b25"
  copper-subtle: "#fcf4f0"
  copper-border: "#e8c6b7"
  match: "#2a6044"
  match-subtle: "#f0f7f3"
  match-border: "#b8dac5"
  neutral-badge: "#4a5568"
  neutral-bg: "#edf2f7"
  neutral-border: "#cbd5e0"
  focus: "#18324a"
typography:
  display:
    fontFamily: "'Source Serif 4', Georgia, serif"
    fontSize: "2.25rem"
    fontWeight: 500
    lineHeight: 1.2
    letterSpacing: "-0.02em"
  body:
    fontFamily: "'Source Sans 3', system-ui, -apple-system, sans-serif"
    fontSize: "15px"
    lineHeight: 1.5
  title:
    fontFamily: "'Source Sans 3', system-ui, -apple-system, sans-serif"
    fontSize: "1.5rem"
    fontWeight: 600
    letterSpacing: "-0.01em"
  label:
    fontFamily: "'Source Sans 3', system-ui, -apple-system, sans-serif"
    fontSize: "0.8125rem"
    fontWeight: 600
rounded:
  control: "4px"
components:
  button-primary:
    backgroundColor: "{colors.copper}"
    textColor: "{colors.surface}"
    rounded: "{rounded.control}"
    padding: "0.5rem 0.875rem"
  button-secondary:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.control}"
    padding: "0.5rem 0.875rem"
  button-quiet:
    backgroundColor: "transparent"
    textColor: "{colors.muted}"
    rounded: "{rounded.control}"
    padding: "0.375rem 0.625rem"
  button-danger:
    backgroundColor: "#b91c1c"
    textColor: "{colors.surface}"
    rounded: "{rounded.control}"
    padding: "0.5rem 0.875rem"
  input-text:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.control}"
    padding: "0.5rem 0.75rem"
    width: "100%"
  badge-matched:
    backgroundColor: "{colors.match-subtle}"
    textColor: "{colors.match}"
    rounded: "{rounded.control}"
    padding: "0.125rem 0.5rem"
  badge-neutral:
    backgroundColor: "{colors.neutral-bg}"
    textColor: "{colors.neutral-badge}"
    rounded: "{rounded.control}"
    padding: "0.125rem 0.5rem"
  reconcile-container:
    backgroundColor: "{colors.surface}"
    rounded: "{rounded.control}"
    padding: "1.5rem"
---

# Design System: Lastro

## Overview

**Creative North Star: "Mesa de conferência contábil"**

Papel claro, tinta azul e cobre sustentam uma interface de trabalho em modo Operate. A hierarquia serve à leitura de fontes, comparação de valores e registro de análise. Os números são tabulares; as divisões são regras finas; a identidade combina marca serifada e texto operacional sem serifa.

Este documento é um REFRESH do provisório, extraído do código vigente. A direção manual code-led foi escolhida por Codex sob autonomia delegada, registrada em `.superpowers/sdd/2026-10-10-lastro/progress.md`. Não houve comp aprovado, seed/concept roll ou quality-bar card; a ausência é uma exceção delegada, não uma escolha em workshop. As decisões qualitativas preservam a direção já registrada, sem nova preferência atribuída ao usuário.

**Key Characteristics:**

- Papel claro e tinta azul, com cobre e verde vinculados à classificação.
- Comparação rotulada, origem integral e valores tabulares.
- Superfícies planas separadas por regras finas.
- Navegação superior e registros móveis empilhados.

## Colors

A paleta reserva o azul à leitura e usa os acentos para decisões e estados explicáveis. Os valores normativos estão no frontmatter e correspondem às custom properties de `resources/css/app.css`.

### Primary

- **Tinta azul** (`ink`): texto, títulos, navegação ativa e fronteira de campos em foco. `focus` conserva o token separado usado pelo outline global.

### Secondary

- **Cobre de conferência** (`copper`): ação principal, diferença monetária e mensagens de validação. `copper-subtle` e `copper-border` compõem badges de divergência e sinal da demonstração.

### Tertiary

- **Verde de correspondência** (`match`): valores correspondentes e confirmação; seus tokens sutis delimitam badges e flash de sucesso.

### Neutral

- **Papel e superfície** (`page`, `surface`): plano da aplicação e áreas de trabalho.
- **Texto auxiliar e regras** (`muted`, `border`, `border-subtle`): rótulos, metadados e separação de registros.
- **Revisão neutra** (`neutral-badge`, `neutral-bg`, `neutral-border`): estado de análise separado da classificação.

**The Classification Rule.** Marcar um resultado como revisado não muda sua classificação nem seu valor e não transforma uma divergência em correspondência verde.

Há cores literais locais para ausência, perigo, hover e seleção. Elas permanecem nos snippets de seus componentes; não constituem novas escalas globais.

## Typography

**Display Font:** Source Serif 4 com Georgia e serif como fallback.
**Body Font:** Source Sans 3 com system-ui, -apple-system e sans-serif como fallback.

As fontes locais WOFF2 são declaradas em `resources/css/app.css`; arquivos e licença OFL ficam em `public/fonts`. Source Sans 3 tem faces declaradas de peso 400 e 600, Source Serif 4 de peso 500. O título de entrada usa a classe de marca serifada; títulos operacionais usam a sem serifa.

### Hierarchy

- **Display:** título de entrada conforme o token `display`; no breakpoint móvel passa a (1.625rem), com balanceamento de linhas.
- **Title:** páginas de lista, prévia e execução usam `title`; a página da conferência tem título local de (1.625rem). Não há escala matemática global de títulos.
- **Body:** token `body`; no breakpoint móvel o corpo passa a (14px). Texto de tabela e campos usa (0.875rem).
- **Label:** token `label`; badges e metadados curtos usam (0.75rem).
- **Hash:** monospace (0.75rem), com valor SHA256 completo, sem abreviação.

**The Numeric Rule.** Valores monetários, datas e identificadores indicados pelo código usam `font-variant-numeric: tabular-nums`; ausências continuam explícitas.

## Layout

`resources/js/layouts/AppLayout.vue` define um contêiner central com largura máxima (76rem), cabeçalho sticky e navegação superior. O conteúdo usa padding (1.5rem 1.25rem 3rem 1.25rem). Até (640px), o cabeçalho empilha e o conteúdo passa a (1rem 0.75rem 2rem 0.75rem). A assinatura auxiliar da marca aparece a partir de (768px).

A mesa de execução divide lista e detalhe em `minmax(0, 1.1fr)` e `minmax(0, 1.3fr)`, com gap (1.5rem); até (1024px) torna-se uma coluna. Pedido e repasse usam duas colunas iguais e se empilham até (640px). A área de fontes/importação da conferência se empilha até (768px).

Até (640px), tabelas de resultados e prévia cedem lugar a registros rotulados. Resultados têm borda inferior; prévia tem regra superior e divisória de cabeçalho. O sumário da execução usa duas colunas no celular, sem molduras individuais. A busca permite redução do campo (`min-width: 0`) e preserva o botão Buscar inteiro (`flex-shrink: 0; white-space: nowrap`).

Não existe escala de spacing declarada. Paddings e gaps acima descrevem componentes reais e não autorizam inventar uma escala. A documentação captura o CSS; uma validação de navegador deve ser reportada separadamente.

## Elevation & Depth

As superfícies de trabalho são planas, com fundos tonais e bordas finas. Não há sombra nas listas, prévias ou comparação. O diálogo de reinício é a exceção observada em `resources/js/components/DemoControls.vue`: backdrop `rgba(24, 50, 74, 0.4)` e sombra `0 4px 12px rgba(24, 50, 74, 0.08)`.

**The Flat Surface Rule.** Use regras e fundos para organizar dados; reserve a sombra implementada ao diálogo de reinício.

## Shapes

Controles e containers usam o único token de raio (`control`). Registros móveis de resultado têm cantos retos, sem borda externa completa. Exemplos compactos da entrada usam raio local de (2px); isso não é uma escala global. Bordas de trabalho são sólidas (1px); ausência e seleção vazia usam contorno tracejado.

## Components

### Buttons

Quatro variantes reais: principal cobre, secundária branca com borda, discreta transparente e perigo vermelho. Padding e raio estão no frontmatter. Hover principal usa `#853e1d`; perigo usa `#991b1b`. Transições de fundo, borda e texto usam (150ms ease). Disabled reduz opacity a (0.5). Botões herdam foco global com outline (2px) e offset (2px). No móvel permitem quebra de texto, com a exceção específica de Buscar.

### Inputs / Fields

Texto, select e textarea compartilham preenchimento branco, borda fina, largura total e padding do token `input-text`. Focus muda a borda para tinta e remove o outline; documentar esse comportamento não equivale a uma nova auditoria de acessibilidade. Disabled usa papel e texto auxiliar. Rótulo, dica e erro são elementos próprios; erro usa cobre e peso 600.

### Chips / Status

Badges de correspondência, divergência, ausência, revisão e pendência incluem texto. Revisão permanece neutra. Ausência usa as cores literais `#fdf2e9`, `#a04000`, `#f5cba7`; pendência usa fundo `#f7fafc`. Os snippets preservam esses valores locais quando aplicáveis.

### Cards / Containers

Áreas de importação, conciliação e comparação usam superfície, borda e raio, sem sombra. O container de conciliação tem padding normativo `reconcile-container`; a execução vigente e os registros móveis usam regras, reduzindo molduras dentro de molduras. Containers não são cartões promocionais nem métricas inventadas.

### Navigation

Marca serifada (1.375rem), link Conferências (0.875rem, peso 600), estado ativo e hover em tinta. O shell é superior; breadcrumbs usam texto (0.75rem) e quebram no celular. Hover do breadcrumb sublinha o link. O atalho de pular conteúdo aparece ao receber foco.

### Source Comparison / Review

`SourceComparison.vue` mantém classificação, revisão e diferença visíveis no cabeçalho. Campos têm rótulos, linhas de origem e hash integral; nome de arquivo quebra com `overflow-wrap: anywhere` e hash com `word-break: break-all`. `ReviewPanel.vue` mantém ação de salvar, salvar e próxima e histórico de análise. A revisão anota decisões sem reclassificar o resultado.

### Reset Dialog

O diálogo de `DemoControls.vue` tem largura máxima (28rem), padding (1.5rem), backdrop e sombra descritos acima. Sua confirmação de reinício é uma ação explícita; o componente contém gerenciamento de foco e Escape. Nenhuma animação de entrada nem regra reduced-motion aparece no CSS escaneado; não registrar tokens de movimento ausentes.

## Do's and Don'ts

### Do:

- **Do** usar rótulos e valores tabulares para comparar fontes fictícias.
- **Do** manter classificação e revisão como estados independentes.
- **Do** preservar nomes de arquivos e hashes completos com quebra de linha.
- **Do** usar registros rotulados no celular e regras finas dentro das áreas de trabalho.
- **Do** manter o botão Buscar inteiro enquanto o campo reduz sua largura.

### Don't:

- **Don't** transformar ausência em zero ou revisão em correspondência verde.
- **Don't** inventar escala de spacing, sombras, métricas ou componentes sem fonte no código.
- **Don't** atribuir comp, seed ou escolha em workshop a esta direção manual delegada.
- **Don't** repetir grafite, laranja e navegação lateral do Bancada.
- **Don't** adicionar imagens, textura física simulada, gradient text ou faixas decorativas à direção vigente.
