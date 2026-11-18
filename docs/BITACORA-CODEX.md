> Tarea 6b — verificación final 2026-11-18: 116/116 pruebas, 715 aserciones; npm run build OK.
> Logo PNG en los tres documentos; caché print-v3; sin bloqueos pendientes de esta tarea.

## Tarea 6b — Logo PNG en documentos (2026-11-18)

- Archivos: `resources/views/documents/{order,invoice,statement,print-style}.blade.php`,
  `app/Services/DocumentPdfService.php`, `config/documents.php`,
  `tests/Feature/{PrintLayoutTest,DocumentPdfTest}.php`, `docs/DECISIONES.md` e
  `instrucciones/CONTEXTO.md` (más esta bitácora).
- Logo `public/brand/logo-print.png` cargado una sola vez por plantilla como data URI PNG.
  Tabla de dos celdas junto al emisor, con columna fija y `table-layout: auto` en el
  encabezado anidado para que dompdf respete su ancho. Sin cambios a datos, textos,
  snapshots, lógica de venta, UI, rutas, migraciones, SVG ni PNG originales.
- Altura: carta 22 mm; media carta 16 mm. Estado de cuenta media carta: 13 mm,
  alternativa prevista en el prompt. A 16 mm el ejemplo ocupaba dos páginas; a 13 mm
  conserva una. Se mantiene el logo. Plan B si vuelve a desbordarse: quitarlo únicamente
  del estado de cuenta media carta y documentarlo; no fue necesario en el diseño final.
- Caché `print-v3/{paper}`: pruebas comprueban cuatro rutas diferentes (ambos papeles y
  variantes), invalidación al anular y conservación de la caché antigua `print-v2`.
- Pruebas ampliadas: data URI en los tres HTML; imagen incrustada en PDF; texto del PDF
  sin «Image dimensions» ni «exceed» (CPDF sin comprimir, normalizando UTF-16BE);
  una página por ejemplo y dimensiones 612 × 792 / 612 × 396 pt, sin aflojar expectativas.
- Revisión visual real: muestras generadas mediante los fixtures de `frutverdrey_test`
  fuera del proyecto (`/tmp/frutverdrey-6b-visual`), convertidas con `sips` a PNG y JPEG
  (JPEG para ver fondo blanco). Se inspeccionaron pedido, factura y estado de cuenta en
  carta y media carta: logos visibles, proporcionados, sin recortes ni superposición en
  número/CAI; textos legibles y totales completos. Las muestras y exportaciones temporales
  de prueba se retiraron al terminar. No se agregaron dependencias.
- Verificación final: `php artisan test --compact`, 116/116 pruebas, 715 aserciones;
  `npm run build` OK. PostgreSQL requirió ejecución fuera del sandbox, exclusivamente en
  `frutverdrey_test`; no se operó sobre la base real. Persisten los avisos locales conocidos
  de extensiones PHP xsl/zip, sin fallos en pruebas.
- BLOQUEOS: ninguno de Tarea 6b. Pendientes externos: recorrido interactivo por cada rol,
  impresora y papel reales, documentos largos y validación fiscal con el contador.

# Bitácora de la Tarea 5 — 2026-11-17

**Resultado final: 116/116 pruebas aprobadas (638 aserciones), `npm run build` OK.**
Bloques A–F implementados. Base inicial: 91 pruebas (326 aserciones).
Las pruebas usan exclusivamente `frutverdrey_test`; PostgreSQL requiere acceso fuera del sandbox.

## Revalidación — 2026-11-18

- `php artisan test`: 116/116 aprobadas, 638 aserciones. El primer intento quedó bloqueado
  por la conexión TCP del sandbox; la ejecución con acceso a PostgreSQL terminó correctamente.
- `npm run build`: OK.
- Instalación contrastada con DatabaseSeeder, DocumentSeriesSeeder, BackupService y scheduler.
- Se agregó `docs/REVISION-MANUAL.md` con comprobaciones de roles, teclado, anchos,
  impresión y recuperación. Enlazada desde ENTREGA.md. Pendiente de ejecutar; sin navegador disponible.
- Sin cambios al código de la aplicación ni migraciones de la base operativa.

