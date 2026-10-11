# Lastro — conciliação de pedidos e repasses

Data: 10/10/2026. Autor: Diogo Pereira. Estado: especificação e plano aprovados pelo usuário em 10/10/2026; implementação iniciada após retomada e redesign do Bancada.

## Objetivo

Demonstrar desenvolvimento full stack através de um problema de dados: uma pequena loja precisa conferir se os pedidos pagos correspondem aos repasses informados pelo intermediador. O usuário importa dois CSVs, encontra diferenças verificáveis e registra sua análise. Cada classificação deve explicar a regra que a produziu. Dados fictícios, sem conexão bancária, execução de pagamentos ou afirmação de experiência comercial.

A força técnica está na ingestão confiável, precisão monetária, rastreabilidade, idempotência, concorrência e exportação segura. A força visual está na mesa de conferência: arquivos de origem, linhas e diferenças lado a lado, com destaque para o trabalho pendente.

## Alternativas e escolha

1. CSVs com contrato fixo e exemplos baixáveis: torna erros reproduzíveis e permite fechar um percurso real. Escolha desta versão.
2. Mapeador genérico de colunas: aceita mais formatos, mas aumenta a superfície de validação sem provar melhor a conciliação. Pode vir depois.
3. Integração direta com gateways: exige credenciais, adaptações e termos externos. Fora desta versão.

## Percurso principal

Criar uma conferência com nome e período de validação. Importar pedidos e repasses pelos modelos fornecidos. Validar todos os registros antes de gravar o lote. Examinar a prévia com contagens calculadas e mensagens por linha. Confirmar importação. Executar conciliação e abrir cada divergência para consultar valores e origem. Registrar justificativa e marcar a divergência como revisada. Exportar relatório da execução. Recarregar a aplicação e encontrar a mesma execução, fontes e notas.

O período pode ser editado até a primeira fonte ser confirmada; depois é imutável. As datas de pedido e repasse devem estar no intervalo definido, inclusive os extremos. O período não descarta linhas silenciosamente: uma data fora dele é erro explícito. O usuário pode corrigir o arquivo ou criar outra conferência. Filtros e paginação não alteram os totais da execução.

## Contratos de entrada

UTF-8, delimitador vírgula, cabeçalho exato, aspas e campos escapados conforme o parser CSV. Modelos e arquivos de exemplo baixáveis. Datas ISO YYYY-MM-DD. Valores BRL como texto decimal com ponto e exatamente duas casas. Identificadores são texto preservado, inclusive zeros iniciais. Espaços externos são removidos de forma documentada; identificadores não mudam de caixa nem recebem correspondência aproximada.

Pedidos: order_id, ordered_at, gross_amount, currency. Todos representam pedidos pagos. gross_amount positivo; currency BRL.

Repasses: order_id, payout_id, payout_date, gross_amount, fee_amount, net_amount, currency. Uma linha por pedido. gross_amount positivo; fee_amount entre zero e gross_amount; net_amount igual a gross_amount menos fee_amount; currency BRL. Não modelar parcelamento, split, reembolso, chargeback ou taxas adicionais ocultas nesta versão.

Limites da demonstração: arquivo de até 1 MB e até 100 linhas de dados; uma fonte de pedidos e uma de repasses por conferência; até cinco conferências por visitante. Cabeçalho repetido, CSV inválido, coluna ausente/extra, identificador duplicado no mesmo lote, data impossível e precisão indevida são erros por linha/campo. Lote inválido não grava parcialmente. Preview não armazena dados globais nem arquivo em filesystem público.

## Regras de conciliação

A execução só começa quando as duas fontes válidas estão confirmadas. Comparar por order_id exato:

- Conciliado: pedido e repasse existem e valores brutos coincidem; valor líquido do repasse já foi validado.
- Valor divergente: ambos existem e os valores brutos diferem. Exibir esperado, informado e diferença.
- Sem repasse: pedido não possui repasse.
- Sem pedido: repasse não possui pedido.

As contagens e somas vêm do servidor; um registro pertence a uma classificação. Não calcular uma taxa prevista sem contrato que a forneça. Registrar uma justificativa não altera os valores importados nem transforma discrepância em correspondência automática. Somente divergências possuem status de revisão: pendente/revisada, com autor, horário e nota obrigatória. Conciliados não entram na fila de pendências. Exibir X de Y divergências revisadas, calculado pelo servidor. Permitir reabrir revisão com motivo. Exportação está disponível com pendências e sempre inclui o status de revisão; não representar um relatório parcial como totalmente revisado.

## Precisão, persistência e concorrência

Converter valores validados em centavos inteiros sem float; somas e diferenças também inteiras. Limitar cada valor a R$ 1.000.000,00 e conferir overflow antes de persistir. PostgreSQL bigint com constraints pertinentes. API/projeção apresenta valores como texto decimal ou centavos explicitamente contratados; Vue apenas formata.

Fonte confirmada é imutável. Guardar hash SHA-256 do conteúdo normalizado, registros normalizados, número da linha e nome de origem tratado como texto. Reenviar a mesma fonte confirmada é idempotente; reenviar outra fonte exige nova conferência, sem apagar histórico. Não exigir armazenamento do arquivo bruto para reconstituir valores.

