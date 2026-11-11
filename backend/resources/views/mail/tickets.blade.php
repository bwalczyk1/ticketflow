<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <title>Twoje bilety</title>
</head>
<body style="font-family: Arial, sans-serif; font-size: 14px; color: #1a1a1a;">
    <p>Dzień dobry {{ $buyer->name }},</p>

    <p>dziękujemy za zakup. W załącznikach znajdziesz bilety w formacie PDF (liczba biletów: {{ $ticketCount }}).</p>

    <p>
        <strong>{{ $event->title }}</strong><br>
        {{ $event->venue }}, {{ $event->city }}<br>
        {{ $startsAt->format('d.m.Y H:i') }} ({{ $startsAt->getTimezone()->getName() }})
    </p>

    <p>Każdy bilet ma własny kod QR, który zeskanujemy przy wejściu. Kod można wykorzystać tylko raz.</p>

    <p>Do zobaczenia!<br>Zespół TicketFlow</p>
</body>
</html>
