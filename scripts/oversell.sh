#!/usr/bin/env bash
# Fires parallel reservations through nginx and php-fpm, then checks the database with SQL.
#
# Scenario 1: 20 people ask for one ticket each while only 5 exist.
# Scenario 2: one person sends 10 parallel requests for one ticket against the limit of 6.
#
# Usage: scripts/oversell.sh (with the stack running: docker compose up -d)
#   BASE_URL   address of nginx, by default http://localhost:8080
#   KEEP_DATA  set to 1 to keep the seeded rows for inspection
set -euo pipefail

cd "$(dirname "$0")/.."

base_url=${BASE_URL:-http://localhost:8080}
keep_data=${KEEP_DATA:-0}
run="oversell-$(date +%s)-$$"
work_dir=$(mktemp -d)
failures=0

sql() {
    docker compose exec -T mysql sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" exec mysql -N -B -u"$MYSQL_USER" "$MYSQL_DATABASE"'
}

cleanup() {
    if [ "$keep_data" != "1" ]; then
        sql <<SQL || echo "Could not remove the rows of $run" >&2
DELETE order_items FROM order_items
    JOIN orders ON orders.id = order_items.order_id
    JOIN users ON users.id = orders.user_id
    WHERE users.email LIKE '$run-%';
DELETE orders FROM orders JOIN users ON users.id = orders.user_id WHERE users.email LIKE '$run-%';
DELETE events FROM events JOIN users ON users.id = events.organizer_id WHERE users.email LIKE '$run-%';
DELETE personal_access_tokens FROM personal_access_tokens
    JOIN users ON users.id = personal_access_tokens.tokenable_id
    WHERE personal_access_tokens.tokenable_type LIKE '%User' AND users.email LIKE '$run-%';
DELETE FROM users WHERE email LIKE '$run-%';
SQL
    fi

    rm -rf "$work_dir"
}

trap cleanup EXIT

create_user() {
    local name=$1
    local role=$2

    sql <<SQL
INSERT INTO users (name, email, password, role, created_at, updated_at)
VALUES ('$name', '$run-$name@example.test', 'no-login', '$role', UTC_TIMESTAMP(), UTC_TIMESTAMP());
SELECT LAST_INSERT_ID();
SQL
}

issue_token() {
    docker compose exec -T app php artisan ticketflow:token "$run-$1@example.test" | tr -d '\r\n'
}

# Prints "<event id> <ticket type id>" of a new published event with one pool.
create_pool() {
    local organizer_id=$1
    local quantity=$2

    sql <<SQL | tr '\n' ' '
INSERT INTO events (organizer_id, title, description, venue, city, category, starts_at, ends_at, status, created_at, updated_at)
VALUES ($organizer_id, '$run', 'Seeded by scripts/oversell.sh', 'Test hall', 'Krakow', 'concert',
    UTC_TIMESTAMP() + INTERVAL 30 DAY, UTC_TIMESTAMP() + INTERVAL 31 DAY, 'PUBLISHED', UTC_TIMESTAMP(), UTC_TIMESTAMP());
SET @event_id = LAST_INSERT_ID();
INSERT INTO ticket_types (event_id, name, price, quantity, created_at, updated_at)
VALUES (@event_id, 'Standard', 10000, $quantity, UTC_TIMESTAMP(), UTC_TIMESTAMP());
SELECT @event_id, LAST_INSERT_ID();
SQL
}

# Starts one background request. The answer lands in <dir>/<name>.status and <dir>/<name>.body.
reserve() {
    local dir=$1
    local name=$2
    local token=$3
    local event_id=$4
    local ticket_type_id=$5

    curl -s -o "$dir/$name.body" -w '%{http_code}\n' -X POST "$base_url/api/orders" \
        -H "Authorization: Bearer $token" \
        -H 'Accept: application/json' \
        -H 'Content-Type: application/json' \
        -d "{\"eventId\":$event_id,\"items\":[{\"ticketTypeId\":$ticket_type_id,\"quantity\":1}]}" \
        > "$dir/$name.status" &
}

count_status() {
    cat "$1"/*.status | grep -c "^$2\$" || true
}

count_code() {
    cat "$1"/*.body | grep -o "\"code\":\"$2\"" | wc -l | tr -d ' ' || true
}

print_answers() {
    echo "  answers:"
    cat "$1"/*.status | sort | uniq -c | sed 's/^/    /'

    cat "$1"/*.body | grep -o '"code":"[A-Z_]*"' | sort | uniq -c | sed 's/^/    /' || true
}

check() {
    local description=$1
    local actual=$2
    local expected=$3

    if [ "$actual" = "$expected" ]; then
        echo "  ok: $description ($actual)"
    else
        echo "  FAILED: $description: got $actual, expected $expected"
        failures=$((failures + 1))
    fi
}

check_at_most() {
    local description=$1
    local actual=$2
    local limit=$3

    if [ "$actual" -le "$limit" ]; then
        echo "  ok: $description ($actual, at most $limit)"
    else
        echo "  FAILED: $description: got $actual, at most $limit allowed"
        failures=$((failures + 1))
    fi
}

echo "Seeding data of $run..."

organizer_id=$(create_user organizer ORGANIZER)

echo "Scenario 1: 20 people, 5 tickets"

read -r event_id ticket_type_id <<< "$(create_pool "$organizer_id" 5)"
dir="$work_dir/pool"
mkdir -p "$dir"
tokens=()

for i in $(seq 1 20); do
    create_user "buyer-$i" ATTENDEE > /dev/null
    tokens+=("$(issue_token "buyer-$i")")
done

for i in $(seq 1 20); do
    reserve "$dir" "$i" "${tokens[$((i - 1))]}" "$event_id" "$ticket_type_id"
done

wait
print_answers "$dir"

created=$(count_status "$dir" 201)
refused=$(count_status "$dir" 409)
sold_out=$(count_code "$dir" TICKETS_SOLD_OUT)
conflicts=$(count_code "$dir" RESERVATION_CONFLICT)

read -r quantity sold held <<< "$(echo "SELECT quantity, sold, held FROM ticket_types WHERE id = $ticket_type_id;" | sql)"

reserved=$(sql <<SQL
SELECT COALESCE(SUM(order_items.quantity), 0)
FROM order_items
JOIN orders ON orders.id = order_items.order_id
WHERE order_items.ticket_type_id = $ticket_type_id AND orders.status = 'PENDING';
SQL
)

check_at_most "sold + held never exceeds the pool" "$((sold + held))" "$quantity"
check "every answer is 201 or 409" "$((created + refused))" 20
check "every 409 says sold out or conflict" "$((sold_out + conflicts))" "$refused"
check "held equals the 201 answers" "$held" "$created"
check "pending order items equal the 201 answers" "$reserved" "$created"

if [ "$created" -lt "$quantity" ]; then
    echo "  note: $((quantity - created)) seats stayed free, $conflicts requests got RESERVATION_CONFLICT"
fi

echo "Scenario 2: one person, 10 parallel requests, limit of 6"

read -r event_id ticket_type_id <<< "$(create_pool "$organizer_id" 100)"
dir="$work_dir/limit"
mkdir -p "$dir"
create_user limit-buyer ATTENDEE > /dev/null
token=$(issue_token limit-buyer)

for i in $(seq 1 10); do
    reserve "$dir" "$i" "$token" "$event_id" "$ticket_type_id"
done

wait
print_answers "$dir"

read -r held <<< "$(echo "SELECT held FROM ticket_types WHERE id = $ticket_type_id;" | sql)"

reserved=$(sql <<SQL
SELECT COALESCE(SUM(order_items.quantity), 0)
FROM order_items
JOIN orders ON orders.id = order_items.order_id
JOIN users ON users.id = orders.user_id
WHERE users.email = '$run-limit-buyer@example.test' AND orders.status = 'PENDING';
SQL
)

check "exactly the limit was reserved" "$reserved" 6
check "held equals the reserved tickets" "$held" 6
check "the 201 answers equal the limit" "$(count_status "$dir" 201)" 6
check "the other answers are TICKET_LIMIT_EXCEEDED" "$(count_code "$dir" TICKET_LIMIT_EXCEEDED)" 4

if [ "$failures" -gt 0 ]; then
    echo "FAILED: $failures checks"
    exit 1
fi

echo "All checks passed"
