# Recorrido antes de operar

Preparado el 18 de noviembre de 2026. **Pendiente de ejecutar en navegador y en la PC del negocio.**
Use una instalación de demostración separada de la base real. Las ventas y abonos de estas
pruebas quedan registrados; no se deben generar operaciones ficticias en producción.
No hace falta borrar ni volver a sembrar una base existente para seguir este recorrido.

## Pantallas y teclado

Repita con ventanas de 1024 y 1366 píxeles de ancho. Marque cada comprobación solo después
de ejecutarla. Anote usuario, ancho, pantalla y pasos cuando encuentre un problema.

- [ ] Entrar con cada rol: administrador, vendedor y contador. Verificar que el menú muestre
  las opciones permitidas y que se pueda cerrar sesión.
- [ ] Inicio: abrir las cuatro acciones con las teclas 1–4 usando administrador o vendedor.
- [ ] Nuevo pedido: buscar producto, indicar cantidad y agregarlo con teclado; corregir una
  cantidad inválida; comprobar errores junto al campo y que no se pierdan las otras líneas.
- [ ] Escribir 2,5 y abc como cantidad: la primera vale 2.50, la segunda se marca en rojo y no deja guardar.
- [ ] Abrir una nueva venta desde la ficha de un cliente y comprobar la selección del cliente.
- [ ] Guardar una venta: pulsar dos veces el botón y comprobar que se emite un solo documento.
- [ ] Convertir un pedido: provocar un error sin CAI utilizable en la instalación de prueba;
  comprobar que el diálogo conserva los datos y explica el problema.
- [ ] Anular una venta de prueba: cancelar primero el diálogo; después probar PIN incorrecto
  y PIN correcto. Comprobar que aparece anulada y conserva número y motivo.
- [ ] Clientes y Quién me debe: buscar un nombre sin acentos, cambiar de página y comprobar
  que el total de deuda corresponde al filtro completo.
- [ ] Cuenta del cliente: anotar abono y usar Deshacer; cancelar primero la confirmación y
  comprobar después que el movimiento anulado sigue en el estado de cuenta.
- [ ] Fletes: alternar semana y mes; abrir, editar y cancelar un viaje de prueba. Comprobar
  que la confirmación explica el efecto sobre la cuenta del cliente.
- [ ] Productos, Usuarios y Ajustes: abrir formularios con administrador, recorrer campos
  con Tab, enviar con Enter y cerrar diálogos con Esc. Revisar errores y confirmaciones.
- [ ] Reportes: elegir un período y descargar Excel y ZIP con el contador; abrir los archivos
  y comprobar que las ventas anuladas y los pedidos facturados no aumentan los totales.
- [ ] En todas las pantallas: botones principales visibles, textos legibles, tablas con
  desplazamiento y formularios completos sin quedar fuera de la ventana.

## Impresión

- [ ] En Ajustes elegir carta e imprimir pedido, factura y estado de cuenta de demostración.
- [ ] Repetir con media carta horizontal (8.5 × 5.5 pulgadas).
- [ ] Probar original y copia; revisar identificación, número, fecha, líneas, totales y pie.
- [ ] Probar un documento largo que ocupe varias páginas: sin líneas cortadas ni texto
  superpuesto; verificar que aparezcan todas las líneas y el total final.
- [ ] Comprobar que la orden diga «ORDEN DE PEDIDO» y que no diga «FACTURA» ni lleve CAI.
- [ ] Revisar los campos fiscales de la factura con el contador según
  [DECISIONES.md](DECISIONES.md); la impresión técnica no sustituye esa revisión.

## PC definitiva

Siga [INSTALACION.md](INSTALACION.md) para estas comprobaciones.

- [ ] Cambiar contraseñas y PIN; configurar emisor, CAI, papel, precios y unidades reales.
- [ ] Reiniciar la PC y comprobar servidor, PostgreSQL y acceso directo de Chrome.
- [ ] Crear respaldo, copiarlo a USB y restaurarlo en una base separada; revisar ventas,
  clientes y saldos recuperados.
- [ ] Comprobar ejecución programada del respaldo a las 18:00 con la PC encendida.

## Registro del resultado

| Fecha | Persona | Equipo y ancho | Comprobación | Resultado o problema |
| --- | --- | --- | --- | --- |
| — | — | — | — | Pendiente |
