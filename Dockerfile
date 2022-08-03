FROM php:8.1-cli

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && php -r "unlink('composer-setup.php');"

WORKDIR /app

COPY . .
RUN composer install

CMD ["bash", "-c", "php -S 0.0.0.0:8080 -t public public/index.php"]
