# Runs inside the setup container (non-root, egress only via allowlist proxy).
set -euo pipefail
cd /work
if [ ! -d src ]; then git clone --depth 1 "https://github.com/$REPO.git" src; fi
cd src
echo "commit: $(git rev-parse HEAD)"
date -u +"composer start %H:%M:%S"
composer install --no-interaction --no-progress --prefer-dist 2>&1 | tail -40
date -u +"composer end %H:%M:%S"
# Local env: SQLite instead of MySQL, no Redis, test-mode values only.
mkdir -p /work/db && : > /work/db/database.sqlite
cp .env.example .env
sed -i -e 's/^DB_CONNECTION=.*/DB_CONNECTION=sqlite/' -e 's|^DB_DATABASE=.*|DB_DATABASE=/work/db/database.sqlite|' \
       -e 's/^BROADCAST_DRIVER=.*/BROADCAST_DRIVER=log/' -e 's/^QUEUE_CONNECTION=.*/QUEUE_CONNECTION=sync/' \
       -e 's/^MAIL_MAILER=.*/MAIL_MAILER=log/' .env
php artisan key:generate --force
php artisan migrate --force 2>&1 | tail -30
echo "setup done"
