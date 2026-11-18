# Sistema de Facturación — Frutas y Verduras "El Rey" (negocio del abuelo de Farid)

> **Documento de contexto para futuras sesiones de Claude.** Léelo completo antes de proponer código.
> Última actualización: **2026-10-12 (v2, con foto del formulario)** · Autor: Claude (análisis con Farid)
> Foto de referencia: `orden-de-pedido-ejemplo.jpg` (formulario Nº 012954, en blanco).
> **Estado: ANÁLISIS ACTUALIZADO · SIN CÓDIGO · FASE 1 DESBLOQUEADA** (se puede empezar a construir). **Pendiente en paralelo:** respuesta del contador sobre facturación fiscal (§4, §13).

---

## 0. TL;DR

| Tema | Conclusión |
|---|---|
| **Hallazgo de la foto** | Lo que el abuelo llama "factura" es una **ORDEN DE PEDIDO**: **no tiene CAI, ni rango autorizado, ni fecha límite de emisión**; número de 6 dígitos (Nº 012954). **No es un documento fiscal del SAR.** |
| Consecuencia técnica | El sistema puede imprimir la orden de pedido en **papel bond con numeración propia desde el día 1**. Ya no hace falta formulario de imprenta ni impresora matricial para arrancar. **La Fase 1 no depende del SAR.** |
| Riesgo fiscal (para el contador) | El reglamento de facturación (Acuerdo 481-2017) obliga a **personas naturales con actividades gravadas o no gravadas** a emitir **comprobante fiscal por toda venta de bienes, aunque no pague impuesto** (Arts. 3 y 9). Que las verduras sean exentas **no exime de facturar**. Lo tiene que aclarar el contador (§4). |
| Qué se construye | Sistema local para registrar ventas rápido, imprimir original (cliente) + copia (archivo), controlar ventas y deudas, y exportar todo al contador por rango de fechas. |
| Stack | **Laravel + Inertia.js + Vue 3 + Vuetify + PostgreSQL** (el de Ayala Market), instalado **local/offline-first** en la PC del negocio, en Chrome modo app. |
| Impresión | **Impresora láser B/N + papel bond**, 2 copias rotuladas "ORIGINAL – CLIENTE" / "COPIA – ARCHIVO". Si luego se emiten facturas con CAI, se agrega un driver nuevo sin reescribir (§6). |
| Impuesto | Frutas/verduras/granos básicos = **exento de ISV** por defecto; el producto tiene campo de tipo de impuesto (algunas frutas, p. ej. uvas y manzanas, sí pagan). |
| Prioridades del usuario | 1) Facturar rápido · 2) Control de ventas · 3) Contador obtiene el registro por plazo sin fricción · + control de deudas. |
| Siguiente paso | Arrancar Fase 1 (§12) y, en paralelo, llevar las preguntas del §13 al contador. |

---

## 1. Contexto del proyecto

**Datos del negocio (pre-impresos en el formulario):**

| Campo | Valor |
|---|---|
| Nombre comercial | **Frutas y Verduras "El Rey"** |
| Giro | Venta de frutas, verduras, granos básicos · **Venta al por mayor y al detalle** |
| Dirección | Frente a Residencial Génova, Calle de Copena hacia el Muelle de Cabotaje, La Ceiba, Atlántida, Honduras, C.A. |
| Celular | 9967-2292 |
| RTN | 01041959002984 (14 dígitos; por el formato parece RTN de **persona natural / comerciante individual** → confirmar con el contador) |
| Razón social | **No aparece** en el formulario (probablemente el nombre del abuelo) → confirmar |

- **Desarrollador:** Farid (full-stack Laravel/Vue/Inertia, PhpStorm). Ya construyó **Ayala Market** (POS del supermercado familiar) con este stack → reutilizar:
  - `SaleService` con **locking transaccional** → asignación atómica del número de documento.
  - Manejo de **ISV 15 % / 18 % / exento** con **snapshots de precio** en `SaleItem`.
  - Despliegue **LAN con Caddy + ufw** y **backup cifrado**.
  - Flujo: Claude planea y redacta `instructions/*.md`; Claude Code/Codex implementa con ediciones quirúrgicas.
