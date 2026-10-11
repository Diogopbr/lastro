# Ambiente local

PHP 8.5.11 x64 NTS e Composer 2.10.3 portáteis; downloads oficiais com SHA256 verificado. Extensões curl, mbstring, openssl, pdo_pgsql, pgsql, fileinfo, intl, zip e XML presentes. Não houve mudança de PHP global.

Laravel 13.35.0, Inertia Laravel 3.5.1 e League CSV 9.28.0 nos locks. PHPUnit 12.5.38 é a versão suportada pelo scaffold; não foi forçada a versão 13 da baseline. Laravel Boost instalado somente como dependência de desenvolvimento, conforme instruções geradas do scaffold.

Vue 3.5.43/Vite 8.3.4 e vue-tsc 3.3.13. TypeScript 7.0.2 não exporta typescript/lib/tsc, que vue-tsc utiliza; typecheck falhou com ERR_PACKAGE_PATH_NOT_EXPORTED. TypeScript 6.0.3 foi fixado e a verificação passou. Node 24.15.0 portátil atende engine dos transitivos de Vue Test Utils; Bancada continua com seu ambiente próprio. engine-strict impede ignorar incompatibilidade nos installs.

PostgreSQL 17 local porta 55417, bancos/roles lastro e lastro_test distintos sem superuser/createdb/createrole. Acesso via PUBLIC removido de lastro/lastro_test e do banco bancada; o owner bancada permanece com acesso. APP_KEY e senhas aleatórias vivem em .local/settings.json e arquivos .env ignorados. Nenhum segredo está no repositório.

Windows: colocar PHP/Composer/Node portáteis em ../.tools/{php,composer,node}, definir .local/settings.json com host,port,database,username,password,testDatabase,testUsername,testPassword,appKey; banco e roles devem existir. scripts/bootstrap.ps1 instala dos locks, verifica plataforma, aplica migrations explicitamente e compila. scripts/local-env.ps1 resolve a raiz comum também em worktrees. Fora desse ambiente, usar PHP/Composer/Node instalados, copiar .env.example e configurar seu próprio PostgreSQL antes de composer install/npm ci/php artisan migrate/npm run build.

Referências de download: [PHP Windows](https://windows.php.net/downloads/releases/releases.json), [Composer](https://getcomposer.org/download/), [Node 24.15.0](https://nodejs.org/dist/v24.15.0/SHASUMS256.txt). O runtime não aplica migrations nem seed automaticamente ao iniciar.
