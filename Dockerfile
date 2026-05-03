# Usamos una imagen de PHP con Apache
FROM php:8.1-apache

# Instalamos extensiones de PHP necesarias para MySQL
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Habilitamos el módulo de reescritura para que el .htaccess funcione
RUN a2enmod rewrite

# Copiamos todo tu código a la carpeta del servidor
COPY . /var/www/html/

# Exponemos el puerto 80
EXPOSE 80
