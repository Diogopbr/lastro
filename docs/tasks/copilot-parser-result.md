# Lastro — resultado do parser puro (tarefa 3)

Data: 10/10/2026. Worktree: `D:\Eu\Projetos\2\.worktrees\copilot`.
Branch conferida antes e depois: `agent/copilot-lastro`. Estado inicial limpo.

## Entrega e limites

Implementada somente a parte pura: conversão monetária, inspeção estrutural CSV,
validação de pedidos/repasses, prévia, hash normalizado e testes unitários reais.
Não foram criados serviços de persistência, controllers, requests, migrations,
rotas ou integração de acesso. Não houve alteração de frontend, manifestos,
dependências, configuração, scripts ou contratos compartilhados. Sem commit,
push, deploy ou abertura de outra janela do VS Code.

### Arquivos

- [Money.php](../../app/Domain/Imports/Money.php)
- [CsvStructureValidator.php](../../app/Domain/Imports/CsvStructureValidator.php)
- [CsvValidationException.php](../../app/Domain/Imports/CsvValidationException.php)
- [CsvImportValidator.php](../../app/Domain/Imports/CsvImportValidator.php)
- [SourceKind.php](../../app/Domain/Imports/SourceKind.php)
- [ImportRow.php](../../app/Domain/Imports/ImportRow.php)
- [ImportPreview.php](../../app/Domain/Imports/ImportPreview.php)
- [MoneyTest.php](../../tests/Unit/MoneyTest.php)
- [CsvStructureValidatorTest.php](../../tests/Unit/CsvStructureValidatorTest.php)
- [CsvImportValidatorTest.php](../../tests/Unit/CsvImportValidatorTest.php)
- [orders-valid.csv](../../tests/Fixtures/csv/orders-valid.csv)
- [payouts-valid.csv](../../tests/Fixtures/csv/payouts-valid.csv)
- Este relatório.

## Contratos lidos e decisões

Lidos `AGENTS.md`, a especificação e o plano de Lastro, os três documentos
`docs/contracts/{csv,http,projections}.md` e
`resources/js/core/contracts.ts`. Pacotes conferidos com `composer show --direct`:
PHP 8.5.11 x64, Laravel 13.35.0, League CSV 9.28.0, PHPUnit 12.5.38 e
Pint 1.32.1. Não foi necessário instalar ou alterar dependências.

1. Namespace `App\Domain\Imports`. Enum `SourceKind::Orders = orders` e
   `SourceKind::Payouts = payouts`; assinaturas solicitadas preservadas.
2. `Money::parse` aceita exclusivamente dígitos ASCII, ponto e duas casas, sem
   sinal. Remove zeros iniciais apenas para verificar magnitude; recusa mais
   de sete dígitos significativos antes da conversão e aplica o teto de
   100000000 centavos. Nunca usa float. `format` trabalha sobre os dígitos do
   inteiro, inclusive `PHP_INT_MIN`, sem `abs`/negação que causaria float.
3. Scanner com estados de campo inicial, não delimitado, entre aspas e aspas
   fechadas. Escape CSV é somente `""`, não barra invertida. Aspas não podem
   começar após espaços nem ser seguidas por texto/espaços. CR isolado, NUL,
   UTF-8 inválido e registros vazios são erros explícitos.
4. BOM UTF-8 inicial é aceito, mas conta no limite de 1048576 bytes.
   LF/CRLF, inclusive misturados, e um terminador final são aceitos.
   O scanner rastreia quebras dentro de aspas para localizar o início físico
   dos registros seguintes; isso **não** torna campos multilinha válidos.
   O importador rejeita controles Cc/Cf e separadores Unicode Zl/Zp em
   qualquer campo, antes de remover espaços externos.
5. Trim remove somente espaço ASCII U+0020. Tabulação/CR/LF são erros, não
   caracteres removidos silenciosamente. Identificadores têm 1..100 caracteres
   UTF-8 e preservam zeros, caixa, acentos, vírgulas/aspas escapadas e texto
   semelhante a fórmula. Não há execução ou neutralização de fórmula na entrada;
   o guard pertence à futura exportação.
