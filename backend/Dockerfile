FROM composer:2 AS composer

FROM php:8.5-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo_mysql pcntl

COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

# コンテナ内のプロセスはrootで実行しない
RUN useradd --create-home --uid 1000 app
USER app

WORKDIR /var/www/html

ENTRYPOINT ["entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
