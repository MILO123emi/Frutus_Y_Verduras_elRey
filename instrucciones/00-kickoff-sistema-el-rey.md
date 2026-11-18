# Kickoff — Sistema de ventas "Frutas y Verduras El Rey"

> **Para:** el Claude (Claude Code) que va a construir el proyecto.
> **Autor:** Claude (planeación) con Farid · **Fecha:** 2026-10-14
> **Contexto ampliado:** `facturacion-verduras-estado-del-proyecto.md` (análisis fiscal y de negocio) y `orden-de-pedido-ejemplo.jpg` (foto del formulario actual). Si algo de esos archivos contradice este, **manda este archivo** porque es más reciente. Ojo: aquel doc usa nombres de columnas en español como borrador; aquí ya están en inglés.

---

## 0. Cómo trabajar (léelo primero)

1. **Plan antes de código.** Al empezar cada parte (§8), propón: cambios en la BD, rutas/controladores/servicios nuevos, páginas Vue y librerías (con versión verificada como compatible). **Espera la confirmación de Farid** antes de implementar.
2. **Por partes y en orden** (§8). Al cerrar cada parte, entrega un resumen de lo hecho, cómo probarlo y qué criterios de aceptación cumple. **Espera confirmación antes de pasar a la siguiente.**
3. **Pregunta si algo no está claro** o si hay varias opciones razonables. Pero **no vuelvas a preguntar lo que ya está decidido en §5**.
4. **Cambios quirúrgicos.** No reescribas archivos enteros sin necesidad y no agregues features que nadie pidió (hay una lista de "después" en §10).
5. **Idiomas:** la UI va en español. Código, tablas, columnas y rutas internas van en inglés (convención de Ayala Market). Las URLs visibles pueden ir en español (`/ventas`, `/panel`).
6. **Tests (Pest)** para la lógica crítica que se indica en cada parte.
7. **En la Parte 0A crea un `CLAUDE.md`** en la raíz con un resumen de §3 (reglas) y §5 (decisiones y convenciones), para que las sesiones futuras lo carguen solas.

## 1. Contexto

- **Negocio:** Frutas y Verduras "El Rey", en La Ceiba, Atlántida, Honduras. Vende frutas, verduras y granos básicos al por mayor y al detalle. El dueño es el abuelo de Farid, que **no es una persona técnica**.
- **Cómo trabaja hoy:** usa talonarios pre-impresos de **"ORDEN DE PEDIDO"** (numerados, ej. Nº 012954) y llena **2 copias con papel carbón**: el original se lo da al cliente y la copia la archiva. **Cada cierto período le entrega al contador todas las copias de ese período.**
- **Qué escribe por línea:** cantidad, unidad (matate, saco, caja, unidad, bolsa, maleta…), descripción (el formulario trae 29 productos pre-impresos y el resto lo escribe a mano), precio unitario y total de la línea. Al final pone el total general, el nombre del cliente y la fecha.
- Algunos clientes compran **al crédito**.
- **Estado del software: proyecto nuevo, todavía no hay código.**
- **Desarrollador:** Farid. Ya construyó **Ayala Market** (un POS) con este mismo stack. Reutiliza sus patrones: `SaleService` con lock transaccional, snapshots de precio en `SaleItem`, flujo de PIN de admin con cooldown/lockout y el componente reutilizable de filtros de tabla en Vuetify.
- **Uso:** una PC en el negocio, con el sistema instalado localmente y **sin depender de internet**.

**Datos del emisor** (para el seeder de `business_settings`, tomados del formulario):

| Campo | Valor |
|---|---|
| trade_name | Frutas y Verduras "El Rey" |
| activity | Venta de frutas, verduras, granos básicos · Venta al por mayor y al detalle |
| address | Frente a Residencial Génova, Calle de Copena hacia el Muelle de Cabotaje, La Ceiba, Atlántida, Honduras, C.A. |
| phone | 9967-2292 |
| rtn | 01041959002984 |
| legal_name | ⏳ por confirmar (no aparece en el formulario) |

## 2. Objetivos, en orden de prioridad

1. Registrar una venta e imprimirla **mucho más rápido que a mano**.
2. Control de ventas: listado, visor y panel de desempeño.
3. Que el contador reciba **todo un período sin fricción**, en un ZIP con PDFs y resumen en Excel.
4. Control de deudas (Parte 5).

## 3. Reglas de negocio (invariantes, no negociables)

