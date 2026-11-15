# Runs inside the app container (--network none). Laravel's own dev server on loopback.
cd /work/src
exec php artisan serve --host=127.0.0.1 --port="$PORT" --no-reload
