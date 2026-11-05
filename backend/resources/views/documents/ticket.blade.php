<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <title>Bilet {{ $ticket->code }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #1a1a1a; }
        h1 { font-size: 20px; margin: 0 0 4px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 6px 4px; border-bottom: 1px solid #dddddd; vertical-align: top; }
        td.label { width: 32%; color: #666666; }
        .code { font-size: 9px; color: #444444; text-align: center; }
    </style>
</head>
<body>
    <h1>{{ $event->title }}</h1>
    <p>Bilet wstępu</p>

    <table>
        <tr><td class="label">Miejsce</td><td>{{ $event->venue }}, {{ $event->city }}</td></tr>
        <tr>
            <td class="label">Początek</td>
            <td>{{ $startsAt->format('d.m.Y H:i') }} ({{ $startsAt->getTimezone()->getName() }})</td>
        </tr>
        <tr><td class="label">Rodzaj biletu</td><td>{{ $ticketType->name }}</td></tr>
        <tr><td class="label">Właściciel</td><td>{{ $holder->name }}</td></tr>
    </table>

    <p style="text-align: center; margin-top: 24px;">
        <img src="{{ $qrCode }}" width="180" height="180" alt="QR">
    </p>
    <p class="code">{{ $ticket->code }}</p>
</body>
</html>