## A — Interfaz
Revisión de código de todas las páginas y componentes. Sin navegador automatizado disponible;
el recorrido visual real a 1024 y 1366 px queda pendiente, no se presenta como verificado.

- Inicio → saludo dependía del reloj del navegador → hora de Honduras.
- Login → botón de mostrar contraseña sin icono → icono visible.
- Ventas/Index → filtros, paginación y estados ya presentes; tablas con desplazamiento.
- Ventas/Nueva → cliente preseleccionado llamaba funciones antes de inicializar las filas → inicialización después de las filas; cliente nuevo con Enter, errores e iconos.
- Ventas/Show → conversión cerraba el diálogo aunque fallara → conserva diálogo y muestra errores; anulación protegida contra doble envío.
- Clientes/Index → vacío sin acción → botón Cliente nuevo.
- Clientes/Show → anulación ya tenía confirmación; se agregó icono; tablas desplazables.
- Cobros → vacío sin acción → botón Nuevo pedido; abonos con Enter y bloqueo de doble envío.
- Fletes → cancelación directa → ConfirmDialog con efecto sobre la deuda; formularios con Enter, foco, errores y bloqueo de doble envío.
- Productos → formulario sin Enter y errores incompletos → formulario nativo y mensajes por campo; acción en vacío.
- Reportes → estructura responsiva y descargas ya presentes; sin cambios funcionales.
- Ajustes → confirmaciones, errores y formularios ya presentes.
- Usuarios → confirmación al desactivar y formularios ya presentes.
- AppLayout → Deshacer sin confirmación → ConfirmDialog, bloqueo durante envío.
- Componentes/tema → traducción local de Vuetify, iconos comunes, confirmación bloqueada durante envío, desplazamiento de tablas y acciones que pueden envolver.
- Fechas compartidas → zona horaria explícita de Honduras, incluso cuando el navegador está en otro país.

Verificación: 91/91 pruebas (326 aserciones), build OK.

## BLOQUEOS
Ningún bloqueo nuevo de negocio. Pendientes fiscales y de instalación se conservan.

## B — Validaciones y errores

- Se crearon traducciones locales de validación, acceso, contraseñas y paginación; atributos de todos los formularios con nombres sencillos.
- APP_LOCALE=es ya estaba en .env.example; config/app.php usa español por defecto.
- Form Requests revisadas: sus mensajes propios ya estaban en español.
- Errores 403, 404, 419 y 500 con explicación y enlace al inicio, sin paquetes ni recursos remotos.
- Verificación: 91/91 pruebas (326 aserciones), build OK.

## C — Permisos y flujos críticos

- AccessPermissionsTest recorre todas las rutas privadas: escritura del contador denegada (excepto cerrar sesión), administración denegada al vendedor e invitados enviados al login. Comprueba también las pantallas de lectura del contador.
- CriticalFlowsTest cubre PIN ausente y auditoría, error HTTP sin CAI, salto de CAI vencido/agotado, doble conversión sin duplicar deuda, deshacer abono conservando datos y límites de medianoche en Honduras.
- Se reutilizan las pruebas existentes de RTN desde L 10,000, FIFO, excedente de abono y numeración.
- Decisión: conservar el saldo a favor cuando el abono supera la deuda. Ya era el comportamiento de AccountsTest y PaymentDialog, compatible con el libro derivado de DECISIONES #9. No se inventa un límite nuevo.
- SpanishMessagesTest verifica validaciones anidadas y respuestas HTTP 403/404/419/500 sin revelar detalles internos.
- Bug demostrado con test rojo: la conversión fallida enviaba flash.error y activaba onSuccess; se cambió solo la respuesta a ValidationException. La UI conserva datos y errores.
- Verificación: 107/107 pruebas (507 aserciones), build OK.

## D — Robustez y rendimiento

