FROM php:8.2-apache

# Enable mysqli for MySQL
RUN docker-php-ext-install mysqli

# Enable Apache rewrite module
RUN a2enmod rewrite

# Copy frontend files
COPY frontend/ /var/www/html/liveconnect/

# Copy PHP backend
COPY php/ /var/www/html/liveconnect-api/

# Allow Apache to serve the application
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