Execução de conciliação é snapshot imutável vinculado às fontes. Duas solicitações simultâneas da mesma execução não duplicam resultados. Confirmar fonte, produzir resultado e evento associado são operações transacionais. Revisar divergência inclui versão: gravação antiga retorna conflito e a interface oferece atualizar os dados. A execução não depende de tarefa em background nesta escala.

## Demonstração e segurança

Entrada sem senha compartilhada cria namespace privado por visitante, cookie HttpOnly/Secure em produção, validade fixa de 24 horas e CSRF nas mutações. Consultas, importações, execuções, notas e exportações sempre verificam o namespace no servidor. Sem identificador de visitante confiado apenas ao navegador. Reset revoga o ambiente anterior e cria exemplos novos, limitado por visitante e IP. A limpeza de dados vencidos usa lotes limitados e continua enquanto houver backlog.

Três conferências fictícias iniciais: uma conciliada, uma com pedido sem repasse/repasse sem pedido/valor diferente, uma aguardando importação. Links de exemplos usam somente dados fictícios. Limites incluem exemplos. Instalação normal tem login de equipe, sem permitir acesso anônimo aos dados da loja.

Nome do arquivo, identificadores e notas exibidos como texto escapado. Não executar conteúdo CSV. Exportação previne fórmula em células textuais iniciadas por =, +, - ou @, preservando a informação como texto. Prevenir injeção de cabeçalho no nome do download. Não registrar arquivos ou cookies em logs. Upload não cria URL pública nem caminho controlado pelo usuário.

## Organização e publicação

Monólito Laravel/PHP com Vue e Inertia, PostgreSQL e migrations, organizado por importação, conferências, conciliação, revisão e acesso. Sessões/CSRF do framework; validação e regras no servidor. Contratos de entrada e projeções documentados. Não adicionar microserviços, filas ou cache sem necessidade.

Versões exatas, parser CSV, execução PHP no Container Runtime da Vercel e persistência de sessões serão definidos e verificados no plano com fontes oficiais. Manter app e banco na mesma região, segredos externos e planos gratuitos. Não aceitar filesystem efêmero como fonte de sessões ou resultados. Se alguma limitação de plataforma impedir o fluxo, registrar a evidência antes de escolher alternativa.

Codex integra contratos e verificação; Copilot implementa ingestão/conciliação e testes em worktree própria; Antigravity implementa a interface em outra worktree. Nenhum agente faz commit/push/deploy por conta própria. Codex valida as entregas independentemente e uma revisão separada examina a implementação antes de publicar.

## Interface

Direção própria: papel claro neutro, tinta azul escura, linhas e números alinhados, tipografia de leitura e tabelas cuidadosas. Acento cobre para diferenças e verde para correspondências, sempre com texto. Evitar reaproveitar o layout de oficina do Bancada.

Lista de conferências com andamento real; assistente de importação com modelo baixável, arquivo escolhido, prévia e erros por linha; mesa de conciliação com filtros e totais calculados; detalhe com pedido e repasse comparados, origem, diferença e revisão; relatório exportável. Na comparação, uma fonte ausente recebe mensagem explícita, como Pedido não encontrado, sem transformar ausência em valor zero. Classificação e revisão são sinais separados: divergência revisada mantém sua classificação e recebe um marcador neutro de Revisada, sem cor de correspondência automática. O detalhe oferece Salvar e próxima pendência, avançando apenas após persistir a nota; erros mantêm o texto e o foco para correção. No celular, linhas tornam-se registros legíveis com campos rotulados; comparação não exige rolagem horizontal. Teclado, foco, mensagens anunciadas e diálogos com retorno de foco fazem parte do aceite.

## Verificação e aceite

1. Importação válida persiste; inválida não deixa lote parcial. Testar aspas, zeros iniciais, CSV malformado e limites.
2. Conversão em centavos preserva precisão e recusa expoente, casas extras e overflow.
3. As quatro classificações e totais são calculados corretamente; fonte repetida não duplica registros.
4. Duas execuções simultâneas não duplicam resultados; duas revisões concorrentes produzem conflito sem perder a nota vencedora.
5. Fontes e resultado permanecem imutáveis; revisão é evento separado.
6. Visitantes não acessam fontes/resultados/exportações alheios; reset/expiração invalida sessões antigas.
7. Exportação não interpreta fórmula de campos textuais; nomes de arquivo não alteram cabeçalhos ou filesystem.
8. Percurso completo no navegador: importar, conciliar, investigar diferença, revisar, exportar, recarregar e resetar.
9. Build/análise estática/testes com PostgreSQL real, CI, teclado, mobile e validação hospedada registrados separadamente.
10. README com screenshots reais, decisões e limitações; LinkedIn descreve apenas comportamento verificado.

## Limites

Sem integração bancária/gateway, pagamentos, câmbio, classificação contábil, recomendação financeira, múltiplas moedas, arquivos reais de clientes, mapeamento arbitrário, reembolso ou conciliação aproximada. Os resultados conferem os dados fornecidos; não certificam um repasse bancário.

## Próxima etapa

Revisar e aprovar esta especificação própria. Depois, elaborar o plano de implementação com versões verificadas, contratos, distribuição de tarefas, testes e execução. O plano também deve ser revisado antes do início da implementação, conforme o processo escolhido para a coleção.
