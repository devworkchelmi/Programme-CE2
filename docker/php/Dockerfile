FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
        git \
        unzip \
        libicu-dev \
        libzip-dev \
    && docker-php-ext-install -j$(nproc) pdo_mysql intl opcache zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini

WORKDIR /var/www/carnet-ce2

# var/cache et var/log sont montés en volumes Docker nommés (cf. docker-compose.yml,
# pour la vitesse de rechargement) : Docker pré-remplit un volume nommé vide avec le
# contenu — et les droits — qu'a ce chemin dans l'image au premier démarrage. On crée
# donc ces dossiers ici, appartenant à www-data (utilisateur des workers php-fpm), pour
# que Symfony puisse y écrire sans erreur de permission.
RUN mkdir -p var/cache var/log \
    && chown -R www-data:www-data var/cache var/log \
    && chmod -R 777 var/cache var/log

CMD ["php-fpm"]
