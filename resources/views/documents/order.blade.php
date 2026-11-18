@php
    $logo = 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('brand/logo-print.png')));
    /** @var \App\Models\Sale $sale */
    $issuer = $sale->issuer_snapshot ?? [];
    $isVoided = $sale->status === \App\Models\Sale::STATUS_VOIDED;
    $copyLabels = ['original' => 'ORIGINAL – CLIENTE', 'copy' => 'COPIA – EMISOR'];
    $termsLabel = $sale->terms === 'credit' ? 'CRÉDITO' : 'CONTADO';
    $money = fn ($v) => 'L ' . number_format((float) $v, 2, '.', ',');
    $hasTax = ((float) $sale->tax_15 > 0) || ((float) $sale->tax_18 > 0)
        || ((float) $sale->subtotal_g15 > 0) || ((float) $sale->subtotal_g18 > 0);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $sale->full_number }}</title>
    <style>
        /* CSS apto para dompdf: tablas, sin flex ni grid. */
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #1F2A1C; margin: 0; font-size: 12px; }
        .doc { padding: 18px 22px; page-break-after: always; position: relative; }
        .doc:last-child { page-break-after: auto; }
        .copy-tag { text-align: right; font-size: 10px; font-weight: bold; letter-spacing: .5px; color: #2E7D32; }
        .issuer { text-align: center; border-bottom: 2px solid #1F2A1C; padding-bottom: 6px; margin-bottom: 8px; }
        .issuer .name { font-size: 18px; font-weight: bold; text-transform: uppercase; }
        .issuer .line { font-size: 10px; }
        .meta { width: 100%; margin: 8px 0; border-collapse: collapse; }
        .meta td { font-size: 11px; padding: 2px 0; vertical-align: top; }
        .meta .num { text-align: right; font-weight: bold; color: #C62828; font-size: 13px; }
        .title { text-align: center; font-size: 15px; font-weight: bold; letter-spacing: 1px;
                 border: 1px solid #1F2A1C; padding: 3px; margin: 6px 0; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.items th, table.items td { border: 1px solid #9aa89a; padding: 4px 6px; font-size: 11px; }
        table.items th { background: #E8F0E3; text-align: left; }
        .r { text-align: right; }
        .c { text-align: center; }
        .totals { width: 100%; margin-top: 8px; border-collapse: collapse; }
        .totals td { padding: 2px 6px; font-size: 11px; }
        .totals .grand td { font-size: 16px; font-weight: bold; border-top: 2px solid #1F2A1C; }
        .foot { margin-top: 10px; font-size: 9px; color: #5C534B; text-align: center; }
        .voided-banner { border: 2px solid #C62828; color: #C62828; text-align: center;
                         font-weight: bold; padding: 4px; margin: 6px 0; font-size: 13px; }
        .watermark { position: absolute; top: 44%; left: 0; right: 0; text-align: center;
                     font-size: 72px; color: #C62828; opacity: .18; font-weight: bold; }
    </style>
    @include('documents.print-style')
</head>
<body>
@foreach ($copies as $copy)
    <div class="doc">
        @if ($isVoided)
            <div class="watermark">ANULADA</div>
        @endif

        <div class="copy-tag">{{ $copyLabels[$copy] ?? '' }}</div>

        <div class="issuer">
            <table class="issuer-heading">
                <tr>
                    <td class="logo-cell"><img class="print-logo" src="{{ $logo }}" alt="Frutas y Verduras El Rey"></td>
                    <td>
                        <div class="name">{{ $issuer['trade_name'] ?? 'Frutas y Verduras "El Rey"' }}</div>
                        @if (!empty($issuer['activity']))<div class="line">{{ $issuer['activity'] }}</div>@endif
                        @if (!empty($issuer['address']))<div class="line">{{ $issuer['address'] }}</div>@endif
                        <div class="line">
                            @if (!empty($issuer['phone']))Tel. {{ $issuer['phone'] }}@endif
                            @if (!empty($issuer['rtn'])) · RTN {{ $issuer['rtn'] }}@endif
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <table class="meta">
            <tr>
                <td>SEÑOR(A): <strong>{{ $sale->customer_name_snapshot }}</strong></td>
                <td class="num">Nº {{ $sale->full_number }}</td>
            </tr>
            <tr>
                <td>
                    FECHA: {{ $sale->issued_at->timezone('America/Tegucigalpa')->format('d/m/Y h:i a') }}
                    @if ($sale->customer_rtn_snapshot) · R.T.N. {{ $sale->customer_rtn_snapshot }}@endif
                </td>
                <td class="r">CONDICIÓN: <strong>{{ $termsLabel }}</strong></td>
            </tr>
        </table>

        <div class="title">ORDEN DE PEDIDO</div>

        @if ($sale->status === 'converted')
            <div class="voided-banner" style="border-color:#1E5B3A;color:#1E5B3A">PEDIDO YA FACTURADO</div>
        @endif
        @if ($isVoided)
            <div class="voided-banner">
                DOCUMENTO ANULADO — {{ $sale->voided_at?->timezone('America/Tegucigalpa')->format('d/m/Y h:i a') }}<br>
                Motivo: {{ $sale->void_reason }}
            </div>
        @endif

        <table class="items">
            <thead>
                <tr>
                    <th class="c" style="width:12%">Cant.</th>
                    <th style="width:18%">Unidad</th>
                    <th>Descripción</th>
                    <th class="r" style="width:16%">Precio</th>
                    <th class="r" style="width:18%">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sale->items as $item)
                    <tr>
                        <td class="c">{{ rtrim(rtrim(number_format((float) $item->quantity, 2, '.', ''), '0'), '.') }}</td>
                        <td>{{ $item->unit_snapshot }}</td>
                        <td>{{ $item->description_snapshot }}</td>
                        <td class="r">{{ $money($item->unit_price) }}</td>
                        <td class="r">{{ $money($item->line_total) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="totals">
            @if ($hasTax)
                <tr><td class="r">Subtotal exento</td><td class="r">{{ $money($sale->subtotal_exempt) }}</td></tr>
                @if ((float) $sale->subtotal_g15 > 0)
                    <tr><td class="r">Gravado 15%</td><td class="r">{{ $money($sale->subtotal_g15) }}</td></tr>
                    <tr><td class="r">ISV 15%</td><td class="r">{{ $money($sale->tax_15) }}</td></tr>
                @endif
                @if ((float) $sale->subtotal_g18 > 0)
                    <tr><td class="r">Gravado 18%</td><td class="r">{{ $money($sale->subtotal_g18) }}</td></tr>
                    <tr><td class="r">ISV 18%</td><td class="r">{{ $money($sale->tax_18) }}</td></tr>
                @endif
            @endif
            <tr class="grand"><td class="r">TOTAL</td><td class="r">{{ $money($sale->total) }}</td></tr>
        </table>

        <div class="foot">
            Documento interno de venta. No es un comprobante fiscal (sin CAI).
        </div>
    </div>
@endforeach
</body>
</html>
