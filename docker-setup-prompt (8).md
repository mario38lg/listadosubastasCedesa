# Guia de Dockerizacion Laravel con S3 y Portainer

Guia paso a paso para dockerizar proyectos Laravel con almacenamiento S3, soporte multi-arquitectura (arm64/amd64), build cache, y despliegue en Portainer.

---

## Estructura de Archivos

```
proyecto/
├── Dockerfile
├── portainer-stack.yml
├── build_push.sh
└── docker/
    ├── nginx.conf
    ├── supervisord.conf
    └── entrypoint.sh
```

---

## Notas Importantes sobre Infraestructura

> **IMPORTANTE - Base de Datos en Produccion:**
>
> En produccion, las bases de datos **SIEMPRE** estan alojadas en AWS (RDS u otro servicio gestionado).
> **NUNCA** incluir MySQL/PostgreSQL en el stack de Docker a menos que se indique explicitamente lo contrario.
>
> Esto garantiza:
> - Backups automaticos gestionados por AWS
> - Alta disponibilidad y failover
> - Escalabilidad independiente
> - Seguridad y cifrado gestionado

---

## Checklist de Necesidades Especiales

**IMPORTANTE:** Antes de aplicar esta guia, revisar con el equipo:

- [ ] **Version de PHP**: Verificar en `composer.json` (7.3, 7.4, 8.1, 8.2, 8.3, etc.)
- [ ] **Redis**: Si se necesita, agregar servicio y variables `REDIS_HOST`, `CACHE_DRIVER=redis`
- [ ] **Queue Workers**: Si usa colas (Horizon), agregar al supervisord o servicio separado
- [ ] **Scheduler/Cron**: Decidir si va en mismo container o servicio separado (ver seccion dedicada)
- [ ] **WebSockets**: Si necesita tiempo real (Laravel Echo/Pusher/Reverb)
- [ ] **Conexiones externas**: APIs de terceros, servicios especificos
- [ ] **Extensiones PHP adicionales**: imagick, redis, soap, wkhtmltopdf, etc.
- [ ] **Node.js/NPM**: Si necesita build de assets en produccion
- [ ] **Volumes persistentes**: Si necesita almacenamiento local adicional

---

## Paso 1: Instalar Dependencias S3

### 1.1 Composer

```bash
composer require league/flysystem-aws-s3-v3 "^3.0"
```

### 1.2 Variables de entorno

Agregar a `.env.example`:

```env
# AWS S3 Configuration
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=eu-central-1
AWS_BUCKET=
AWS_URL=
AWS_ENDPOINT=
AWS_USE_PATH_STYLE_ENDPOINT=false

# Disco para archivos (s3 o local)
FILESYSTEM_DISK=s3
```

### 1.3 Configurar filesystems.php

En `config/filesystems.php`:

```php
<?php

return [
    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],
    ],
];
```

---

## Paso 2: Configurar HTTPS (Proyectos con Blade)

En `app/Providers/AppServiceProvider.php`, agregar en el metodo `boot()`:

```php
use Illuminate\Support\Facades\URL;

public function boot(): void
{
    // Forzar HTTPS en produccion
    if (config('app.env') === 'production') {
        URL::forceScheme('https');
    }

    // ... resto del codigo
}
```

---

## Paso 3: Configurar TrustProxies

En `app/Http/Middleware/TrustProxies.php`:

```php
<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    protected $proxies = '*';
    protected $headers = Request::HEADER_X_FORWARDED_ALL;
}
```

> **Nota:** En Laravel 11+ esto puede estar en `bootstrap/app.php` o en el archivo de configuracion `config/trustedproxy.php`.

---

## Paso 4: Crear Archivos Docker

### 4.1 Dockerfile

```dockerfile
# syntax=docker/dockerfile:1.4
# Soporte: linux/amd64, linux/arm64

# IMPORTANTE: Ajustar version de PHP segun el proyecto
# Opciones: php:7.3-fpm-alpine, php:7.4-fpm-alpine, php:8.1-fpm-alpine, php:8.2-fpm-alpine, php:8.3-fpm-alpine
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
```

