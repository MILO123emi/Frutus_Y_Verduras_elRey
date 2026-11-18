# Decisiones tomadas en modo autónomo

Formato: **decisión** — por qué — cómo cambiarla.

## Facturación SAR
1. **Cada CAI es una "serie" (`document_series` con `kind=invoice`).** — Un CAI autoriza un rango
   con fecha límite; registrar un CAI nuevo = agregar una fila, sin tocar código. Cuando una se
   agota o vence, el sistema usa automáticamente la siguiente activa del mismo tipo. — Ajustes → Facturación.
2. **Formato `EEE-PPP-TT-NNNNNNNN`** (establecimiento, punto de emisión, tipo `01` = factura,
   correlativo de 8 dígitos). — Requisito SAR. — Campos de la serie.
3. **Bloqueo:** no se emite si el rango se agotó o la fecha límite pasó (comparando con la fecha
   de Honduras). **Avisos** cuando quedan ≤ 50 números o ≤ 15 días (configurables por serie). —
   Pedido explícito. — Columnas `range_alert_threshold` / `deadline_alert_days`.
4. **Campos impresos (enfoque conservador, Acuerdo 481-2017 Art. 10):** razón social, nombre
   comercial, RTN y dirección/teléfono del emisor; CAI; rango autorizado; fecha límite de
   emisión; número; fecha; nombre y RTN del comprador ("CONSUMIDOR FINAL" si no hay); detalle
   (cantidad, unidad, descripción, precio, total); subtotales exento/exonerado/gravado 15/18,
   ISV 15/18, total; **total en letras**; destino "ORIGINAL: CLIENTE" / "COPIA: OBLIGADO
   TRIBUTARIO EMISOR"; casillas para Nº de orden de compra exenta, constancia de registro de
   exonerado y registro SAG; leyenda "La factura es beneficio de todos. Exíjala".
   ⚠️ **No pude leer el texto oficial completo del Art. 10** (el PDF de RSM no se pudo extraer). Se
   imprimió *más* de lo mínimo conocido para no quedarse corto. **Validar con el contador.**
   Si la modalidad es **imprenta**, los datos de la imprenta van pre-impresos en el papel y este
   sistema no aplica tal cual; el diseño asume **autoimpresor** (papel bond + láser).
5. **Comprador ≥ L 10,000:** la factura exige nombre y RTN/identificación. — Art. 11. — `SaleService::INVOICE_ID_THRESHOLD`.
6. **Razón social** del emisor quedó como "por confirmar" en Ajustes; la factura no se puede
   emitir sin razón social y RTN cargados (se avisa en pantalla). — Dato fiscal obligatorio.
7. **Anulación de factura** = estado "Anulada" con motivo, usuario y fecha; el número no se
   reutiliza y la factura sigue apareciendo en reportes marcada. ⚠️ El SAR usa **nota de
   crédito** para revertir operaciones ya entregadas al cliente; aquí no se implementó nota de
   crédito (pendiente, ver entrega).
8. **Orden → factura:** la orden queda con estado **"Facturada"** (ligada a la factura) y deja de
   contar en totales y deudas; la factura la reemplaza con las mismas líneas, cliente y
   condición. Así no se cuenta dos veces. Una orden anulada o ya facturada no se puede convertir.

## Cuentas por cobrar
9. **Saldo = ventas a crédito activas + saldos anteriores + fletes cobrados a cuenta − abonos.**
   Se calcula en SQL al leer; no hay tabla de asignaciones. El estado "pagada / pendiente" de cada
   venta y la antigüedad (0–30, 31–60, 61–90, 90+) se calculan aplicando los abonos **FIFO**
   (primero lo más viejo). — Sin estado duplicado que se pueda descuadrar. No hay asignación
   manual de abonos a una venta específica (se puede agregar si el abuelo la necesita).
10. **Las ventas de contado no entran a la deuda** (nacen pagadas).
11. **Abonos y saldos anteriores no se borran: se anulan** (con motivo). Justo después de
    guardar un abono aparece **"Deshacer"** (anula ese abono).
12. Se **advierte** (no se bloquea) si una venta a crédito pasa el límite de crédito del cliente.

## Fletes
13. **Viajes fijos** = "plantillas" (día de la semana, motorista, destino, vehículo). Al abrir el
    calendario se crean los viajes de esa semana a partir de las plantillas (una sola vez por
    día: `unique(freight_schedule_id, date)`). Cada viaje se puede editar o cancelar sin tocar la
    plantilla. Al cambiar una plantilla, los viajes **futuros sin modificar** se regeneran.
    Semilla: miércoles y viernes a San Pedro Sula.
14. Un flete con cobro y cliente puede marcarse **"Cargar a la cuenta del cliente"**; entonces
    suma a lo que ese cliente debe. Un flete cancelado no suma.
15. Vehículo = texto libre (no hay catálogo de vehículos) — es un negocio pequeño.

