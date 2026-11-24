#!/bin/sh
set -eu
php artisan config:clear
php artisan migrate --force --no-interaction
php artisan db:seed --force --no-interaction
php artisan looksmaxxing:bootstrap-admin --no-interaction
php artisan config:cache

worker_loop() {
    while true; do
        php artisan queue:work database --sleep=3 --tries=3 --timeout=30 --max-time=1800 || true
        sleep 3
    done
}
worker_loop &
worker_pid=$!
php artisan schedule:work --no-interaction &
scheduler_pid=$!
trap 'kill "$worker_pid" "$scheduler_pid" 2>/dev/null || true' EXIT INT TERM
ethiodeploy-start