6. Cabeçalhos são comparados pelos campos CSV decodificados, na ordem exata,
   sem trim ou ajuste de caixa. Cabeçalhos entre aspas são aceitos quando seu
   conteúdo é exato. Todas as larguras são verificadas **antes** de invocar
   League CSV; não é usado header mapping que preencheria/descartaria colunas.
7. Scanner mantém no máximo 101 descritores (cabeçalho + 100 dados) e rejeita
   explicitamente o registro de dados 101. Cabeçalho sem dados também falha.
   Datas usam calendário ISO válido, sem ano zero, e período inclusivo pelas
   datas locais `Y-m-d` dos argumentos, sem considerar hora/fuso.
8. `order_id` é único por fonte mesmo quando outra coluna da primeira ocorrência
   é inválida. Chaves recebem prefixo para evitar coerção de identificadores
   numéricos pelo PHP. `payout_id` pode repetir entre pedidos distintos.
   Gross é positivo; fee/net podem ser zero. Fee é verificada contra gross
   mesmo quando net não pode ser convertida; net válida deve ser gross-fee.
9. `ImportRow` e `ImportPreview` são readonly e implementam `toArray`/
   `JsonSerializable`. Centavos permanecem inteiros internamente
   (`grossCents`, `feeCents`, `netCents`, `grossTotalCents`); projeções expõem
   apenas os nomes e strings decimais do contrato TypeScript, sem vazar centavos.
   Dados de pedidos mantêm payoutId/fee/net nulos, não zeros inventados.
10. Hash SHA-256 de JSON compacto com `JSON_THROW_ON_ERROR`,
    `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES`. Ordem fixa:
    `{kind,rows}`, e cada row contém
    `{orderId,payoutId,businessDate,grossCents,feeCents,netCents,currency}`.
    Currency é BRL; campos não aplicáveis são null. Mantém a ordem dos registros.
    Não contém filename, linha física, BOM, espaços externos, aspas de
    representação ou estilo de newline. Testes verificam o hash contra JSON
    literal independente, inclusive Unicode/barra, não apenas equivalência
    entre duas chamadas do mesmo algoritmo.
11. Prévia inválida sempre tem `normalizedHash = null`. Erros têm
    `{line,field,code,message}`, com mensagens pt-BR. Máximo 100 rows/errors.
    Com estrutura/largura inválida não são projetadas rows nem totais parciais;
    com erro semântico, rows/grossTotal representam apenas registros totalmente
    válidos, mas o lote continua inválido. `rowCount` conta todos os dados quando
    a estrutura foi concluída; falhas do scanner/período retornam 0 porque a
    contagem confiável não foi concluída. Validação continua até o final mesmo
    quando o limite de 100 mensagens foi atingido.
12. `CsvValidationException` expõe `recordLine`, não `$line`: a propriedade
    nativa de `Exception` representa a linha do código PHP e não pode ser
    redeclarada readonly. `toArray()` entrega o campo de contrato `line`.

## TDD e comandos executados

Todos os comandos PHP abaixo foram executados após:

```powershell
Set-Location 'D:\Eu\Projetos\2\.worktrees\copilot'
. .\scripts\local-env.ps1 -Test
```

Conferência de ambiente/geradores:

```powershell
git --no-pager status --short
git branch --show-current
composer show --direct
php -v
php artisan make:class --help
php artisan make:test --help
php artisan make:enum --help
```

Testes gerados primeiro via Artisan, substituindo integralmente os exemplos:

```powershell
php artisan make:test MoneyTest --unit --phpunit --no-interaction
php artisan make:test CsvStructureValidatorTest --unit --phpunit --no-interaction
php artisan make:test CsvImportValidatorTest --unit --phpunit --no-interaction
```

Comando de testes em todas as etapas:

```powershell
php artisan test --compact --filter 'MoneyTest|CsvStructureValidatorTest|CsvImportValidatorTest'
```

| Etapa efetivamente executada | Resultado |
| --- | --- |
| RED, antes de gerar/implementar domínio | 120 testes, 0 passed, 21 failures, 99 errors, 22 assertions; saída 2 |
| Primeira tentativa após implementação | Fatal por conflito de `Exception::$line`; saída 2 |
| GREEN após corrigir para `recordLine` | 120 passed, 392 assertions; saída 0 |
| RED adicional, casos de controles Unicode e fee com net inválida | 154 testes, 139 passed, 15 failures, 473 assertions; saída 1 |
| GREEN ampliado após correções | 154 passed, 492 assertions; saída 0 |
| Reexecução após Pint | 154 passed, 492 assertions; saída 0 |
| Execução final, após tornar caminhos das fixtures portáveis | 154 passed, 492 assertions, 483 ms; saída 0 |

