#!/bin/sh
# Reverb boot check (decision 50, compose-profile.md §3.2). Runs through app-entrypoint first,
# then each check exits non-zero with one line naming what failed. Never prints env values.
set -eu

fail() {
    echo "reverb-boot: $1" >&2
    exit 1
}

# (a) An event-loop extension, or ReactPHP falls back to stream_select (dies near 1,014 sockets).
php -r 'exit(extension_loaded("uv") || extension_loaded("event") ? 0 : 1);' \
    || fail "neither ext-uv nor ext-event is loaded"

# (b) Soft nofile limit.
min="${REVERB_MIN_NOFILE:-4096}"
soft="$(ulimit -Sn)"
if [ "$soft" != unlimited ] && [ "$soft" -lt "$min" ]; then
    fail "nofile soft limit $soft is below $min"
fi

# (c) Client events refused in the loaded config.
php -r '
    require "/app/vendor/autoload.php";
    $app = require "/app/bootstrap/app.php";
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    foreach ((array) config("reverb.apps.apps") as $a) {
        if (($a["accept_client_events_from"] ?? null) !== "none") { exit(1); }
    }
    exit(0);
' >/dev/null 2>&1 \
    || fail "accept_client_events_from is not none"

exec php artisan reverb:start --host=0.0.0.0 --port=8080 --no-interaction
