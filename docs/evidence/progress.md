# Lastro — progresso de implementação

Especificação/plano aprovados em 10/10/2026. Repositório local separado, branch codex/lastro. Nenhum deploy ou repositório GitHub público do Lastro foi criado nesta etapa.

## Fundação

- Readiness RED: rota ausente retornou 404 em vez de 503.
- Readiness GREEN: 1 teste/9 assertions com PostgreSQL real, banco indisponível, schema vazio, migrations aplicadas e registro de migration pendente.
- Fundação: 2 testes/19 assertions passaram (prontidão e página Inertia); exemplos do scaffold não entram na cobertura.
- composer install, validate e check-platform-reqs passaram.
- npm ci dos locks, typecheck e build passaram após ajustes de compatibilidade documentados.
- HTTP real: /health/ready 200 com status ready; /entrar 200 com raiz Inertia e referência a assets compilados.
- Bancos de desenvolvimento e teste separados; roles sem superuser; segredos ignorados.

A página atual é uma fundação declarada em implementação, não uma demonstração de conciliação. Acesso privado, CSV, conciliação, revisão/exportação, interface final e publicação ainda dependem das tarefas seguintes. Não contar os testes de exemplo do scaffold como cobertura de produto.

