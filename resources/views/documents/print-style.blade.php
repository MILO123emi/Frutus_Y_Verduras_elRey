@php
    $halfLetter = ($print_settings['paper_size'] ?? 'letter') === 'half-letter';
@endphp
<style>
    @page { size: {{ $halfLetter ? '8.5in 5.5in' : '8.5in 11in' }}; margin: 12pt; }
    table { table-layout: fixed; }
    td, th, .words { word-wrap: break-word; }
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
    .words, .legend, .foot { page-break-inside: avoid; }
    .issuer-heading { width: 100%; border-collapse: collapse; table-layout: auto; }
    .issuer-heading td { border: 0; padding: 0; vertical-align: top; }
    .issuer-heading .logo-cell { width: 25mm; text-align: left; }
    .print-logo { display: block; width: 22mm; height: 22mm; }
    @if ($halfLetter)
        .issuer-heading .logo-cell { width: 19mm; }
        .print-logo { width: 16mm; height: 16mm; }
        .statement .print-logo { width: 13mm; height: 13mm; }
        .statement .issuer-heading .logo-cell { width: 16mm; }
        body { font-size: 10px; line-height: 1.15; }
        .doc { padding: 6px 8px; }
        .name, .issuer .name { font-size: 14px; }
        .small, .issuer .line, .copy-tag { font-size: 9px; }
        .title { font-size: 14px; letter-spacing: 1px; }
        .meta td, table.items td, table.items th, .totals td { font-size: 9px; padding: 2px 4px; }
        .totals .grand td { font-size: 12px; }
        .words, .legend { font-size: 9px; margin-top: 4px; }
    @endif
</style>
