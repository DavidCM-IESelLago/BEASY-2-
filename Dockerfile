FROM php:8.2-apache

# Instalar extensiones y configurar Apache
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Habilitar módulos
RUN a2enmod rewrite

# Copiar configuraciones personalizadas
COPY backend.conf /etc/apache2/sites-available/backend.conf
COPY frontend.conf /etc/apache2/sites-available/frontend.conf

# Configurar puertos
RUN echo "Listen 80" > /etc/apache2/ports.conf && \
    echo "Listen 8081" >> /etc/apache2/ports.conf

# Deshabilitar el sitio por defecto y habilitar los nuestros
RUN a2dissite 000-default.conf && \
    a2ensite backend.conf && \
    a2ensite frontend.conf

# Configurar ServerName para evitar warnings
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Establecer permisos
RUN chown -R www-data:www-data /var/www/html
