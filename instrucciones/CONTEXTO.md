# CONTEXTO VIVO — leer primero en cada chat nuevo

> Cada chat con Claude empieza desde cero. **Este archivo es el traspaso entre chats.**
> Claude: léelo antes de cualquier otra cosa y actualízalo antes de terminar cada respuesta
> (reescribe lo que quedó viejo; no lo conviertas en bitácora).
> Última actualización: 2026-11-18.

## Cómo trabajamos
1. **Farid** pide algo en un chat nuevo con Claude.
2. **Claude** lee este archivo, analiza y escribe el **plan + prompt para Codex** en
   `docs/codex-prompts.md` (objetivo, archivos exactos, pasos, convenciones, criterios de
   aceptación, comandos de verificación, qué NO tocar). Tareas chicas y verificables.
3. **Farid** pega el prompt en Codex (extensión de VS Code). **Claude no ejecuta Codex.**
   Desde la Tarea 5 los prompts son **largos y autónomos** (varios bloques, bitácora, tests
   tras cada bloque) para gastar menos idas y vueltas.
4. Farid vuelve y avisa → Claude **revisa** (diff, `php artisan test`, `npm run build`) y
   corrige solo detalles pequeños; si hay algo grande, escribe otro prompt.
5. Claude actualiza este archivo.

Fuentes de verdad: el prompt de "modo autónomo" de Farid (2026-11-17), `docs/DECISIONES.md`
(decisiones y cómo cambiarlas), `docs/DIAGNOSTICO.md`. El kickoff y el análisis de esta carpeta
quedan como historia (antes era solo la orden de pedido; ahora también hay factura SAR).

## Qué es el sistema
Ventas del negocio de verduras del abuelo de Farid (La Ceiba, Honduras), para un señor de
60 años o un empleado poco tecnológico. Tiene pedidos (no fiscales) y **facturas SAR con CAI**,
clientes y cuentas por cobrar, fletes con calendario, reportes para el contador y respaldos.
Stack: Laravel 13, Inertia 2, Vue 3, Vuetify 4, PostgreSQL 17, sin CDN.

## Estado actual (2026-11-18)
**Hecho y verificado (132/132 tests PHP, 1434 aserciones, 5/5 tests Node, build OK; Tarea 8):**
La Tarea 5 no ejecutó migraciones de la base operativa; pruebas solo en `frutverdrey_test`.
- Facturas SAR: cada CAI es una `document_series` (kind=invoice); formato `000-001-01-00000001`;
  bloqueo por agotada o vencida; avisos (≤ 50 números o ≤ 15 días); pasa sola al CAI siguiente;
  exige RTN desde L 10,000; exonerados; total en letras; plantilla `documents/invoice.blade.php`.
- Pedido → factura (`SaleService::convertToInvoice`; el pedido queda `converted` y no suma).
- Cuentas por cobrar: `AccountsService` (libro derivado, abonos FIFO, antigüedad, saldo
  anterior, deshacer abono). Página "Quién me debe" y ficha del cliente con estado de cuenta imprimible.
- Fletes: motoristas, viajes fijos (miércoles y viernes a SPS) generados de forma idempotente,
  calendario semanal y mensual, fletes extra y cobro cargado a la cuenta del cliente.
- Reportes: resumen del período, Excel (5 hojas) y ZIP con PDFs. Respaldos con `backup:run`
  (programado a las 18:00) y botón en Ajustes.
- Usuarios y roles (admin, vendedor, contador), login por **usuario**, anular con PIN de admin.
- UI nueva "pulpería ceibeña": Inicio con 4 botones grandes (teclas 1–4), Atkinson Hyperlegible
  y Bricolage Grotesque locales. Se quitaron Tailwind y las páginas Breeze.
- Datos de ejemplo (`DemoDataSeeder`, solo fuera de producción). Usuarios de prueba:
  `abuelo`/`rey2026` (admin, PIN 1234), `empleado`/`venta2026`, `contador`/`contador2026`.

**Tareas de Codex (`docs/codex-prompts.md`):** 1 Ajustes ✅ · 2 Usuarios ✅ · 3 Seeders ✅ ·
4b Documentación ✅ · **Tarea 5 implementada A–F** (2026-11-17).
- Bitácora completa: `docs/BITACORA-CODEX.md`; entrega: `docs/ENTREGA.md`.
- UI: confirmación para deshacer abonos, cancelar fletes y anular ventas; formularios con
  Enter, errores junto a campos, protección contra doble envío, Vuetify en español.