- **Idioma de la UI:** español. Usuarios probables: el abuelo y/o un empleado → UI grande, simple, operable con teclado.

## 2. Cómo funciona hoy (proceso manual + lectura de la foto)

### 2.1 Proceso
1. Usa **talonarios de "Orden de Pedido"** pre-impresos y numerados. Cada hoja trae una **lista pre-impresa de 29 productos**; si el producto no está, lo escribe a mano en una fila en blanco.
2. Por línea escribe: **cantidad**, **unidad**, **precio unitario**, **total de línea**. Abajo escribe el **total** y arriba el **nombre del cliente** y la **fecha**.
3. Escribe con **papel carbón**: el **original se arranca y se entrega al cliente**; la **copia queda en la libreta**.
4. Unidades usadas (no exhaustivo): **matate**, **saco**, **caja**, **unidad**, **bolsa**, **maleta** (ej. culantro). Probablemente también docena, libra, quintal, manojo, ciento → confirmar.
5. Algunos clientes compran **al crédito**. Hoy no hay control formal de deudas.
6. "Cada cierto tiempo entrega las facturas o las libretas de copias **al cliente**." ⚠️ **Ambiguo:** (a) al **contador** o (b) al **cliente de crédito** como estado de cuenta. El diseño cubre ambos (§10, §11). **Confirmar con Farid.**
7. Lo que más tiempo le consume: **escribir a mano**.

### 2.2 Anatomía del formulario (foto `orden-de-pedido-ejemplo.jpg`)

Formulario vertical, largo y angosto (medir si se quiere imitar el tamaño; con papel bond ya no es necesario).

```
┌──────────────────────────────────────────────┐
│        FRUTAS Y VERDURAS "EL REY"            │  encabezado pre-impreso
│  Venta de frutas, verduras, granos básicos   │
│     VENTA AL POR MAYOR Y AL DETALLE          │
│  dirección · La Ceiba, Atlántida · celular   │
├──────────────────────────────────────────────┤
│ SEÑOR: ____________________________________  │  ← cliente (a mano)
│ FECHA: ________  R.T.N. 01041959002984  Nº 012954 (rojo) │
│               ORDEN DE PEDIDO                │
├──────┬─────┬──────────────┬─────────┬────────┤
│ CANT.│ (*) │ DESCRIPCION  │ PRECIO  │ TOTAL  │  (*) columna sin encabezado = UNIDAD
│      │     │ REPOLLO      │  L │ ¢  │  L │ ¢ │  PRECIO y TOTAL divididos en lempiras | centavos
│      │     │ … 29 productos pre-impresos …   │
│      │     │ (≈7 filas en blanco)            │
└──────┴─────┴──────────────┴─────────┴────────┘
 Pie de imprenta: IMPRESOS COPY PRINT · Tel. … · 24 TAL. · TINTA CIAN · Nº ≈12,301–13,500 · 20/01/2026
 "LA FACTURA ES BENEFICIO DE TODOS — EXÍJALA"
```

- **≈36 filas** en total (29 pre-impresas + ≈7 en blanco).
- **No tiene:** CAI, rango autorizado, fecha límite de emisión, razón social, casilla de total general (lo escribe abajo a mano), condición contado/crédito, firma, RTN del cliente.
- **Pie de imprenta** (lectura difícil, confirmar con el formulario físico): imprenta *Impresos Copy Print*, **24 talonarios**, numeración **≈12,301–13,500** (= 1,200 juegos = 24 × 50), impreso el **20/01/2026**. Si es así, al Nº 012954 le quedan ≈546 formularios.
- Ejemplo de numeración que dio Farid antes ("37042") era ilustrativo; el número real en curso es **012954**.

### 2.3 Catálogo inicial (seeder de `products`, en el orden del formulario)

