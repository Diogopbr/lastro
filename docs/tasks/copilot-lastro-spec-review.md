# Revisão documental da especificação Lastro

Data: 10/10/2026.

Documento revisado: [2026-10-10-lastro-design.md](../superpowers/specs/2026-10-10-lastro-design.md), 93 linhas.

Escopo: importação CSV, idempotência, precisão monetária, concorrência, isolamento de visitantes e critérios de aceite. Somente a especificação foi examinada; não houve inspeção de código, execução de testes, instalação, commit, push ou alteração da especificação.

## Resultado

Há achados: oito lacunas de contrato ou de verificação descritas abaixo. São riscos da especificação proposta, não defeitos ou vulnerabilidades comprovados em uma implementação. Recomenda-se fechar especialmente os itens A3, A4 e A7 antes de aprovar o plano.

Prioridades: **alta** quando a indefinição pode comprometer identidade, atomicidade ou isolamento; **média** quando permite interpretações incompatíveis do contrato ou do aceite.

O documento já estabelece corretamente: validação sem gravação parcial; correspondência exata por `order_id`; centavos inteiros sem float; fontes e snapshot imutáveis; revisão separada da classificação; conflito de versão; autorização por namespace no servidor; exportação com status de revisão e proteção de campos textuais. Não foi encontrado erro aritmético na regra de líquido nem necessidade de substituir centavos inteiros.

## Achados e sugestões

### A1 — O contrato CSV ainda admite resultados diferentes entre parsers

**Prioridade:** média. **Referências:** linhas 25–31; aceite 1, linha 76.

**Achado:** “aspas e campos escapados conforme o parser CSV” delega parte do formato a uma ferramenta ainda não escolhida. Não há decisão sobre BOM UTF-8, quebras LF/CRLF, linhas vazias, campos com quebra de linha entre aspas, arquivo apenas com cabeçalho ou valores obrigatórios vazios. “Até 1 MB” também não define o número de bytes. Como erros têm localização e o limite é de 100 linhas de dados, um campo multilinha torna ambíguos tanto a contagem quanto o número da linha informado.

**Impacto:** o mesmo arquivo pode ser aceito numa prévia e recusado por outro percurso; limites e mensagens não possuem um oráculo de teste único.

**Sugestão:** fechar uma tabela de aceitação/rejeição dessas variantes, definir o limite em bytes e distinguir registros de dados de linhas físicas. Definir a localização de erros estruturais e a linha física inicial de cada registro. Declarar quais campos não podem ficar vazios após a remoção de espaços.

**Aceite sugerido:** fixtures para BOM, LF/CRLF, cabeçalho apenas, campos vazios, linha vazia e campo multilinha; exatamente 100/101 registros e limite de bytes/limite + 1. Toda rejeição deve deixar a conferência sem fonte confirmada.

### A2 — “Identificador duplicado” não define as chaves dos repasses

**Prioridade:** média. **Referências:** linhas 27–31.

**Achado:** repasses contêm `order_id` e `payout_id`, mas a proibição de identificador duplicado não especifica a quais colunas se aplica. “Uma linha por pedido” determina unicidade de `order_id` no lote, mas não determina se um mesmo `payout_id` pode identificar um repasse agrupado de vários pedidos. Tampouco fica explícito que o `order_id` correspondente nas duas fontes é esperado, não uma duplicata inválida.

**Impacto:** uma implementação pode rejeitar repasses agrupados que outra aceita, ou deixar passar duplicatas incompatíveis com o modelo escolhido.

**Sugestão:** declarar a unicidade por tipo de fonte e por conferência, incluindo a política de `payout_id`. Aplicar a detecção após a normalização documentada, preservando zeros e caixa.

**Aceite sugerido:** dois repasses para o mesmo `order_id` são rejeitados; dois pedidos diferentes com o mesmo `payout_id` seguem a decisão explícita; `001` e `1` continuam distintos; `abc` e `ABC` continuam distintos; ` abc ` e `abc` colidem após trim; um `order_id` presente em ambas as fontes é válido.

### A3 — A identidade usada na idempotência não está suficientemente contratada

**Prioridade:** alta. **Referências:** linhas 25, 48 e 78.

**Achado:** exige-se hash do “conteúdo normalizado”, sem definir a serialização, a participação do tipo de fonte, a ordem dos registros ou o escopo da identidade. Mudanças de nome, espaços externos, aspas, BOM e quebras de linha podem ou não alterar o hash conforme a implementação. Também não está definido se repetir a execução depois de concluída reutiliza o snapshot ou cria uma nova execução; o texto cobre explicitamente apenas chamadas simultâneas.

**Impacto:** repetição semanticamente equivalente pode gerar conflito indevido, e deduplicação com escopo inadequado pode misturar conferências. Uma nova representação equivalente também pode substituir indevidamente nome e números de linha da origem confirmada.