### 4.2 docker/nginx.conf

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name _;

    root /var/www/html/public;
    index index.php index.html;
    charset utf-8;

    access_log /var/log/nginx/access.log;
    error_log /var/log/nginx/error.log;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    client_max_body_size 100M;

    # Gzip
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css text/xml text/javascript application/x-javascript application/xml+rss application/json application/javascript;

    # Health check endpoint
    location /health {
        access_log off;
        return 200 "healthy\n";
        add_header Content-Type text/plain;
    }

    # Laravel routing
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
        fastcgi_param PHP_VALUE "upload_max_filesize=100M \n post_max_size=100M";
        fastcgi_read_timeout 300;
        fastcgi_buffer_size 128k;
        fastcgi_buffers 256 16k;

        # Headers para proxy (Nginx Proxy Manager)
        fastcgi_param HTTP_X_FORWARDED_FOR $http_x_forwarded_for;
        fastcgi_param HTTP_X_FORWARDED_PROTO $http_x_forwarded_proto;
        fastcgi_param HTTP_X_FORWARDED_HOST $http_x_forwarded_host;
        fastcgi_param HTTP_X_FORWARDED_PORT $http_x_forwarded_port;

        fastcgi_busy_buffers_size 256k;
        fastcgi_temp_file_write_size 256k;
    }

    # Deny access to hidden files
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }

    # Deny access to sensitive files
    location ~ /(?:web\.config|composer\.json|composer\.lock|package\.json|\.env) {
        deny all;
        access_log off;
        log_not_found off;
    }

    # Static files with caching
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }
}
```

### 4.3 docker/supervisord.conf

> **IMPORTANTE - Scheduler:** Esta configuracion incluye el scheduler en el mismo container.
> Si prefieres un servicio separado para el scheduler, ver seccion "Laravel Scheduler como Servicio Separado".

```ini
[supervisord]
nodaemon=true
user=root
logfile=/var/log/supervisor/supervisord.log
pidfile=/var/run/supervisord.pid

[program:php-fpm]
command=/usr/local/sbin/php-fpm -F
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:nginx]
command=/usr/sbin/nginx -g "daemon off;"
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:scheduler]
process_name=%(program_name)s
command=/bin/sh -c "while true; do php /var/www/html/artisan schedule:run --verbose --no-interaction; sleep 60; done"
user=laravel
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
```

### 4.4 docker/entrypoint.sh

```bash
#!/bin/bash
set -e

echo "Starting Laravel Application..."

# Esperar a que MySQL este listo
echo "Waiting for MySQL..."
until php artisan db:show 2>/dev/null; do
    echo "   MySQL unavailable - sleeping"
    sleep 2
done
echo "MySQL ready!"

# Ejecutar migraciones
echo "Running migrations..."
php artisan migrate --force --no-interaction || echo "Migrations already applied or failed"

# Crear symlink de storage si no existe
if [ ! -L "/var/www/html/public/storage" ]; then
    echo "Creating storage symlink..."
    php artisan storage:link --force
fi

# Limpiar y cachear configuracion
echo "Caching configuration..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

php artisan config:cache
php artisan route:cache
php artisan view:cache

# Optimizar autoloader
composer dump-autoload --optimize --no-dev --classmap-authoritative 2>/dev/null || true

echo "Application ready!"
exec "$@"
```

---

## Paso 5: Crear portainer-stack.yml

> **Recordatorio:** La base de datos MySQL/PostgreSQL **NO** va en el stack.
> Siempre usar AWS RDS u otro servicio gestionado en produccion.

```yaml
# ============================================
# [NOMBRE_PROYECTO] - Stack para Portainer
# ============================================
# Requisitos:
# - Base de datos MySQL en AWS (RDS) - NO incluir en el stack
# - Nginx Proxy Manager externo apuntando al puerto 80
#
# Variables de entorno a configurar en Portainer:
# - APP_KEY, APP_URL, DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD
# - AWS_BUCKET, AWS_DEFAULT_REGION
# - MAIL_*, y otras segun necesidad

