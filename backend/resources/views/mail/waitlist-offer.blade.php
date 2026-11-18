<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <title>Zwolniły się miejsca</title>
</head>
<body style="font-family: Arial, sans-serif; font-size: 14px; color: #1a1a1a;">
    <p>Dzień dobry {{ $buyer->name }},</p>

    <p>na liście oczekujących przyszła Twoja kolej. Trzymamy dla Ciebie miejsca w puli <strong>{{ $ticketTypeName }}</strong> (liczba biletów: {{ $quantity }}).</p>

    <p>
        <strong>{{ $event->title }}</strong><br>
        {{ $event->venue }}, {{ $event->city }}<br>
        {{ $startsAt->format('d.m.Y H:i') }} ({{ $startsAt->getTimezone()->getName() }})
    </p>

    <p>Oferta jest ważna do <strong>{{ $offeredUntil->format('d.m.Y H:i') }}</strong> ({{ $offeredUntil->getTimezone()->getName() }}). Po tym czasie miejsca przejdą do następnej osoby w kolejce.</p>

    <p>Przyjmij ofertę na liście oczekujących w TicketFlow: <a href="{{ $waitlistUrl }}">{{ $waitlistUrl }}</a>. Po przyjęciu masz 15 minut na opłacenie zamówienia.</p>

    <p>Zespół TicketFlow</p>
</body>
</html>
