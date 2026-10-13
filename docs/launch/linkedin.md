# LinkedIn — rascunho, não publicado

## Texto

Um pedido de R$200 e um repasse de R$190: como mostrar a diferença sem perder de onde cada valor veio?

Criei o Lastro para explorar esse problema. Ele importa dois CSVs, valida as linhas e cruza os identificadores exatos. A mesa mostra valores, taxas, arquivo, linha e hash. Cada divergência pode receber uma análise, com histórico de revisão e reabertura; os valores originais permanecem intactos.

Usei Vue, Laravel e PostgreSQL. O banco protege as operações concorrentes, e a exportação lê um estado consistente mesmo quando outra pessoa está revisando. A demonstração oferece um ambiente privado por visitante, inclusive pelo celular.

É um projeto de portfólio com dados fictícios, sem integração bancária. O código inclui testes e decisões técnicas para quem quiser examinar como funciona.

Demonstração: https://lastro-red.vercel.app
GitHub: https://github.com/Diogopbr/lastro

## Roteiro de demonstração

1. Iniciar a demo e abrir Marketplace — maio. Examinar0002: pedido200,
   bruto informado190, diferença−10, taxa5 e líquido185; consultar as fontes.
2. Registrar uma análise e recarregar. Mostrar que revisar preserva a
   classificação e os valores, e acrescenta um evento de auditoria.
3. Reabrir com motivo; exportar o CSV completo, independentemente dos filtros.
4. Abrir Pedidos avulsos — maio, que aguarda importação. Baixar os CSVs de
   exemplo, confirmar fontes e executar a conciliação.
5. Reiniciar o ambiente e explicar a separação entre visitantes e o limite24h.

Tom e extensão podem ser ajustados antes de postar. Publicação social é uma
etapa separada da criação do repositório e do deploy.
