FROM php:8.2-apache

RUN docker-php-ext-install mysqli

RUN a2enmod rewrite

COPY frontend/ /var/www/html/

COPY php/ /var/www/html/liveconnect-api/

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
