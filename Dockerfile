FROM php:8.1-cli


RUN apt-get update && apt-get install -y libzip-dev
RUN docker-php-ext-install zip

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && php -r "unlink('composer-setup.php');"

WORKDIR /app

COPY . .
RUN composer install

CMD ["bash", "-c", "make start"]
