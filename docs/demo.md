# Scenariusz demonstracji (3-5 minut)

Scenariusz używa wyłącznie istniejących funkcji, komend i danych z seedera. Wszystkie polecenia uruchamiaj w katalogu głównym repozytorium, przy działającym stosie (`docker compose up -d`, patrz [README](../README.md)).

## Przygotowanie przed nagraniem

1. **Czysty stan danych** (około 1 minuty):

   ```sh
   docker compose exec -T app php artisan migrate:fresh --seed --force
   docker compose exec redis redis-cli FLUSHALL
   docker compose restart horizon mailpit
   ```

   Pierwsze polecenie odtwarza bazę z kontami demo i wydarzeniami, drugie czyści sesje, kolejki i statystyki Horizona, trzecie restartuje Horizon i opróżnia skrzynkę Mailpit. Zanim zaczniesz nagrywać, poczekaj kilkanaście sekund, aż http://localhost:8080/horizon pokaże status `Active`.

2. **Bez losowych porażek płatności.** Domyślnie bramka symuluje porażkę 10% płatności (`FAKE_PAYMENT_FAILURE_RATE=0.1`). Na czas nagrania ustaw w `.env` `FAKE_PAYMENT_FAILURE_RATE=0`, a potem utwórz kontenery ponownie, bo zmienna jest czytana przy starcie (Horizon trzyma konfigurację w pamięci):

   ```sh
   docker compose up -d --force-recreate app horizon scheduler
   ```

   Po nagraniu przywróć `0.1`, jeśli chcesz pokazać ścieżkę nieudanej płatności.

