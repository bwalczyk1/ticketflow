# TicketFlow

Platforma sprzedaży biletów na wydarzenia (koncerty, konferencje, meetupy). Organizator tworzy wydarzenie i pule biletów, uczestnik rezerwuje bilety, płaci (płatność jest symulowana), dostaje bilety w PDF z kodem QR i fakturę, a w razie potrzeby zwraca zamówienie. Projekt semestralny z przedmiotu ZTPAI (Politechnika Krakowska).

Backend jest **sterowany zdarzeniami**. Opłacenie zamówienia, nieudana płatność, wygasła rezerwacja czy zwrot publikują zdarzenie domenowe (`OrderPaid`, `PaymentFailed`, `ReservationExpired`, `OrderRefunded`, `EventCancelled` itd.). Zdarzenie nie zna swoich odbiorców: obsługuje je kilka niezależnych listenerów, a każdy trafia do kolejki Redisa nadzorowanej przez Laravel Horizon (dziewięć kolejek, osobny supervisor na każdą). Nowa funkcja to nowy listener, bez zmian w kodzie, który zdarzenie publikuje.

## Technologie

| Obszar | Wybór |
|---|---|
| Backend | PHP 8.5, Laravel 13, REST API pod `/api` |
| Uwierzytelnianie | Laravel Sanctum, cookie sesji dla SPA (sesja w Redisie, ochrona CSRF) |
| Baza danych | MySQL 8.4 (osobny kontener) |
| Kolejki | Redis + Laravel Horizon, osobny supervisor na każdą kolejkę |
| Scheduler | `schedule:work` we własnym kontenerze |
| E-mail | Mailpit (lokalna skrzynka) |
| Dokumenty | dompdf (PDF) i chillerlan/php-qrcode (kod QR na bilecie) |
| Dokumentacja API | Scramble (OpenAPI 3.1 generowane z kodu) |
| Testy | Pest 4 na prawdziwej bazie MySQL |
| Frontend | React, TypeScript, Vite, React Router, TanStack Query, shadcn/ui (Tailwind CSS 4) |
| Uruchomienie | Docker Compose |
| CI | GitHub Actions: testy backendu oraz typy i build frontendu (`.github/workflows/ci.yml`) |

## Uruchomienie od zera

Wymagania: Docker z Compose v2, `openssl`, `curl` oraz wolne porty 8080 i 8025. Pierwsze uruchomienie pobiera obrazy i instaluje zależności (`composer install`, `npm install`), więc trwa kilka minut.

### Skrypt

```sh
./scripts/setup.sh
```

Skrypt robi to samo, co kroki ręczne poniżej, i można go bezpiecznie uruchomić ponownie.

### Kroki ręczne

```sh
cp .env.example .env
```

W `.env` trzeba uzupełnić trzy puste wartości. Sekretów nie ma w repozytorium, każdy generuje własne:

```sh
echo "APP_KEY=base64:$(openssl rand -base64 32)"   # klucz aplikacji (szyfrowanie sesji i ciasteczek)
echo "DB_PASSWORD=$(openssl rand -hex 16)"         # hasło użytkownika bazy
echo "MYSQL_ROOT_PASSWORD=$(openssl rand -hex 16)" # hasło roota MySQL
```

Wygenerowane linie wpisz w miejsce pustych `APP_KEY=`, `DB_PASSWORD=` i `MYSQL_ROOT_PASSWORD=` w pliku `.env`. Pozostałe wartości z `.env.example` działają bez zmian. Aplikacja nie wystartuje bez `APP_KEY` (zatrzymuje się z czytelnym komunikatem o błędnej konfiguracji). Następnie:

```sh
docker compose up -d --build --wait app mysql redis   # backend, baza i Redis; czeka na gotowość
docker compose exec -T app php artisan migrate --seed --force   # schemat bazy i dane demonstracyjne
docker compose up -d                                  # reszta: nginx, Horizon, scheduler, frontend, Mailpit
```

