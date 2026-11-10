# =============================================================================
# Shared PHP-FPM image (used by both development and production)
# =============================================================================
# This Dockerfile uses multi-stage builds:
#   - node-builder:  compiles the frontend assets (Vite) with Node
#   - builder:       installs Composer dependencies and PHP extensions
#   - production:    minimal runtime image (extensions + app code)
#   - development:   extends production with Xdebug and a synced user
# =============================================================================

# Stage 1: Build frontend assets with Node
# -------------------------------------------------------------------------
# Vite 8 / Tailwind 4 require a recent Node version, so we use the official
# node image here and copy only the built assets into the PHP image.
# -------------------------------------------------------------------------
FROM node:22-alpine AS node-builder

WORKDIR /var/www

# Copy the application code (respects .dockerignore)
COPY . /var/www

# Install npm dependencies and build the production assets.
# --ignore-scripts avoids running native build scripts we don't need here.
RUN npm install --ignore-scripts && npm run build


# Stage 2: Build environment and Composer dependencies
FROM php:8.4-fpm AS builder

# Install system dependencies and PHP extensions required for Laravel
# plus MySQL / PostgreSQL / MongoDB support.
RUN apt-get update && apt-get install -y --no-install-recommends \
        curl \
        unzip \
        libpq-dev \
        libonig-dev \
        libssl-dev \
        libxml2-dev \
        libcurl4-openssl-dev \
        libicu-dev \
        libzip-dev \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        pdo_pgsql \
        pgsql \
        intl \
        zip \
        bcmath \
        soap \
    && pecl install redis mongodb \
    && docker-php-ext-enable redis mongodb \
    && apt-get autoremove -y && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

WORKDIR /var/www

# Copy the entire Laravel application code into the container.
# composer install triggers scripts (e.g. package:discover) that need the
# full app code (artisan) to be present, so we copy everything first.
COPY . /var/www

# Copy the frontend assets built in the node-builder stage so the
# production image (and the shared volume) contains the Vite manifest.
COPY --from=node-builder /var/www/public/build /var/www/public/build

# Install Composer and the application dependencies.
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer install --no-dev --optimize-autoloader --no-interaction --no-progress --prefer-dist


# Stage 3: Production runtime image
FROM php:8.4-fpm AS production

# Install only the runtime libraries needed in production.
# libfcgi-bin and procps are required by the php-fpm-healthcheck script.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev \
        libicu-dev \
        libzip-dev \
        libfcgi-bin \
        procps \
        ca-certificates \
    && apt-get autoremove -y && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Download and install the php-fpm health check script
RUN curl -o /usr/local/bin/php-fpm-healthcheck \
        https://raw.githubusercontent.com/renatomefi/php-fpm-healthcheck/master/php-fpm-healthcheck \
    && chmod +x /usr/local/bin/php-fpm-healthcheck

# Copy the initialization script
COPY ./docker/production/php-fpm/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Copy the initial storage structure (used to seed the storage volume)
COPY ./storage /var/www/storage-init

# Copy PHP extensions and libraries from the builder stage
COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
COPY --from=builder /usr/local/bin/docker-php-ext-* /usr/local/bin/

# Use the recommended production PHP configuration
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Keep the image-provided FPM global config intact and add pool overrides
COPY ./docker/common/php-fpm/conf.d/*.conf /usr/local/etc/php-fpm.d/

# Copy the application code and dependencies from the build stage
COPY --from=builder /var/www /var/www

WORKDIR /var/www

# Ensure correct permissions
RUN chown -R www-data:www-data /var/www

# Switch to the non-privileged user to run the application
USER www-data

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

EXPOSE 9000
CMD ["php-fpm"]


# Stage 4: Development image (extends production with Xdebug + synced user)
FROM production AS development

# Xdebug configuration passed from Docker Compose / build args
ARG XDEBUG_ENABLED=true
ARG XDEBUG_MODE=develop,coverage,debug,profile
ARG XDEBUG_HOST=host.docker.internal
ARG XDEBUG_IDE_KEY=DOCKER
ARG XDEBUG_LOG=/dev/stdout
ARG XDEBUG_LOG_LEVEL=0

USER root

# Configure Xdebug if enabled
RUN if [ "${XDEBUG_ENABLED}" = "true" ]; then \
    pecl install xdebug && \
    docker-php-ext-enable xdebug && \
    echo "xdebug.mode=${XDEBUG_MODE}" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && \
    echo "xdebug.idekey=${XDEBUG_IDE_KEY}" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && \
    echo "xdebug.log=${XDEBUG_LOG}" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && \
    echo "xdebug.log_level=${XDEBUG_LOG_LEVEL}" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && \
    echo "xdebug.client_host=${XDEBUG_HOST}" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini ; \
    echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini ; \
fi

# Sync the container user with the host user to avoid permission issues
ARG UID=1000
ARG GID=1000

RUN if getent group ${GID}; then \
      group_name=$(getent group ${GID} | cut -d: -f1); \
      useradd -m -u ${UID} -g ${GID} -s /bin/bash www; \
    else \
      groupadd -g ${GID} www && \
      useradd -m -u ${UID} -g www -s /bin/bash www; \
      group_name=www; \
    fi

# Dynamically update php-fpm to use the new user and group
RUN sed -i "s/user = www-data/user = www/g" /usr/local/etc/php-fpm.d/www.conf && \
    sed -i "s/group = www-data/group = $group_name/g" /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www

# Copy the development entrypoint script
COPY ./docker/development/php-fpm/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Switch back to the non-privileged user to run the application
USER www-data

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

EXPOSE 9000
CMD ["php-fpm"]
