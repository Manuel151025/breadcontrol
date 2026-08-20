# Imagen de la aplicación: PHP 8.2 con Apache y soporte MySQL.
FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

# rewrite  → reescritura de URL
# deflate  → compresión gzip de HTML, CSS y JS
# expires  → cabeceras de caducidad de los recursos estáticos
# headers  → Cache-Control explícito
#
# Sin deflate, expires y headers, las reglas de rendimiento del .htaccess
# quedan dentro de <IfModule> que nunca se cumplen: no fallan, simplemente
# no se aplican, que es lo que ocurría hasta ahora.
RUN a2enmod rewrite deflate expires headers

WORKDIR /var/www/html

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

# Directorio de registros FUERA de la carpeta pública, para montarlo como volumen
# y que sobrevivan al despliegue (hoy el contenedor se reemplaza y se los lleva).
#
# Se crea aquí, con dueño www-data, a propósito: cuando Docker monta un volumen
# vacío sobre un directorio que ya existe en la imagen, hereda su propietario. Si
# no existiera, el volumen nacería de root y Apache no podría escribir en él — un
# fallo que además sería mudo, porque lo que no se puede registrar es el error.
#
# Se activa poniendo APP_LOG_PATH=/var/log/breadcontrol en el entorno.
RUN mkdir -p /var/log/breadcontrol && chown www-data:www-data /var/log/breadcontrol

# OPcache guarda el bytecode compilado de PHP en memoria. Estaba DESACTIVADO,
# de modo que el servidor volvía a leer y compilar cada archivo del proyecto en
# cada petición — con el coste repetido en todas las páginas.
#
# `validate_timestamps=1` con `revalidate_freq=60` mantiene una red de
# seguridad: si alguien edita un archivo dentro del contenedor, el cambio se
# recoge en un minuto. El código normalmente no cambia en caliente (cada
# despliegue construye una imagen nueva), así que el coste de esa comprobación
# es despreciable frente a recompilarlo todo.
# expose_php=Off retira la cabecera X-Powered-By, que anunciaba la version
# exacta de PHP (R-05 del informe tecnico). No sustituye a mantener PHP
# actualizado: solo deja de regalar el dato a un escaneo automatizado.
RUN echo "expose_php = Off" > /usr/local/etc/php/conf.d/ocultar-version.ini

RUN { \
        echo "opcache.enable=1"; \
        echo "opcache.memory_consumption=128"; \
        echo "opcache.max_accelerated_files=10000"; \
        echo "opcache.validate_timestamps=1"; \
        echo "opcache.revalidate_freq=60"; \
    } > /usr/local/etc/php/conf.d/opcache.ini
