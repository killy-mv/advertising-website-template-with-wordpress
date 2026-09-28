#!/usr/bin/env bash
# One-command setup for Linux/macOS: builds the stack, installs WordPress, loads demo content.
set -euo pipefail
cd "$(dirname "$0")/.."

if [[ ! -f .env ]]; then
  cp .env.example .env
  echo "Created .env from .env.example. Edit the passwords, then run this script again." >&2
  exit 1
fi
set -a; source .env; set +a

wp() { docker compose run --rm -q wpcli "$@"; }

echo "==> Starting containers"
docker compose up -d --build --remove-orphans

echo "==> Waiting for WordPress files"
for _ in $(seq 30); do
  docker compose exec -T wordpress test -f /var/www/html/wp-config.php && break
  sleep 2
done

if ! wp core is-installed 2>/dev/null; then
  echo "==> Installing WordPress"
  wp core install --url="$SITE_URL" --title="$SITE_TITLE" \
    --admin_user="$ADMIN_USER" --admin_password="$ADMIN_PASSWORD" \
    --admin_email="$ADMIN_EMAIL" --skip-email
fi

echo "==> Configuring"
wp theme activate restaurant-theme
wp rewrite structure '/%postname%/'
wp eval-file /scripts/seed.php

echo "==> Redis object cache"
wp plugin install redis-cache --activate
wp redis enable 2>/dev/null || true  # errors harmlessly if already enabled
wp cache flush

echo
echo "Done! Site:  $SITE_URL"
echo "Admin: $SITE_URL/wp-admin  (user: $ADMIN_USER, password in .env)"
