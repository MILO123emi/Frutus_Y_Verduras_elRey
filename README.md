# Frutas y Verduras El Rey

Sistema local para el negocio de verduras de La Ceiba, Honduras, pensado para trabajar sin internet.
Registra pedidos no fiscales y facturas SAR con CAI, cuentas por cobrar y fletes.
Incluye reportes para el contador en Excel y ZIP con PDFs, impresión y respaldos de la base de datos.

## Requisitos

- PHP 8.4 con `pdo_pgsql`, `bcmath`, `gd` y `zip`, además de las extensiones que valide Composer.
- Composer y Node.js 20.19+ de la rama 20, o 22.12+ (requisito de Vite), con npm.
- PostgreSQL 17 con `unaccent` disponible; las migraciones crean la extensión.
- Herramientas PostgreSQL `pg_dump` y `pg_restore` compatibles con la versión del servidor.
- Chrome e impresora para la PC del negocio. Las fuentes y recursos se empaquetan sin CDN.

## Instalación para desarrollo

Desde la carpeta del proyecto:

```sh
composer install
npm ci
cp .env.example .env
php artisan key:generate
```

En Windows puede copiar `.env.example` a `.env` desde el explorador. Edite `DB_HOST`, `DB_PORT`,
`DB_USERNAME` y `DB_PASSWORD` en `.env`; mantenga `DB_CONNECTION=pgsql` y `DB_DATABASE=frutverdrey`.
Cree las dos bases con un usuario PostgreSQL que tenga permiso para hacerlo:

```sh
createdb -h 127.0.0.1 -U postgres frutverdrey
createdb -h 127.0.0.1 -U postgres frutverdrey_test
```

Sustituya `postgres` por su usuario local. `phpunit.xml` usa el usuario `milo` para pruebas:
adapte esa conexión a su instalación y conserve una base de pruebas separada.
El usuario que ejecute las migraciones necesita permiso para crear `unaccent`.

```sh
php artisan migrate --seed
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

Abra `http://127.0.0.1:8000`. Mantenga el servidor abierto durante el uso.
Fuera de producción se generan ventas, un CAI y otros datos de ejemplo.
Para la PC del negocio siga [la guía de instalación](docs/INSTALACION.md).

## Usuarios iniciales

| Usuario | Contraseña | Acceso |
| --- | --- | --- |
| abuelo | rey2026 | Administrador; PIN 1234 |
| empleado | venta2026 | Vendedor |
| contador | contador2026 | Solo lectura y reportes |

**Cambie las tres contraseñas y el PIN antes de usar el sistema con datos reales.**
Estos usuarios también se crean en producción. Las variables `ADMIN_USERNAME`, `ADMIN_NAME`,
`ADMIN_PASSWORD` y `ADMIN_PIN` permiten configurar al administrador al sembrar la base.

## Comandos útiles

```sh
php artisan test
php artisan backup:run
php artisan documents:warm-pdfs --from=2026-11-01 --to=2026-11-17
php artisan schedule:run
npm run build
```

Los respaldos quedan en `storage/app/backups`; se conservan los últimos 30.
El programador del sistema debe ejecutar `schedule:run` cada minuto: respaldo a las 18:00 y
preparación de PDFs a las 02:00, en horario de Honduras. No requiere un trabajador de colas.

## Documentación

- [Instalación, arranque y restauración](docs/INSTALACION.md).
- [Decisiones del proyecto](docs/DECISIONES.md).
- [Diagnóstico inicial](docs/DIAGNOSTICO.md).
- [Contexto vivo y pendientes](instrucciones/CONTEXTO.md).