Gdy `http://localhost:8080` odpowiada (Vite potrzebuje chwili po starcie), aplikacja jest gotowa. Logi: `docker compose logs -f frontend` (lub inna usługa: `app`, `horizon`, `scheduler`, `nginx`, `mysql`, `redis`, `mailpit`).

### Zatrzymanie i powrót do stanu początkowego

| Cel | Polecenie |
|---|---|
| Zatrzymać stos (dane zostają) | `docker compose down` |
| Uruchomić ponownie | `docker compose up -d` |
| Wyczyścić dane i zacząć od nowa | `docker compose down -v`, usunąć `.env`, uruchomić `./scripts/setup.sh` |
| Tylko odtworzyć dane demo na działającym stosie | `docker compose exec -T app php artisan migrate:fresh --seed --force`, potem `docker compose exec -T redis redis-cli FLUSHALL` (czyści sesje i statystyki Horizona), potem `docker compose restart horizon mailpit` (Horizon odtwarza swój stan, Mailpit czyści skrzynkę) |

## Adresy

| Co | Adres |
|---|---|
| Aplikacja (SPA) | http://localhost:8080 |
| Health check | http://localhost:8080/api/health |
| Dokumentacja API (Scramble) | http://localhost:8080/docs/api |
| Specyfikacja OpenAPI (JSON) | http://localhost:8080/docs/api.json |
| Horizon (panel kolejek) | http://localhost:8080/horizon |
| Mailpit (skrzynka e-mail) | http://localhost:8025 |

Nginx wystawia backend i frontend pod jednym adresem (`/api`, `/sanctum`, `/docs`, `/horizon` trafiają do php-fpm, reszta do serwera Vite), więc cookie sesji i CSRF działają bez CORS. Horizon jest otwarty bez logowania tylko w środowisku `local` (takie ustawia `.env.example`); poza nim dostęp ma lista dozwolonych adresów w `HorizonServiceProvider`, domyślnie pusta.

Specyfikacja jest zapisana także w [docs/openapi.json](docs/openapi.json) (adres `/docs/api` działa tylko przy uruchomionej aplikacji). Odświeżenie pliku:

```sh
docker compose exec -T app php artisan scramble:export --stdout > docs/openapi.json
```

Komenda wypisuje na stderr ostrzeżenia Scramble `PD001` o zbędnych adnotacjach `@var`; są nieszkodliwe, a plik wynikowy jest poprawny.

Frontend generuje z niego typy (`npm run api:types` w `frontend/`), więc rozjazd kontraktu wychodzi przy kompilacji.

## Konta demo

Wszystkie konta mają hasło `ticketflow-demo` (testowa wartość z seedera, używana tylko lokalnie).

| Rola | E-mail |
|---|---|
| Admin | `admin@ticketflow.test` |
| Organizator | `olivia@ticketflow.test` |
| Organizator | `oscar@ticketflow.test` |
| Uczestnik | `anna@ticketflow.test` |
| Uczestnik | `adam@ticketflow.test` |
| Uczestnik | `alicja@ticketflow.test` |

Seeder tworzy wydarzenia w każdym statusie (szkic, opublikowane, zakończone, odwołane) z pulami biletów, m.in. „Krakow Jazz Night” (Standard 200 szt., VIP 20 szt.) i „Laravel Krakow Meetup” z pulą zaledwie 5 biletów, która służy do pokazania wyprzedania i listy oczekujących. Zamówień seeder nie tworzy. Gdy `PAYMENT_GATEWAY=fake`, bramka odrzuca losowo `FAKE_PAYMENT_FAILURE_RATE` płatności (domyślnie 0,1, czyli 10%), co pokazuje ścieżkę nieudanej płatności.

## Funkcje

### Uczestnik (`ATTENDEE`)

