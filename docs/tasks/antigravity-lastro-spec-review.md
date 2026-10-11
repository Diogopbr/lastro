# Revisão de especificação — Antigravity

O hook de leitura do CLI falhou; Codex forneceu o texto completo da especificação diretamente ao CLI em modo plan, sem ferramentas ou alterações de arquivos. Os achados abaixo são a resposta recebida. Codex incorporou clarificações de período, pendências, comparação com fonte ausente, navegação e estados separados no documento. Não houve implementação.

Aqui estão os cinco achados de produto e interface da especificação:

1. **Ambiguidade entre "período informativo" e erro bloqueante no CSV**
   * **Problema:** O percurso principal descreve a criação da conferência com "nome e período informativo", mas em seguida define que "uma data fora dele é erro explícito" e que o lote inválido não grava nada. Se a data fora do intervalo rejeita o arquivo inteiro, o período não é meramente informativo, mas um filtro rígido de validação.
   * **Impacto/Ajuste:** Definir explicitamente se a edição do período da conferência é permitida antes da importação ou assumir o período como regra restritiva de corte. Caso o usuário erre o intervalo ao cadastrar a conferência, a impossibilidade de ajuste o obrigará a descartar a conferência inteira antes mesmo de validar o arquivo.

2. **Falta de delimitação do trabalho pendente e métrica de progresso**
   * **Problema:** A especificação elege o "trabalho pendente" como destaque visual, definindo o status de revisão como `pendente/revisada`. Porém, não esclarece se registros no status *Conciliado* já nascem resolvidos (fora da fila de trabalho) ou se entram como pendentes.
   * **Impacto/Ajuste:** Delimitar que apenas as três classes de divergência (*Valor divergente*, *Sem repasse* e *Sem pedido*) compõem a fila de trabalho pendente. Além disso, a interface necessita de um indicador explícito de progresso (ex.: "X de Y pendências revisadas") na listagem e no cabeçalho da mesa, orientando o usuário sobre quando a conferência está apta para exportação final.

3. **Apresentação e alinhamento visual de divergências assimétricas**
   * **Problema:** A mesa de conferência propõe exibir "arquivos de origem, linhas e diferenças lado a lado". Essa disposição funciona de forma direta para *Valor divergente*, mas quebra para *Sem repasse* (falta o repasse) e *Sem pedido* (falta o pedido).
   * **Impacto/Ajuste:** Especificar o estado de ausência no painel comparativo (placeholder ou slot visual vazio com identificação clara de qual fonte originou o registro órfão). Sem isso, tanto na tabela desktop quanto na visualização em cartões no mobile, a interface pode passar a impressão de campo quebrado ou falha de renderização.

4. **Ergonomia de navegação sequencial no fluxo de revisão (mobile e teclado)**
   * **Problema:** O fluxo exige abrir cada divergência, registrar justificativa com nota obrigatória e salvar. O documento prevê acessibilidade por teclado e diálogos, mas não contempla navegação sequencial entre pendências.
   * **Impacto/Ajuste:** Para evitar um ciclo repetitivo e exaustivo de "abrir diálogo → salvar → fechar → rolar lista → abrir próximo", a interface deve prever atalhos de teclado (ex.: navegação direta entre divergências) e ações de "Salvar e ir para a próxima pendência" dentro do diálogo/painel de detalhe, inclusive no mobile.

5. **Colisão visual entre status de conciliação e status de revisão**
   * **Problema:** A direção visual estabelece papel claro, tinta azul escura, verde para correspondências e acento cobre para diferenças. No entanto, cada linha possui dois estados ortogonais: a **classificação da conciliação** (ex.: valor divergente) e o **status da análise humana** (pendente vs. revisada).
   * **Impacto/Ajuste:** É necessário definir como uma divergência revisada é sinalizada visualmente. Manter o acento cobre em destaque pode dar a entender que o item ainda requer atenção, enquanto adotar verde pode confundi-lo com um item originalmente conciliado. A especificação deve prever um tratamento neutro de completude (ex.: redução de saturação/contraste, badge neutro com ícone de confirmação) para diferenciar claramente "divergência pendente" de "divergência já justificada".
