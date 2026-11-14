FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_sqlite \
    && a2enmod headers

COPY . /var/www/html/
RUN mkdir -p /var/www/html/data && chown -R www-data:www-data /var/www/html/data

ENV APACHE_DOCUMENT_ROOT=/var/www/html
EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s CMD php -r "exit(file_exists('/var/www/html/index.php')?0:1);"
CMD ["apache2-foreground"]