1. **Nunca se elimina un documento, solo se anula**, registrando motivo, usuario y fecha. No hay botón de borrar, ni soft delete, ni borrados en cascada sobre `sales`/`sale_items`.
2. **La numeración es correlativa por serie y nunca se repite ni se salta.** El número se asigna dentro de una transacción con `SELECT … FOR UPDATE` sobre la fila de la serie, y hay `unique(document_series_id, number)`.
3. **Un documento emitido es inmutable**: líneas, montos, cliente y fecha no cambian. Para corregir, se anula y se emite uno nuevo.
4. **Snapshots:** cada venta guarda una copia de los datos del emisor (`issuer_snapshot`), del cliente y de cada línea (descripción, unidad, precio, tipo de impuesto). Así, volver a renderizar un documento viejo da **exactamente lo que se imprimió**, aunque después cambie el catálogo o la dirección del negocio.
5. **No es factura fiscal.** El documento actual es una "ORDEN DE PEDIDO" **sin CAI**. Ningún documento impreso, PDF ni nombre de archivo puede decir **"FACTURA"** mientras su serie no tenga CAI.
   - El prefijo y el título salen de la configuración de la serie: hoy `OP` y "ORDEN DE PEDIDO".
   - Si en el futuro se agrega una serie fiscal, usará `FAC` y llevará los campos obligatorios del SAR (CAI, rango autorizado, fecha límite de emisión, RTN del emisor y del cliente). Diséñalo para que eso sea **configuración más una plantilla nueva**, no una reescritura.
6. **Dinero:** nunca `float`. En BD se usa `numeric(12,2)` y los cálculos se hacen con bcmath o brick/math. El total de la línea es `round(cantidad × precio, 2)`.
7. **Impuesto:** los productos son **exentos** por defecto. Cada producto tiene `tax_type (exempt|g15|g18)`, cada línea guarda su snapshot y la venta guarda subtotales separados.
8. **Anuladas:** aparecen en listados y en el ZIP, marcadas como anuladas, y **se excluyen de todos los totales y métricas**.
9. **Fechas:** zona horaria `America/Tegucigalpa`. Los rangos son **inclusivos** (la fecha fin incluye todo ese día) y la semana empieza el **lunes**.
10. **UI para alguien no técnico:** botones grandes, textos claros, pocos pasos y uso posible con el teclado. Nada de efectos pesados (en Ayala, `backdrop-filter` causó problemas de rendimiento).

## 4. Glosario: UI vs código

| Concepto | En la UI | En el código |
|---|---|---|
| Venta / documento | Menú "Ventas"; el documento impreso se titula "ORDEN DE PEDIDO" | `Sale`, `SaleItem` |
| Estado | Activa / Anulada | `status: issued \| voided` |
| Copias | **ORIGINAL – CLIENTE** / **COPIA – EMISOR** | `variant: original \| copy` |
| Condición | Contado / Crédito | `terms: cash \| credit` |
| Serie | Serie | `DocumentSeries` |

## 5. Decisiones tomadas (no volver a preguntar)

| Área | Decisión | Por qué / nota |
|---|---|---|
| Backend | Laravel (versión estable actual) con PHP 8.3+ | El stack que Farid domina |
| Frontend | Inertia.js + Vue 3 + Vuetify 3 + Vite | **Todo empaquetado localmente, sin CDNs** (el sistema corre offline) |
| BD | PostgreSQL con la extensión `unaccent` | Buscar "Maria" encuentra "María"; `date_trunc` para el panel |
| Auth | Igual que Ayala (Breeze + Inertia/Vue) si es compatible con la versión actual de Laravel. Si no, el starter kit oficial de Vue con componentes de Vuetify. **Propón la opción en el plan de 0A.** | Roles: `admin`, `seller`, `accountant` (solo lectura y reportes) |
| Plantilla del documento | **Una sola plantilla Blade** (`resources/views/documents/order.blade.php`) que sirve para el **visor, la impresión y el PDF** | Garantiza que el visor muestre la venta "tal como se imprime". Su CSS debe ser compatible con dompdf: tablas, **nada de flex ni grid** |
| PDF | `barryvdh/laravel-dompdf`, con la fuente DejaVu Sans (soporta acentos y ñ) | Es PHP puro, sin binarios ni Node/Chrome, así que se instala fácil en la PC del negocio |
| ZIP | `maennchen/zipstream-php` (streaming, método STORE) | No carga todo en memoria; los PDFs ya vienen comprimidos. Alternativa: `ZipArchive` con archivo temporal |
| Excel | `phpoffice/phpspreadsheet` (directo, o vía `maatwebsite/excel` si es compatible) | **.xlsx, no CSV**: un CSV con acentos se ve mal en Excel de Windows, y el .xlsx permite formato |
| Gráficas | `chart.js` + `vue-chartjs` | Livianas y suficientes |
| Impresión | La plantilla en HTML + `window.print()`, con 2 copias. En producción, Chrome con `--kiosk-printing` | Imprimir sin pasar por PDF es más rápido |
| Colas | **Sin queue worker en v1** | Menos piezas que mantener en la PC del abuelo. Ver la estrategia de caché de PDFs en §7.2 |
| Tests | Pest | |

