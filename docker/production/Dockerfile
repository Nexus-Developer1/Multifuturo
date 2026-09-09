# syntax=docker/dockerfile:1.7
#
# Imagem de produção da Multifuturo Propriedades — Apache + mod_php.
#
# Etapas:
#   base    → PHP 8.3 com Apache e as extensões que o projeto usa
#   vendor  → dependências PHP (Composer, já com as extensões: o Filament
#             exige ext-intl para instalar)
#   assets  → CSS/JS com o Vite (Node 24). Precisa do vendor porque o tema do
#             backoffice importa CSS do Filament
#   app     → a aplicação completa: base + vendor + assets. É a única imagem;
#             serve o site (Apache) e corre a fila e o agendador (artisan)
#
# O HTTPS termina à frente do contentor (Apache/painel do anfitrião — DEPLOY.md §10).
#
# Construir: docker compose -f compose.production.yaml build

# ---------------------------------------------------------------- base
FROM php:8.3-apache AS base

# Extensões: MySQL, Redis (phpredis), intl (datas e números em pt-PT),
# gd (imagens), zip/bcmath/exif/pcntl (Laravel, Filament, fila), opcache.
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql redis intl gd zip bcmath exif pcntl opcache

# mysqldump/mysql para as cópias de segurança (backup:run, restore.sh). O
# Debian traz o cliente da MariaDB, que fala com um servidor MySQL 8 sem
# problema — só as colunas JSON saem no ficheiro como texto, e entram na mesma.
RUN apt-get update \
    && apt-get install -y --no-install-recommends curl ca-certificates default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

# Apache: raiz em public/, .htaccess do Laravel a mandar, cabeçalhos e compressão.
RUN a2enmod rewrite headers deflate \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/apache2.conf
COPY docker/production/vhost.conf /etc/apache2/sites-available/000-default.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/production/php.ini /usr/local/etc/php/conf.d/99-multifuturo.ini

WORKDIR /var/www/html

# ---------------------------------------------------------------- vendor
FROM base AS vendor
# Camada em cache enquanto o composer.lock não muda.
# --no-scripts: o package:discover corre na etapa app, já com o código todo.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --prefer-dist --optimize-autoloader \
    && composer clear-cache

# ---------------------------------------------------------------- assets
FROM node:24-alpine AS assets
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
# O tema do backoffice (resources/css/filament/admin/theme.css) importa CSS do Filament.
COPY --from=vendor /var/www/html/vendor/filament ./vendor/filament
RUN npm run build

# ---------------------------------------------------------------- app
FROM base AS app

COPY --chown=www-data:www-data --from=vendor /var/www/html/vendor ./vendor
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=assets /build/public/build ./public/build

# O que foi só para desenvolvimento não vai na imagem.
# APP_ENV=build: sem .env a aplicação assumiria "production" e recusaria
# arrancar sem AGENCY_AMI — na construção da imagem ainda não há .env.
RUN rm -rf tests docker/nginx docker/php-dev.ini .env .env.example node_modules storage/app/demo \
    && mkdir -p storage/app/public storage/app/private storage/backups storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && APP_ENV=build php artisan package:discover --ansi \
    && APP_ENV=build php artisan filament:assets --ansi \
    && chown -R www-data:www-data storage bootstrap/cache public

COPY --chmod=0755 docker/production/entrypoint.sh /usr/local/bin/entrypoint

# O processo principal do Apache arranca como root (para a porta 80) e os
# trabalhadores correm como www-data. A fila e o agendador correm como
# www-data via `user:` no compose.
EXPOSE 80
ENTRYPOINT ["entrypoint"]
CMD ["apache2-foreground"]

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -fsS -o /dev/null http://127.0.0.1/up || exit 1
