<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <title>Płatność nieudana</title>
</head>
<body style="font-family: Arial, sans-serif; font-size: 14px; color: #1a1a1a;">
    <p>Dzień dobry {{ $buyer->name }},</p>

    <p>płatność za zamówienie nr {{ $orderId }} na wydarzenie <strong>{{ $event->title }}</strong> nie powiodła się, więc zamówienie nie zostało opłacone.</p>

    <p>Kwota zamówienia: {{ $total }}</p>

    <p>Zarezerwowane miejsca wróciły do puli. Jeśli nadal chcesz wziąć udział w wydarzeniu, złóż nowe zamówienie w TicketFlow.</p>

    <p>Zespół TicketFlow</p>
</body>
</html>