## 6. Modelo de datos (Partes 0–4)

```
business_settings  trade_name, legal_name?, activity, rtn, address, phone, print_settings(json)
document_series    id, code ('OP'), title ('ORDEN DE PEDIDO'), kind(order|invoice), origin(system|paper_book),
                   next_number, number_from, number_to?, pad_length (6),
                   cai?, establishment?, emission_point?, doc_type_code?, issue_deadline?,   ← solo kind=invoice (futuro)
                   status(active|exhausted|expired|inactive)
customers          id, name, rtn?, phone?, address?, default_terms(cash|credit),
                   credit_limit?, credit_days?, active, notes
units              id, name, abbreviation, active
products           id, name, default_unit_id?, reference_price?, tax_type(exempt|g15|g18), form_order?, active
sales              id, document_series_id, number, full_number (único, ej. 'OP-100123'),
                   issued_at (timestamptz), issue_date (date),
                   customer_id?, customer_name_snapshot, customer_rtn_snapshot?, issuer_snapshot (json),
                   terms(cash|credit), due_date?,
                   subtotal_exempt, subtotal_exonerated, subtotal_g15, subtotal_g18, tax_15, tax_18, total,
                   status(issued|voided), void_reason?, voided_at?, voided_by?,
                   print_count, user_id, notes, timestamps
sale_items         id, sale_id, position, product_id?, description_snapshot, unit_snapshot,
                   quantity numeric(10,2), unit_price numeric(12,2), line_total numeric(12,2), tax_type_snapshot
users              (auth) + role, pin_hash
audit_logs         id, user_id, action, auditable_type, auditable_id, data(json), created_at
```

- **Índices:** `sales(issue_date)`, `sales(status, issue_date)`, `sales(customer_id, issue_date)`, `unique(document_series_id, number)`, `sale_items(product_id)`.
- La **Parte 5** agrega `payments`, `payment_allocations` y `opening_balances`.

**Seeders:**
- `business_settings` con los datos de §1.
- `units`: matate, saco, caja, unidad, bolsa, maleta. Agrega docena, libra, quintal y manojo **marcadas como "por confirmar"**.
- `products`: los 29 productos del formulario, **en este orden** (`form_order`), todos `exempt` y sin precio: Repollo, Tomate pera, Tomate manzano, Papas, Cebolla roja, Cebolla amarilla, Chiles rellenos, Ajos, Lechuga chapina, Zanahoria, Patastillos, Apio chapín, Habichuelas, Remolacha, Pepinos, Jilotes, Ayotes tiernos, Melón, Piñas, Bananos maduros, Yuca, Perejil, Limones, Coliflor, Camote, Aguacate, Brócoli, Cilantro, Sandías.
- `document_series`, dos series:
  - `OP` (origin `system`): número inicial **⏳ por confirmar**. Déjalo configurable; el placeholder es `100001`, para no chocar con los talonarios de papel (≈012954).
  - `TAL` (origin `paper_book`): para **contingencias** como un corte de luz. Si se vende en el talonario de papel, después se registra en el sistema **con el número del papel** (entrada manual, única dentro de esa serie).
- Un usuario `admin` inicial.
- **Factory para generar ~20,000 ventas de prueba** realistas, para medir rendimiento.

## 7. Especificación por parte

### 7.0 Base: registrar, imprimir y anular (Partes 0A y 0B)