| # | Producto | # | Producto | # | Producto |
|---|---|---|---|---|---|
| 1 | Repollo | 11 | Patastillos | 21 | Yuca |
| 2 | Tomate pera | 12 | Apio chapín | 22 | Perejil |
| 3 | Tomate manzano | 13 | Habichuelas | 23 | Limones |
| 4 | Papas | 14 | Remolacha | 24 | Coliflor |
| 5 | Cebolla roja | 15 | Pepinos | 25 | Camote |
| 6 | Cebolla amarilla | 16 | Jilotes | 26 | Aguacate |
| 7 | Chiles rellenos | 17 | Ayotes tiernos | 27 | Brócoli (impreso "Brocoly") |
| 8 | Ajos | 18 | Melón | 28 | Cilantro |
| 9 | Lechuga chapina | 19 | Piñas | 29 | Sandías |
| 10 | Zanahoria | 20 | Bananos maduros | | |

- Guardar el **orden del formulario** (`orden_formulario`) para que la pantalla pueda mostrar la lista igual que en papel (§9).
- Falta por producto: **unidad por defecto** y **precio de referencia** → preguntar al abuelo (§13). Granos básicos (frijol, arroz, maíz…) no están en la lista pero el negocio los vende → agregar.
- Todos **exentos** por defecto. 🔎 Confirmar con el contador si alguna fruta (p. ej. importada) es gravada.

## 3. Marco fiscal de Honduras

> Todo lo marcado 🔎 lo debe validar el **contador**. Las citas del Acuerdo 481-2017 vienen de una copia publicada por RSM Honduras; puede no incluir reformas posteriores.

- **Régimen de Facturación (SAR), Acuerdo 481-2017.** Un documento fiscal (factura) lleva **CAI** ligado a un **rango autorizado**, al **contribuyente/establecimiento** y a una **fecha límite de emisión**. Fuera de rango o después de la fecha límite → inválido.
- **Quién está obligado:** "Personas naturales y jurídicas que realicen actividades económicas **gravadas o no gravadas**" (Art. 3). Se debe emitir comprobante fiscal a quien se le **transfieran bienes**, incluso si la operación no está gravada (Art. 9).
  - Excepción: ventas a consumidor final de **L 50.00 o menos** no requieren documento en el momento (salvo que el comprador lo pida), pero se registran al final del día (Art. 9). No aplica a autoimpresores.
- **Original y copia** obligatorios en cualquier modalidad (Art. 5); factura: **original al cliente, copia al emisor** (Art. 10). Las copias se conservan durante el plazo de prescripción.
- **Identificación del comprador:** nombre + identificación o "CONSUMIDOR FINAL"; en ventas **mayores de L 10,000.00** es obligatorio nombre, apellido y tipo/número de identificación en el campo RTN (Art. 11). SAR puede cambiar el umbral.
- **Modalidades:**
  - **Imprenta:** formularios con CAI impresos por una imprenta del Registro Fiscal de Imprentas.
  - **Autoimpresor (sistema computarizado):** registrar cada sistema y puntos de emisión (Art. 47); requisitos técnicos (Art. 53): integración con al menos un sistema contable o de inventario, mecanismos de seguridad y control de auditoría, registros persistentes y disponibles de inmediato, código de barras 2D/3D opcional, y capacidad de generar archivos de texto para transmitir al SAR. Requiere declaración jurada, RTN al día y estar solvente (Art. 45). → **El diseño de este sistema ya apunta a cumplir el Art. 53** (audit log, inmutabilidad, exportaciones).
