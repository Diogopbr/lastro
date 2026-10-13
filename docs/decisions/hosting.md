# Hospedagem do Lastro

Escolha autorizada: Vercel Container Runtime, Laravel/PHP servido por FrankenPHP
em modo clássico e assets Vue no mesmo domínio, PostgreSQL separado do Bancada.
Sem contratação paga automática. Dockerfile preparado não equivale a deploy.

O primeiro deploy detectou o package.json/Vite na raiz e procurou `dist`, sem
construir o container. A configuração agora declara o serviço `lastro` com
entrypoint `Dockerfile.vercel` e rewrite de todo o tráfego para ele, conforme
a configuração oficial de Services. O segundo build efetivamente iniciou
Buildah e o registro VCR; publicação continua condicionada ao runtime real.

Em10/10/2026 os manifests oficiais do Docker Registry foram consultados via HEAD
autenticado com token público de pull, sem gravar/expor token. Imagens fixadas:

- Node24.15.0-bookworm-slim: sha256:4e6b70dd6cbfc88c8157ba19aa3d9f9cce6ba4703576d55459e45efcbc9c5f5d.
- Composer2.10.3: sha256:af98f42dfff7c68ba8d53c2164fd9fde1087b7d449514baa38c418b1f6bc4bac.
- FrankenPHP1.13.0/PHP8.5-bookworm: sha256:265db135c5bddce6e3aab85d8943052d37a2f0b75211a21fec5ab5ff05826713.

Build instala dependências dos locks e compila assets, sem .env/segredos. Runtime
recebe APP_KEY e banco externos e gera config/views cache; nunca migra ou faz seed
ao iniciar. Usuário sem root, porta8080, somente /app/public exposto, logs stderr.
Migrations e instalação de usuários/dados são operações administrativas explícitas.
Sessões/cache em PostgreSQL; arquivos de upload são temporários durante request.

Docker não está disponível no host Windows desta execução. O build da imagem,
HTTPS/cookies, proxy, conexão TLS, dados fora de public e fluxos hospedados precisam
de validação real antes de declarar publicação. Middleware do gateway foi testado
localmente: modo explícito LASTRO_VERCEL_INGRESS requer VERCEL=1 e usa IP de
x-vercel-forwarded-for/protocolo HTTPS, sem confiar em x-forwarded-host.
Servidor direto ignora headers de proxy; não ativar esse modo fora do runtime
isolado da Vercel. Readiness/liveness sem sessão aceitam sondas internas.
Sessão usa lastro_session, compatível com o nome padrão do Laravel/PHP.
Banco hospedado deve usar sslmode=verify-full e sslrootcert da CA do sistema;
URL/segredos serão injetados externamente, nunca copiados do Bancada.

Em 12/10/2026, projeto Lastro criado e banco Neon Free provisionado em São Paulo,
separado do Bancada. Migrations aplicadas por credencial administrativa em TLS
com verificação de certificado/hostname. Runtime usa role própria sem superuser,
createdb, createrole ou CREATE no schema; somente DML e leitura de migrations.
O cliente psql confirmou TLSv1.3 com verify-full. Estatística de TLS do backend
gerenciado não é usada como prova da conexão do cliente.

Depois do provisionamento, a conexão automática da integração ao projeto Vercel
foi desfeita sem remover o banco. Isso retira as credenciais administrativas do
ambiente da aplicação. DB_URL com role de runtime e APP_KEY próprio foram
configurados por stdin, como variáveis sensíveis. Migrations futuras continuam
administrativas e explícitas; credenciais não estão no repositório.

Fontes oficiais:
[Vercel Container Runtime](https://vercel.com/docs/functions/runtimes/container),
[FrankenPHP Docker/non-root](https://frankenphp.dev/docs/docker/),
[Laravel deployment](https://laravel.com/framework/docs/13.x/deployment),
[headers do gateway Vercel](https://vercel.com/docs/headers/request-headers).