**0A — Cimientos (sin UI, salvo el login)**
- Scaffold, auth y roles, migraciones, modelos, seeders y factory.
- `DocumentNumberService`: asigna el siguiente número de la serie (lock + transacción) y arma `full_number` con padding fijo (`OP-100123`).
- `SaleService`:
  - `issue(data)`: calcula subtotales e impuestos, guarda los snapshots y escribe el audit log, todo en una sola transacción.
  - `void(sale, reason, user)`: solo puede hacerlo un admin, el motivo es obligatorio y queda en el audit log. Además invalida la caché de PDFs (§7.2).
- Guardas de inmutabilidad: actualizar una venta emitida (fuera de la anulación) lanza una excepción.
- Crear el `CLAUDE.md` (§0.7).
- **Tests:**
  - Numeración: emisiones paralelas producen números consecutivos sin huecos ni duplicados. Si los procesos paralelos son difíciles de lograr, como mínimo verifica el lock y la restricción única.
  - Inmutabilidad.
  - Redondeo y subtotales.
  - Las anuladas quedan fuera de los totales.

**0B — Pantalla de venta, impresión y anulación**
- **Nueva venta:**
  - Cliente con autocompletar, o "Consumidor final".
  - Selector Contado/Crédito, con el valor por defecto del cliente.
  - Líneas, con dos modos de captura:
    - **Modo búsqueda:** escribe ("rep" → Repollo) → se rellena la unidad por defecto → el precio se rellena con **el último precio cobrado a ese cliente por ese producto y unidad** (si no hay, el precio de referencia) → cantidad → Enter pasa a la siguiente fila.
    - **Modo planilla:** muestra los 29 productos **en el mismo orden del formulario de papel** y se llenan solo las filas que se usan. Es el modo más familiar para el abuelo.
  - Se permite un **producto libre** que no esté en el catálogo, ofreciendo "¿Agregar al catálogo?" sin interrumpir.
  - Botón **"Repetir último pedido"** del cliente.
  - Totales en vivo. **Ctrl+Enter = guardar e imprimir.**
- **Impresión:** renderiza la plantilla con **2 copias** (ORIGINAL – CLIENTE y COPIA – EMISOR) y suma uno a `print_count`. El tamaño de papel (carta o media carta) está **⏳ por confirmar**: déjalo en `print_settings`.
- **Anular:** solo un admin, con **PIN** (patrón Ayala) y motivo obligatorio.
- **Contenido de la plantilla:**
  - Encabezado del emisor tomado del snapshot.
  - Título de la serie, número, fecha y hora, cliente y condición.
  - Tabla `Cant. · Unidad · Descripción · Precio · Total` **solo con las líneas usadas**.
  - Total grande.
  - Rótulo de la copia.
  - Si está anulada, **marca de agua "ANULADA"** más el motivo y la fecha.
- **Aceptación:** una venta típica de 5 a 8 líneas se registra e imprime **solo con teclado**, sin tocar el mouse.

### 7.1 Listado y visor (Parte 1)

- **`/ventas`**, con el filtro por defecto en **este mes**.
- **Filtros:**
  - Rango de fechas, con atajos: Hoy, Esta semana, Este mes, Mes anterior y Personalizado.
  - Cliente (autocompletar, sin distinguir acentos).
  - Número (acepta `100123` u `OP-100123`).
  - Estado: Todas, Activas o Anuladas.
  - Además, un **cuadro de búsqueda rápida** que acepta número o cliente.
  - El **componente de filtros de fecha es compartido** con la Parte 3 y la Parte 4.
- Los filtros viven en la **query string**, para que recargar la página no los pierda. Reutiliza el componente de filtros de Ayala.
- Tabla con **paginación del servidor** (`v-data-table-server`), ordenada por fecha y número, de más reciente a más antigua. Columnas: Número, Fecha, Cliente, Condición, Total y Estado (chip). Las anuladas se ven en gris con el chip "ANULADA".
- Al pie: cantidad de ventas y total de las **activas** según los filtros aplicados.
- **Visor `/ventas/{sale}`:**
  - Muestra la plantilla en un `iframe` (`/ventas/{sale}/documento?variant=original|copy`).
  - Selector Original/Copia.
  - Botones grandes: **Imprimir**, **Descargar PDF** (se activa en la Parte 2) y **Anular** (solo admin).
- **Aceptación:** con las 20k ventas de prueba, filtrar y paginar se siente instantáneo, y el visor es idéntico a lo impreso.

### 7.2 Descarga individual en PDF (Parte 2)

