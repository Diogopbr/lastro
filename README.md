# Lastro

Conferência de pedidos e repasses através de CSVs, com valores precisos, fontes imutáveis e revisão de divergências rastreável. Projeto de portfólio de Diogo Pereira em implementação, com dados fictícios e sem integração bancária.

O escopo aprovado está na [especificação](docs/superpowers/specs/2026-10-10-lastro-design.md) e no [plano](docs/superpowers/plans/2026-10-10-lastro.md). Consulte o [progresso verificado](docs/evidence/progress.md) antes de considerar um fluxo entregue. O Lastro ainda não está publicado.

Stack: Laravel 13, Vue 3/Inertia 3, PostgreSQL 17. Locks incluem versões compatíveis; as [decisões de ambiente](docs/decisions/environment.md) registram os ajustes da baseline. Dinheiro será convertido em centavos inteiros no servidor; o cliente apenas formata projeções.

Para desenvolvimento local, configure seu próprio PostgreSQL e os segredos ignorados conforme o documento de ambiente. No host Windows preparado: scripts/bootstrap.ps1 e scripts/dev.ps1. Para testes, dot-source scripts/local-env.ps1 -Test e execute php artisan test; o banco de teste é separado do desenvolvimento.

Contratos: [HTTP](docs/contracts/http.md), [CSV](docs/contracts/csv.md) e [projeções](docs/contracts/projections.md). A presença de um endpoint no contrato não declara sua implementação.
