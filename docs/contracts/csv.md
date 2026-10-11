# CSV — Lastro

UTF-8, vírgula, cabeçalho exato. BOM inicial e CRLF/LF aceitos. Fonte tem1..100registros e até1048576bytes. Rejeitar NUL, UTF-8 inválido, linhas vazias, aspas incompletas/malformadas, largura divergente e cabeçalho sem dados. Scanner estrutural precede League CSV para impedir normalização silenciosa de colunas ausentes/extras.

Pedidos: order_id,ordered_at,gross_amount,currency.

Repasses: order_id,payout_id,payout_date,gross_amount,fee_amount,net_amount,currency.

Todos os campos obrigatórios. Trim ASCII externo; identificadores1..100caracteres, preservando zeros/caixa. Não aceitar controles/newlines em campos. order_id único por fonte; payout_id pode repetir para pedidos diferentes. Datas ISO válidas dentro do período inclusivo. currency=BRL.

Dinheiro: texto com ponto e exatamente duas casas; sem sinal, expoente ou float. Teto100000000centavos por valor. Gross positivo; fee/net podem zero, fee<=gross e net=gross-fee. Somas/diferenças com inteiros64bits. Money::parse e Money::format são a autoridade única.

Hash canonical: JSON estável {kind,rows}, campos normalizados e centavos inteiros, mantendo ordem; excluir filename/line física/BOM/CRLF. Prévia até100rows/100errors, com início da linha física e total do servidor. Lote inválido não salva source/rows/event.

Exportação UTF-8: conference_code,run_id,order_id,classification,order_gross,payout_gross,fee,net,difference,review_status,review_note,reviewed_at. Ausência monetária é célula vazia. Formula guard apenas em texto livre cujo primeiro caractere efetivo após controles/espaços seja=,+,-,@: prefixar apóstrofo. Diferença negativa monetária é valor confiável formatado. Filename fixo e sem texto arbitrário: lastro-LT-{numero}-{runUuid}.csv.