- rejestruje się samodzielnie (nowe konta zawsze dostają rolę `ATTENDEE`; rolę organizatora lub admina nadaje administrator)
- przegląda katalog wydarzeń (dostępny też bez logowania): wyszukiwanie `q`, filtry `city`, `category`, `from`, `to`, `price_max`, sortowanie i paginacja
- rezerwuje bilety (zamówienie `PENDING` trzyma miejsca przez 15 minut), płaci, może anulować niezapłaconą rezerwację
- po płatności dostaje e-mail z biletami, bilety w PDF z kodem QR oraz fakturę PDF
- zwraca całe zamówienie do 48 godzin przed wydarzeniem; dostaje fakturę korygującą
- zapisuje się na listę oczekujących na wyprzedaną pulę i przyjmuje ofertę w ciągu 30 minut
- dostaje przypomnienie e-mailem 24 godziny przed wydarzeniem

### Organizator (`ORGANIZER`)

- tworzy, edytuje, publikuje, odwołuje i usuwa własne wydarzenia (strefa czasowa wydarzenia jest zapisana razem z nim)
- zarządza pulami biletów (nazwa, cena, ilość) i widzi podsumowanie sprzedaży
- odwołanie wydarzenia uruchamia masowe zwroty wszystkich opłaconych zamówień i powiadomienia uczestników
- skanuje (wpisuje) kody biletów przy wejściu: check-in
- dostaje e-mail o każdym opłaconym zamówieniu i raport po zakończeniu wydarzenia

### Administrator (`ADMIN`)

- lista użytkowników i zmiana ich ról (własnej roli nie można zmienić)
- lista wszystkich wydarzeń ze wszystkich organizatorów

### Reguły biznesowe

| Reguła | Gdzie jest w kodzie |
|---|---|
| Rezerwacja trzyma miejsca 15 minut (`hold_minutes`); po czasie scheduler wygasza zamówienie, a próba zapłaty wygasłego zamówienia daje 410 `RESERVATION_EXPIRED` | `ReserveTickets`, `ExpireReservations`, `StartPayment` |
| Brak sprzedaży ponad pulę: wolne = `quantity - sold - held`; blokada miejsc to `UPDATE ... WHERE version = ?` (optimistic locking), przy konflikcie do 5 ponowień, a w bazie dodatkowo `CHECK (sold + held <= quantity)` | `ReserveTickets::holdSeats`, `TicketAvailability`, migracja `ticket_types` |
| Sprzedaż tylko na wydarzenie opublikowane i przed jego początkiem; bilety kupuje tylko rola `ATTENDEE` | `TicketAvailability::ensureOnSale`, middleware `role:ATTENDEE` |
| Maksymalnie 6 biletów na osobę na wydarzenie (wiersz użytkownika blokowany `FOR UPDATE`, żeby równoległe żądania nie ominęły limitu) | `TicketLimit`, `ReserveTickets` |
| Zwrot tylko do 48 godzin przed początkiem; unieważnia bilety i wystawia fakturę korygującą | `RefundEligibility`, `StartRefund`, `VoidTickets`, `IssueCorrectionInvoice` |
| Jednorazowy check-in: bilet `USED` nie wejdzie drugi raz (409), a check-in robi tylko organizator tego wydarzenia, w dniu wydarzenia liczonym w jego strefie czasowej | `CheckInTicket`, `CheckInWindow` |
| Lista oczekujących (FIFO): zwolnione miejsca trafiają jako oferta ważna 30 minut do pierwszej osoby w kolejce i są wliczone do `held`, więc nikt ich nie kupi poza kolejką | `WaitlistOffers`, `OfferSeatsToWaitlist`, `ExpireWaitlistOffers` |
| Ochrona puli: organizator nie zmniejszy `quantity` poniżej `sold + held` i nie usunie wydarzenia ze sprzedażą; wydarzenia zakończone i odwołane nie podlegają edycji | `PoolProtection`, `OrganizerEvents` |
| Odwołanie wydarzenia: zwroty bez okna 48 godzin, jako jedna paczka jobów (`Bus::batch`), jeden nieudany zwrot nie zatrzymuje reszty | `RefundAllOrders`, `RefundOrder` |
| Przypomnienia 24 godziny przed wydarzeniem i raport po wydarzeniu | `SendEventReminders`, `SendPostEventReport` |
| Płatność jest symulowana (`FakePaymentGateway`), a zła płatność zwalnia miejsca (kompensacja sagi) | `FakePaymentGateway`, `SettlePayment`, `ReleaseSeats` |