- **`GET /ventas/{sale}/pdf?variant=original|copy`** genera el PDF con la misma plantilla y le pone el rótulo de la variante.
- **Nombre del archivo:** `OP-100123_2026-10-01_Juan-Perez_ORIGINAL.pdf` (o `_COPIA`). Si está anulada lleva `_ANULADA` antes de la variante.
  - El cliente se convierte a ASCII (`Str::ascii`), los espacios pasan a `-`, se quitan los caracteres inválidos y se corta a 40 caracteres. Sin cliente queda `Consumidor-Final`.
  - El número usa **padding fijo**, así el orden alfabético coincide con el numérico.
  - El prefijo sale de la serie, **nunca está fijo como "FAC"**.
- **Caché de PDFs:**
  - Ruta: `storage/app/documents/{yyyy}/{mm}/{full_number}_{variant}.pdf`.
  - Se genera **la primera vez que se pide** y después se sirve desde la caché.
  - Al anular se borra la caché de esa venta, y se regenera con la marca "ANULADA".
  - El comando `php artisan documents:warm-pdfs --from= --to=` pre-genera PDFs (se programa de noche en la Parte 6).
  - Si el servidor es PHP-FPM, se puede generar además con `dispatch(...)->afterResponse()` al emitir. Nada de esto debe hacer lenta la pantalla de venta.
  - La caché es **derivada y se puede borrar**; la fuente de verdad es la BD con sus snapshots.
- La descarga va por un **enlace normal** (`<a href>`), **no por Inertia** (XHR).
- **Tests:** el nombre del archivo; que la variante aparezca en el contenido del PDF; la marca ANULADA; que la caché se invalide al anular.

### 7.3 ZIP por período para el contador (Parte 3)

- **Pantalla "Reporte para el contador":** fecha de inicio y fecha de fin (atajos: Mes anterior, Este mes) y la variante. Por defecto es **COPIA – EMISOR**, porque es la copia que el abuelo le entrega al contador. Pueden entrar `admin` y `accountant`.
- **Paso 1, vista previa:** `GET /reportes/periodo/resumen?from&to` devuelve cantidad de activas, anuladas y el total. La pantalla muestra algo como *"Se descargarán 412 órdenes (3 anuladas) · Total L 384,220.00"*.
  - **Si hay 0 ventas**, muestra el aviso "No hay ventas en ese rango" y **no aparece el botón de descargar**. El endpoint del ZIP también responde 422 en ese caso.
- **Paso 2, descarga:** `GET /reportes/periodo/zip?from&to&variant` hace streaming de los archivos ordenados por número ascendente:
  ```
  ElRey_Ventas_2026-10-01_a_2026-10-31.zip
  ├─ 00_RESUMEN_2026-10-01_a_2026-10-31.xlsx
  ├─ OP-100001_2026-10-01_Juan-Perez.pdf
  ├─ OP-100002_2026-10-01_Consumidor-Final.pdf
  └─ OP-100003_2026-10-02_Pulperia-La-Esperanza_ANULADA.pdf
  ```
  (Dentro del ZIP no hace falta el sufijo de variante, porque todos los PDFs son de la misma.)
- **`00_RESUMEN.xlsx`:**
  - **Hoja "Ventas":** una fila por documento, **incluidas las anuladas**. Columnas: Número, Fecha, Cliente, RTN, Condición, Subtotal exento, Subtotal gravado, ISV, Total, Estado, Motivo de anulación.
    - Las filas anuladas se ven marcadas (en gris) y **no suman en los totales**.
    - Al final: total del período (solo activas), número de activas y número de anuladas.
    - Formato: fecha `dd/mm/yyyy`, moneda `L #,##0.00`, encabezado congelado y autofiltro.
  - **Hoja "Por día":** fecha, número de ventas y total.
  - Los totales se calculan en PHP: no dependas de fórmulas.
- **Rendimiento:**
  - Si algún PDF no está en caché, se genera en ese momento antes de agregarlo al ZIP.
  - Usa streaming, sin cargar todo en memoria, y un `set_time_limit` adecuado para esta ruta.
  - **Mide con 3,000 ventas**, con caché fría y con caché tibia, y reporta los tiempos.
  - Objetivo: un mes con la caché tibia se descarga en menos de ~30 s.
- **Tests:**
  - El ZIP trae N PDFs más el xlsx, con nombres correctos.
  - Las anuladas llevan sufijo y no suman.
  - El rango es inclusivo en los bordes.
  - Un rango vacío da 422.

