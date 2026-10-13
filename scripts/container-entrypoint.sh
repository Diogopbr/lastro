#!/bin/sh
set -eu
cd /app
if [ -z "${APP_KEY:-}" ]; then
    printf '%s\n' 'APP_KEY is required at runtime.' >&2
    exit 1
fi
# Runtime environment is injected by the host; never cache secrets at image build.
php artisan config:cache --no-interaction
php artisan view:cache --no-interaction
exec frankenphp run --config /etc/caddy/Caddyfile --adapter caddyfile
