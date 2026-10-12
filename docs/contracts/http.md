# HTTP — Lastro

Contrato de implementação aprovado no plano. A existência de uma rota neste documento não declara que ela já foi entregue; o estado real está em docs/evidence/progress.md.

Aplicação Laravel/Vue/Inertia no mesmo domínio. Páginas recebem props do servidor; chamadas com Accept: application/json recebem JSON. Cookies de sessão HttpOnly; em produção Secure. Nenhum workspace recebido por campo do navegador autoriza a operação. GET /csrf retorna {token}; obter token fresco antes de cada mutação e enviar X-CSRF-TOKEN. Não isentar mutações de CSRF.

| Método | Caminho | Comportamento |
| --- | --- | --- |
| GET | /health/ready | 200 {status:ready} apenas com PostgreSQL disponível e migrations aplicadas; 503 {status:unavailable} sem detalhes internos. Sem sessão. |
| GET | /entrar | Auth/Login; modo demo oferece início privado, modo normal pede credenciais de equipe. |
| POST | /login, /logout | Sessão regenerada/invalida; demo recusa login normal. |
| GET | /demo | Redireciona para entrada ou conferências, sem criar dados por GET. |
| POST | /demo/start, /demo/reset | Namespace privado; reset revoga o anterior. Identidade do visitante e rate limits sobrevivem. |
| GET | /csrf | Token CSRF da sessão atual. |
| GET, POST | /conferencias | Index com conferências/quota; criar com name,startDate,endDate. JSON criação201 com ConferenceProjection. |
| GET, PATCH | /conferencias/{id} | Show/update; update exige expectedVersion. Período bloqueado após primeira fonte. |
| POST | /conferencias/{id}/fontes/{orders\|payouts}/preview | Multipart file; retorna ImportPreview sem persistência parcial. |
| POST | /conferencias/{id}/fontes/{orders\|payouts}/confirm | Multipart file,expectedHash,expectedVersion; retorna SourceProjection201 ou200 na repetição equivalente. |
| POST | /conferencias/{id}/conciliar | RunProjection201; repetição200 mesma execução/eventos. |
| GET | /execucoes/{id} | Runs/Show; conference,run,results,filters. |
| GET | /execucoes/{id}/resultados | PageOfResults; classification,reviewStatus,search,page,pageSize (1..20); totais da execução permanecem globais. |
| POST | /resultados/{id}/revisoes | expectedVersion,action(reviewed/reopened),note; ResultProjection. Não muda classificação/valores. |
| GET | /resultados/{id} | ResultProjection atual e isolada; recuperação de409 sem depender do filtro/página atual. |
| GET | /execucoes/{id}/exportar | CSV completo, mesmo com filtros/pendências; snapshot REPEATABLE READ materializado antes de transmitir. |
| GET | /modelos/{orders\|payouts}.csv, /exemplos/{orders\|payouts}.csv | Modelos/exemplos fictícios fixos, sem arquivos de visitantes. |

Escritas por formulário Inertia retornam redirect303; respostas JSON preservam os códigos acima. Errors JSON: {code,message,errors?}; sessão ausente401 (navegação redireciona /entrar), CSRF419, objeto estrangeiro/revogado/expirado404, versão/fonte conflitante409, entrada inválida422, limite429 com Retry-After. Não divulgar se um identificador estrangeiro existe.

Normalização/idempotência da confirmação: SHA256 recalculado no servidor, revalidado com período vigente após workspace→conference lock. Repetição de mesmo hash devolve fonte existente antes de cobrar expectedVersion antigo; hash diferente409. Preview não garante confirmação futura. Excesso de tamanho/linhas gera422 com mensagem, não grava dados.

Limites demo: arquivos1048576bytes/100registros, cinco conferências incluindo três exemplos; validade fixa24h. start/login10/min/IP, reset1/min/visitante e10/h/IP, import20/min/visitante. Cache/rate limits e sessões no PostgreSQL; não em memória do container.

A prévia é uma avaliação: CSV estruturalmente/semanticamente inválido recebe200 com ImportPreview{valid:false,errors}, permitindo mensagens por linha. Request sem arquivo ou acima do limite de bytes recebe422 ApiError. Confirmar conteúdo inválido sempre recebe422 e não persiste fonte/linhas/evento. A prévia nunca reserva versão nem garante confirmação futura.

## Interface entre serviços

CurrentWorkspace em App\Domain\Access contém id,expiresAt,kind,userId. WorkspaceTransaction::run(scope,callback) executa transação, bloqueia workspace e verifica revogação/DB clock_timestamp depois do lock e antes do commit. Serviços de fontes/conciliação/revisão usam essa operação e depois bloqueiam conference/result, nessa ordem. Não alterar a geração de um scope antigo pelo novo silenciosamente.

CreateConference/UpdateConference, ConfirmSource, ReconcileConference, ReviewResult e ExportRun seguem as assinaturas do plano. ExportRun retorna conteúdo já materializado sob snapshot (ou resposta de streaming sobre esse conteúdo), com transação liberada antes de enviar ao cliente. UUIDs e FKs compostas protegem associações entre namespaces.
