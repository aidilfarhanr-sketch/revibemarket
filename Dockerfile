FROM php:8.2-apache AS production

ENV APACHE_DOCUMENT_ROOT=/var/www/html \
    PHP_MEMORY_LIMIT=256M \
    PHP_UPLOAD_MAX_FILESIZE=16M \
    PHP_POST_MAX_SIZE=20M

RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev libonig-dev libcurl4-openssl-dev unzip ca-certificates \
    && docker-php-ext-install mysqli pdo pdo_mysql mbstring fileinfo curl opcache \
    && pecl install redis || true \
    && docker-php-ext-enable redis || true \
    && a2enmod rewrite headers expires deflate remoteip \
    && rm -rf /var/lib/apt/lists/*

RUN { \
      echo "display_errors=Off"; \
      echo "log_errors=On"; \
      echo "error_log=/proc/self/fd/2"; \
      echo "memory_limit=${PHP_MEMORY_LIMIT}"; \
      echo "upload_max_filesize=${PHP_UPLOAD_MAX_FILESIZE}"; \
      echo "post_max_size=${PHP_POST_MAX_SIZE}"; \
      echo "expose_php=Off"; \
      echo "opcache.enable=1"; \
      echo "opcache.enable_cli=1"; \
      echo "opcache.validate_timestamps=0"; \
      echo "opcache.jit=0"; \
    } > /usr/local/etc/php/conf.d/revibe-production.ini

WORKDIR /var/www/html
COPY --chown=www-data:www-data . /var/www/html

RUN rm -rf .git .github .env logs/*.log backups/* storage/private/* storage/cache/* *.zip *.sql *.sql.gz \
    && mkdir -p logs storage/private storage/cache storage/public uploads/products uploads/profile uploads/payment_proofs uploads/complaints backups \
    && chown -R www-data:www-data logs storage uploads backups \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && find /var/www/html -type f -exec chmod 644 {} \; \
    && chmod +x scripts/*.sh || true

USER www-data
EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --retries=3 CMD php -r "$s=@json_decode(@file_get_contents('http://127.0.0.1/health.php'),true); exit(empty($s['ok']) ? 1 : 0);"