**Sugestão:** definir uma representação canônica determinística e o escopo da fonte como namespace + conferência + tipo. Decidir se reordenar registros muda a identidade. Reenvio equivalente deve devolver a identidade já persistida, sem substituir sua origem nem criar novo evento de importação; conteúdo diferente deve produzir conflito explícito. Definir a identidade da execução pelo par de fontes imutáveis e a política para repetição sequencial.

**Aceite sugerido:** repetir após perda da resposta, mudar apenas nome/representação equivalente e repetir em paralelo retorna a mesma fonte; conteúdo diferente conflita; outra conferência ou outro visitante permanece independente. Repetir a conciliação concluída segue a política definida, sem duplicação acidental de resultados ou eventos.

### A4 — Transações não bastam para fechar as corridas de confirmação e período

**Prioridade:** alta. **Referências:** linhas 19–21, 31 e 48–50; aceite 4–5, linhas 79–80.

**Achado:** o documento exige operações transacionais e protege execução/revisão concorrentes, mas não especifica a disputa pela única fonte de cada tipo nem a corrida entre editar o período e confirmar a primeira fonte. A prévia ocorre antes da confirmação e não há contrato que a vincule ao conteúdo efetivamente confirmado.

**Impacto:** duas confirmações podem tentar ocupar o mesmo slot; uma fonte validada no período antigo pode ser confirmada após uma edição; a confirmação pode persistir conteúdo diferente do exibido. Uma transação isolada, sem coordenação das operações concorrentes, não garante essas invariantes.

**Sugestão:** exigir invariantes atômicas para período e slot de fonte, usando unicidade e bloqueio/versionamento pertinentes. Vincular a confirmação à prévia e ao conteúdo validado, ou revalidar integralmente a entrada e o período vigente no commit. Explicitar que confirmar fonte + evento é uma unidade e criar snapshot + resultados + evento é outra; não exigir transação aberta durante a interação com a prévia.

**Aceite sugerido:** confirmar simultaneamente arquivos iguais resulta em uma fonte/evento; arquivos diferentes têm um vencedor e um conflito, sem sobrescrita. Concorrer edição do período com primeira confirmação nunca persiste datas fora do período final. Alteração de conteúdo entre prévia e confirmação é rejeitada ou gera nova validação explícita. Falha antes do commit não deixa fonte, resultado ou evento órfão.

### A5 — Totais, unidade de contagem e sinal da diferença não têm definição verificável

**Prioridade:** média. **Referências:** linhas 35–42 e 46; aceite 3, linha 78.

**Achado:** “um registro pertence a uma classificação” não distingue linha importada de resultado por `order_id`. Um par conciliado tem duas linhas de origem, mas provavelmente um resultado. “Contagens e somas” não especifica quais valores entram nos totais nem se ausências participam de um saldo. “Diferença” não define sinal.

**Impacto:** servidor, interface e exportação podem apresentar contagens ou saldos diferentes, todos aparentemente compatíveis com o texto. Tratar ausência como zero no detalhe é proibido, mas a política de agregação continua indefinida.

**Sugestão:** definir um resultado por `order_id` da união das fontes, se essa for a intenção. Nomear somas por origem e campo; definir diferença dos pares como informado menos esperado, ou o inverso, explicitamente. Não atribuir diferença monetária a uma fonte ausente sem uma regra própria de agregação.

**Aceite sugerido:** usar a fixture abaixo, com todos os campos restantes válidos e datas dentro do período:

| Pedido | Bruto pedido | Bruto repasse | Taxa repasse | Líquido repasse | Resultado |
|---|---:|---:|---:|---:|---|
| A | 100.00 | 100.00 | 2.00 | 98.00 | Conciliado |
| B | 200.00 | 190.00 | 3.00 | 187.00 | Valor divergente |
| C | 50.00 | ausente | ausente | ausente | Sem repasse |
| D | ausente | 70.00 | 1.00 | 69.00 | Sem pedido |

Pela unidade sugerida: quatro resultados, um de cada classe, três divergências e inicialmente zero revisadas. Totais por origem: pedidos 35000 centavos; repasses brutos 36000, taxas 600, líquidos 35400. Para informado menos esperado, B tem diferença de -1000 centavos; C e D mostram ausência, não diferença zero. Filtros, paginação e exportação devem preservar esses totais.

### A6 — A precisão está bem escolhida, mas seus extremos não estão no aceite

**Prioridade:** média. **Referências:** linhas 25, 27, 29 e 46; aceite 2, linha 77.