- Ventas: paginación SQL existente y búsqueda con unaccent; agregados usan ahora explícitamente Sale::issued(). Se muestran anuladas y facturadas sin sumarlas.
- Clientes: paginación SQL de 25, búsqueda con unaccent por nombre y por teléfono/RTN; saldos de los clientes de la página en una sola consulta.
- Cobros: paginación SQL de 25 sobre balancesQuery; suma global del filtro separada de la página, sin truncar deuda. Pruebas con 30 deudores y nombres con acentos.
- Productos: paginación visual de 25; se conserva catálogo local y búsqueda tolerante a errores, según DECISIONES #20.
- Fletes: consultas con relaciones precargadas y rango acotado a semana/mes; sin N+1 detectado.
- Reportes: agregados SQL y top clientes limitado a diez; exportaciones completas necesarias para contabilidad.
- Ajustes: existía una consulta por serie para saber si fue usada; withExists('sales') elimina ese N+1.
- Ficha cliente: últimas 10 compras y últimos 60 movimientos visibles; FIFO/libro completo permanece para calcular saldos sin perder historia.
- Búsqueda de delete/forceDelete/cascadas: no hay borrado de ventas, líneas o movimientos. FreightService elimina solo viajes futuros sin personalizar al regenerar plantillas (DECISIONES #13). No se toca esa lógica ni migraciones.
- Fechas: los helpers del navegador y la orden impresa usan zona explícita. La revisión de E encontró y corrigió además UTC en la configuración del servidor.
- Roles en visor: contador puede imprimir sin intentar el POST de contador de impresión y sin botón de nueva venta.
- No se modificó DocumentNumberService, Money ni la inmutabilidad de Sale; sin dependencias ni migraciones nuevas.
- Verificación: 109/109 pruebas (545 aserciones), build OK.

## E — Impresión

- PrintLayoutTest empezó rojo: ninguna plantilla aplicaba el papel configurado. Se agregó print-style.blade.php compartido con @page para carta (8.5 × 11 pulgadas) y media carta horizontal (8.5 × 5.5), encabezados repetibles, filas sin corte y ajuste compacto en media carta.
- Pruebas dompdf: original y copia de pedido/factura en ambos tamaños; una página por documento de ejemplo; dimensiones PDF exactas; CAI, rango, total en letras, copia y ausencia de «FACTURA» en la OP.
- Estado de cuenta renderizado y pasado por dompdf en los dos tamaños, con saldo y enlace de impresión.
- La OP usa título fijo histórico «ORDEN DE PEDIDO», sin depender del nombre actual de la serie.
- Caché de PDF versionada bajo print-v2/{paper} para evitar servir los PDFs del diseño anterior y separar ambos papeles; al anular se invalidan los dos tamaños. Prueba de regresión en DocumentPdfTest.
- Hallazgo horario con test rojo: config/app.php fijaba UTC y SaleService guardaba issue_date antes de convertir a Honduras. Se configuró APP_TIMEZONE con valor predeterminado America/Tegucigalpa y se normaliza issued_at antes de guardar; la conexión PostgreSQL usa la misma zona para conservar el instante al guardar y leer. La prueba de medianoche usa importes distintos para detectar el desplazamiento. No se reescriben documentos históricos ni se cambia la inmutabilidad.
- Corrección complementaria de A: anular venta usa ConfirmDialog con errores junto a motivo y PIN. ConfirmDialog usa envío de formulario nativo, respetando Enter sobre Cancelar y saltos de línea de las notas.
- Verificación: 116/116 pruebas (638 aserciones), build OK.
- Pendiente: prueba física con impresora y papel definitivos, y recorrido visual en navegador. No se certifica cumplimiento SAR; validar con el contador, según DECISIONES #4.

## F — Entrega y cierre

- docs/ENTREGA.md describe cada pantalla, accesos de prueba, decisiones y pendientes. Enlaza a INSTALACION.md sin duplicar sus pasos.
- instrucciones/CONTEXTO.md actualizado para la siguiente revisión.
- Nueva venta muestra también los errores de campos y líneas junto a sus casillas.
- Revisión final: los archivos del proyecto están mayormente sin seguimiento en Git desde el inicio de la sesión; git diff no representa el alcance completo. No se añadieron archivos al índice ni se tocaron otros proyectos del repositorio padre.
- La auditoría detectó conversiones float preexistentes en previsualizaciones del navegador y formato de importes/ordenación de reportes. Los cálculos autoritativos de emisión siguen con Money y bcmath; no se amplió el alcance para reescribir esas previsualizaciones ni Money.
- Verificación final: 116/116 pruebas (638 aserciones), build OK. Pruebas exclusivamente en frutverdrey_test. Sin migraciones ni paquetes nuevos.
- Pendientes de comprobación externa: recorrido visual a 1024/1366 px, impresión física, contador SAR, datos reales y despliegue. Detallados en ENTREGA.md; no son una decisión nueva tomada por Codex.


## Tarea 6 — identidad visual (2026-11-18)

### A — Marca
- Copias limpias en public/brand, favicon SVG y BrandLogo.vue; originales intactos. Sin filtros ni cambios a paths.
- Pruebas: 116/116, 638 aserciones. Build OK. El sandbox bloqueó PostgreSQL; repetido con acceso autorizado a frutverdrey_test.

### B — Base
- Tokens, tipografía óptica, botones, tarjetas y tablas uniformes. Verde #2D7A3E en Vuetify; contraste explícito.
- Pruebas: 116/116, 638 aserciones. Build OK.

### C — Marco
- AppLayout: cajón verde oscuro, logo horizontal blanco, selección mango, barra clara y fecha de Honduras.
- Pruebas: 116/116, 638 aserciones. Build OK.

### D — Inicio y entrada
- Home: rótulos con marco y ficha de teclado; resumen del día en un solo panel adaptable. Login: logo inverse y fondo de marca.
- Pruebas: 116/116, 638 aserciones. Build OK.

### E — Pantallas de trabajo
- Nueva venta: talonario perforado, total y pasos numerados; script intacto. EmptyState alineado a la izquierda.
- Colores, radios y títulos uniformes en Ajustes, Clientes, Cobros, Fletes, Reportes, Usuarios y Ventas. Cobros conserva mango/chile.
- Pruebas: 116/116, 638 aserciones. Build OK.

### F — Documentos (alternativa prevista)
- Se probaron los tres encabezados y ambos papeles. Pedidos/facturas se generaban, pero inspección visual de PDF real reveló logo ausente.
- Dompdf: «Image dimensions or size exceed the configured limit»; SVG mono 4358×4358, estimación 303874624 bytes, límite 134217728.
- BLOQUEO: logo impreso incompatible con el límite actual de dompdf. Según F.5 se retiró de las tres plantillas, se conserva print-v2 y la caché antigua. Originales y datos intactos.
- El estado de cuenta media carta llegó a dos páginas al añadir el encabezado; retirada la adición, se revalida el formato original.

- Verificación F tras aplicar la alternativa: 116/116 pruebas, 638 aserciones. Build OK.

### G — Revisión final
- SVG parseados: sin filtros; todos los atributos d de paths coinciden con sus originales. Páginas/componentes sin hex sueltos.
- Sin degradados decorativos, corona genérica, verde anterior, espaciado excesivo ni flechas. `uppercaseCai` conserva su función de normalizar el CAI, no es estilo de interfaz.
- Capturas Chrome headless de vistas reales generadas en frutverdrey_test: Inicio y Nueva venta a 1024×900 y 1366×1000, Login a 1024×900. Logo, barra y rótulos sin desbordamiento; talonario visible a 1366, apilado a 1024. No constituye recorrido interactivo de todos los roles ni prueba de impresora.
- Las vistas fueron estáticas servidas en localhost desde /tmp. Se completó la configuración Ziggy para la captura de Nueva venta (el segundo render del proceso de prueba emitía solo Object.assign). No se cambió Ziggy en la aplicación. Prueba auxiliar eliminada.
- Sin dependencias nuevas, migraciones ni cambios de lógica. BLOQUEOS: logo en PDF (F, alternativa aplicada).

- Verificación final G: 116/116 pruebas, 638 aserciones; npm run build OK.

## Tarea 7 — Montos exactos y aviso de credenciales (2026-11-18)

### A — Dinero en pantalla
- `resources/js/lib/money.js`: centavos seguros, productos intermedios BigInt y redondeo half-up con signo. Nueva venta, impuestos, umbral de identificación y límite de crédito usan centavos; PaymentDialog calcula el saldo con enteros. Promedio de Reportes convertido también.
- `tests/fixtures/money-cases.json`, `tests/Unit/MoneyParityTest.php`, `resources/js/lib/money.test.js`: 206 casos compartidos con Money, incluidos negativos, límites de medio centavo y montos grandes. Entradas vacías/coma decimal, saldos y umbral L 10,000 cubiertos en Node.
- Revisión `Number/parseFloat/toFixed`: usos restantes son presentación, cantidades, bytes, hora/teclado o comprobaciones de signo/visibilidad; no acumulan dinero.
- Node v25.4.0 no resuelve carpetas de pruebas sin entrada: `resources/js/lib/index.js` carga el test y permite el comando exacto solicitado sin modificar package.json.
- Verificación: PHP 117/117, 1334 aserciones; Node 4/4; build OK. Primer intento PHP bloqueado por sandbox; repetido con acceso autorizado solo a `frutverdrey_test`. Avisos PHP de extensiones ya conocidos. BLOQUEOS: ninguno.

### B — Aviso para administradores
- `app/Support/DefaultCredentials.php` y `config/default_credentials.php`: Hash::check de usuarios activos contra valores públicos y overrides de siembra; configuración compatible con cache. Solo devuelve usernames y bandera de PIN, sin secretos. También comprueba PIN predeterminado de administradores activos con otro username.
- `UserController`, `SettingsController`, `Pages/Usuarios/Index.vue`, `Pages/Ajustes/Index.vue`: prop y advertencia solo en rutas admin existentes; acceso a Usuarios desde Ajustes. El aviso permanece si solo falta cambiar el PIN.
- `tests/Feature/DefaultCredentialsTest.php`: nueve casos (ambas páginas, cambios de contraseña/PIN, usuarios inactivos, overrides y denegación a vendedor/contador).
- Verificación: PHP 126/126, 1374 aserciones; Node 4/4; build OK. BLOQUEOS: ninguno.

### C — Cierre
- Decisiones 26–27 en `docs/DECISIONES.md`, estado de Tarea 7 en `instrucciones/CONTEXTO.md` y comando Node en `CLAUDE.md`/`AGENTS.md`.
- Se amplió el caso grande de Node para comprobar un producto intermedio superior a MAX_SAFE_INTEGER con resultado final seguro y rechazo explícito de resultados fuera del rango exacto.
- Sin dependencias, migraciones ni cambios en Money, SaleService, DocumentNumberService, modelos, rutas o plantillas de documentos.
- Verificación final C: `php artisan test` 126/126, 1374 aserciones (solo `frutverdrey_test`); `node --test resources/js/lib/` 4/4; `npm run build` OK. Formato PHP (Pint) OK en archivos PHP tocados. Sin recorrido interactivo de navegador. BLOQUEOS: ninguno.

## Tarea 8 — Correcciones del recorrido (2026-11-18)

### A — Líneas de venta
- Parser de entradas: coma o punto, máximo dos decimales; `1.000` se rechaza, nunca significa mil. `toCents/toQty` conservan el redondeo y signo para cálculos financieros/paridad; comparten normalización de coma con las entradas. La validación estricta se aplica antes de calcular/enviar casillas escritas.
- Nueva venta conserva visibles las filas escritas aunque no coincidan con el filtro, marca cantidad/precio inválidos, bloquea confirmación y envío, normaliza payload y asocia errores del servidor con las filas efectivamente enviadas.
- Verificación: PHP 129/129; Node 5/5; build OK. PHP requirió acceso a PostgreSQL fuera del sandbox, exclusivamente `frutverdrey_test`.

### B — Movimientos anulados
- `ledger(..., includeVoided: true)` añade abonos/saldos anteriores anulados con monto original, motivo, autor y fecha; cargo/abono cero. Solo ficha y estado imprimible activan esta opción; entriesQuery, FIFO y antigüedad intactos.
- Ficha: monto tachado, chip Anulado y sin botón de anular. Impresión: detalle textual con Anulado, monto, motivo y autor.
- BLOQUEO de alcance: el monto tachado/chip en la impresión requiere cambiar `documents/statement.blade.php`, prohibido por el prompt. Se conserva la plantilla y se muestra toda la información en su columna Detalle.
- Verificación: PHP 130/130, 1418 aserciones; Node 5/5; build OK.

### C — Edición de fletes
- Reproducción Pest previa a la corrección: payload de props reales (YYYY-MM-DD y HH:MM) más extras del spread; el mismo payload se acepta con status string. Con SubmitEvent serializado a objeto solo falla `status` (422), notas intactas.
- Causa: `@submit.prevent="saveFreight"` entrega el evento a `saveFreight(status)`, que lo asignaba al estado. Se invoca ahora sin argumentos y solo se admiten estados string conocidos en las acciones explícitas.
- Edición copia únicamente campos del formulario; fecha/hora normalizadas. Se aplicó también a viajes fijos y motoristas. Los tres diálogos muestran todos los errores en v-alert, incluidos campos condicionales/sin casilla.
- Pest cubre persistencia de notas/customized y cancelación que retira deuda.

- Verificación C: PHP 132/132, 1434 aserciones; Node 5/5; build OK.

### D — Montos y calendario
- Reportes: tarjetas con tamaño de monto según ancho de su contenedor (clamp/cqi), texto completo y salto de línea permitido; rankings permiten envolver filas.
- Fletes: cobro envuelve texto, chips envuelven y conservan contenido visible; días apilados sin mínimo de 220 px; Anotar con tamaño normal.
- Auditoría de resources/js y resources/css: la única regla general que forzaba nowrap sobre dinero era `.money` en app.css; ahora permite envolver, incluidos Nueva venta, ficha/tabla de Clientes, Cuentas, Inicio, Ventas y PaymentDialog. Las tablas conservan desplazamiento horizontal. `m-trip` solo trunca destino/hora, sin dinero; `sr-only` en Usuarios es accesibilidad, se conserva.

- Verificación D: PHP 132/132, 1434 aserciones; Node 5/5; build OK.
- Chrome headless con HTML real capturado exclusivamente en frutverdrey_test y assets compilados: a 1024/1366 todas las tarjetas muestran L 99,999,999.99 sin desbordamiento; cobro y Pendiente completos. Días vacíos apilados: 115 px. Capturas de Reportes y Fletes inspeccionadas; cero errores JS. No equivale al recorrido integral ni prueba de impresora.

### E — Cierre
- Decisiones 28–29, revisión manual ampliada sin marcar casillas y contexto actualizado.
- Sin migraciones, dependencias, cambios a Money.php/servicios de emisión/numeración/inmutabilidad o plantillas. Base operativa intacta.

- Comprobación interactiva adicional sobre capturas estáticas a ambos anchos: `abc` marca el campo y bloquea confirmación; `2,5` en cantidad/precio produce payload `2.50`/`2.50`. Guardar flete emite status string y solo campos de blank. Una respuesta Inertia simulada con error de status aparece en v-alert. Peticiones interceptadas: ninguna escritura real. El helper Ziggy completo se agregó solo al arnés temporal para resolver su ausencia en los renders sucesivos del test.
- El formateador `--dirty` incluyó archivos ajenos por estar sin seguimiento; se retiraron esos cambios de formato usando versiones previas y comprobando equivalencia tras formatear copias temporales. No se conserva cambio funcional fuera del alcance.
- Archivos de aplicación: `resources/js/lib/money.js`, `resources/js/Pages/Ventas/Nueva.vue`, `resources/js/Pages/Clientes/Show.vue`, `resources/js/Pages/Fletes/Index.vue`, `resources/js/Pages/Reportes/Index.vue`, `resources/css/app.css`, `app/Services/AccountsService.php`, `app/Http/Controllers/CustomerController.php`.
- Pruebas: `resources/js/lib/money.test.js`, `tests/Feature/SaleWorkflowTest.php`, `tests/Feature/AccountsTest.php`, `tests/Feature/FreightTest.php`. Documentación: esta bitácora, `docs/DECISIONES.md`, `docs/REVISION-MANUAL.md`, `instrucciones/CONTEXTO.md`.

- Verificación final E: `php artisan test` 132/132, 1434 aserciones (solo `frutverdrey_test`); `node --test resources/js/lib/` 5/5; `npm run build` OK. BLOQUEOS: únicamente el estilo tachado/chip del estado imprimible por prohibición de modificar plantillas; la información anulada sí se muestra.
