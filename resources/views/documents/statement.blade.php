@php
    $logo = 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('brand/logo-print.png')));
    $money = fn ($v) => 'L ' . number_format((float) $v, 2, '.', ',');
    $d = fn ($v) => \Illuminate\Support\Carbon::parse($v)->format('d/m/Y');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Estado de cuenta — {{ $customer->name }}</title>
    <style>
        body { font-family: 'DejaVu Sans', Arial, sans-serif; color: #1b2320; margin: 0; padding: 24px; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #b8c4bd; padding: 5px 7px; }
        th { background: #e3ece7; text-align: left; }
        .r { text-align: right; }
        h1 { font-size: 20px; margin: 0; }
        .head td { border: 0; vertical-align: top; padding: 0; }
        .big { font-size: 22px; font-weight: bold; }
        .bar { margin: 0 0 16px; }
        .bar button { font-size: 16px; padding: 10px 18px; margin-right: 8px; cursor: pointer; }
        @media print { .bar { display: none; } body { padding: 0; } }
    </style>
    @include('documents.print-style', ['print_settings' => $issuer?->print_settings ?? []])
</head>
<body class="statement">
<div class="bar">
    <button onclick="window.print()">Imprimir</button>
    <button onclick="window.close()">Cerrar</button>
</div>
<table class="head">
    <tr>
        <td>
            <table class="issuer-heading">
                <tr>
                    <td class="logo-cell"><img class="print-logo" src="{{ $logo }}" alt="Frutas y Verduras El Rey"></td>
                    <td>
                        <h1>{{ $issuer?->trade_name }}</h1>
                        <div>{{ $issuer?->address }}</div>
                        <div>Tel. {{ $issuer?->phone }} @if($issuer?->rtn) · RTN {{ $issuer->rtn }} @endif</div>
                    </td>
                </tr>
            </table>
        </td>
        <td class="r">
            <div class="big">ESTADO DE CUENTA</div>
            <div>Al {{ $today->format('d/m/Y') }}</div>
        </td>
    </tr>
</table>
<p style="margin:16px 0 8px"><strong>Cliente:</strong> {{ $customer->name }} @if($customer->rtn) · RTN {{ $customer->rtn }} @endif @if($customer->phone) · Tel. {{ $customer->phone }} @endif</p>
@if ($from)<p>Movimientos del {{ $d($from) }} al {{ $d($to) }}.</p>@endif

<table>
    <thead><tr><th>Fecha</th><th>Detalle</th><th class="r">Cargo</th><th class="r">Abono</th><th class="r">Saldo</th></tr></thead>
    <tbody>
    @forelse ($ledger as $r)
        <tr>
            <td>{{ $d($r['date']) }}</td>
            <td>{{ $r['kind'] }} {{ $r['description'] }}</td>
            <td class="r">{{ (float) $r['debit'] ? $money($r['debit']) : '' }}</td>
            <td class="r">{{ (float) $r['credit'] ? $money($r['credit']) : '' }}</td>
            <td class="r">{{ $money($r['balance']) }}</td>
        </tr>
    @empty
        <tr><td colspan="5">Sin movimientos.</td></tr>
    @endforelse
    </tbody>
</table>

<table style="margin-top:14px; width:55%; margin-left:auto">
    <tr><td>De 0 a 30 días</td><td class="r">{{ $money($open['aging']['0_30']) }}</td></tr>
    <tr><td>De 31 a 60 días</td><td class="r">{{ $money($open['aging']['31_60']) }}</td></tr>
    <tr><td>De 61 a 90 días</td><td class="r">{{ $money($open['aging']['61_90']) }}</td></tr>
    <tr><td>Más de 90 días</td><td class="r">{{ $money($open['aging']['90_plus']) }}</td></tr>
    <tr><th>{{ (float) $balance < 0 ? 'SALDO A FAVOR' : 'TOTAL QUE DEBE' }}</th><th class="r big">{{ $money(abs((float) $balance)) }}</th></tr>
</table>
<p style="margin-top:24px; font-size:11px; color:#52615a">Documento informativo. No es un comprobante fiscal.</p>
</body>
</html>
