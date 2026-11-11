FROM php:8.3-apache

# Dependencias del sistema requeridas por extensiones PHP y web-push
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libgmp-dev \
    libicu-dev \
    libzip-dev \
    libonig-dev \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        gmp \
        bcmath \
        intl \
        mbstring \
        zip \
        opcache \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# Instalar Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configurar Apache apuntando a frontend/public
ENV APACHE_DOCUMENT_ROOT=/var/www/html/frontend/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && printf '<Directory /var/www/html/frontend/public>\n    Options -Indexes +FollowSymLinks\n    AllowOverride All\n    Require all granted\n</Directory>\n' > /etc/apache2/conf-available/natillera.conf \
    && a2enconf natillera

WORKDIR /var/www/html

# Instalar dependencias PHP primero (aprovecha caché de capas Docker)
COPY backend/composer.json backend/composer.lock ./backend/
RUN cd backend && composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Copiar código de la aplicación
COPY backend/ ./backend/
COPY frontend/ ./frontend/

# Asegurar carpeta de uploads con permisos de escritura para Apache
RUN mkdir -p /var/www/html/frontend/public/uploads/prestamos \
    && chown -R www-data:www-data /var/www/html/frontend/public/uploads

# Puerto dinámico exigido por Railway / Render
EXPOSE 80
CMD sh -c 'PORT=${PORT:-80}; sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf; sed -i "s/:80>/:${PORT}>/g" /etc/apache2/sites-available/000-default.conf; exec apache2-foreground'