services:
  # ============================================
  # Laravel App (PHP-FPM + Nginx + Scheduler)
  # Con Rolling Updates para actualizaciones sin downtime
  # ============================================
  app:
    image: registry.gitlab.com/cedesa/[PROYECTO]:${APP_VERSION:-latest}
    deploy:
      replicas: 1
      # Rolling Updates - Actualizaciones sin downtime
      update_config:
        parallelism: 1              # Actualizar 1 contenedor a la vez
        order: start-first          # Iniciar el nuevo antes de detener el viejo (zero downtime)
        failure_action: rollback    # Volver atrás si falla la actualización
        delay: 10s                  # Esperar 10s entre actualizaciones
        monitor: 20s                # Monitorear 20s después de actualizar
      restart_policy:
        condition: on-failure
        delay: 5s
        max_attempts: 3
    networks:
      - app_network
      - public_proxy
    environment:
      - APP_NAME=${APP_NAME:-MiApp}
      - APP_ENV=${APP_ENV:-production}
      - APP_KEY=${APP_KEY}
      - APP_DEBUG=${APP_DEBUG:-false}
      - APP_URL=${APP_URL}
      # Database (AWS RDS - externa al stack)
      - DB_CONNECTION=mysql
      - DB_HOST=${DB_HOST}
      - DB_PORT=${DB_PORT:-3306}
      - DB_DATABASE=${DB_DATABASE}
      - DB_USERNAME=${DB_USERNAME}
      - DB_PASSWORD=${DB_PASSWORD}
      - CACHE_DRIVER=file
      - SESSION_DRIVER=file
      - QUEUE_CONNECTION=sync
      # S3 Configuration (usa IAM roles en AWS, sin keys)
      - AWS_DEFAULT_REGION=${AWS_DEFAULT_REGION:-eu-central-1}
      - AWS_BUCKET=${AWS_BUCKET}
      - FILESYSTEM_DISK=${FILESYSTEM_DISK:-s3}
      # Mail (opcional)
      - MAIL_MAILER=${MAIL_MAILER:-smtp}
      - MAIL_HOST=${MAIL_HOST}
      - MAIL_PORT=${MAIL_PORT:-587}
      - MAIL_USERNAME=${MAIL_USERNAME}
      - MAIL_PASSWORD=${MAIL_PASSWORD}
      - MAIL_ENCRYPTION=${MAIL_ENCRYPTION:-tls}
      - MAIL_FROM_ADDRESS=${MAIL_FROM_ADDRESS}
      - MAIL_FROM_NAME=${MAIL_FROM_NAME:-MiApp}
      # [AGREGAR VARIABLES ADICIONALES DEL PROYECTO AQUI]
    healthcheck:
      test: ["CMD", "wget", "-q", "--spider", "http://localhost/health"]
      interval: 30s
      timeout: 10s
      retries: 3
      start_period: 60s

networks:
  app_network:
    driver: overlay
    attachable: true
  public_proxy:
    external: true
```

---

## Paso 6: Build y Push con Script Automatizado

### 6.1 Configurar Docker Buildx (solo una vez)

```bash
docker buildx create --name multiarch --use
docker buildx inspect --bootstrap
```

### 6.2 Autenticacion a GitLab Registry

```bash
docker login registry.gitlab.com
```

### 6.3 Usar el Script build_push.sh

El proyecto incluye un script `build_push.sh` que automatiza todo el proceso de build y push:

```bash
# Dar permisos de ejecucion (solo la primera vez)
chmod +x build_push.sh

# Build y push con tag especifico
./build_push.sh v1.0.0

# Build y push con tag latest (por defecto)
./build_push.sh

