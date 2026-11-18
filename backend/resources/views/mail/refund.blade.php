<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <title>Zwrot za zamówienie</title>
</head>
<body style="font-family: Arial, sans-serif; font-size: 14px; color: #1a1a1a;">
    <p>Dzień dobry {{ $buyer->name }},</p>

    <p>przyjęliśmy zwrot zamówienia nr {{ $orderId }} na wydarzenie <strong>{{ $event->title }}</strong>.</p>

    <p>Kwota zwrotu: {{ $amount }}</p>

    @if ($reason !== null)
        <p>Powód: {{ $reason }}</p>
    @endif

    <p>Bilety z tego zamówienia zostały unieważnione. Pieniądze wrócą tą samą metodą płatności, a fakturę korygującą pobierzesz w szczegółach zamówienia w TicketFlow.</p>

    <p>Zespół TicketFlow</p>
</body>
</html>
