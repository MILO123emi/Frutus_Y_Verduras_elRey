<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Caché de PDFs de documentos (§7.2)
    |--------------------------------------------------------------------------
    |
    | Directorio base donde se guardan los PDF generados, organizados por
    | print-v3/{paper}/{yyyy}/{mm}/{full_number}_{variant}.pdf. La caché es DERIVADA: la fuente
    | de verdad es la BD con sus snapshots, así que se puede borrar y regenerar.
    | En pruebas se apunta a un directorio temporal para aislarla.
    |
    */

    'cache_path' => storage_path('app/documents'),

];
