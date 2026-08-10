FROM php:8.2-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends $PHPIZE_DEPS \
    && docker-php-ext-install mysqli \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY . /app

RUN chmod 755 /app \
    && find /app -type f -name "*.php" -exec chmod 644 {} \;

EXPOSE 8080

CMD ["sh", "-c", "php /app/setup.php; exec php -S 0.0.0.0:${PORT:-8080} -t /app"]
