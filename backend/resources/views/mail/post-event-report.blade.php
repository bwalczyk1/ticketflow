<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <title>Podsumowanie wydarzenia</title>
</head>
<body style="font-family: Arial, sans-serif; font-size: 14px; color: #1a1a1a;">
    <p>Dzień dobry {{ $organizer->name }},</p>

    <p>wydarzenie <strong>{{ $event->title }}</strong> z dnia {{ $startsAt->format('d.m.Y H:i') }} ({{ $startsAt->getTimezone()->getName() }}) dobiegło końca. Oto jego podsumowanie:</p>

    <p>
        Sprzedane bilety: {{ $ticketsSold }}<br>
        Przychód: {{ $revenue }}<br>
        Zwroty: {{ $refunds }}<br>
        Wejścia: {{ $checkedIn }} z {{ $ticketsSold }}
    </p>

    <p>Szczegóły sprzedaży znajdziesz w panelu organizatora.</p>

    <p>Dziękujemy za organizację wydarzenia w TicketFlow!<br>Zespół TicketFlow</p>
</body>
</html>
