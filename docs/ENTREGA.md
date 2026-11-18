# Entrega de Frutas y Verduras El Rey

Para Farid · 17 de noviembre de 2026

El sistema permite llevar las ventas y las cuentas del negocio en una PC local. La Tarea 5
incluye revisión de interfaz, mensajes en español, pruebas de permisos y cobros, paginación
y ajustes de impresión. Los resultados están en [BITACORA-CODEX.md](BITACORA-CODEX.md).

## Qué puede hacer en cada pantalla

| Pantalla | Para qué sirve |
| --- | --- |
| Inicio | Abrir pedido, factura, cobros o fletes con botones grandes y teclas 1–4; ver el resumen del día. |
| Entrar | Ingresar con usuario y contraseña. El administrador cambia contraseñas olvidadas. |
| Nuevo pedido / Nueva factura | Elegir cliente, buscar productos o llenar la lista, indicar contado/crédito y guardar e imprimir. Desde la ficha se puede abrir una venta con ese cliente ya elegido. |
| Ventas hechas | Buscar por número o cliente, filtrar fechas y consultar ventas activas, anuladas y pedidos facturados. |
| Ver una venta | Imprimir original y copia, descargar PDF, convertir un pedido en factura o anular con motivo y PIN de administrador. |
| Clientes | Buscar por nombre, teléfono o RTN, agregar clientes y consultar sus cuentas. Los listados van por páginas. |
| Cuenta del cliente | Ver deuda, antigüedad y últimas compras; anotar abonos o deuda de antes; imprimir estado de cuenta. |
| Quién me debe | Consultar deudores de mayor a menor deuda y anotar abonos. El total incluye todas las páginas del filtro. |
| Fletes | Ver semana o mes, anotar viajes extra, modificar viajes fijos y motoristas y cargar un cobro al cliente. Cancelar un viaje pide confirmación. |
| Productos y precios | Cambiar precios sugeridos, unidad e impuesto; apagar productos que ya no se venden. |
| Reportes | Consultar el período y descargar Excel o ZIP con los documentos para el contador. |
| Ajustes | Guardar datos del negocio, registrar CAI, elegir carta o media carta y hacer/descargar respaldos. Solo administrador. |
| Usuarios | Crear accesos, cambiar permisos, contraseñas y PIN o desactivar usuarios. Solo administrador. |

## Instalar y comenzar

Siga [INSTALACION.md](INSTALACION.md) y el [README](../README.md): incluyen requisitos,
arranque automático, Chrome, impresora, tareas programadas y respaldo a USB.
**No ejecute `migrate:fresh` sobre una base con ventas reales: borra todas las tablas.**

Accesos iniciales, que deben cambiarse antes de operar:

| Usuario | Contraseña inicial | Permiso |
| --- | --- | --- |
| abuelo | rey2026 | Administrador; PIN inicial 1234 |
| empleado | venta2026 | Vendedor |
| contador | contador2026 | Consulta y descargas |

## Decisiones que conviene conocer

La explicación completa está en [DECISIONES.md](DECISIONES.md).

- Ventas y abonos se anulan; nunca se borran. Una venta emitida conserva sus datos originales.
- Los números son correlativos por serie. Un número anulado no se reutiliza.
- Cada CAI tiene rango y vencimiento. El sistema bloquea los que ya no sirven y busca el siguiente.
- El pedido facturado deja de sumar: solo cuenta la factura que lo reemplaza.
- Los abonos pagan primero la deuda más vieja. Un abono mayor a la deuda deja saldo a favor; se puede deshacer con confirmación y queda registrado como anulado.
- El contador consulta y descarga. El vendedor vende, cobra y maneja fletes; administración queda reservada al administrador.
- Las fechas nuevas usan la hora de Honduras, también al guardar en PostgreSQL. No se cambiaron fechas de documentos existentes.
- Los PDFs se regeneran en una caché por tamaño de papel. Media carta se prepara horizontal, 8.5 × 5.5 pulgadas; hay que probarla con la impresora definitiva.

## Pendientes y bloqueos

No hubo bloqueos nuevos que exigieran una decisión para continuar la corrida. Permanecen:

- **Validar con el contador la plantilla SAR y la modalidad autoimpresor o imprenta.** El texto oficial completo del Art. 10 no fue verificado. Esta revisión no constituye aprobación fiscal.
- El sistema todavía no emite **notas de crédito**.
- Confirmar razón social y datos reales del emisor, CAI real, número inicial de OP, papel, precios y unidades.
- Probar físicamente la impresión de original y copia, incluyendo documentos largos.
- Hacer el recorrido visual en navegador a 1024 y 1366 px, con teclado y usuarios de cada rol. Se revisaron código, compilación y permisos, pero ese recorrido no se realizó en esta sesión.
- Usar [REVISION-MANUAL.md](REVISION-MANUAL.md) para registrar ese recorrido, la impresión y las comprobaciones de la PC definitiva; sus casillas todavía están pendientes.
- Comprobar en la PC definitiva el arranque, el respaldo diario y una restauración en una base aparte.

Las pruebas se ejecutaron solo en `frutverdrey_test`. No se hicieron migraciones destructivas,
ni se instalaron dependencias. La bitácora contiene el resultado final de pruebas y compilación.