## Usuarios y acceso
16. **Login con nombre de usuario** (no correo): el abuelo no usa correo. Sin "olvidé mi
    contraseña" por correo (es offline): el administrador la restablece en Usuarios.
17. Roles: **admin** (todo), **seller** "Vendedor" (vender, cobrar, fletes), **accountant**
    "Contador" (solo ver y descargar reportes). Anular sigue pidiendo **PIN de admin**.
18. Usuarios sembrados: `abuelo` / `rey2026` (admin, PIN 1234), `empleado` / `venta2026`
    (vendedor), `contador` / `contador2026`. **Cambiar en el despliegue.**

## Interfaz
19. Dirección visual "mercado fresco": verde hoja profundo, fondo crema, acento tomate y mango.
    Letra **Atkinson Hyperlegible** (diseñada para baja visión) a 18 px base; títulos en
    **Fraunces**. Fuentes empaquetadas (sin CDN). Se quitó Tailwind.
20. Búsqueda tolerante a errores (sin acentos, mayúsculas ni errores de 1–2 letras: "tomte" →
    Tomate, "pulperia esperansa" → Pulpería La Esperanza) hecha en el navegador; los catálogos son
    chicos.
21. No se implementó el panel con gráficas del kickoff (§7.4); en su lugar **Reportes** muestra
    totales del período, contado/crédito, cobrado, por cobrar y los más vendidos. Menos piezas
    para el usuario.

## Reportes y respaldos
22. **Reporte para el contador:** Excel (.xlsx) con hojas Ventas, Libro de ventas (facturas),
    Por día, Abonos y Saldos; y **ZIP** con ese Excel más el PDF de cada documento (copia emisor).
23. **Respaldos:** `php artisan backup:run` (pg_dump comprimido en `storage/app/backups`,
    guarda los últimos 30). Programado a diario a las 18:00 vía el scheduler de Laravel, y botón
    "Hacer respaldo ahora" + descarga en Ajustes. Copiar a USB sigue siendo manual (ver entrega).

## Identidad visual
24. **Logo en documentos con PNG (`public/brand/logo-print.png`, 400 px, derivado de `logo-mono.svg`).** — php-svg-lib de dompdf no escala los trazos SVG. El logo va junto al emisor: 22 mm en carta, 16 mm en media carta; en el estado de cuenta de media carta se reduce a 13 mm para conservar una página en el ejemplo. Afecta la apariencia de reimpresiones antiguas, no sus datos; caché `print-v3`, sin borrar la anterior. — Plantillas `documents` y `print-style.blade.php`. Si un ajuste futuro vuelve a desbordar el estado de cuenta de media carta a 13 mm, la alternativa prevista es omitir su logo solo en ese tamaño y documentarlo.
25. **Verde de marca #2D7A3E y cajón #1E5A2C; títulos Bricolage Grotesque, texto Atkinson.** — Identidad del logo real y lectura clara; reemplaza los colores y títulos anteriores de la decisión 19. — Tokens CSS y tema Vuetify.

## Montos en pantalla y credenciales
26. **Montos en pantalla con enteros (`resources/js/lib/money.js`), mismo redondeo que Money; prueba de paridad con JSON compartido.** — Evita diferencias de un centavo entre la vista previa y el documento emitido. Usa centavos seguros y BigInt en productos intermedios; el servidor conserva el cálculo que se guarda. — Helpers y pruebas PHP/Node con `tests/fixtures/money-cases.json`.
27. **Aviso de contraseñas de prueba en Usuarios y Ajustes, solo admin.** — Recuerda cambiar las contraseñas y el PIN de demostración antes de operar. Solo revisa usuarios activos con Hash::check en esas dos páginas; devuelve nombres y un indicador de PIN, nunca contraseñas ni hashes. `config/default_credentials.php` conserva los valores de siembra ADMIN_USERNAME/ADMIN_PASSWORD/ADMIN_PIN con configuración cacheada; también se reconocen los valores públicos originales. — Cambiar contraseñas/PIN en Usuarios; el aviso desaparece al corregirlos.

## Entradas y movimientos anulados
28. **Cantidades y precios aceptan coma o punto decimal; una casilla escrita pero inválida bloquea el guardado; las vacías se ignoran.** — Cantidades vacías no crean líneas; precio vacío conserva el valor cero y el aviso de precio faltante. Máximo dos decimales: `1.000` se rechaza, no se interpreta como mil. Cantidad cero o negativa no se permite. `toCents/toQty` mantienen signo y redondeo para cálculos financieros; la entrada de venta se valida antes de usarlos. — Parser y Nueva venta.
29. **Los movimientos anulados se muestran en la cuenta del cliente, marcados y sin sumar.** — Conservan monto original, motivo, autor y fecha de anulación; cargo/abono cero y saldo anterior. La ficha tacha el monto y el estado imprimible conserva la información en el detalle textual, sin cambiar su plantilla. Los cálculos SQL/FIFO siguen usando solo activos. — Opción informativa `AccountsService::ledger`.
