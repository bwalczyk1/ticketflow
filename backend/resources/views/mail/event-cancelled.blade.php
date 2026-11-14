<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <title>Wydarzenie odwołane</title>
</head>
<body style="font-family: Arial, sans-serif; font-size: 14px; color: #1a1a1a;">
    <p>Dzień dobry {{ $attendee->name }},</p>

    <p>
        z przykrością informujemy, że wydarzenie <strong>{{ $event->title }}</strong>
        zaplanowane na {{ $startsAt->format('d.m.Y H:i') }} ({{ $startsAt->getTimezone()->getName() }}) zostało odwołane przez organizatora.
    </p>

    <p>Twoje bilety tracą ważność, a pieniądze są zwracane tą samą metodą płatności. Kwota zwrotu: {{ $amount }}</p>

    <p>Osobno dostaniesz potwierdzenie zwrotu dla każdego zamówienia, a fakturę korygującą pobierzesz w szczegółach zamówienia w TicketFlow.</p>

    <p>Zespół TicketFlow</p>
</body>
</html>
