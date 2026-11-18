# Diagnóstico del proyecto (2026-11-17)

## Qué había
- Laravel 13 + Inertia 2 + Vue 3 + Vuetify 4 + PostgreSQL. Partes 0A, 0B, 1 y 2 del kickoff hechas.
- **Backend sólido:** `DocumentNumberService` (lock `FOR UPDATE` + `unique(series, number)`),
  `SaleService` (snapshots, bcmath, auditoría), inmutabilidad en el modelo `Sale`, `Money`,
  `DateRange`, caché de PDFs con dompdf. 76 tests pasando.
- **Frontend a medias:** mezcla de Breeze/Tailwind (login, perfil, dashboard vacío) con Vuetify.
  Fuentes cargadas desde un CDN (rompe el modo sin internet). Sin pantalla de inicio útil,
  sin catálogos editables, sin clientes, sin deudas, sin reportes, sin gestión de usuarios.
- El sistema estaba pensado **solo para órdenes de pedido** (sin factura fiscal).

## Qué se conserva
Todo el núcleo de ventas: modelos, migraciones existentes, servicios de numeración/venta/PDF,
plantilla de la orden, tests. Son correctos y cumplen las reglas (nunca borrar, correlativo,
inmutable, snapshots, dinero sin float).

## Qué se refactoriza
- `DocumentSeries` / `DocumentNumberService`: soportar series fiscales (CAI, rango, fecha límite,
  formato `000-001-01-00000001`, bloqueo por agotada/vencida, avisos).
- `SaleService`: emitir facturas, convertir orden → factura.
- Pantalla de venta: sirve para pedido y factura; autocompletado tolerante a errores.
- Layout y tema: dirección visual nueva, letra grande, fuentes locales.

## Qué se rehace / elimina
- Páginas Breeze con Tailwind (login, perfil, recuperar contraseña por correo, verificación de
  correo, Welcome, Dashboard): no sirven offline ni para el usuario. Login nuevo en Vuetify con
  **usuario** (no correo). Contraseñas las restablece el administrador.
- Tailwind se quita del build (solo lo usaban esas páginas).

## Qué se agrega
Inicio con botones grandes · Facturas SAR · Clientes y cuentas por cobrar (abonos, saldos
iniciales, estado de cuenta, antigüedad) · Fletes con calendario y viajes fijos · Reportes para
el contador (Excel y ZIP) · Productos · Usuarios · Ajustes (negocio, CAI, respaldos).