### 7.4 Panel de desempeño (Parte 4)

- **`/panel`**, con el mismo componente de filtros de fecha. Por defecto: **este mes**.
- **Tarjetas grandes:**
  - **Ventas (L)**, **N.º de ventas** y **Ticket promedio**, cada una con su % frente al período anterior.
  - **Contado vs Crédito (L)**.
- **Período anterior:**
  - Con un atajo de mes, se compara con el **mismo tramo del mes anterior** (1–14 oct contra 1–14 sep).
  - Con un rango personalizado, con el **mismo número de días inmediatamente antes**.
  - **La pantalla muestra contra qué rango se compara** (ej. "vs 1–14 sep").
  - Si hay división entre 0, se muestra "—" (nunca `Infinity%` ni `NaN`).
- **Gráfica de tendencia de ventas:**
  - Por **día** si el rango es de hasta 31 días, por **semana** hasta ~6 meses y por **mes** si es más largo.
  - Opcional: la línea del período anterior, punteada.
- **Top 10 clientes por monto.** "Consumidor final / sin cliente" se muestra como una fila aparte que no compite en el ranking.
- **Top 10 productos por monto.**
  - Las cantidades se muestran **por unidad** ("85 sacos · 12 cajas"). **Nunca sumes unidades distintas.**
  - Las líneas libres (sin producto del catálogo) se agrupan por descripción normalizada: minúsculas, sin acentos y sin espacios sobrantes.
- **Las anuladas se excluyen de todo.**
- Las agregaciones se hacen **en SQL** (`date_trunc`, `GROUP BY`), no en PHP. Con las 20k ventas de prueba, el panel debe responder en menos de 1 s.
- **Tests:** agregados contra un fixture conocido, que incluya anuladas, bordes de rango y porcentajes con cero.

### 7.5 Deudas (Parte 5, con su propio plan cuando llegue)

Ventas a crédito, **abonos** (aplicación FIFO automática con opción manual), **saldos iniciales** para las deudas que ya existen en papel, **estado de cuenta** por cliente y **antigüedad de saldos** (0–30 / 31–60 / 61–90 / 90+ días). Al terminar, el panel suma las tarjetas "Cobrado" y "Por cobrar".

## 8. Orden de implementación

| Parte | Contenido | Tamaño |
|---|---|---|
| **0A** | Cimientos: scaffold, BD, seeders, servicios de numeración y venta, tests, `CLAUDE.md` | L |
| **0B** | Pantalla de venta, impresión de 2 copias y anulación con PIN | L |
| **1** | Listado y visor | M |
| **2** | Descarga individual en PDF y caché | S–M |
| **3** | ZIP por período con Excel de resumen | M |
| **4** | Panel de desempeño | M |
| **5** | Deudas | M–L |
| **6** | Despliegue en la PC del negocio: instalación, Chrome en modo app/kiosk, backups cifrados, tareas programadas (`documents:warm-pdfs` y backup) | M |

⚠️ **Detente y espera la confirmación de Farid al terminar cada parte.**

## 9. Preguntas abiertas

| Pregunta | ¿Qué bloquea? |
|---|---|
| Número inicial de la serie `OP`: ¿continuar después del último talonario (≈13,500) o empezar en 100001? | Nada: usa el placeholder configurable |
| Unidad y precio habituales de los 29 productos, y la lista completa de unidades | Nada: se editan en el catálogo |
| Tamaño de impresión: carta o media carta | El diseño final de la plantilla (0B) |
| Volumen real de ventas por día y por mes | Calibrar las pruebas de rendimiento (Parte 3) |
| La PC del negocio, ¿es Windows o Linux? | Parte 6 |
| ¿El contador quiere además un PDF único consolidado, o un formato propio para el Excel? | Ajustes de la Parte 3 |
| ¿El negocio debe emitir facturas fiscales con CAI? (lo responde el contador) | Nada de este plan; sería una serie fiscal futura |

## 10. Ideas para después (no implementar sin que Farid lo pida)

- Un PDF consolidado del período dentro del ZIP.
- Envío automático del ZIP al contador el día 1 de cada mes.
- Ventas por día de la semana.
- Reimpresiones marcadas como "REIMPRESIÓN".
- Serie fiscal con CAI: imprenta con impresora matricial, o registro como autoimpresor ante el SAR. Ver el doc de estado, §4.
- Módulo de compras y márgenes.