## Architektura

### Warstwy

```text
żądanie → nginx → php-fpm → middleware → Controller → Form Request → Service → model Eloquent → MySQL
                                              └→ Resource (DTO odpowiedzi)      └→ zdarzenie → listenery (kolejki)
```

| Warstwa | Katalog | Rola |
|---|---|---|
| Obsługa HTTP | `backend/app/Http/Controllers`, `Http/Requests`, `Http/Resources`, `Policies` | cienkie kontrolery; Form Request waliduje wejście (request DTO); Resource kształtuje odpowiedź (response DTO), więc API nie ujawnia kształtu tabel; Policy sprawdza własność zasobu |
| Logika biznesowa | `backend/app/Services/<moduł>` | reguły i transakcje; serwisy nie znają `Request` ani `Response`; czyste reguły bez bazy (`TicketAvailability`, `RefundEligibility`, `CheckInWindow`) mają testy jednostkowe |
| Dostęp do danych | `backend/app/Models` | modele Eloquent (relacje, casty, scope'y), bez osobnej warstwy repozytoriów; każde zapytanie wybiera jawne kolumny, nigdy `select *` |
| Zdarzenia i kolejki | `backend/app/Events`, `Listeners`, `Jobs` | patrz niżej |

Enumy (`Role`, `EventStatus`, `OrderStatus`, `PaymentStatus`, `TicketStatus`, `RefundStatus`, `WaitlistStatus`, `QueueName`) są typami PHP; dozwolone przejścia statusu zamówienia są danymi w `OrderStatus::canTransitionTo()`. Schemat bazy powstaje wyłącznie z migracji (`backend/database/migrations`), a ograniczenia (`CHECK`, `UNIQUE`, klucze obce) chronią reguły także wtedy, gdy zawiedzie kod.

### Format błędów

Jeden handler (`app/Exceptions/ApiExceptionRenderer.php`) zamienia wszystkie wyjątki na tę samą kopertę, z identyfikatorem żądania także w nagłówku `X-Request-Id`:

```json
{ "error": { "code": "TICKETS_SOLD_OUT", "message": "…", "details": [], "requestId": "…" } }
```

Kody HTTP: 400 błąd walidacji (`VALIDATION_ERROR`, także nieznane pole i niepoprawny JSON), 401 brak sesji, 403 zła rola, 404 brak zasobu (także cudze zamówienie lub bilet), 409 konflikt ze stanem i reguły biznesowe, 410 wygasła rezerwacja, 419 brak tokenu CSRF, 500 bez szczegółów (te są tylko w logach). Wyjątki domenowe leżą w `app/Exceptions/Domain`. Walidacja odpowiada po polsku, gdy żądanie niesie nagłówek `Accept-Language: pl` (tak robi frontend).

### Uwierzytelnianie i autoryzacja

- Sanctum w trybie SPA: `GET /sanctum/csrf-cookie`, potem `POST /api/auth/login`; sesja trzyma się w cookie, a stan w Redisie. Hasła: bcrypt (koszt 12), ten sam błąd 401 `INVALID_CREDENTIALS` dla złego e-maila i złego hasła.
- Trzy role: `ADMIN`, `ORGANIZER`, `ATTENDEE`, sprawdzane middleware `role:` (`app/Http/Middleware/EnsureRole.php`) w plikach `backend/routes/api/*.php`.
- Własność zasobu sprawdzają policies: cudze zamówienie lub bilet daje 404 (samo istnienie to informacja), a cudze wydarzenie w panelu organizatora 403.
- Kolejność sprawdzeń: 401, 403 (rola), 400 (walidacja), 404, 403/404 (własność), 409 (reguły biznesowe).
- Do „Try it” w `/docs/api` i skryptów służy lokalna komenda `php artisan ticketflow:token <email>` (tylko `APP_ENV=local`), która wystawia token na 60 minut.

### Zdarzenia, listenery i kolejki

Zdarzenia są publikowane po zatwierdzeniu transakcji (`ShouldDispatchAfterCommit`), a listenery są idempotentne (ponowiony job nie dubluje biletów, faktur ani maili). Lista wynika z atrybutów `#[Queue(...)]` w `backend/app/Listeners` i `backend/app/Jobs`:

| Zdarzenie | Listener | Kolejka |
|---|---|---|
| `OrderPlaced` (start płatności) | `ProcessPayment` | `payments` |
| `OrderPaid` | `IssueTickets` (potem job `GenerateTicketPdfs`) | `tickets` (PDF: `documents`) |
| `OrderPaid` | `IssueInvoice` | `documents` |
| `OrderPaid` | `UpdateSalesSummary` | `stats` |
| `OrderPaid` | `NotifyOrganizer` | `notifications` |
| `TicketsGenerated` (po wygenerowaniu PDF) | `SendTicketsEmail` | `mail` |
| `PaymentFailed` | `ReleaseSeats` | `orders` |
| `PaymentFailed` | `SendPaymentFailedEmail` | `mail` |
| `ReservationExpired` | `ReleaseSeats` | `orders` |
| `SeatsReleased` | `OfferSeatsToWaitlist` | `waitlist` |
| `WaitlistOfferCreated` | `SendWaitlistOfferEmail` | `mail` |
| `OrderRefunded` | `RefundPayment` | `payments` |
| `OrderRefunded` | `VoidTickets` | `tickets` |
| `OrderRefunded` | `ReleaseRefundedSeats` | `orders` |
| `OrderRefunded` | `IssueCorrectionInvoice` | `documents` |
| `OrderRefunded` | `UpdateSalesSummary` | `stats` |
| `OrderRefunded` | `SendRefundEmail` | `mail` |
| `EventCancelled` | `RefundAllOrders` (paczka jobów `RefundOrder`) | `refunds` |
| `EventCancelled` | `NotifyAttendees` | `mail` |
| `EventCancelled` | `CloseWaitlist` | `waitlist` |
| `TicketCheckedIn` | `UpdateSalesSummary` | `stats` |
| `EventFinished` | `SendPostEventReport` | `notifications` |

Do tego dochodzą joby `SendEventReminder` (kolejka `mail`) i `ProcessRefund` (`payments`, zwrot spóźnionej płatności).

### Zadania zaplanowane

Definicje są w `backend/routes/console.php`, a wykonuje je kontener `scheduler` (`schedule:work`):

| Komenda | Kiedy | Co robi |
|---|---|---|
| `ticketflow:expire-reservations` | co minutę | wygasza zamówienia po upływie 15 minut i publikuje `ReservationExpired` |
| `ticketflow:expire-waitlist-offers` | co minutę | wygasza nieprzyjęte oferty z listy oczekujących |
| `ticketflow:finish-events` | co 5 minut | przenosi wydarzenia po `ends_at` do `FINISHED` i publikuje `EventFinished` (raport) |
| `ticketflow:reconcile-orders` | co 5 minut | naprawia zamówienia, którym zginęło zdarzenie |
| `ticketflow:send-event-reminders` | co godzinę | przypomnienia dla wydarzeń za 24-25 godzin |
| `horizon:snapshot` | co 5 minut | migawki metryk Horizona (wykresy w Metrics, „Jobs Per Minute”) |
| `model:prune` | codziennie | usuwa stare zamknięte wpisy listy oczekujących i wpisy księgi maili |

Poza harmonogramem są komendy `ticketflow:simulate-traffic --orders=500` (ruch demonstracyjny widoczny w Horizonie) i `ticketflow:token`.

### Przebieg żądania: `POST /api/orders` (rezerwacja biletów)

1. **Nginx** (`docker/nginx/default.conf`) kieruje `/api/*` do php-fpm, czyli do `backend/public/index.php`.
2. **Middleware** (`backend/bootstrap/app.php`): `RequestId` nadaje identyfikator, `statefulApi` sprawdza sesję i token CSRF, `SetLocaleFromHeader` wybiera język komunikatów, `RejectMalformedJson` odrzuca zepsuty JSON, `auth:sanctum` wymaga zalogowania (inaczej 401), `role:ATTENDEE` sprawdza rolę (inaczej 403). Trasa: `backend/routes/api/orders.php`.
3. **Kontroler** `OrderController::store` (`app/Http/Controllers/Orders`) dostaje już zwalidowane dane z `StoreOrderRequest` (`app/Http/Requests/Orders`); błędne pole daje 400 `VALIDATION_ERROR`.
4. **Serwis** `ReserveTickets::handle` (`app/Services/Orders`), w transakcji (do 5 prób):
   1. blokuje wiersz użytkownika (`SELECT ... FOR UPDATE`), żeby równoległe żądania nie przekroczyły limitu 6 biletów;
   2. wczytuje wydarzenie i pule, sprawdza czyste reguły z `TicketAvailability` (sprzedaż otwarta, limit na osobę, wolne miejsca) i przy naruszeniu rzuca wyjątek domenowy, np. 409 `TICKETS_SOLD_OUT`;
   3. dla każdej puli wykonuje `UPDATE ticket_types SET held = held + n, version = version + 1 WHERE id = ? AND version = ? AND quantity - sold - held >= n`; zero zmienionych wierszy oznacza, że ktoś był szybszy, więc kolejna próba w nowej transakcji (po 5 próbach 409 `RESERVATION_CONFLICT`);
   4. tworzy `orders` (status `PENDING`, `expires_at` za 15 minut) i `order_items`.
5. **Baza**: ograniczenie `CHECK (sold + held <= quantity)` w tabeli `ticket_types` jest ostatnią linią obrony.
6. **Odpowiedź**: `OrderResource` zwraca 201 z zamówieniem. Wyjątki domenowe trafiają do `ApiExceptionRenderer`, który buduje kopertę błędu.

Dalszy ciąg: `POST /api/orders/{id}/pay` (`StartPayment`) zapisuje płatność i publikuje `OrderPlaced`, a odpowiedź 202 wraca od razu. Płatność kończy listener `ProcessPayment` na kolejce `payments` (`FakePaymentGateway`), a `SettlePayment` ustawia zamówienie na `PAID` i publikuje `OrderPaid`, który uruchamia pozostałe listenery z tabeli powyżej.

## Testy

```sh
docker compose exec -T app php artisan test
```

Testy działają na osobnej bazie `ticketflow_test` (tworzy ją `docker/mysql/init.sh` przy pierwszym starcie MySQL), więc nie dotykają danych deweloperskich. Rodzaje:

- **Unit** (`backend/tests/Unit`): czyste reguły bez bazy (dostępność biletów, okno zwrotu, okno check-inu, kolejka oczekujących, przejścia statusów, numeracja faktur) oraz testy architektury (`ArchTest.php`: serwisy nie znają HTTP, kontrolery nie sięgają do bazy, modele nie zależą od serwisów, `env()` tylko w `config/`, brak pozostawionych `dd()`).
- **Feature** (`backend/tests/Feature`): testy HTTP całych endpointów (kody, koperta błędu, kolejność 401/403/400/404/409, role) oraz listenerów, komend i schematu bazy.
- **Integration** (`backend/tests/Integration`): testy współbieżności na prawdziwych, zatwierdzanych transakcjach z dwoma połączeniami (wyścigi przy rezerwacji, limicie, płatności, zwrocie, check-inie, numeracji faktur, ofertach listy oczekujących).
- Skrypt `scripts/oversell.sh` wysyła równoległe żądania przez nginx i sprawdza stan bazy SQL-em (20 osób na 5 biletów, limit 6 biletów dla jednej osoby).

Aktualny wynik: 1143 testy przechodzą (4370 asercji).

## Dokumentacja i materiały

- Model danych (ERD w Mermaid): [docs/erd.md](docs/erd.md)
- Specyfikacja OpenAPI: [docs/openapi.json](docs/openapi.json), a przy działającej aplikacji http://localhost:8080/docs/api
- Nagranie demonstracyjne: link do nagrania – do uzupełnienia
