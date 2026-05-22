FROM php:8.2-apache

# Instalar dependencias del sistema necesarias para Composer
RUN apt-get update && apt-get install -y unzip git && rm -rf /var/lib/apt/lists/*

# Instalar Composer globalmente
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Instalar extensiones PHP necesarias para conectar con MySQL
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Habilitar módulos Apache
RUN a2enmod rewrite headers

# Copiar configuraciones personalizadas de VirtualHost
COPY backend.conf /etc/apache2/sites-available/backend.conf
COPY frontend.conf /etc/apache2/sites-available/frontend.conf

# Configurar puertos de escucha
RUN echo "Listen 80" > /etc/apache2/ports.conf && \
    echo "Listen 8081" >> /etc/apache2/ports.conf

# Deshabilitar el sitio por defecto y habilitar los nuestros
RUN a2dissite 000-default.conf && \
    a2ensite backend.conf && \
    a2ensite frontend.conf

# Suprimir advertencia de ServerName
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Copiar e instalar dependencias de Composer durante el build
COPY backend/composer.json backend/composer.lock /var/www/html/backend/
RUN composer install --no-dev --optimize-autoloader --working-dir=/var/www/html/backend

# Establecer permisos correctos para el servidor web
RUN chown -R www-data:www-data /var/www/html
