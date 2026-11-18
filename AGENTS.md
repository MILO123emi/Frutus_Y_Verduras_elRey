# Frutas y Verduras El Rey

Sistema local de ventas en La Ceiba: pedidos no fiscales, facturas SAR con CAI,
cuentas por cobrar, fletes, reportes y respaldos.

Lee `instrucciones/CONTEXTO.md` y `docs/DECISIONES.md` antes de empezar.
Consulta `CLAUDE.md` para arquitectura y `docs/codex-prompts.md` para el alcance de cada tarea.
El kickoff es histórico; prevalecen las decisiones y el contexto actuales.

## Reglas no negociables

1. Nunca borrar ventas, sus líneas ni abonos: solo anular con motivo, usuario y fecha.
   Sin soft delete ni borrados en cascada sobre `sales`/`sale_items`.
2. Numeración correlativa por serie, sin huecos ni duplicados: `SELECT … FOR UPDATE` en
   transacción y `unique(document_series_id, number)`. No reutilizar números anulados.
3. Documento emitido inmutable en líneas, montos, cliente y fecha; corregir anulando y emitiendo
   otro. Solo permitir transiciones controladas de anulación/conversión y contador de impresión.
4. Snapshots del emisor, cliente, líneas e información fiscal: renderizar documentos históricos
   con los datos originales.
5. Cada CAI es una `document_series` con `kind=invoice`, formato `000-001-01-00000001`.
   Bloquear series agotadas o vencidas y elegir la siguiente utilizable. Avisos con ≤ 50 números
   o ≤ 15 días (configurables); exigir nombre y RTN/identidad desde L 10,000.
   Plantilla: `resources/views/documents/invoice.blade.php`.
   La orden OP no lleva CAI ni debe decir «FACTURA».
6. Dinero con `App\Support\Money` y bcmath, nunca `float`; BD `numeric(12,2)`.
   Total de línea: cantidad × precio, redondeo half-up a dos decimales.
7. Productos exentos por defecto; snapshot de `tax_type` (`exempt|g15|g18`) en cada línea;
   subtotales separados, incluido exonerado cuando corresponda.
8. Totales con `Sale::issued()`: excluir anuladas y pedidos `converted` para no duplicar
   ventas ni deuda. Mantenerlos identificados en listados y reportes.
9. Fechas `America/Tegucigalpa`, rangos inclusivos, semana desde el lunes.
10. UI para personas poco técnicas: pocos pasos, teclado, botones grandes con ícono y texto,
    confirmación en acciones importantes y sin efectos pesados.

## Convenciones

- Cambios quirúrgicos dentro del prompt; no agregar dependencias npm/composer salvo petición.
- UI en español sencillo para un señor de 60 años; código, tablas y columnas en inglés.
  Conservar los nombres de rutas existentes y usar Ziggy en Vue.
- Laravel 13, Inertia 2, Vue 3 (`script setup`), Vuetify 4, PostgreSQL 17; sin CDN ni Tailwind.
- Pest para lógica crítica. Nunca ejecutar comandos destructivos contra datos reales.
- Login por usuario, roles `admin`, `seller`, `accountant`; anular ventas exige PIN de admin.

## Verificación

```sh
php artisan test
node --test resources/js/lib/
npm run build
```

Solo en una BD descartable o antes de operar por primera vez:

```sh
php artisan migrate:fresh --seed
```

Ese último comando borra todas las tablas. Pruebas: `frutverdrey_test` (ver `phpunit.xml`).
