# TicketFlow

Ticket sales platform for events such as concerts, conferences and meetups. The backend is event-driven: paying for an order, a failed payment or an expired reservation publishes a domain event, and independent queued listeners react to it on their own queues.

The project is built in stages. This README describes what works today.

## Technologies

| Area | Choice |
|---|---|
| Backend | PHP 8.5, Laravel 13, REST API under `/api` |
| Authentication | Laravel Sanctum, session cookie for the SPA |
| Database | MySQL 8.4 |
| Queues | Redis with Laravel Horizon, one supervisor per queue |
| Scheduler | `schedule:work` in its own container |
| E-mail | Mailpit for local delivery |
| Documents | dompdf and chillerlan/php-qrcode |
| API documentation | Scramble (OpenAPI 3.1) |
| Tests | Pest 4 on a real MySQL database |
| Frontend | React, TypeScript, Vite, React Router, TanStack Query |
| Runtime | Docker Compose |

## Running from zero

Requirements: Docker with Compose v2, `openssl`, `curl`, and free ports 8080 and 8025.

```sh
./scripts/setup.sh
```

The script creates `.env` with random secrets, builds and starts the containers, runs the migrations and seeds the demo data. It can be run again safely.

Stop the stack with `docker compose down`. To start over, run `docker compose down -v`, delete `.env` and run the script again.

## URLs

| What | URL |
|---|---|
| Application | http://localhost:8080 |
| Health check | http://localhost:8080/api/health |
| API documentation | http://localhost:8080/docs/api |
| OpenAPI specification | http://localhost:8080/docs/api.json |
| Horizon | http://localhost:8080/horizon |
| Mailpit | http://localhost:8025 |

The specification is also stored in [docs/openapi.json](docs/openapi.json). Refresh it with:

```sh
docker compose exec -T app php artisan scramble:export --stdout > docs/openapi.json
```

## Demo accounts

All accounts use the password `ticketflow-demo`.

| Role | Email |
|---|---|
| Admin | `admin@ticketflow.test` |
| Organizer | `olivia@ticketflow.test` |
| Organizer | `oscar@ticketflow.test` |
| Attendee | `anna@ticketflow.test` |
| Attendee | `adam@ticketflow.test` |
| Attendee | `alicja@ticketflow.test` |

The seeder also creates events in every status (draft, published, finished, cancelled) with their ticket types.

## Tests

```sh
docker compose exec -T app php artisan test
```

Tests run on the separate database `ticketflow_test`, so they never touch the development data. `tests/Integration` commits for real, which lets a second database connection see the data in concurrency tests.

## Data model

The diagram is in [docs/erd.md](docs/erd.md).

## Features so far

- Docker Compose stack: nginx, php-fpm, Horizon, scheduler, MySQL, Redis, Mailpit and the Vite dev server
- Health endpoint checking the database and Redis
- Database schema with constraints that protect the rules even when the code fails: no sale beyond the ticket pool, one payment and one refund per order, unique invoice numbers, one active waitlist entry per user and pool
- Eloquent models, factories and demo data
- One error format for the whole API, with a request id in the body and the `X-Request-Id` header
- Domain events, payment and document contracts, and helpers for idempotent status changes and notifications
- A separate Horizon supervisor for each of the nine queues
- Startup check that stops the application on invalid configuration
- API documentation generated from the code
- Continuous integration on GitHub Actions: backend tests, frontend type check and build
