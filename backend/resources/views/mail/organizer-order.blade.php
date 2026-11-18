<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <title>Nowe zamówienie</title>
</head>
<body style="font-family: Arial, sans-serif; font-size: 14px; color: #1a1a1a;">
    <p>Dzień dobry {{ $organizer->name }},</p>

    <p>na wydarzenie <strong>{{ $event->title }}</strong> wpłynęło nowe opłacone zamówienie nr {{ $orderId }}.</p>

    <p>
        Liczba biletów: {{ $ticketCount }}<br>
        Kwota: {{ $total }}
    </p>

    <p>Aktualne podsumowanie sprzedaży znajdziesz w panelu organizatora.</p>

    <p>Zespół TicketFlow</p>
</body>
</html>
