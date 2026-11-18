@php
    $logo = 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('brand/logo-print.png')));
    /** @var \App\Models\Sale $sale  Factura SAR (Acuerdo 481-2017). Todo sale de snapshots. */
    $issuer = $sale->issuer_snapshot ?? [];
    $fiscal = $sale->fiscal_snapshot ?? [];
    $isVoided = $sale->status === \App\Models\Sale::STATUS_VOIDED;
    $copyLabels = ['original' => 'ORIGINAL: CLIENTE', 'copy' => 'COPIA: OBLIGADO TRIBUTARIO EMISOR'];
    $termsLabel = $sale->terms === 'credit' ? 'CRÉDITO' : 'CONTADO';
    $money = fn ($v) => 'L ' . number_format((float) $v, 2, '.', ',');
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    $deadline = !empty($fiscal['issue_deadline']) ? \Illuminate\Support\Carbon::parse($fiscal['issue_deadline'])->format('d/m/Y') : '';
    $blank = '____________________';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $sale->full_number }}</title>
    <style>
        /* CSS apto para dompdf: tablas, sin flex ni grid. */
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #1a1a1a; margin: 0; font-size: 11px; }
        .doc { padding: 16px 20px; page-break-after: always; position: relative; }
        .doc:last-child { page-break-after: auto; }
        table { border-collapse: collapse; width: 100%; }
        .copy-tag { text-align: right; font-size: 10px; font-weight: bold; }
        .head td { vertical-align: top; }
        .name { font-size: 16px; font-weight: bold; text-transform: uppercase; }
        .small { font-size: 9.5px; }
        .box { border: 1.5px solid #1a1a1a; padding: 6px 8px; }
        .title { font-size: 18px; font-weight: bold; letter-spacing: 2px; text-align: center; }
        .num { font-size: 13px; font-weight: bold; text-align: center; color: #b3261e; }
        .meta td { padding: 3px 4px; font-size: 10.5px; border: 1px solid #9a9a9a; }
        .meta .lbl { background: #eef3ea; font-weight: bold; width: 22%; }
        table.items { margin-top: 8px; }
        table.items th, table.items td { border: 1px solid #9a9a9a; padding: 4px 6px; font-size: 10.5px; }
        table.items th { background: #eef3ea; text-align: left; }
        .r { text-align: right; } .c { text-align: center; }
        .totals td { padding: 2px 6px; font-size: 10.5px; border: 1px solid #c9c9c9; }
        .totals .grand td { font-size: 14px; font-weight: bold; border: 1.5px solid #1a1a1a; }
        .words { margin-top: 6px; font-size: 10px; border: 1px solid #9a9a9a; padding: 4px 6px; }
        .legend { margin-top: 10px; text-align: center; font-weight: bold; font-size: 10px; }
        .voided-banner { border: 2px solid #b3261e; color: #b3261e; text-align: center;
                         font-weight: bold; padding: 4px; margin: 6px 0; font-size: 12px; }
        .watermark { position: absolute; top: 44%; left: 0; right: 0; text-align: center;
                     font-size: 72px; color: #b3261e; opacity: .18; font-weight: bold; }
    </style>
    @include('documents.print-style')
</head>
<body>
@foreach ($copies as $copy)
    <div class="doc">
        @if ($isVoided)<div class="watermark">ANULADA</div>@endif

        <table class="head">
            <tr>
                <td style="width:62%">
                    <table class="issuer-heading">
                        <tr>
                            <td class="logo-cell"><img class="print-logo" src="{{ $logo }}" alt="Frutas y Verduras El Rey"></td>
                            <td>
                                <div class="name">{{ $issuer['trade_name'] ?? '' }}</div>
                                @if (!empty($issuer['legal_name']))<div><strong>{{ $issuer['legal_name'] }}</strong></div>@endif
                                <div class="small">{{ $issuer['activity'] ?? '' }}</div>
                                <div class="small">{{ $issuer['address'] ?? '' }}</div>
                                <div class="small">
                                    @if (!empty($issuer['phone']))Tel. {{ $issuer['phone'] }}@endif
                                    @if (!empty($issuer['email'])) · {{ $issuer['email'] }}@endif
                                </div>
                                <div><strong>RTN: {{ $issuer['rtn'] ?? '' }}</strong></div>
                            </td>
                        </tr>
                    </table>
                </td>
                <td class="box" style="width:38%">
                    <div class="title">{{ $fiscal['title'] ?? 'FACTURA' }}</div>
                    <div class="num">Nº {{ $sale->full_number }}</div>
                    <div class="small" style="margin-top:4px"><strong>CAI:</strong> {{ $fiscal['cai'] ?? '' }}</div>
                    <div class="small"><strong>Rango autorizado:</strong> {{ $fiscal['range'] ?? '' }}</div>
                    <div class="small"><strong>Fecha límite de emisión:</strong> {{ $deadline }}</div>
                    <div class="copy-tag" style="margin-top:4px">{{ $copyLabels[$copy] ?? '' }}</div>
                </td>
            </tr>
        </table>

        <table class="meta" style="margin-top:8px">
            <tr>
                <td class="lbl">Fecha de emisión</td><td>{{ $sale->issued_at->timezone('America/Tegucigalpa')->format('d/m/Y h:i a') }}</td>
                <td class="lbl">Condición</td><td>{{ $termsLabel }}</td>
            </tr>
            <tr>
                <td class="lbl">Cliente</td><td>{{ $sale->customer_name_snapshot }}</td>
                <td class="lbl">RTN / Identidad</td><td>{{ $sale->customer_rtn_snapshot ?: '—' }}</td>
            </tr>
            <tr>
                <td class="lbl">Nº Orden de compra exenta</td><td>{{ $fiscal['exempt_purchase_order'] ?? '' ?: $blank }}</td>
                <td class="lbl">Nº Constancia registro exonerado</td><td>{{ $fiscal['exonerated_record'] ?? '' ?: $blank }}</td>
            </tr>
            <tr>
                <td class="lbl">Nº Registro SAG</td><td>{{ $fiscal['sag_record'] ?? '' ?: $blank }}</td>
                <td class="lbl">Documento de origen</td><td>{{ $sale->sourceSale?->full_number ?? '—' }}</td>
            </tr>
        </table>

        @if ($isVoided)
            <div class="voided-banner">
                FACTURA ANULADA — {{ $sale->voided_at?->timezone('America/Tegucigalpa')->format('d/m/Y h:i a') }}<br>
                Motivo: {{ $sale->void_reason }}
            </div>
        @endif

        <table class="items">
            <thead>
                <tr>
                    <th class="c" style="width:10%">Cant.</th>
                    <th style="width:14%">Unidad</th>
                    <th>Descripción</th>
                    <th class="c" style="width:9%">ISV</th>
                    <th class="r" style="width:15%">Precio unit.</th>
                    <th class="r" style="width:16%">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sale->items as $item)
                    <tr>
                        <td class="c">{{ $qty($item->quantity) }}</td>
                        <td>{{ $item->unit_snapshot }}</td>
                        <td>{{ $item->description_snapshot }}</td>
                        <td class="c">{{ ['exempt' => 'E', 'g15' => '15%', 'g18' => '18%'][$item->tax_type_snapshot] ?? '' }}</td>
                        <td class="r">{{ $money($item->unit_price) }}</td>
                        <td class="r">{{ $money($item->line_total) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table style="margin-top:8px">
            <tr>
                <td style="width:55%; vertical-align:top" class="small">
                    E = exento de ISV.
                    @if ($sale->notes)<br>Notas: {{ $sale->notes }}@endif
                </td>
                <td style="width:45%">
                    <table class="totals">
                        <tr><td>Importe exento</td><td class="r">{{ $money($sale->subtotal_exempt) }}</td></tr>
                        <tr><td>Importe exonerado</td><td class="r">{{ $money($sale->subtotal_exonerated) }}</td></tr>
                        <tr><td>Importe gravado 15%</td><td class="r">{{ $money($sale->subtotal_g15) }}</td></tr>
                        <tr><td>Importe gravado 18%</td><td class="r">{{ $money($sale->subtotal_g18) }}</td></tr>
                        <tr><td>ISV 15%</td><td class="r">{{ $money($sale->tax_15) }}</td></tr>
                        <tr><td>ISV 18%</td><td class="r">{{ $money($sale->tax_18) }}</td></tr>
                        <tr class="grand"><td>TOTAL A PAGAR</td><td class="r">{{ $money($sale->total) }}</td></tr>
                    </table>
                </td>
            </tr>
        </table>

        <div class="words"><strong>Valor en letras:</strong> {{ \App\Support\AmountInWords::lempiras((string) $sale->total) }}</div>

        <div class="legend">LA FACTURA ES BENEFICIO DE TODOS. "EXÍJALA"</div>
    </div>
@endforeach
</body>
</html>