# Build y push con tag de fecha
./build_push.sh $(date +%Y%m%d)
```

El script automaticamente:
1. Verifica que estas en el directorio correcto
2. Instala dependencias de composer para produccion
3. Configura Docker Buildx si no existe
4. Construye imagen multi-arquitectura (amd64 + arm64)
5. Usa cache del registry para builds mas rapidos
6. Sube la imagen con el tag especificado

### 6.4 Build Manual (alternativa)

Si prefieres hacerlo manualmente:

```bash
# Primero instalar dependencias localmente
composer install --no-dev --optimize-autoloader

# Build con cache en registry
docker buildx build \
  --platform linux/amd64,linux/arm64 \
  --cache-from type=registry,ref=registry.gitlab.com/cedesa/[PROYECTO]:cache \
  --cache-to type=registry,ref=registry.gitlab.com/cedesa/[PROYECTO]:cache,mode=max \
  -t registry.gitlab.com/cedesa/[PROYECTO]:latest \
  -t registry.gitlab.com/cedesa/[PROYECTO]:v1.0.0 \
  --push .
```

---

## Paso 7: Despliegue en Portainer

1. **Acceder a Portainer** y seleccionar el entorno (Swarm/Standalone)

2. Ir a **Stacks** > **Add Stack**

3. **Pegar el contenido** de `portainer-stack.yml`

4. **Configurar variables de entorno**:
   - `APP_KEY` - Generar con: `php artisan key:generate --show`
   - `APP_URL` - URL completa con https (ej: `https://miapp.ejemplo.com`)
   - `DB_HOST` - **Endpoint de AWS RDS** (ej: `mydb.xxxxx.eu-central-1.rds.amazonaws.com`)
   - `DB_DATABASE` - Nombre de la base de datos
   - `DB_USERNAME` - Usuario de MySQL
   - `DB_PASSWORD` - Contrasena de MySQL
   - `AWS_BUCKET` - Nombre del bucket S3
   - `AWS_DEFAULT_REGION` - Region AWS (ej: `eu-central-1`)
   - Variables adicionales segun el proyecto

5. **Deploy** el stack

6. **Configurar Nginx Proxy Manager**:
   - Crear proxy host apuntando al servicio `app` puerto 80
   - Configurar SSL con Let's Encrypt

---

## Verificacion

1. **Health check**:
   ```bash
   curl https://[TU_DOMINIO]/health
   # Debe responder: healthy
   ```

2. **Ver logs**:
   ```bash
   docker service logs [stack]_app -f
   ```

3. **Probar S3**:
   - Subir un archivo desde la aplicacion
   - Verificar que aparece en el bucket de S3

4. **Verificar migraciones**:
   ```bash
   docker exec -it [container_id] php artisan migrate:status
   ```

---

## Servicios Adicionales (Segun Necesidad)

### Laravel Scheduler como Servicio Separado

> **Cuando usar servicio separado:**
> - Proyectos grandes que necesitan escalar independientemente
> - Alta disponibilidad donde el scheduler no debe depender del container web
> - Cuando quieres aislar fallos del scheduler del servicio principal
>
> **Cuando usar mismo container (default):**
> - Mayoria de proyectos pequenos/medianos
> - Menos recursos y complejidad
> - Configuracion mas simple

Si decides usar un servicio separado, **primero quita** `[program:scheduler]` de `supervisord.conf`, y luego agrega a `portainer-stack.yml`:

```yaml
  # ============================================
  # Laravel Scheduler (Cron) - OPCIONAL
  # ============================================
  cron:
    image: registry.gitlab.com/cedesa/[PROYECTO]:${APP_VERSION:-latest}
    entrypoint: []
    networks:
      - app_network
    deploy:
      restart_policy:
        condition: on-failure
        delay: 5s
        max_attempts: 3
    command: ["sh", "-c", "while true; do php /var/www/html/artisan schedule:run --verbose --no-interaction; sleep 60; done"]
    environment:
      # Copiar las mismas variables que el servicio app
      - APP_NAME=${APP_NAME:-MiApp}
      - APP_ENV=${APP_ENV:-production}
      - APP_KEY=${APP_KEY}
      - APP_DEBUG=${APP_DEBUG:-false}
      - APP_URL=${APP_URL}
      - DB_CONNECTION=mysql
      - DB_HOST=${DB_HOST}
      - DB_PORT=${DB_PORT:-3306}
      - DB_DATABASE=${DB_DATABASE}
      - DB_USERNAME=${DB_USERNAME}
      - DB_PASSWORD=${DB_PASSWORD}
      - CACHE_DRIVER=file
      - SESSION_DRIVER=file
      - QUEUE_CONNECTION=sync
      - AWS_DEFAULT_REGION=${AWS_DEFAULT_REGION:-eu-central-1}
      - AWS_BUCKET=${AWS_BUCKET}
      - FILESYSTEM_DISK=${FILESYSTEM_DISK:-s3}
    depends_on:
      - app
```

### Queue Worker / Horizon (si usa colas)

Agregar a `portainer-stack.yml`:

```yaml
  worker:
    image: registry.gitlab.com/cedesa/[PROYECTO]:${APP_VERSION:-latest}
    entrypoint: []
    networks:
      - app_network
    deploy:
      restart_policy:
        condition: on-failure
    command: ["php", "/var/www/html/artisan", "queue:work", "--sleep=3", "--tries=3", "--max-time=3600"]
    environment:
      # Mismas variables que app + QUEUE_CONNECTION=redis/database
    depends_on:
      - app
```

O agregar Horizon a `supervisord.conf`:

```ini
[program:horizon]
process_name=%(program_name)s
command=php /var/www/html/artisan horizon
user=laravel
autostart=true
autorestart=true
stopwaitsecs=3600
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
```

### Redis (si usa cache/sesiones/colas con Redis)

Agregar a `portainer-stack.yml`:

```yaml
  redis:
    image: redis:7-alpine
    networks:
      - app_network
    deploy:
      restart_policy:
        condition: on-failure
    volumes:
      - redis_data:/data

volumes:
  redis_data:
```

Y agregar variables al servicio app:
```yaml
- REDIS_HOST=redis
- CACHE_DRIVER=redis
- SESSION_DRIVER=redis
- QUEUE_CONNECTION=redis
```

---

## Notas Importantes

- **Solo produccion**: Esta guia es exclusivamente para despliegue en produccion
- **GitLab Registry**: Siempre usar `registry.gitlab.com/cedesa/[proyecto]`
- **IAM Roles**: En AWS se recomienda usar IAM roles en lugar de access keys para S3
- **Nginx Proxy Manager**: SSL y routing externo se maneja con NPM
- **Base de datos en AWS**: MySQL/PostgreSQL **SIEMPRE** en AWS RDS, **NUNCA** en el stack Docker (a menos que se indique explicitamente)

---

## Troubleshooting

### La aplicacion no inicia
```bash
docker service logs [stack]_app -f
```

### Error de permisos en storage
```bash
docker exec -it [container] chown -R laravel:laravel /var/www/html/storage
docker exec -it [container] chmod -R 775 /var/www/html/storage
```

### S3 no funciona
- Verificar que el bucket existe y tiene los permisos correctos
- Si usa IAM roles, verificar que la instancia tiene el rol asignado
- Si usa access keys, verificar `AWS_ACCESS_KEY_ID` y `AWS_SECRET_ACCESS_KEY`

### Mixed content (HTTP/HTTPS)
- Verificar que `URL::forceScheme('https')` esta en `AppServiceProvider`
- Verificar que `TrustProxies` tiene `$proxies = '*'`

### No conecta a la base de datos
- Verificar que el Security Group de RDS permite conexiones desde la IP/VPC del servidor
- Verificar que `DB_HOST` es el endpoint correcto de RDS
- Probar conexion manualmente: `mysql -h [endpoint] -u [user] -p`
