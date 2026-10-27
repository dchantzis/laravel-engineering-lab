FROM ubuntu:24.04
ENV DEBIAN_FRONTEND=noninteractive TZ=UTC
WORKDIR /var/www
RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates curl gnupg software-properties-common \
    git unzip zip nginx supervisor sqlite3 mysql-client \
    && add-apt-repository -y ppa:ondrej/php \
    && apt-get update && apt-get install -y --no-install-recommends \
    php8.5-cli php8.5-fpm php8.5-common php8.5-curl php8.5-mysql \
    php8.5-sqlite3 php8.5-redis php8.5-mbstring php8.5-xml \
    php8.5-zip php8.5-bcmath php8.5-intl php8.5-gd php8.5-soap \
    php8.5-xdebug \
    && mkdir -p /run/php \
    && rm -rf /var/lib/apt/lists/*
# Node 24 LTS and npm, matching the existing Laravel development projects.
RUN curl -fsSL https://deb.nodesource.com/setup_24.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker-configuration/nginx/conf.d/default.conf /etc/nginx/sites-available/default
COPY docker-configuration/php/mods-available/local.ini /etc/php/8.5/cli/conf.d/99-local.ini
COPY docker-configuration/php/mods-available/local.ini /etc/php/8.5/fpm/conf.d/99-local.ini
COPY docker-configuration/php/mods-available/xdebug.ini /etc/php/8.5/cli/conf.d/99-xdebug.ini
COPY docker-configuration/php/mods-available/xdebug.ini /etc/php/8.5/fpm/conf.d/99-xdebug.ini
COPY docker-configuration/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker-configuration/entrypoint/start-container /usr/local/bin/start-container
COPY docker-configuration/entrypoint/start-container-local /usr/local/bin/start-container-local
RUN chmod +x /usr/local/bin/start-container /usr/local/bin/start-container-local \
    && rm -f /etc/nginx/sites-enabled/default \
    && ln -s /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default \
    && ln -sf /dev/stdout /var/log/nginx/access.log \
    && ln -sf /dev/stderr /var/log/nginx/error.log
EXPOSE 80
ENTRYPOINT ["/usr/local/bin/start-container"]
