FROM php:8.2-apache

RUN apt-get update \
	&& apt-get install -y --no-install-recommends libcurl4-openssl-dev \
	&& docker-php-ext-install pdo pdo_mysql curl \
	&& rm -rf /var/lib/apt/lists/* \
	&& a2enmod rewrite

# Cho phép .htaccess override
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

WORKDIR /var/www/html
