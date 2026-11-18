# Frutas y Verduras El Rey

Lee primero `instrucciones/CONTEXTO.md` (estado vivo del proyecto y traspaso entre chats).
Fuente de verdad: prompt de modo autónomo del 2026-11-17 y `docs/DECISIONES.md`.
El kickoff `instrucciones/00-kickoff-sistema-el-rey.md` queda como historia.
Sistema local para La Ceiba: pedidos, facturas SAR, cuentas por cobrar, fletes, reportes y respaldos.

## Cómo trabajar

- Claude planifica y escribe el prompt en `docs/codex-prompts.md` → Farid lo pega en Codex
  (extensión de VS Code) → Codex escribe el código → Claude revisa el diff, ejecuta
  `php artisan test` y `npm run build`, y actualiza `instrucciones/CONTEXTO.md`.
- Claude NO ejecuta Codex. No se exige confirmación entre las partes del kickoff histórico.
- Cambios quirúrgicos; respeta el alcance del prompt. No agregues dependencias sin que lo pida.
- UI en español sencillo; código, tablas y columnas en inglés. Conserva nombres de rutas existentes.
- Tests con Pest para lógica crítica. No ejecutes migraciones destructivas sobre datos reales.

## Reglas de negocio no negociables

1. Nunca borrar ventas, sus líneas ni abonos: anular con motivo, usuario y fecha; sin soft delete
   ni borrados en cascada sobre `sales`/`sale_items`.
2. Numeración correlativa por serie, sin huecos ni duplicados: `SELECT … FOR UPDATE` dentro de
   transacción y `unique(document_series_id, number)`. Un número anulado no se reutiliza.
3. Documento emitido inmutable en líneas, montos, cliente y fecha; para corregir, anular y emitir otro.
   Solo se permiten las transiciones controladas de anulación/conversión y el contador de impresión.
4. Guardar snapshots del emisor, cliente, líneas e información fiscal; los documentos históricos
   se renderizan con sus datos originales.
5. Hay factura SAR: cada CAI es una `document_series` con `kind=invoice`, formato
   `000-001-01-00000001`; bloquear series agotadas o vencidas y elegir la siguiente utilizable.
   Avisar con ≤ 50 números o ≤ 15 días (configurable); exigir nombre y RTN/identidad desde
   L 10,000. Plantilla `resources/views/documents/invoice.blade.php`.
   La orden de pedido OP sigue sin CAI y nunca debe decir «FACTURA».
6. Dinero sin `float`: BD `numeric(12,2)` y `App\Support\Money` con bcmath;
   total de línea = cantidad × precio, redondeado half-up a dos decimales.
7. Productos exentos por defecto; `tax_type` (`exempt|g15|g18`) se copia a cada línea.
   Guardar subtotales separados, incluido exonerado cuando corresponda.
8. Usar `Sale::issued()` para totales: anuladas y pedidos `converted` no suman.
   Conservarlos en los listados y reportes identificados con su estado.
9. Fechas en `America/Tegucigalpa`, rangos inclusivos y semana desde el lunes.
10. UI para personas poco técnicas: botones grandes con ícono y texto, pocos pasos, teclado
    y confirmación en acciones importantes; sin efectos pesados como `backdrop-filter`.

## Stack y arquitectura

- Laravel 13 / PHP 8.4, Inertia 2, Vue 3, Vuetify 4, Vite y PostgreSQL 17 con `unaccent`.
  Recursos locales sin CDN; sin Tailwind ni trabajador de colas en v1.
- Login por `username`, sin registro público; roles `admin`, `seller`, `accountant` mediante
  middleware `role:`. El contador solo lee; las anulaciones de venta requieren PIN de admin.
- `DocumentNumberService::assign()`: numeración transaccional. `SaleService::issue()/void()`:
  snapshots, cálculos y auditoría. `SaleService::convertToInvoice()` enlaza pedido y factura;
  el pedido queda `converted`, sin duplicar totales ni deuda.
- `Sale` protege inmutabilidad (`ImmutableSaleException`). `AccountsService`: libro derivado,
  abonos FIFO, antigüedad y saldos anteriores; movimientos anulables, nunca borrados.
- `FreightService`: viajes fijos idempotentes. `ReportService`: Excel de cinco hojas y ZIP.
  `BackupService`: `pg_dump`, últimos 30 respaldos en `storage/app/backups`.
- `App\Support\AmountInWords`: total en letras. `DateRange`: rangos y comparaciones.
- `DocumentPdfService` y dompdf: visor, impresión y PDF comparten la Blade de cada tipo;
  CSS compatible con dompdf (tablas, sin flex/grid). Caché derivada en `storage/app/documents`.
  Impresión de original y copia; ZIP con ZipStream y Excel con PhpSpreadsheet.
- UI «pulpería ceibeña»: verde #14532D, turquesa #0E7C86, mango #F5B700, tinta #1D3557.
  Atkinson Hyperlegible y Bricolage Grotesque locales; Inicio con cuatro botones (teclas 1–4).

## Comandos y acceso

```sh
php artisan test
node --test resources/js/lib/
npm run build
php artisan backup:run
php artisan documents:warm-pdfs --from=2026-11-01 --to=2026-11-17
php artisan schedule:run
```

Solo en una BD descartable o en la instalación inicial: `php artisan migrate:fresh --seed`
(borra todo). Pruebas: BD `frutverdrey_test`; revisar conexión en `phpunit.xml`.
Usuarios sembrados: `abuelo` / `rey2026` (admin, PIN 1234), `empleado` / `venta2026`,
`contador` / `contador2026`. Cambiarlos antes de operar. Ver `docs/INSTALACION.md`.
Scheduler: respaldo 18:00; PDFs 02:00, Honduras.

## Pendientes conocidos

Validar plantilla SAR y modalidad autoimpresor/imprenta con el contador (Art. 10 no verificado).
No hay nota de crédito. Confirmar razón social, número inicial OP, papel, precios y unidades reales.
Los montos de demostración no representan el negocio. PHP local avisa sobre rutas de `xsl` y `zip`.
