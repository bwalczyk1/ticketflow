#!/bin/sh
set -e

cd "$(dirname "$0")/.."

if [ ! -f .env ]; then
    cp .env.example .env
    key="base64:$(openssl rand -base64 32)"
    db_password="$(openssl rand -hex 16)"
    root_password="$(openssl rand -hex 16)"
    sed -i.bak \
        -e "s|^APP_KEY=.*|APP_KEY=$key|" \
        -e "s|^DB_PASSWORD=.*|DB_PASSWORD=$db_password|" \
        -e "s|^MYSQL_ROOT_PASSWORD=.*|MYSQL_ROOT_PASSWORD=$root_password|" \
        .env
    rm -f .env.bak
fi

docker compose up -d --build --wait app mysql redis
docker compose exec -T app php artisan migrate --seed --force
docker compose up -d

echo "Waiting for the frontend dev server..."
attempts=0

until [ "$(curl -s -o /dev/null -w '%{http_code}' http://localhost:8080/)" = "200" ]; do
    attempts=$((attempts + 1))

    if [ "$attempts" -ge 120 ]; then
        echo "Frontend did not start. Check: docker compose logs frontend" >&2
        exit 1
    fi

    sleep 1
done

echo "TicketFlow is running at http://localhost:8080"
