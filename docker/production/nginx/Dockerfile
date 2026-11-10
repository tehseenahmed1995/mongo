# Production Nginx image
# -------------------------------------------------------------------------
# The frontend assets are built in the shared PHP-FPM Dockerfile (node stage)
# and shared with Nginx via the `laravel-public-assets` volume, which keeps
# the Vite manifest and hashed files consistent between the two services.
# Here we only need the static, non-built public files (index.php, robots,
# favicon, ...). The built assets live under /var/www/public/build which is
# provided by the shared named volume mounted by Docker Compose.
# -------------------------------------------------------------------------
FROM nginx:alpine

COPY ./docker/production/nginx/nginx.conf /etc/nginx/nginx.conf
COPY ./public /var/www/public

WORKDIR /var/www/public

EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
