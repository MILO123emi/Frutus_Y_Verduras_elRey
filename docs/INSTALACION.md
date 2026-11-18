# Instalación en la PC del negocio

## Preparar la instalación

Instale los requisitos y siga los pasos del [README](../README.md). Las rutas de Windows
`C:\FrutVerdRey`, `C:\php\php.exe`, y las de Linux `/opt/frutverdrey`, `/usr/bin/php`
son ejemplos: reemplácelas por las rutas reales. PostgreSQL debe arrancar con el equipo.
La aplicación usa archivos locales; después de instalar y compilar no necesita internet.

Antes de sembrar la base definitiva, configure `.env`:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=http://127.0.0.1:8000
APP_TIMEZONE=America/Tegucigalpa
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=frutverdrey
DB_USERNAME=postgres
DB_PASSWORD="CAMBIAR_POR_LA_CLAVE_LOCAL"
OP_START_NUMBER=100001
```

Use la conexión y el número inicial reales. `OP_START_NUMBER` lo lee `DocumentSeriesSeeder`
al crear la serie; cambiarlo después no renumera una serie existente.

**Solo antes de empezar a registrar operaciones, y sobre la base destinada a la instalación:**

```sh
php artisan migrate:fresh --seed
npm run build
```

**`migrate:fresh` borra todas las tablas. Nunca lo ejecute sobre ventas reales.**
Laravel pedirá confirmar la ejecución en producción. `APP_ENV=production` omite
`DemoDataSeeder`: no genera ventas ni CAI de ejemplo. Sí crea catálogo, clientes de ejemplo y
los tres usuarios iniciales. Revise los clientes y apague los que no correspondan; cambie las
contraseñas y el PIN en Usuarios. No vuelva a sembrar usuarios para cambiar credenciales,
pues el seeder actualiza las cuentas iniciales.

## Abrir Chrome como aplicación

En Windows cree un acceso directo en el escritorio con este destino (ajuste la ruta de Chrome):

```text
"C:\Program Files\Google\Chrome\Application\chrome.exe" --app=http://127.0.0.1:8000 --kiosk-printing
```

Llámelo «El Rey». Configure primero la impresora predeterminada, el papel y los márgenes;
pruebe original y copia. `--kiosk-printing` permite imprimir sin el diálogo de confirmación.
El servidor debe estar funcionando antes de abrir el acceso directo.

## Arrancar el servidor al iniciar la PC

Este ejemplo usa el servidor de Artisan solicitado para una sola PC y escucha únicamente
en `127.0.0.1`. Para acceso desde otras computadoras hace falta preparar un despliegue aparte.

### Windows: Programador de tareas

Cree una tarea «El Rey servidor» con desencadenador **Al iniciar el sistema**, una cuenta con
permiso sobre el proyecto y opción de reiniciar si falla. En la acción «Iniciar un programa»:

- Programa: `C:\php\php.exe`.
- Argumentos: `artisan serve --host=127.0.0.1 --port=8000`.
- Iniciar en: `C:\FrutVerdRey`.

Quite el límite que detiene la tarea después de varias horas y permita funcionar conectado
a corriente. Iníciela manualmente una vez y compruebe `http://127.0.0.1:8000`.

### Linux: servicio systemd

Ejemplo de `/etc/systemd/system/elrey.service`, para instalar con permisos de administrador:

```ini
[Unit]
Description=Sistema de ventas El Rey
After=network.target postgresql.service
Wants=postgresql.service

[Service]
Type=simple
User=elrey
WorkingDirectory=/opt/frutverdrey
ExecStart=/usr/bin/php artisan serve --host=127.0.0.1 --port=8000
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

Sustituya `elrey` por una cuenta existente con acceso al proyecto y escritura en `storage`
y `bootstrap/cache`; adapte el nombre del servicio PostgreSQL a la distribución. Active:

```sh
sudo systemctl daemon-reload
sudo systemctl enable --now elrey.service
```

## Programador de Laravel

`routes/console.php` define dos tareas en la zona `America/Tegucigalpa`:

| Hora | Trabajo |
| --- | --- |
| 02:00 | Preparar PDFs de los últimos siete días hasta hoy, original y copia |
| 18:00 | Respaldo de la base de datos |

La PC debe estar encendida y despierta en esos horarios. No se necesita trabajador de colas.

En Linux agregue al crontab de la cuenta que usa el proyecto (adapte las rutas):

```cron
* * * * * cd /opt/frutverdrey && /usr/bin/php artisan schedule:run >> /opt/frutverdrey/storage/logs/scheduler.log 2>&1
```

En Windows cree otra tarea «El Rey programador», al iniciar el sistema, que se repita cada
minuto indefinidamente. Programa: `C:\php\php.exe`; argumentos: `artisan schedule:run`;
«Iniciar en»: `C:\FrutVerdRey`. Use la misma cuenta con permisos del servidor, desactive
los límites de duración y evite iniciar una segunda instancia si la anterior sigue activa.

## Respaldos y copia en USB

En Ajustes pulse **Hacer respaldo ahora**, o ejecute desde el proyecto:

```sh
php artisan backup:run
```

`BackupService` ejecuta `pg_dump -Fc` y guarda archivos comprimidos
`storage/app/backups/elrey_YYYY-MM-DD_HHMMSS.dump`. Conserva los últimos 30 archivos;
los anteriores se eliminan al hacer un respaldo nuevo. Son copias de la base de datos,
sin cifrado incorporado; guarde también el proyecto y `.env` en un lugar seguro para recuperar
la instalación completa. Los PDFs se pueden regenerar a partir de la base.

Si `pg_dump` no está en el PATH, agregue su ruta real a `.env`; por ejemplo, en Windows:

```dotenv
PG_DUMP_PATH="C:/Program Files/PostgreSQL/17/bin/pg_dump.exe"
```

Esta variable existe en `BackupService`, aunque no está incluida en `.env.example`.
Una vez por semana descargue el respaldo más reciente desde Ajustes y cópielo a una USB.
Compruebe que el archivo está en la USB, expúlsela y guárdela separada de la PC.

## Restaurar un respaldo

Detenga el servidor y la tarea del programador para evitar escrituras. Conserve una copia de
la base actual antes de restaurar: el siguiente comando reemplaza los objetos respaldados.
La base `frutverdrey` debe existir. Desde la carpeta del archivo, con las herramientas de
PostgreSQL en el PATH y usando su usuario local:

```sh
pg_restore -h 127.0.0.1 -U postgres --clean --if-exists -d frutverdrey archivo.dump
```

Sustituya usuario y archivo por los reales. Revise que no haya errores; reinicie servidor y
programador, entre al sistema y compruebe ventas, clientes y saldos. Pruebe periódicamente
la restauración en otra base para confirmar que los respaldos son recuperables.

## Antes de usarlo con datos reales

- Cambiar contraseñas de abuelo, empleado y contador, y el PIN del administrador.
- Poner razón social, RTN, dirección y teléfono reales en Ajustes.
- Apagar el CAI de ejemplo si se usó una base de demostración y registrar el real.
  En una base nueva de producción no se crea CAI: debe registrarlo antes de facturar.
- Validar con el contador la plantilla y la modalidad SAR (autoimpresor o imprenta).
  El sistema todavía no emite notas de crédito.
- Confirmar el número inicial de OP antes de crear la serie, el papel, unidades y precios.
- Revisar los clientes sembrados y practicar impresión, cobros y un respaldo con restauración.
- Verificar el arranque tras reiniciar la PC y que funcionen las tareas programadas.
- Usar un UPS para mantener PC e impresora protegidas ante cortes de energía.
