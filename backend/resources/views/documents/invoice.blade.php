<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <title>{{ $isCorrection ? 'Faktura korygująca' : 'Faktura' }} {{ $invoice->number }}/{{ $invoice->year }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1a1a1a; }
        h1 { font-size: 20px; margin: 0 0 6px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th { text-align: left; background: #eeeeee; padding: 6px 4px; }
        td { padding: 6px 4px; border-bottom: 1px solid #dddddd; }
        .right { text-align: right; }
        .total td { font-weight: bold; border-bottom: none; }
    </style>
</head>
<body>
    <h1>{{ $isCorrection ? 'Faktura korygująca' : 'Faktura' }} nr {{ $invoice->number }}/{{ $invoice->year }}</h1>

    @if ($isCorrection)
        <p>do faktury nr {{ $invoice->corrects->number }}/{{ $invoice->corrects->year }}</p>
        <p>Powód: {{ $invoice->refund->reason }}</p>
    @endif

    <p>
        Nabywca: {{ $buyer->name }}<br>
        E-mail: {{ $buyer->email }}
    </p>

    <p>Wydarzenie: {{ $order->event->title }}</p>

    <table>
        <thead>
            <tr>
                <th>Pozycja</th>
                <th class="right">Ilość</th>
                <th class="right">Cena jedn.</th>
                <th class="right">Wartość</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $item)
                <tr>
                    <td>{{ $item->ticketType->name }}</td>
                    <td class="right">{{ $item->quantity }}</td>
                    <td class="right">{{ \App\Services\Documents\Pln::format($item->unit_price) }}</td>
                    <td class="right">{{ \App\Services\Documents\Pln::format($item->quantity * $item->unit_price) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="3" class="right">{{ $isCorrection ? 'Do zwrotu' : 'Razem do zapłaty' }}</td>
                <td class="right">{{ \App\Services\Documents\Pln::format($total) }}</td>
            </tr>
        </tbody>
    </table>
</body>
</html>