3. **Dwa okna przeglądarki**, żeby nie wylogowywać się w trakcie: zwykłe okno dla organizatora i okno prywatne dla uczestnika. Otwórz od razu osobne karty: Horizon (http://localhost:8080/horizon) i Mailpit (http://localhost:8025).

4. **Terminal** otwarty w katalogu repozytorium, z czytelną czcionką.

5. **Po 23:00 czasu warszawskiego nie nagrywaj kroku 1 i 6**: wydarzenie z kroku 1 musi zaczynać się jeszcze tego samego dnia (patrz uwaga niżej).

Konta (hasło wszędzie `ticketflow-demo`): organizator `olivia@ticketflow.test`, uczestnik `anna@ticketflow.test`.

## Przebieg

Czasy są orientacyjne i sumują się do około 4 minut 40 sekund. Marginesem jest ewentualne przyspieszenie nagrania w krokach 1 i 2.

| Czas | Krok |
|---|---|
| 0:00-0:15 | Wstęp |
| 0:15-1:00 | 1. Organizator publikuje wydarzenie |
| 1:00-1:50 | 2. Zakup jako uczestnik |
| 1:50-2:30 | 3. Horizon, Mailpit i PDF biletu |
| 2:30-3:00 | 4. Próba zakupu ponad pulę (409) |
| 3:00-3:25 | 5. Zwrot |
| 3:25-4:00 | 6. Check-in dwa razy (409) |
| 4:00-4:40 | 7. Symulacja ruchu w Horizonie |

### Wstęp (0:00-0:15)

Powiedz jednym zdaniem: platforma sprzedaży biletów, Laravel plus React, a płatność, bilety, faktury i maile to niezależne listenery zdarzeń na osobnych kolejkach Horizona. Pokaż stronę główną http://localhost:8080 z listą wydarzeń z seedera.

### 1. Organizator publikuje wydarzenie (0:15-1:00)

Okno zwykłe, http://localhost:8080/login, konto `olivia@ticketflow.test`.

1. Z menu wybierz **Moje wydarzenia**, kliknij **Nowe wydarzenie**.
2. Wpisz: tytuł `Demo Night`, opis dowolny, miejsce `Klub Studio`, miasto `Krakow`, kategoria `concert`, strefa czasowa `Europe/Warsaw`.
3. **Początek**: dzisiejsza data, godzina za około godzinę. **Koniec**: dzisiejsza data, 3 godziny po początku (lub następny dzień, gdy zbliża się północ). Zapisz.
4. Wydarzenie jest w szkicu, a aplikacja przenosi na stronę z pulami biletów (komunikat „Dodaj pule biletów"). Dodaj pulę: nazwa `Standard`, cena `50`, ilość `100`.
5. Wróć do **Moje wydarzenia**, w menu „⋯” przy `Demo Night` wybierz **Opublikuj**.

Oczekiwany wynik: komunikat „Opublikowano wydarzenie „Demo Night””, status wydarzenia `Opublikowane`.

Dlaczego dzisiaj: check-in (krok 6) działa tylko w dniu wydarzenia liczonym w jego strefie czasowej, a wydarzenia z seedera zaczynają się za 14 i więcej dni. Sprzedaż jest otwarta tylko przed początkiem, więc początek musi być jeszcze dziś, ale w przyszłości.

### 2. Zakup jako uczestnik (1:00-1:50)

Okno prywatne, http://localhost:8080/login, konto `anna@ticketflow.test`.

1. Na liście wydarzeń otwórz **Krakow Jazz Night**, wybierz 1 bilet `Standard`, **Kup bilety**. Zamówienie ma status `PENDING` z odliczaniem 15 minut. Kliknij **Zapłać**. Po chwili (symulowana płatność trwa 1-3 sekundy w tle) status zmienia się na `PAID`. To zamówienie posłuży do zwrotu w kroku 5.
2. Wróć do listy, otwórz **Demo Night**, wybierz 1 bilet `Standard`, **Kup bilety**, **Zapłać**. To zamówienie posłuży do check-inu w kroku 6.

Oczekiwany wynik: oba zamówienia w statusie `PAID`, na stronie zamówienia pojawia się faktura do pobrania.

### 3. Horizon, Mailpit i PDF biletu (1:50-2:30)

1. Karta **Horizon**, widok **Dashboard** i **Recent Jobs**: widać joby `ProcessPayment` (kolejka `payments`), `IssueTickets` (`tickets`), `GenerateTicketPdfs` i `IssueInvoice` (`documents`), `UpdateSalesSummary` (`stats`), `NotifyOrganizer` (`notifications`) oraz `SendTicketsEmail` (`mail`). Zwróć uwagę, że jedno zdarzenie `OrderPaid` uruchomiło kilka niezależnych listenerów na osobnych kolejkach.
2. Karta **Mailpit**: wiadomości `Twoje bilety: Krakow Jazz Night` i `Twoje bilety: Demo Night` (do Anny) oraz `Nowe zamówienie: …` (do organizatora Olivii).
3. Okno uczestnika, **Moje bilety**: kliknij **Pobierz PDF** przy bilecie `Demo Night`. Pokaż w PDF kod QR i kod biletu. Skopiuj kod tekstowy biletu z tabeli (przyda się w kroku 6).

### 4. Próba zakupu ponad pulę (2:30-3:00)

W terminalu:

```sh
./scripts/oversell.sh
```

Skrypt tworzy tymczasowe wydarzenie z pulą 5 biletów, wysyła 20 równoległych żądań rezerwacji od 20 użytkowników i sprawdza stan bazy zapytaniami SQL, a na końcu sam sprząta dane. Oczekiwany wynik: w sekcji `answers` kilka odpowiedzi `201` (nie więcej niż 5 biletów) i reszta `409` z kodem `TICKETS_SOLD_OUT` (brak miejsc) lub `RESERVATION_CONFLICT` (konflikt optimistic locking po wyczerpaniu prób), a na końcu `All checks passed`. Powiedz, że nigdy nie sprzedano więcej niż pula i że chroni to także `CHECK` w bazie.

Wariant z interfejsu, jeśli wolisz pokazać to w przeglądarce: w oknie uczestnika `anna@ticketflow.test` otwórz **Laravel Krakow Meetup** (pula 5 biletów) i nie klikaj jeszcze zakupu; w trzecim oknie prywatnym zaloguj się jako `adam@ticketflow.test`, kup 5 biletów (rezerwacja `PENDING` trzyma je 15 minut); wróć do okna Anny i kup 1 bilet na nieodświeżonej stronie. Dostaniesz błąd 409 `TICKETS_SOLD_OUT`. Wariant jest dłuższy, więc skrypt jest domyślnym wyborem.

### 5. Zwrot (3:00-3:25)

Okno uczestnika, **Moje zamówienia**, zamówienie na `Krakow Jazz Night` (wydarzenie za 14 dni, więc okno 48 godzin jest otwarte): **Zwróć bilety**, w oknie dialogowym potwierdź.

Oczekiwany wynik: komunikat „Zlecono zwrot biletów”, po chwili status zamówienia `REFUNDED`, zwrot `SUCCEEDED`, bilety unieważnione (`VOID`), dostępna faktura korygująca. W Horizonie widać listenery `OrderRefunded`: `RefundPayment`, `VoidTickets`, `ReleaseRefundedSeats`, `IssueCorrectionInvoice`, `UpdateSalesSummary`, `SendRefundEmail`; w Mailpit przychodzi `Zwrot za zamówienie: Krakow Jazz Night`.

Opcjonalnie (5 sekund): przy zamówieniu na `Demo Night` zamiast przycisku zwrotu jest informacja, że zwrot jest możliwy tylko do 48 godzin przed wydarzeniem (przez API to 409 `REFUND_WINDOW_CLOSED`).

### 6. Check-in dwa razy (3:25-4:00)

Okno organizatora (Olivia), **Check-in**:

1. Wybierz wydarzenie `Demo Night` (wydarzenia z dzisiejszą datą są na górze listy).
2. Wklej kod biletu skopiowany w kroku 3 w pole **Kod biletu** i kliknij **Sprawdź**. Oczekiwany wynik: zielony komunikat „Wejście zatwierdzone” z nazwą puli i imieniem posiadacza.
3. Wklej ten sam kod jeszcze raz. Oczekiwany wynik: czerwony komunikat „Odrzucono: Ten bilet został już wykorzystany” z godziną pierwszego skanu (API zwraca 409 `TICKET_ALREADY_USED`).

Wróć na chwilę do **Moje wydarzenia**, **Pule biletów** przy `Demo Night`: karta **Sprzedaż** pokazuje sprzedane bilety, przychód i liczbę check-inów (odświeżone listenerem `UpdateSalesSummary` na kolejce `stats`).

### 7. Symulacja ruchu w Horizonie (4:00-4:40)

W terminalu:

```sh
docker compose exec -T app php artisan ticketflow:simulate-traffic --orders=100
```

Komenda tworzy kupujących i składa zamówienia przez prawdziwe serwisy, a po wydrukowaniu tabeli wyników (`Payment started`, `TICKETS_SOLD_OUT` itd.) wraca natychmiast. Płatności przetwarza Horizon w tle. Od razu przełącz się na kartę Horizona: na **Dashboard** rośnie liczba jobów na minutę, a kolejka `payments` ma największy backlog, bo każda płatność trwa 1-3 sekundy i ma 2 procesy robocze. Pokaż **Metrics** i **Recent Jobs**; po kilkudziesięciu sekundach backlog maleje, a skrzynka Mailpit zapełnia się mailami.

Domyślne `--orders=500` jest dobre do dłuższego pokazu: opróżnienie kolejki płatności zajmuje wtedy kilka minut, co w nagraniu 3-5 minut jest zbyt długie. Przy `--orders=100` kolejka opróżnia się w około 1-2 minuty.

Po nagraniu wróć do czystego stanu procedurą z sekcji „Przygotowanie”, bo symulacja wyprzedaje część pul i tworzy użytkowników `traffic-…@traffic.ticketflow.test`.

## Co mówić o architekturze (jedno zdanie na element)

- Warstwy: kontroler, Form Request, serwis z regułami, model Eloquent (README, sekcja „Architektura”).
- Brak sprzedaży ponad pulę: optimistic locking (`version`) w jednym `UPDATE`, a w bazie `CHECK`.
- Zdarzenia domenowe i niezależne listenery na osobnych kolejkach (tabela w README).
- Uwierzytelnianie: cookie sesji Sanctum zamiast tokenów w JavaScripcie, trzy role, policies dla własności zasobów.
- Płatność jest symulowana za interfejsem `PaymentGateway`, a nieudana płatność zwalnia miejsca (kompensacja).