O RED inicial reportou, por exemplo,
`Class "App\Domain\Imports\Money" not found`; testes de exceção esperavam
`InvalidArgumentException` e receberam `Error` pela ausência da classe.
Os 99 errors restantes também decorreram das classes de domínio ausentes.
Nenhum scaffold foi contado como teste de comportamento.

Somente depois do RED inicial, classes/enums geradas via Artisan:

```powershell
php artisan make:class Domain/Imports/Money --no-interaction
php artisan make:class Domain/Imports/CsvStructureValidator --no-interaction
php artisan make:class Domain/Imports/CsvImportValidator --no-interaction
php artisan make:class Domain/Imports/ImportRow --no-interaction
php artisan make:class Domain/Imports/ImportPreview --no-interaction
php artisan make:class Domain/Imports/CsvValidationException --no-interaction
php artisan make:enum Domain/Imports/SourceKind --string --no-interaction
```

Os argumentos `Domain/Imports/...` acima são nomes de classes aceitos pelo
gerador; todos os acessos ao filesystem e execuções usaram a worktree Windows
autorizada.

Formatação e sintaxe:

```powershell
php vendor\bin\pint --dirty --format agent
```

Primeira execução corrigiu `phpdoc_align` em ImportPreview e
`single_quote`/`blank_line_before_statement` no teste do importador.
Execuções seguintes, inclusive a final, retornaram `passed`.
`php -l` executado separadamente em cada um dos sete arquivos de domínio e
três testes: **10 arquivos sem erros de sintaxe**.
`git --no-pager diff --check` saiu 0; como os arquivos novos ainda são
untracked, esse comando não é evidência de whitespace deles. A formatação
PHP foi verificada pelo Pint.

## Casos exercitados e alcance da verificação

- Money: centavo mínimo, zero, teto, zeros iniciais longos, excesso de
  magnitude, sinal/expoente/precisão indevida, caracteres não ASCII e lixo
  final; formatação positiva/negativa, extremos int64 e soma de 100 tetos.
- CSV: campos escapados, vírgula/aspas literal, backslash literal, largura,
  headers exatos/repetidos, obrigatórios, LF/CRLF/BOM, UTF-8 inválido/NUL,
  quotes malformadas e linhas vazias; rastreio físico após campos multilinha.
- Domínio: identidade textual e caracteres UTF-8, 100/101 caracteres,
  duplicidade, moeda, calendário/período inclusivo e ano bissexto,
  gross/fee/net, limites exatos de 100 registros e 1048576 bytes
  (inclusive BOM), erro 101/1048577, mensagens limitadas sem interromper a
  validação, contrato JSON e hash canonical.
- Controles e separadores de linha testados nas sete colunas de repasses.
- Nenhum percentual de cobertura foi medido ou declarado; 154 são casos
  PHPUnit efetivamente executados, incluindo datasets, não scaffolds.
- Não executados nesta entrega: feature tests de persistência/acesso,
  build frontend, navegador, integração HTTP, banco, concorrência ou deploy.
  A suíte pura não consulta banco nem depende dos serviços da tarefa 2.

## Bloqueios e próxima integração

Sem bloqueio restante para a parte pura. As primeiras tentativas de acesso
haviam sido negadas pelo mecanismo de permissões; após a liberação anunciada,
os comandos originais funcionaram.

`.ai/rules` e arquivos `SKILL.md` não existem nesta worktree; as skills
`testing-best-practices` e `superpowers:executing-plans` citadas por AGENTS/plano
não estavam disponíveis localmente. Isso não exigiu alterar instruções,
contratos ou ferramentas, e as partes independentes foram concluídas.

Continuam deliberadamente fora desta entrega: `ConfirmSource`, sanitização de
filename, locks/transações de workspace/conference, revalidação na confirmação,
idempotência persistida, migrations, controllers e testes de integração.
Devem ser implementados após a integração dos contratos de acesso. Nenhuma
necessidade de mudança dos contratos compartilhados foi identificada para
esta etapa.
