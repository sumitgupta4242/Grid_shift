FROM php:8.2-apache

# Install MySQL extensions for your database
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copy your project files into the container
COPY . /var/www/html/

# Set permissions so Apache can read the files
RUN chown -R www-data:www-data /var/www/html