- **Número fiscal:** formato de 16 dígitos `EEE-PPP-TT-NNNNNNNN` (establecimiento, punto de emisión, tipo de documento — factura `01` —, correlativo). **No aplica a la orden de pedido actual.**
- **ISV:** 15 % general, 18 % ciertos bienes; frutas/verduras frescas y granos básicos **exentos** (salvo excepciones). En una factura fiscal se desglosan subtotales exento / exonerado / gravado 15 / gravado 18 / ISV / total.
- **Conservación:** 5 años mínimo; registros en español.
- **Solo documentos fiscales válidos** sirven como soporte de crédito fiscal o costo/gasto (Art. 37) → clientes que son negocios (restaurantes, pulperías, supermercados) **no pueden deducir** una orden de pedido. Puede que algunos le pidan factura.
- **Factura electrónica (CAEE):** SAR la implementa por fases empezando por grandes contribuyentes; el régimen CAI sigue vigente. 🔎

## 4. La decisión clave: orden de pedido vs factura fiscal

**Hay dos documentos distintos y el sistema debe soportar ambos:**

| | **Orden de pedido** (lo de hoy) | **Factura fiscal con CAI** (si el contador lo indica) |
|---|---|---|
| Naturaleza | Documento comercial interno, **no fiscal** | Comprobante fiscal del SAR |
| Numeración | Propia del sistema, libre | Rango autorizado por CAI, con fecha límite |
| Papel / impresora | **Bond + láser**, 2 copias | Ruta A: formulario con CAI de imprenta (papel continuo 2 partes) + **matricial** · Ruta B: **autoimpresor** (bond + láser) con registro ante SAR |
| Trámite | Ninguno | A: pedir a la imprenta un tiraje de facturas con CAI · B: registro SAR-926/927 + requisitos del Art. 53 |
| Qué decir en el papel | Título **"ORDEN DE PEDIDO"**. **Nunca imprimir la palabra "FACTURA"** en un documento sin CAI. | Todos los campos obligatorios (§3) |

**Recomendación:**
1. **Fase 1 imprime órdenes de pedido en papel bond** (mismo estatus legal que hoy, cero trámites, se puede usar de inmediato).
2. Modelar desde el inicio **series de documento** (`document_series`, §7) con `tipo = orden_pedido | factura`, para que la factura con CAI entre después **sin reescribir nada**: solo una serie nueva + un driver de impresión.
3. **En paralelo**, Farid/el abuelo hablan con el contador (preguntas §13). Si el contador dice que debe facturar con CAI:
   - **Ruta B (autoimpresor)** es la que mejor aprovecha el sistema (papel bond, numeración automática, camino a factura electrónica).
   - **Ruta A (imprenta + matricial)** es la más rápida de implementar mientras se tramita la B. La imprenta actual (Impresos Copy Print) es candidata si está en el Registro Fiscal de Imprentas.

## 5. Stack recomendado y por qué

**Elegido: Laravel + Inertia.js + Vue 3 + Vuetify + PostgreSQL.**

- Farid domina el stack y tiene patrones reutilizables de Ayala Market.
- Inertia + Vue: pantalla de venta rápida, orientada a teclado.
- PostgreSQL: `numeric` exacto, `SELECT ... FOR UPDATE` para la numeración, `pg_dump` para backups.
- Alternativa: **SQLite** si se quiere instalación mínima en una sola PC → dinero en **centavos enteros**.

**Impresora:** **láser monocromática** (costo por página bajo, impresión durable). Descartada la **térmica** (se borra con el tiempo y la copia es registro). La **matricial** solo si se elige Ruta A.

**Descartados:**

