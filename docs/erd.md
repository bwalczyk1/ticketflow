# Diagram ERD

Tabele frameworka (`sessions`, `cache`, `jobs`, `personal_access_tokens` itd.) są pominięte.
Kwoty są przechowywane jako liczby całkowite w groszach. Wszystkie daty to `DATETIME` w UTC.
Statusy i role są zabezpieczone ograniczeniami `CHECK` w bazie; opis w cudzysłowie przy kolumnie to dozwolone wartości.

```mermaid
erDiagram
    users ||--o{ events : organizes
    users ||--o{ orders : places
    users ||--o{ waitlist_entries : joins
    events ||--o{ ticket_types : offers
    events ||--o{ orders : "sold through"
    events ||--o| event_sales_summaries : "summarized by"
    ticket_types ||--o{ order_items : "ordered as"
    ticket_types ||--o{ waitlist_entries : "waited for"
    orders ||--o| waitlist_entries : "created from offer"
    orders ||--|{ order_items : contains
    orders ||--o| payments : "paid by"
    orders ||--o| refunds : "refunded by"
    orders ||--o{ invoices : "billed by"
    order_items ||--o{ tickets : issues
    refunds ||--o| invoices : "corrected by"
    invoices ||--o| invoices : corrects

    users {
        bigint id PK
        varchar name
        varchar email UK
        datetime email_verified_at
        varchar password
        varchar role "ADMIN, ORGANIZER, ATTENDEE"
        varchar remember_token
        datetime created_at
        datetime updated_at
    }

    events {
        bigint id PK
        bigint organizer_id FK
        varchar title
        text description
        varchar venue
        varchar city
        varchar category
        varchar timezone "IANA name, default Europe/Warsaw"
        datetime starts_at
        datetime ends_at "CHECK ends_at > starts_at"
        datetime reminder_sent_at
        varchar status "DRAFT, PUBLISHED, FINISHED, CANCELLED"
        datetime created_at
        datetime updated_at
    }

    ticket_types {
        bigint id PK
        bigint event_id FK "UK with name"
        varchar name
        int price
        int quantity "CHECK sold + held <= quantity"
        int sold "CHECK sold >= 0"
        int held "CHECK held >= 0"
        int version "optimistic locking"
        datetime created_at
        datetime updated_at
    }

    orders {
        bigint id PK
        bigint user_id FK
        bigint event_id FK
        varchar status "PENDING, PAID, EXPIRED, FAILED, CANCELLED, REFUNDED"
        datetime expires_at
        datetime seats_released_at
        int total
        datetime created_at
        datetime updated_at
    }

    order_items {
        bigint id PK
        bigint order_id FK "UK with ticket_type_id"
        bigint ticket_type_id FK
        int quantity "CHECK quantity > 0"
        int unit_price
        datetime created_at
        datetime updated_at
    }

    payments {
        bigint id PK
        bigint order_id FK,UK
        varchar status "PENDING, SUCCEEDED, FAILED"
        int amount
        varchar provider_ref
        datetime created_at
        datetime updated_at
    }

    tickets {
        bigint id PK
        bigint order_item_id FK "UK with position"
        int position
        char code UK "UUID"
        varchar status "VALID, USED, VOID"
        datetime checked_in_at
        varchar pdf_path
        datetime created_at
        datetime updated_at
    }

    refunds {
        bigint id PK
        bigint order_id FK,UK
        int amount
        varchar reason "nullable, up to 500 characters"
        varchar status "PENDING, SUCCEEDED, FAILED"
        datetime created_at
        datetime updated_at
    }

    invoices {
        bigint id PK
        bigint order_id FK "UK with type and refund_key"
        bigint refund_id FK
        bigint corrects_id FK
        varchar type "INVOICE, CORRECTION; UK with year and number"
        smallint year
        int number
        varchar pdf_path
        bigint refund_key "generated: COALESCE(refund_id, 0)"
        datetime created_at
        datetime updated_at
    }

    invoice_sequences {
        smallint year PK
        varchar type PK
        int last_number
    }

    waitlist_entries {
        bigint id PK
        bigint user_id FK "UK with ticket_type_id and active_key"
        bigint ticket_type_id FK
        int quantity
        varchar status "WAITING, OFFERED, CONVERTED, EXPIRED, CANCELLED"
        datetime offered_until
        bigint order_id FK "order created from the accepted offer"
        tinyint active_key "generated: 1 while active, otherwise NULL"
        datetime created_at
        datetime updated_at
    }

    event_sales_summaries {
        bigint event_id PK,FK
        int tickets_sold
        bigint revenue
        bigint refunds
        int checked_in
        datetime created_at
        datetime updated_at
    }

    sent_notifications {
        bigint id PK
        varchar key UK "for example order:12:tickets"
        datetime sent_at
    }
```

`invoice_sequences` nie ma kluczy obcych: to licznik numeracji dla `invoices.number`, jeden wiersz na rok i typ.

## Reguły usuwania

| Klucz obcy | ON DELETE | Dlaczego |
|---|---|---|
| `events.organizer_id`, `orders.user_id`, `orders.event_id`, `waitlist_entries.user_id`, `waitlist_entries.order_id` | `RESTRICT` | historia sprzedaży zostaje |
| `order_items.ticket_type_id`, `tickets.order_item_id` | `RESTRICT` | sprzedane bilety zostają |
| `payments.order_id`, `refunds.order_id`, `invoices.*` | `RESTRICT` | dokumenty finansowe zostają |
| `ticket_types.event_id` | `CASCADE` | wydarzenie bez sprzedaży można usunąć razem z pulami |
| `order_items.order_id` | `CASCADE` | pozycje zamówienia bez biletów znikają razem z nim |
| `waitlist_entries.ticket_type_id`, `event_sales_summaries.event_id` | `CASCADE` | dane pochodne, bez wartości historycznej |
