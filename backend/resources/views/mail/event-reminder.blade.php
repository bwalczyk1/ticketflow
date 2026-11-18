<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <title>Przypomnienie o wydarzeniu</title>
</head>
<body style="font-family: Arial, sans-serif; font-size: 14px; color: #1a1a1a;">
    <p>Dzień dobry {{ $attendee->name }},</p>

    <p>przypominamy, że już jutro odbywa się wydarzenie, na które masz bilety (liczba biletów: {{ $ticketCount }}).</p>

    <p>
        <strong>{{ $event->title }}</strong><br>
        {{ $event->venue }}, {{ $event->city }}<br>
        {{ $startsAt->format('d.m.Y H:i') }} ({{ $startsAt->getTimezone()->getName() }})
    </p>

    <p>Zabierz ze sobą bilety z kodami QR, wydrukowane albo w telefonie. Znajdziesz je w TicketFlow: <a href="{{ $ticketsUrl }}">{{ $ticketsUrl }}</a>.</p>

    <p>Do zobaczenia!<br>Zespół TicketFlow</p>
</body>
</html>