| Opción | Por qué no |
|---|---|
| App de escritorio desde cero (C#, Electron, Tauri) | Stack nuevo, sin reutilizar Ayala. Si se quiere `.exe`, envolver la app Laravel después (p. ej. NativePHP). |
| SaaS en la nube | Depende de internet. Nube solo para backups/exportaciones. |
| Excel / Google Sheets | Sin control de numeración, sin integridad, deudas poco confiables. |
| Filament | Mezcla Livewire con Vue/Inertia. Un solo frontend. |
| Software comercial hondureño | Opción a cotizar solo si se va por autoimpresor y uno ya está registrado. Contra: costo mensual, poca personalización de unidades y deudas. |

## 6. Arquitectura y despliegue

```
[PC del negocio]  (Windows o Linux; UPS obligatorio)
 ├─ Laravel (PHP) + PostgreSQL  ← servidor local
 ├─ Chrome en modo app (--app=http://localhost)  ← lo que ve el abuelo
 ├─ Impresora láser (papel bond)
 └─ Backup diario: pg_dump cifrado → USB + nube cuando haya internet
```

- **Offline-first.** Internet solo para backups y envío de reportes.
- **Servicios de dominio:**
  - `DocumentNumberService` — asigna el siguiente número de la serie activa en transacción con lock de fila sobre `document_series`. Para series tipo `factura`, valida rango CAI y fecha límite y arma el formato `EEE-PPP-TT-NNNNNNNN`. Aquí se enchufa la factura fiscal/electrónica en el futuro.
  - `SaleService` — crea la venta con snapshots (producto, unidad, precio, impuesto, cliente), calcula subtotales, contado/crédito, anulación. (Mismo nombre y patrón que Ayala.)
  - `PaymentService` — abonos y su aplicación a ventas a crédito.
  - `DocumentPrinter` (interfaz):
    - `PlainPaperPrinter` (**Fase 1**): vista HTML de la orden con CSS de impresión; 2 copias rotuladas. Impresión silenciosa con Chrome `--kiosk-printing` (o PDF de respaldo).
    - `DotMatrixFormPrinter` (solo si Ruta A): texto ESC/P en cuadrícula calibrada al formulario con CAI, enviado en crudo (`lp -o raw` / `copy /b`).
  - `AccountantExportService` — exportaciones por rango (§11).
- **Zona horaria:** `America/Tegucigalpa`. Moneda: Lempiras (L), 2 decimales.

### 6.1 Diseño de la orden impresa (Fase 1)
- Encabezado igual al formulario actual (nombre, giro, dirección, celular, RTN) para que clientes la reconozcan.
- Título **ORDEN DE PEDIDO**, **Nº** (serie del sistema), fecha y hora, cliente, condición **Contado / Crédito**.
- Tabla: **Cant. · Unidad · Descripción · Precio · Total**, **solo con las líneas usadas** (no la lista de 29).
- **Total** grande al pie. Opcional para clientes de crédito: **saldo anterior / esta orden / saldo nuevo** (útil para cobrar).
- Rótulo por copia: **ORIGINAL – CLIENTE** / **COPIA – ARCHIVO**.
- Tamaño: definir con el abuelo **carta** o **media carta**; paginar si hay muchas líneas.

## 7. Modelo de datos (borrador)

```
business_settings   nombre_comercial, razon_social?, giro, rtn, direccion, telefono, logo?, print_settings(json)
document_series     id, tipo(orden_pedido|factura), nombre, origen(sistema|talonario_papel),
                    numero_desde, numero_hasta?, siguiente_numero, padding (6),
                    -- solo factura CAI: modalidad(imprenta|autoimpresor), cai, establecimiento(3),
                    --                   punto_emision(3), tipo_documento(2,'01'), fecha_limite_emision
                    estado(activa|agotada|vencida|inactiva), notas
customers           id, nombre, rtn?, telefono?, direccion?, condicion_default(contado|credito),
                    limite_credito?, dias_credito?, activo, notas
units               id, nombre (matate, saco, caja, unidad, bolsa, maleta, …), abreviatura, activo
products            id, nombre, unidad_default_id?, precio_referencia?, tipo_impuesto(exento|g15|g18),
                    orden_formulario?, activo
sales               id, document_series_id, numero, numero_completo (único), fecha,
                    customer_id?, cliente_nombre_snapshot, cliente_rtn_snapshot?,
                    condicion(contado|credito), fecha_vencimiento?,
                    subtotal_exento, subtotal_exonerado, subtotal_g15, subtotal_g18, isv_15, isv_18, total,
                    estado(emitida|anulada), motivo_anulacion?, anulada_en?, anulada_por?,
                    veces_impresa, user_id, notas, timestamps
sale_items          id, sale_id, orden, product_id?, descripcion_snapshot, unidad_snapshot,
                    cantidad numeric(10,2), precio_unitario numeric(12,2), total_linea numeric(12,2),
                    tipo_impuesto_snapshot
payments (abonos)   id, customer_id, fecha, monto, metodo(efectivo|transferencia|cheque|otro), referencia?, notas, user_id
payment_allocations id, payment_id, sale_id, monto
opening_balances    id, customer_id, fecha, monto, descripcion   ← deudas previas al sistema
users               id, nombre, usuario, rol(admin|vendedor|contador_lectura), pin/password
audit_log           id, user_id, accion, entidad, entidad_id, datos(json), created_at
```

Derivados: saldo de venta = `total − Σ allocations`; saldo del cliente = `Σ saldos de ventas a crédito + saldos iniciales − abonos no aplicados`; "último precio cobrado a este cliente por este producto/unidad" = consulta sobre `sale_items`.

**Series iniciales sugeridas:**
- `ORDEN-SISTEMA` (origen `sistema`): arranca en un número **que no choque con los talonarios** (p. ej. `100001`, o el siguiente número libre después del último talonario impreso; decidir con el abuelo).
- `ORDEN-TALONARIO` (origen `talonario_papel`): para **contingencia** (corte de luz/PC dañada). Se sigue usando el talonario y después se registra en el sistema **con el número del papel** (entrada manual del número, única por serie). Así las ventas en papel también entran a reportes y deudas.

## 8. Reglas de negocio (invariantes)

1. **La numeración de serie `sistema` nunca se salta ni se repite.** Asignación con lock sobre `document_series`; `numero_completo` único.
2. **Series `factura`**: no emitir fuera de rango ni después de `fecha_limite_emision`; alertas con < 100 números o < 30 días.
3. **Una venta emitida es inmutable.** Corregir = anular (con motivo) y emitir otra. Nada se borra; las anuladas salen en reportes marcadas.
4. **Snapshots** en cada línea; cambiar el catálogo no altera ventas pasadas.
5. **Documentos sin CAI nunca dicen "FACTURA".**
6. **Umbral L 10,000** (solo series `factura`): exigir nombre e identificación del comprador.
7. **Redondeo:** total de línea = `round(cantidad × precio, 2)`; total = Σ líneas + ISV.
8. **Contado** nace pagada (pago automático, para que el cierre de caja cuadre); **crédito** genera saldo.
9. **Serie `talonario_papel`**: número manual, único, con advertencia si está fuera del rango del talonario registrado.

## 9. Pantalla de venta — prioridad #1 (velocidad)

Meta: **mucho más rápido que a mano**. Medir el tiempo actual por orden como línea base.

- **100 % teclado.** Cliente (autocompletar) → Enter → líneas → `Ctrl+Enter` guardar e imprimir.
- **Dos modos de captura** (decidir con el abuelo cuál usa más):
  - **Modo planilla (familiar):** muestra los **29 productos en el mismo orden del formulario de papel**; el abuelo solo llena cantidad/unidad/precio en las filas que necesita, igual que hoy. Filas libres al final para productos fuera de la lista.
  - **Modo búsqueda:** escribir "rep" → *Repollo*; unidad por defecto; precio = **último precio cobrado a ese cliente** por ese producto/unidad; cantidad → Enter → siguiente.
- **Producto fuera del catálogo:** nombre libre + "¿Agregar al catálogo?" sin interrumpir.
- **"Repetir último pedido de este cliente"**: carga las mismas líneas para ajustar. Probablemente el mayor ahorro de tiempo.
- Panel lateral: **saldo pendiente** del cliente y ventas vencidas.
- Letra grande, alto contraste, sin efectos pesados (lección de Ayala: cuidado con `backdrop-filter` en hardware modesto).
- Selector contado/crédito visible; default por cliente.

## 10. Control de deudas (cuentas por cobrar)

- Venta a crédito → saldo. **Abonos** parciales/totales; aplicación **FIFO automática** con opción manual.
- **Saldos iniciales** por cliente para deudas que ya existen en papel (crítico al arrancar).
- **Estado de cuenta por cliente** (PDF/impresión) por rango: ventas, abonos, saldo. Reemplaza entregar las copias al cliente para cobrar, si esa era la intención del punto 2.1.6.
- **Antigüedad de saldos:** 0–30 / 31–60 / 61–90 / 90+ días.
- Límite de crédito opcional → advertencia al vender.

## 11. Reportes y exportación para el contador (prioridad #3)

**Botón "Reporte para el contador"** → rango (este mes, mes anterior, libre) → **ZIP** con:

1. **Excel "Registro de ventas"**: una fila por documento (incluye anuladas): fecha, serie, número, tipo (orden/factura), CAI si aplica, cliente, RTN, condición, subtotales por tipo de impuesto, ISV, total, estado. Totales al pie. (Si hay series `factura`, este archivo funciona como libro de ventas fiscal.)
2. **Excel de detalle**: una fila por línea.
3. **PDF consolidado** con la imagen de cada documento (equivalente digital de la libreta de copias).
4. **Resumen**: ventas totales, contado vs crédito, abonos, saldo por cobrar al cierre, series y rangos usados, anulados.

Opcional (Fase 4): envío automático el día 1 de cada mes o usuario de solo lectura para el contador. 🔎 Preguntar si tiene plantilla propia.

**Control de ventas (prioridad #2):** ventas por día/semana/mes; por producto (cantidades por unidad y monto); por cliente; top clientes/productos; cierre del día (efectivo esperado = contado + abonos en efectivo).

## 12. Roadmap por fases

| Fase | Contenido | Criterio de "listo" |
|---|---|---|
| **0. Descubrimiento** | ✅ Foto del formulario · ⏳ unidades y precios por producto · ⏳ PC e impresora · ⏳ medir tiempo actual · ⏳ preguntas al contador (en paralelo, no bloquea) | Catálogo inicial con unidades; hardware definido |
| **1. MVP ventas** | Migraciones, `document_series` + `DocumentNumberService` (tests de concurrencia), catálogos (clientes, productos con los 29 del formulario, unidades), pantalla de venta (modo planilla + búsqueda), impresión en bond de 2 copias, listado/búsqueda, anulación | El abuelo hace una orden real impresa más rápido que a mano |
| **2. Deudas** | Contado/crédito, abonos, saldos iniciales, estado de cuenta, antigüedad, serie de contingencia (talonario) | Saldos de todos los clientes de crédito cuadrados con lo que dice el abuelo |
| **3. Reportes + contador** | Reportes de ventas, ZIP para el contador, cierre del día | El contador recibe un mes completo y lo acepta |
| **4. Robustez** | Backups cifrados automáticos, roles, audit log, envío mensual | Restauración de backup probada |
| **5. Fiscal** (según contador) | Serie `factura` con CAI: Ruta A (matricial) o Ruta B (autoimpresor, Art. 53); luego factura electrónica cuando SAR lo exija | Primera factura con CAI válida emitida |
| **6. Futuro** | Módulo de **compras** (el negocio también compra), márgenes | — |

## 13. Preguntas abiertas

**Para el contador (no bloquean la Fase 1):**
1. El abuelo usa **orden de pedido sin CAI**. ¿Emite **además** facturas con CAI (otro talonario)? ¿Cómo se reportan hoy las ventas al SAR?
2. Según los Arts. 3 y 9 del Acuerdo 481-2017, ¿está obligado a emitir comprobante fiscal por cada venta aunque sea exenta? ¿Qué régimen u obligaciones tiene como persona natural?
3. Si debe facturar: ¿**Ruta A** (imprenta + matricial) o **Ruta B** (autoimpresor)? ¿Lo tramita el contador?
4. ¿Qué formato quiere para el registro de ventas? ¿Tiene plantilla?
5. ¿Alguna fruta que venden es gravada?

**Para el abuelo / Farid:**
6. ¿A quién entrega periódicamente las libretas de copias: **contador** o **cliente**? (punto 2.1.6)
7. **Unidad por defecto y precio habitual** de cada uno de los 29 productos; lista completa de unidades; ¿cantidades fraccionarias (medio saco)?
8. ¿Qué **granos básicos** y otros productos fuera de la lista vende seguido?
9. Volumen: órdenes por día, número de clientes, de clientes a crédito.
10. ¿Quién usará el sistema? Comodidad con computadora; ¿preferiría el "modo planilla"?
11. ¿Hay PC en el negocio? ¿Windows o Linux? ¿Internet estable? ¿Cortes de luz frecuentes?
12. ¿Los precios cambian por cliente, por día o ambos?
13. Confirmar el **pie de imprenta** (rango ≈12,301–13,500) y cuántos talonarios quedan; elegir el número inicial de la serie del sistema.
14. ¿Plazo de crédito fijo? ¿Abonos parciales comunes?
15. ¿Tamaño de impresión preferido: carta o media carta?

## 14. Riesgos

- **Cumplimiento fiscal** (el mayor, ya existe hoy): ventas documentadas solo con orden de pedido sin CAI. El sistema no lo empeora y lo deja listo para corregirlo (Fase 5). Mitigación: preguntas al contador; nunca rotular "FACTURA" sin CAI.
- **Clientes negocio** que necesitan factura para deducir: pueden pedirla → se resuelve con la serie `factura`.
- **Pérdida de datos:** UPS, backups diarios cifrados fuera de la PC, restauración probada.
- **Corte de luz / PC caída:** serie de contingencia con talonario de papel + registro posterior.
- **Adopción por el abuelo:** modo planilla familiar, teclado, letra grande, capacitación corta, convivencia temporal con el talonario.
- **Cambios del SAR:** lógica fiscal aislada en `DocumentNumberService` / `DocumentPrinter`.

## 15. Instrucciones para el próximo Claude

1. **No asumas que el documento actual es fiscal**: es una orden de pedido sin CAI (§2.2, §4).
2. Cuando lleguen **unidades/precios por producto**, completa el seeder (§2.3) y actualiza §13.
3. Cuando lleguen **respuestas del contador**, fija la ruta fiscal en §4, convierte los 🔎 en reglas y ajusta §11 y §12 (Fase 5).
4. Redacta `instructions/fase-1-*.md` para Claude Code (estilo Ayala: cambios quirúrgicos, decisiones de arquitectura anticipadas), empezando por migraciones + `DocumentNumberService` con tests de concurrencia + seeders (29 productos, unidades, series).
5. Mantén este archivo como **fuente de verdad**; actualiza fecha y "Estado" en cada sesión.

---

## Fuentes

- Acuerdo 481-2017, Reglamento del Régimen de Facturación (copia publicada por RSM Honduras; Arts. 3, 5, 9, 10, 11, 37, 45, 47, 53): https://www.rsm.global/honduras/sites/default/files/media/acuerdo-481-2017-facturacion.pdf
- Consideraciones sobre documentos fiscales en Honduras — Consortium Legal, 2022: https://consortiumlegal.com/2022/09/06/consideraciones-importantes-respecto-a-los-documentos-fiscales-en-honduras/
- Requisitos de facturación en Honduras (CAI, modalidades, campos, ISV, conservación, CAEE) — Invoice Data Extraction, 2026: https://invoicedataextraction.com/blog/honduras-invoice-requirements
- Estado del régimen CAI y factura electrónica — VATupdate, agosto 2026: https://www.vatupdate.com/2026/08/11/honduras-e-invoicing-e-reporting-country-booklet/
- Canasta básica exenta de ISV y rubros que sí pagan — Revista E&N, 2014: https://www.revistaeyn.com/centroamericaymundo/honduras-con-un-nuevo-listado-de-canasta-basica-IIEN564095

> Nota: no soy abogado ni contador; la interpretación fiscal final la dan el contador y el SAR.
