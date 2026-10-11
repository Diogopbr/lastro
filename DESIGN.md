# Lastro — direção de implementação

Direção manual code-first escolhida por Codex sob autonomia delegada. Não houve comp aprovado, seed/concept roll ou quality-bar card. Critério de qualidade: leitura e comparação de registros reais, estados compreensíveis, ação de revisão explícita, teclado e celular sem overflow.

## Mundo visual

Mesa de conferência editorial: branco neutro, tinta azul escura, regras finas e números tabulares. Fonte Source Sans3 em400/600 para leitura; Source Serif4 em500 na marca e títulos de entrada. Fontes locais WOFF2 e licença OFL em public/fonts. Não repetir grafite/laranja e navegação lateral do Bancada.

Paleta: page #f8fafb, surface #ffffff, ink #18324a, muted #526579, border #d7e0e6; copper #9b4b25 para diferenças/ação, match #2a6044 para correspondência. Revisada é marcador neutro; não pintar divergência revisada como sucesso de conciliação. Raio4px nos controles; seções de dados delimitadas por regras, sem cartões de métricas decorativos.

## Composição

Barra superior com marca, conferências e controles discretos da demonstração. Lista de conferências útil com período, fontes e progresso real do servidor. Importação em etapas legíveis: modelo/arquivo, prévia com erros e confirmação. Mesa de resultados com classificação e revisão distintas; ao abrir um resultado, pedido/repasse lado a lado no desktop e empilhados com rótulos no celular. Ausência explícita, origem/linha/hash consultáveis e formulário de nota preservado em erro.

Entrada pode ensinar o caso usando uma comparação fictícia pequena de pedido e repasse, identificada como exemplo, e ação direta para criar ambiente privado. Não inventar estatísticas ou usar uma landing genérica de três cartões. Sem imagens, textura física simulada, gradient text, eyebrows ou faixas laterais decorativas.

## Interação

Estados vazios/carregamento/erro/sucesso fazem parte do produto. Arquivo selecionado e nota ficam no componente durante validação/conflito. GET /csrf fresco antes das mutações; geração antiga não é reenviada ao namespace novo. Salvar e próxima avança apenas após persistência e usa pendências reais. Reset tem confirmação, trap de foco, Escape e retorno ao botão. Reduced motion, foco visível e contraste AA.

Documento provisório: depois da implementação, reconciliar com o código e as capturas reais, sem converter intenção em evidência.
