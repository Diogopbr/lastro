# Projeções — Lastro

A definição compartilhada está em resources/js/core/contracts.ts. Valores monetários são strings decimais, inclusive totais e diferença; Vue apenas formata, nunca soma/recalcula. UTC em timestamps, YYYY-MM-DD nas datas de negócio.

Props comuns: auth{user,workspace,demoEnabled}, flash{success}. Conferences/Index: conferences:ConferenceProjection[],quota{used,limit:null|number}. Conferences/Show: conference:ConferenceProjection. Imports/Preview: conference,kind,preview (null antes do arquivo). Runs/Show: conference,run,results:PageOfResults,filters{classification,reviewStatus,search,page,pageSize}. Carregar listas de resultados por JSON não substitui totais globais.

ResultProjection contém order/payout completos com origem (filename/hash/line) ou null. missingOrder/missingPayout nunca recebe dinheiro zero inventado. difference=payoutGross-orderGross quando ambos existem; caso contrário null. matched reviewStatus=null; divergência pending/reviewed. Reviewed não muda classification nem passa a usar aparência de matched.

ReviewEvents preserva todos os eventos, do mais recente ao mais antigo. Salvar e próxima só avança após resposta persistida;409 mantém a nota e oferece carregar dados atuais. API e Inertia usam os mesmos nomes camelCase do arquivo TypeScript.