**Achado:** há regra correta de centavos e teto por valor, mas o aceite menciona overflow sem exigir teste do teto de negócio. A projeção permite decimal textual ou centavos, deixando o formato final para um contrato futuro. Falta explicitar a gramática para sinais e o valor zero nos campos cuja regra permite zero.

**Impacto:** testes podem passar com aritmética inteira enquanto aceitam valor acima de R$ 1.000.000,00 ou convertem a projeção por uma rota não contratada. Overflow do `bigint` e ultrapassagem do teto são erros diferentes.

**Sugestão:** fixar a gramática decimal e o formato/tipo da projeção. Exigir validação do teto antes de persistir, além da proteção aritmética. Manter bruto estritamente positivo; taxa zero e líquido zero devem ser aceitos quando compatíveis com a equação.

**Aceite sugerido:** `0.01` vira 1 centavo; `1000000.00` é aceito e `1000000.01` rejeitado; bruto `0.00` é rejeitado; taxa igual ao bruto com líquido `0.00` é aceita. Rejeitar expoente, vírgula decimal, casas extras, negativos e inteiros decimais excessivamente longos; decidir explicitamente `+1.00` e `-0.00`. Somar 100 brutos no teto resulta exatamente em 10000000000 centavos, sem float; verificar também diferença negativa e round-trip da projeção escolhida.

### A7 — Reset, expiração e quota não possuem semântica atômica de namespace

**Prioridade:** alta. **Referências:** linhas 31 e 54–56; aceite 6, linha 81.

**Achado:** a autorização no servidor e a revogação estão explícitas, mas não se define o tratamento de requisições já em andamento durante reset/expiração, a concorrência de dois resets, nem a criação concorrente no limite de cinco conferências. “Limitado por visitante e IP” não informa contagem, janela ou resposta. Como o reset troca o ambiente, o limite por visitante pode ser reiniciado se não tiver identidade estável definida.

**Impacto:** uma requisição autorizada antes do reset pode gravar no namespace revogado; chamadas simultâneas podem ultrapassar quota; um limite associado somente ao namespace descartado não controla resets sucessivos.

**Sugestão:** definir estado/geração revogável no servidor e o ponto em que operações concorrentes são consideradas válidas. Mutações devem validar validade/revogação atomicamente com a gravação; não servir dados de namespace já revogado em novas autorizações. Quota e reset precisam de coordenação transacional. Definir a identidade e a janela do limitador, a resposta de excesso, e que renovação de cookie não estende as 24 horas. A limpeza física pode atrasar, mas nunca a revogação lógica.

**Aceite sugerido:** acessar dados alheios também por prévia/confirmação, criação de execução, edição/reabertura de nota e download direto; a rejeição não revela dados nem muda estado. Partindo de quatro conferências, duas criações concorrentes deixam no máximo cinco. Reset concorre com importação/revisão/exportação sem permitir novo uso do namespace revogado; dois resets seguem uma política definida de ambiente ativo. Após 24 horas, negar acesso mesmo com dados ainda aguardando limpeza. Testar o limite de reset com os números e janela aprovados.

### A8 — O aceite de revisão não cobre reabertura, auditoria e exportação concorrente

**Prioridade:** média. **Referências:** linhas 42, 50 e 72; aceite 4–5 e 8, linhas 79–80 e 83.

**Achado:** exige-se reabertura com motivo, evento separado e “Salvar e próxima pendência” somente após persistência. Os testes listados não fecham esses comportamentos nem a consistência entre linhas e contagem de revisão de uma exportação enquanto outra requisição salva uma nota.

**Impacto:** o percurso feliz pode passar mesmo com reabertura sem motivo, perda do histórico, avanço após conflito ou exportação com estados e contagens de instantes diferentes.

**Sugestão:** definir transições pendente/revisada, validação de nota/motivo, eventos de auditoria e efeito sobre a contagem. Exportação deve refletir um estado consistente da revisão, distinguindo-o do snapshot imutável dos valores. No conflito, preservar o rascunho local e exigir resolução explícita, sem avançar nem sobrescrever a nota vencedora.

**Aceite sugerido:** nota vazia ou só espaços não revisa; revisar mantém a classificação e incrementa X uma vez; reabrir exige motivo, decrementa X e conserva histórico de autor/horário/nota/motivo. Concorrer revisão com reabertura resulta em um vencedor e conflito de versão. Falha ou conflito em “Salvar e próxima” mantém texto/foco e não avança. Exportar durante revisão produz contagem e estados compatíveis e nunca declara tudo revisado se houver pendências.

## Encaminhamento

As sugestões acima são decisões e casos de aceite a incorporar somente após aprovação do responsável; este relatório não modifica o contrato vigente. Não se recomenda ampliar o escopo para integrações, filas, mapeamento genérico ou novos serviços. O objetivo é tornar as garantias já propostas determinísticas e verificáveis antes da implementação.
