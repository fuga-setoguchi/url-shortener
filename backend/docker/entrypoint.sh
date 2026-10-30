#!/bin/sh
set -eu

# 初期化（依存インストール・.env作成・マイグレーション）は app サービスだけが行う
if [ "${RUN_SETUP:-false}" = "true" ]; then
    composer install --no-interaction --prefer-dist

    if [ ! -f .env ]; then
        cp .env.example .env
        php artisan key:generate --ansi
    fi

    php artisan migrate --force
fi

exec "$@"