- Corregida nueva venta con cliente preseleccionado y errores de conversión que cerraban el diálogo.
- Traducciones locales `lang/es` y páginas de error 403/404/419/500.
- Pruebas de permisos sobre todas las rutas privadas, flujos críticos y búsquedas/paginación.
- Clientes y deudores paginados en SQL; total global de deuda separado de la página;
  Ajustes usa withExists para evitar una consulta por serie. Productos paginados en la UI.
- Papel aplicado en HTML/PDF: carta y media carta horizontal (8.5 × 5.5 pulgadas), con pruebas
  de dimensiones y contenido. Caché derivada en `storage/app/documents/print-v3/{paper}`;
  al anular se invalidan ambos tamaños. No se borró la caché antigua.
- Corregido UTC fijo: APP_TIMEZONE predetermina Honduras y la conexión PostgreSQL usa la
  misma zona. SaleService normaliza issued_at antes de derivar issue_date. Prueba con
  montos distintos a ambos lados de medianoche. Documentos ya emitidos no se reescriben.
- Sin migraciones ni dependencias nuevas; no se tocó Money, DocumentNumberService ni la
  inmutabilidad de Sale. No hubo bloqueos nuevos de negocio.

## Pendiente (en orden sugerido)
**Siguiente para Codex (prompts listos en `docs/codex-prompts.md`, 2026-11-18):**
- **Tarea 9 — puesta en marcha** (pegar ya): `system:check` + panel "¿Está todo listo?" en
  Ajustes, latido del programador, `backup:verify` que restaura solo en `<base>_restore_check`
  y la borra, botón "Probar el último respaldo", pruebas de cuadre Excel/ZIP vs resumen.
  Sin migraciones. Ojo al revisar: los tests de respaldo deben usar carpeta temporal (prune borra).
- **Tarea 10 — nota de crédito SAR** (pegar después de revisar la 9): tablas propias
  `credit_notes`/`credit_note_items` (única migración autorizada, aditiva), serie `kind=credit_note`
  con su CAI, solo sobre facturas, por líneas, PIN admin; crédito → abono en `entriesQuery()`,
  contado → efectivo; Excel hoja 6; factura con NC activa no se anula. Supuestos fiscales
  (código de tipo, textos, NC por descuento, plazo) quedan para el contador.

**Tarea 8 implementada (2026-11-18), revisión final documentada en BITACORA-CODEX.**
- Venta: coma/punto decimal, validación visible de casillas escritas y bloqueo del guardado;
  cantidades vacías se omiten, `1.000` inválido (tres decimales), payload normalizado.
- Cuenta: abonos y deudas anteriores anulados visibles, sin sumar ni alterar FIFO/antigüedad.
  Ficha con monto tachado; estado imprimible con información textual en el detalle.
- Fletes: Guardar pasaba SubmitEvent como status; corregida invocación y selección de campos,
  formatos fecha/hora y errores visibles en los tres diálogos. Pruebas de edición y cancelación.
- Reportes: montos adaptados al ancho; Fletes: cobros/chips envuelven, días apilados compactos.
- Pruebas solo en `frutverdrey_test`; no se tocó la base operativa ni demo.
- BLOQUEO de alcance: tachado/chip en estado impreso requeriría modificar una plantilla de
  documentos, prohibido en Tarea 8; contiene Anulado, monto, motivo y autor como texto.
- **Revisada por Claude (2026-11-18):** 132/132 PHP (1434), 5/5 Node y build repetidos;
  parser, filas de Nueva venta, `ledger(includeVoided)` y `saveFreight()` leídos: correctos.
  Plantillas sin tocar (10:08). Sale.php/SaleService/ReportService tienen hora 15:20 por
  `pint --dirty`; Codex dice que revirtió el formato (sin git no se puede comparar).
  Bloqueo aceptado: el texto «Anulado · monto · motivo · autor» basta en el impreso.
- Pendiente repetir recorrido integral por roles e impresión en PC del negocio; las casillas
  de REVISION-MANUAL siguen sin marcar. La revisión visual de Tarea 8 usa HTML real con datos
  de prueba y Chrome headless a 1024/1366, sin operaciones contra la base operativa.

