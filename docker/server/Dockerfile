FROM php:8.2-apache

RUN apt-get update -y \
    && apt-get upgrade -y

# Install PDO and PGSQL Drivers
RUN apt-get install -y libpq-dev \
    libicu-dev \
    libxml2-dev \
    libonig-dev \
    libcurl4-openssl-dev \
    openssh-server \
    unzip \
    postgresql-client \
    openssl \
    git
RUN docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
  && docker-php-ext-install xml mbstring intl curl \
  && docker-php-ext-install pdo pdo_pgsql pgsql \
  && docker-php-ext-install sockets

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer


COPY . /var/www/html
WORKDIR /var/www/html
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

# Skonfiguruj Apache, aby używał katalogu public Symfony
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Włącz moduły Apache i restart
RUN a2enmod rewrite
ENTRYPOINT service ssh start && apache2-foreground


RUN apt-get clean -y && apt-get autoclean -y && apt-get autoremove -y \
    && rm -rf /var/lib/apt/lists/*

# Uruchamianie SSH i serwera Apache
CMD ["apache2-foreground"]