# syntax=docker/dockerfile:1.4
# Soporte: linux/amd64, linux/arm64
FROM php:8.3-fpm-alpine

# Dependencias sistema + extensiones PHP
RUN --mount=type=cache,target=/var/cache/apk,sharing=locked \
    apk add \
    bash curl libpng-dev libjpeg-turbo-dev freetype-dev \
    libzip-dev oniguruma-dev icu-dev libxml2-dev \
    mysql-client supervisor nginx \
    fontconfig \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_mysql mbstring xml gd zip intl opcache bcmath pcntl fileinfo \
    && { \
        echo 'opcache.enable=1'; \
        echo 'opcache.memory_consumption=256'; \
        echo 'opcache.interned_strings_buffer=16'; \
        echo 'opcache.max_accelerated_files=20000'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.revalidate_freq=0'; \
        echo 'opcache.fast_shutdown=1'; \
    } > /usr/local/etc/php/conf.d/opcache.ini \
    && { \
        echo 'memory_limit=512M'; \
        echo 'upload_max_filesize=100M'; \
        echo 'post_max_size=100M'; \
        echo 'max_execution_time=300'; \
        echo 'max_input_time=300'; \
    } > /usr/local/etc/php/conf.d/uploads.ini

# Usuario no-root
RUN addgroup -g 1000 laravel && \
    adduser -D -u 1000 -G laravel laravel

# PHP-FPM como usuario laravel
RUN sed -i 's/user = www-data/user = laravel/' /usr/local/etc/php-fpm.d/www.conf && \
    sed -i 's/group = www-data/group = laravel/' /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www/html

# Copiar codigo (vendor debe estar pre-built localmente)
COPY --chown=laravel:laravel . ./

# Permisos
RUN mkdir -p storage/app/public storage/framework/cache storage/framework/sessions \
    storage/framework/views storage/logs bootstrap/cache \
    && chown -R laravel:laravel storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Nginx config
COPY docker/nginx.conf /etc/nginx/http.d/default.conf

# Supervisor config
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Directorios nginx/supervisor
RUN mkdir -p /run/nginx /var/log/supervisor /var/lib/nginx/tmp/client_body \
    && chown -R nginx:nginx /var/lib/nginx \
    && chown -R laravel:laravel /run/nginx /var/log/nginx /var/log/supervisor \
    && chmod -R 755 /var/lib/nginx/tmp

# Entrypoint
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