Revalidación posterior a Tarea 7 del 2026-11-18: 126/126 pruebas PHP (1374 aserciones),
4/4 pruebas Node y build OK. Las pruebas requirieron acceso fuera del sandbox a
PostgreSQL y usaron `frutverdrey_test`. Sin cambios al código de la aplicación.
Se contrastaron las instrucciones de instalación con seeders, programador y BackupService;
sin cambios al código de la aplicación. Guía ejecutable por una persona en
`docs/REVISION-MANUAL.md`; las casillas siguen pendientes, no son resultados verificados.

0. **Tareas 6 y 6b implementadas.** Verificación final 2026-11-18: 116/116 pruebas,
   715 aserciones, build OK (solo `frutverdrey_test`). Identidad visual en UI y logo PNG en pedido, factura
   y estado de cuenta, junto al emisor. SVG y PNG originales intactos; snapshots sin cambios.
   Caché `print-v3/{paper}`; al anular se invalidan ambos papeles y variantes, sin borrar
   la caché antigua. El bloqueo SVG de Tarea 6 queda resuelto mediante el PNG de 400 px.
   Logo de 22 mm en carta y 16 mm en media carta; estado de cuenta media carta a 13 mm:
   el ejemplo conserva una página. Plan B documentado: omitir su logo solo en ese tamaño
   si deja de caber; no fue necesario aplicarlo.
   PDF de ejemplo de los tres documentos en ambos tamaños convertidos con sips e
   inspeccionados visualmente: logo visible, sin cortes ni superposición sobre número/CAI.
   Archivos temporales retirados. Pendientes el recorrido por roles y la impresora real.
   **Revisada por Claude (2026-11-18):** pruebas y build repetidos (116/116, 715); factura
   000-001-01-00000008 renderizada aparte y vista: correcta.
   **Tarea 7 implementada y verificada (2026-11-18):** montos de Nueva venta, impuestos,
   límite de crédito, umbral de L 10,000, saldo de PaymentDialog y promedio de Reportes con
   centavos enteros (`resources/js/lib/money.js`, BigInt para productos intermedios). Paridad
   con Money en 206 casos compartidos PHP/Node (`tests/fixtures/money-cases.json`).
   Aviso en Usuarios y Ajustes para admin si quedan contraseñas o PIN de prueba de usuarios
   activos; Hash::check solo en esas páginas, sin enviar secretos. Overrides de siembra en
   `config/default_credentials.php`, compatibles con configuración cacheada. Sin migraciones,
   dependencias ni cambios en Money, SaleService, numeración, rutas o documentos.
   Verificar con `php artisan test`, `node --test resources/js/lib/` y `npm run build`.
   Node v25 necesita `resources/js/lib/index.js` para ejecutar la carpeta con ese comando.
   Pruebas PHP exclusivamente en `frutverdrey_test` con acceso fuera del sandbox; avisos
   locales de xsl/zip ya conocidos. No se hizo un recorrido interactivo de navegador.
1. Revisar `docs/BITACORA-CODEX.md` y los archivos indicados. El proyecto está mayormente
   sin seguimiento en el repositorio padre; `git diff` solo no muestra todo el trabajo.
2. Recorrido visual real en navegador a 1024 px y 1366 px con cada rol, teclado, diálogos y
   errores: no se realizó. Seguir `docs/REVISION-MANUAL.md`; no hay herramienta de navegador
   disponible en la sesión de revalidación. La revisión de interfaz fue de código y compilación.
3. Probar impresora y papel definitivo, documentos largos y copia/original. Media carta está
   implementada horizontal; confirmar que coincide con el papel del negocio.
4. Revisar `docs/ENTREGA.md` y completar los datos y comprobaciones de instalación.

## Pendientes conocidos / sin verificar
- Requisitos SAR: no se pudo leer el texto oficial del Art. 10 (Acuerdo 481-2017); se imprimió de
  más por conservador. **Validar con el contador** la plantilla de factura y la modalidad
  (autoimpresor vs. imprenta).
- No hay **nota de crédito** (el SAR la usa para revertir facturas ya entregadas).
- Razón social real, número inicial real de la serie OP, tamaño de papel, precios y unidades
  reales: por confirmar con el abuelo.
- Los montos de los datos de ejemplo salieron altos (~L 450 k por cobrar): sirven para probar, no reflejan el negocio.
- PHP local avisa que faltan las extensiones `xsl` y `zip` en una ruta pecl: es ruido; `zip` sí carga.
