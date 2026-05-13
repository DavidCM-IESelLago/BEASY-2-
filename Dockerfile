FROM php:8.2-apache

# Instalar extensiones y configurar Apache
RUN docker-php-ext-install pdo pdo_mysql mysqli && \
    a2enmod rewrite && \
    sed -ri -e 's!/var/www/html!/var/www/html/backend!g' \
        /etc/apache2/sites-available/*.conf \
        /etc/apache2/conf-available/*.conf && \
    sed -ri -e 's!/var/www/!/var/www/html/backend!g' \
        /etc/apache2/apache2.conf && \
    echo "ServerName localhost" >> /etc/apache2/apache2.conf

# --- CONFIGURAR VIRTUALHOST DEL FRONTEND ---

# Añadir Listen 8081 al archivo ports.conf
RUN echo "Listen 8081" >> /etc/apache2/ports.conf

# Copiar la configuración del VirtualHost del frontend
COPY frontend.conf /etc/apache2/sites-available/frontend.conf

# Habilitar el sitio del frontend
RUN a2ensite frontend.conf