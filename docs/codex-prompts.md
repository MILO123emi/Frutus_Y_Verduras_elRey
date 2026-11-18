# Prompts enviados a Codex

Flujo: Claude escribe aquí el prompt → Farid lo pega en Codex (extensión de VS Code) →
Farid avisa a Claude → Claude revisa (diff, tests, build). Claude no ejecuta Codex.

## Contexto común (va al inicio de cada prompt)

```
Proyecto: sistema de ventas "Frutas y Verduras El Rey" (Honduras). Laravel 13 + Inertia 2 + Vue 3 (<script setup>) + Vuetify 4, PostgreSQL. Lee CLAUDE.md y docs/DECISIONES.md antes de empezar.
Convenciones: la UI va en español sencillo para un señor de 60 años (sin jerga, frases cortas, botones grandes con ícono Y texto, confirmación antes de acciones importantes). El código, las props y las rutas internas van en inglés. Usa Ziggy route('nombre') para las URLs. No agregues dependencias npm ni composer.
Patrón de páginas: copia el estilo de resources/js/Pages/Productos/Index.vue y Pages/Clientes/Show.vue: AppLayout con :title, h1.page-title + p.page-sub, v-card con h2.sec, diálogos con useForm de @inertiajs/vue3 (botones "Cancelar" outlined a la izquierda y la acción principal flat a la derecha), helpers de @/lib/format.js (money, date, roleLabels), componentes @/Components/ConfirmDialog.vue y EmptyState.vue. Los mensajes de éxito ya los muestra AppLayout a partir de flash.status y flash.error: no los dupliques.
NO toques: controladores, rutas, modelos, migraciones, tests, AppLayout.vue ni lib/*. Si algo del backend no alcanza, explícalo al final en lugar de cambiarlo.
```

## Tarea 1: página Ajustes (`resources/js/Pages/Ajustes/Index.vue`)

```
[contexto común]
Objetivo: crear resources/js/Pages/Ajustes/Index.vue (archivo nuevo). Solo la usa el administrador.
Props (vienen de app/Http/Controllers/SettingsController.php@index, léelo):
- business: {trade_name, legal_name, activity, rtn, address, phone, email, print_settings:{paper_size}} o null
- series: [{id, code, title, kind:'order'|'invoice', origin, cai, establishment, emission_point, doc_type_code, number_from, number_to, next_number, status:'active'|'inactive'|'exhausted'|'expired', range_alert_threshold, deadline_alert_days, issue_deadline, range_label, next_label, remaining, days_to_deadline, usable, used}]
- backups: [{name, size (bytes), date (ISO)}]
Secciones, en este orden, cada una en un v-card:
1. "Datos del negocio": formulario useForm con trade_name (Nombre del negocio), legal_name (Razón social, con hint "Nombre legal del dueño o empresa; obligatorio para facturar"), rtn (14 números), activity, address (textarea), phone, email, paper_size (v-btn-toggle: letter "Carta" / half-letter "Media carta", inicial business.print_settings.paper_size || 'letter'). Envía form.put(route('ajustes.business')) con preserveScroll. Si falta legal_name o rtn, muestra un v-alert warning arriba: "Sin razón social y RTN no se pueden hacer facturas."
2. "Facturación (CAI)": texto corto que explique que cada CAI que da el SAR trae un rango de números y una fecha límite, y que cuando uno se acaba el sistema pasa solo al siguiente. Lista de series kind==='invoice' (las más nuevas primero), cada una como bloque con: CAI, rango (range_label), "Sigue: {next_label}", "Quedan {remaining} números", "Fecha límite {date(issue_deadline)}" (y "vence en N días" o "vencido" según days_to_deadline), y un chip de estado: active+usable "En uso" (primary), inactive "Apagado", exhausted "Se terminó" (error), expired "Vencido" (error). Botón "Apagar"/"Encender" solo si el estado es active o inactive, que usa ConfirmDialog y luego router.put(route('ajustes.series.update', id), {status, range_alert_threshold, deadline_alert_days}, {preserveScroll:true}). Si no hay series de factura, EmptyState "Todavía no hay CAI registrado". Botón "Registrar CAI nuevo" que abre un diálogo con useForm: cai (hint "Formato XXXXXX-XXXXXX-XXXXXX-XXXXXX-XXXXXX-XX"; conviértelo a mayúsculas), establishment (default '000'), emission_point (default '001'), doc_type_code (default '01'), number_from, number_to, issue_deadline (type=date), range_alert_threshold (default 50, "Avisarme cuando queden"), deadline_alert_days (default 15, "Avisarme días antes del vencimiento"). Muestra en vivo una vista previa del primer número con formato EEE-PPP-TT-NNNNNNNN (padStart 3/3/2/8). form.post(route('ajustes.cai.store')) y cierra si sale bien. Muestra form.errors en cada campo.
   Debajo, en letra pequeña (muted), la serie de pedidos (kind==='order', origin==='system'): "Pedidos: el próximo número es {next_label}".
3. "Respaldos": explica en una línea que el sistema hace un respaldo cada día a las 6 pm y que conviene copiar el último a una memoria USB una vez por semana. Botón grande "Hacer respaldo ahora" (router.post(route('ajustes.backup')) con un loading). Lista de backups: fecha legible (toLocaleString 'es-HN'), tamaño en MB con 1 decimal y un enlace normal <a :href="route('ajustes.backup.download', b.name)"> con un v-btn "Descargar" (que NO sea navegación de Inertia). Si no hay respaldos, una frase muted.
Aceptación: `npm run build` sin errores; nada de texto en inglés visible; todos los botones con ícono mdi y texto.
Verificación: npm run build
```

## Tarea 2: página Usuarios (`resources/js/Pages/Usuarios/Index.vue`)

```
[contexto común]
Objetivo: crear resources/js/Pages/Usuarios/Index.vue (archivo nuevo). Solo la usa el administrador.
Props (de app/Http/Controllers/UserController.php@index, léelo junto con sus reglas de validación): users: [{id, name, username, role:'admin'|'seller'|'accountant', active, has_pin}].
UI:
- Encabezado "Usuarios" + subtítulo "Quién puede entrar al sistema. Si un empleado se va, apáguelo aquí; sus ventas quedan guardadas." Botón "Usuario nuevo".
- Lista (v-table): Nombre, Usuario, Qué puede hacer (roleLabels de @/lib/format.js), Estado (chip "Activo"/"Apagado"; las filas apagadas con clase row-dim), "Tiene PIN" (Sí/No, solo importa para administradores), y un botón "Cambiar".
- Diálogo único para crear y editar con useForm: name, username (hint "Sin espacios, ej. maria"), role (v-radio-group con 3 opciones y una explicación corta de cada una: Administrador = todo, incluido anular y ajustes; Vendedor = vender, cobrar y fletes; Contador = solo ver y descargar reportes), password (al crear: obligatoria; al editar: opcional, label "Contraseña nueva (déjela vacía para no cambiarla)"), pin (solo si role==='admin'; inputmode numeric; hint "4 a 6 números; sirve para autorizar anulaciones"; al editar es opcional), y active (switch, solo al editar). Botón para mostrar u ocultar la contraseña.
- Crear: form.post(route('usuarios.store')). Editar: form.put(route('usuarios.update', id)). Siempre preserveScroll y cerrar el diálogo con onSuccess. Si al editar apaga un usuario, confirma antes con ConfirmDialog: "¿Apagar a {name}? Ya no podrá entrar."
Aceptación: npm run build sin errores; errores de validación visibles bajo cada campo.
Verificación: npm run build
```

## Tarea 3: usuarios sembrados y datos de ejemplo realistas

```
[contexto común]
Objetivo: que al correr `php artisan migrate:fresh --seed` el sistema abra con datos de ejemplo realistas para probarlo de inmediato. Solo toca database/seeders/ (y el test indicado abajo).
Lee primero: app/Services/SaleService.php (issue, convertToInvoice, void), app/Services/AccountsService.php (record), app/Services/FreightService.php (materialize), app/Models/DocumentSeries.php y app/Http/Controllers/SettingsController.php@storeCai (cómo se crea una serie de facturas), y database/seeders/*.
Pasos:
1. Reescribe database/seeders/AdminUserSeeder.php para crear o actualizar (updateOrCreate por username) 3 usuarios:
   - username env('ADMIN_USERNAME','abuelo'), name env('ADMIN_NAME','Abuelo'), password env('ADMIN_PASSWORD','rey2026'), pin_hash env('ADMIN_PIN','1234'), role admin.
   - username 'empleado', name 'Empleado', password 'venta2026', role seller, sin PIN.
   - username 'contador', name 'Contador', password 'contador2026', role accountant.
   email = username.'@elrey.local', active true, email_verified_at now(). Los casts del modelo User ya hashean password y pin_hash: NO uses Hash::make (quedaría doble hasheado).
2. En tests/Feature/Auth/AuthenticationTest.php cambia el username 'abuelo' del factory por 'pedro' (ahora 'abuelo' ya existe en el seeder). No toques nada más de ese test.
3. Crea database/seeders/DemoDataSeeder.php. Que sea determinista (mt_srand(2026) y Faker con seed) y que no haga nada si ya hay ventas (Sale::exists()). Contenido:
   a. Si BusinessSettings::current()->legal_name es null, pon 'RAZÓN SOCIAL DE EJEMPLO (cambiar en Ajustes)'.
   b. Una serie de facturas de ejemplo (kind invoice) con cai 'EJEMPL-000000-111111-222222-333333-44', establishment '000', emission_point '001', doc_type_code '01', rango 1 a 500, next_number 1, pad_length 8, issue_deadline dentro de 6 meses, code 'FAC-00000101-1', notes 'CAI DE EJEMPLO para probar. Apáguelo y registre el real en Ajustes.'
   c. Productos: dale default_unit_id (solo unidades activas: Matate, Saco, Caja, Unidad, Bolsa, Maleta) y un reference_price realista en lempiras de La Ceiba (2026, venta al por mayor) a los 29 productos del formulario. Ejemplos de la escala: Repollo saco ~L 450, Tomate pera caja ~L 380, Papas saco ~L 900, Cebolla roja saco ~L 1,100, Cilantro maleta ~L 120, Aguacate unidad ~L 25, Piñas unidad ~L 30, Sandías unidad ~L 60, Limones bolsa ~L 90. Todos siguen exempt.
   d. Motoristas (Driver): 'Carlos Mejía' (9876-5432, 'Camión Isuzu blanco') y 'José Ramírez' (9455-1203, 'Pick-up Toyota gris').
   e. Viajes fijos (FreightSchedule): miércoles (3) y viernes (5) a 'San Pedro Sula', motorista Carlos, departure_time '04:30', su vehículo. Después actualiza su created_at a hace 60 días con un query update (no con el modelo).
   f. Ventas de los últimos 45 días (hasta hoy) con SaleService::issue y el usuario admin (o el empleado en ~40% de las ventas): de lunes a sábado, 4 a 9 ventas por día, a horas entre 6:00 y 16:30 (America/Tegucigalpa, en issued_at). Cliente: ~30% consumidor final (sin customer_id) y el resto de los clientes sembrados. terms = default_terms del cliente (consumidor final siempre cash). De 2 a 7 líneas por venta, con productos del catálogo, cantidades enteras de 1 a 12 (a veces .5), su unidad y un precio cercano al de referencia (±10%, redondeado a 5 lempiras). Incluye ~6 facturas directas (kind invoice) a clientes con RTN o de menos de L 10,000.
   g. Anula 2 ventas (SaleService::void, motivos 'Cliente devolvió el producto' y 'Se anotó mal la cantidad'). Convierte 2 pedidos de contado de la última semana en factura (convertToInvoice).
   h. Abonos (AccountsService::record, type payment) para cada cliente de crédito: entre 50% y 85% de lo que compró a crédito, repartido en 2 a 4 abonos con fechas posteriores a sus compras y no futuras. Métodos variados (cash, transfer). Y un saldo anterior (type opening_balance) de L 3,250 para 'Pulpería Los Ángeles', con fecha de hace 60 días y nota 'Deuda de la libreta'.
   i. Fletes: FreightService::materialize desde hace 30 días hasta dentro de 14; marca como 'done' los que ya pasaron. Agrega fletes extra: 'Para mi tío Mario' a Tela (purpose family, hace 5 días, done); uno para el cliente 'Hotel Costa Atlántida' a San Pedro Sula dentro de 3 días con charge_amount 1500 y charge_to_account true (purpose customer); una mudanza (purpose moving) dentro de 6 días a Olanchito, con notas.
4. En DatabaseSeeder llama a DemoDataSeeder después de los demás, solo si ! app()->isProduction().
Aceptación: `php artisan migrate:fresh --seed` termina sin errores en menos de 60 s; `php artisan test` pasa completo; el abuelo puede entrar con abuelo / rey2026.
Verificación: php artisan migrate:fresh --seed && php artisan test
```

## Tarea 4: documentación (README, CLAUDE.md, AGENTS.md, INSTALACION.md)

> 2026-11-17: la primera corrida no dejó cambios (README/AGENTS/CLAUDE intactos, sin
> INSTALACION.md). Se reemplaza por la **Tarea 4b** de abajo, con el flujo nuevo.

## Tarea 4b: documentación, de nuevo (la que hay que pegar)

```
Proyecto: sistema "Frutas y Verduras El Rey" (Laravel 13 + Inertia 2 + Vue 3 + Vuetify 4 + PostgreSQL 17), ventas del negocio de verduras de un señor de 60 años en La Ceiba, Honduras. El código ya está hecho y probado (91 tests en verde); esta tarea es SOLO documentación.
Lee antes de escribir: instrucciones/CONTEXTO.md (estado actual y flujo de trabajo), docs/DIAGNOSTICO.md, docs/DECISIONES.md, CLAUDE.md actual, routes/web.php, routes/console.php, app/Services/BackupService.php, database/seeders/AdminUserSeeder.php, composer.json, package.json y .env.example.
REGLAS: solo crea o edita estos 4 archivos .md: README.md, CLAUDE.md, AGENTS.md, docs/INSTALACION.md. No toques código, tests, configuración ni otros .md. No inventes comandos, variables de entorno ni rutas: cada comando o variable que menciones tiene que existir en el proyecto (verifícalo con grep o `php artisan list`). Todo en español.

1. README.md: reemplázalo completo (hoy es el genérico de Laravel). Qué es el sistema (3 líneas: pedidos no fiscales, facturas SAR con CAI, cuentas por cobrar, fletes, reportes para el contador, respaldos); requisitos (PHP 8.4 con pdo_pgsql, bcmath, gd y zip; Composer; Node 20+; PostgreSQL 17 con la extensión unaccent); instalación paso a paso (composer install, npm ci, cp .env.example .env, php artisan key:generate, crear las BD frutverdrey y frutverdrey_test, php artisan migrate --seed, npm run build, php artisan serve); usuarios de prueba: abuelo / rey2026 (administrador, PIN 1234), empleado / venta2026 (vendedor), contador / contador2026 (solo ver reportes), con un aviso claro de cambiarlos antes de usarlo en serio; comandos útiles (php artisan test, backup:run, documents:warm-pdfs, schedule:run); enlaces a docs/INSTALACION.md, docs/DECISIONES.md, docs/DIAGNOSTICO.md e instrucciones/CONTEXTO.md.

2. CLAUDE.md: actualízalo sin perder las reglas que siguen vigentes. Máximo ~90 líneas.
   (a) Al inicio: "Lee primero instrucciones/CONTEXTO.md (estado vivo del proyecto y traspaso entre chats)". Fuente de verdad: el prompt de modo autónomo del 2026-11-17 + docs/DECISIONES.md; el kickoff (instrucciones/00-kickoff-sistema-el-rey.md) queda como historia.
   (b) Cambia "Cómo trabajar": quita "esperar confirmación de Farid entre partes" y pon el flujo actual: Claude planifica y escribe el prompt en docs/codex-prompts.md → Farid lo pega en Codex (extensión de VS Code) → Codex escribe el código → Claude revisa (diff, php artisan test, npm run build) y actualiza instrucciones/CONTEXTO.md. Claude NO ejecuta Codex. Se mantienen: cambios quirúrgicos, UI en español / código en inglés, tests con Pest.
   (c) Regla 5: ahora SÍ hay factura fiscal SAR: cada CAI es una document_series con kind=invoice; formato 000-001-01-00000001; se bloquea si está agotada o vencida; avisos con ≤ 50 números o ≤ 15 días; pasa sola al CAI siguiente; exige RTN desde L 10,000; plantilla resources/views/documents/invoice.blade.php. La orden de pedido (serie OP) sigue sin decir nunca "FACTURA".
   (d) Arquitectura, agrega: AccountsService (libro derivado, abonos FIFO, antigüedad), FreightService (viajes fijos idempotentes), ReportService (Excel/ZIP), BackupService, SaleService::convertToInvoice y el estado de venta 'converted' (pedido facturado, no suma en totales), login por username, middleware role:, AmountInWords (total en letras). Confirma los nombres reales con grep antes de escribirlos.
   (e) Comandos: reemplaza admin@elrey.test / password por los usuarios del punto 1; agrega backup:run.
   (f) UI: dirección visual "pulpería ceibeña" (verde hoja #14532D, turquesa #0E7C86, mango #F5B700, tinta #1D3557), fuentes Atkinson Hyperlegible y Bricolage Grotesque locales, sin Tailwind; Inicio con 4 botones grandes (teclas 1–4).
   (g) Actualiza "Pendientes" con la lista de "Pendientes conocidos" de instrucciones/CONTEXTO.md (validar plantilla de factura con el contador, nota de crédito, razón social real, número inicial de OP, papel, precios y unidades reales).

3. AGENTS.md: reemplaza TODO el bloque <laravel-boost-guidelines> por un AGENTS.md corto (≤ 60 líneas) para Codex: qué es el proyecto (2 líneas); "lee instrucciones/CONTEXTO.md y docs/DECISIONES.md antes de empezar"; las reglas de negocio no negociables (copiadas de CLAUDE.md, ya con la regla 5 nueva); convenciones (UI en español sencillo para un señor de 60 años con botones grandes de ícono + texto; código en inglés; dinero con App\Support\Money, nunca float; nunca borrar ventas ni abonos, solo anular; no agregar dependencias npm/composer sin que el prompt lo pida; tests con Pest); y los comandos de verificación (php artisan test, npm run build, php artisan migrate:fresh --seed).

4. Crea docs/INSTALACION.md, para dejarlo en la PC del negocio:
   - Chrome en modo app (`--app=http://127.0.0.1:8000 --kiosk-printing`) con acceso directo en el escritorio (ejemplo para Windows).
   - Arrancar el servidor al iniciar la PC: Windows con el Programador de tareas (`php artisan serve`); Linux con un servicio systemd de ejemplo.
   - El programador de Laravel: cron `* * * * * php artisan schedule:run` o tarea de Windows cada minuto; explica que eso dispara el respaldo diario de las 18:00 y el calentado de PDFs (revisa routes/console.php y pon las horas reales que encuentres).
   - Respaldos: dónde quedan (confírmalo en BackupService), cómo copiarlos a una USB una vez por semana, cómo restaurar con `pg_restore --clean --if-exists -d frutverdrey archivo.dump`, y la variable PG_DUMP_PATH si pg_dump no está en el PATH (solo si BackupService la usa de verdad).
   - Se recomienda un UPS.
   - Checklist antes de usarlo en serio: cambiar contraseñas y PIN, poner la razón social y el RTN reales en Ajustes, apagar el CAI de ejemplo y registrar el real, confirmar el número inicial de la serie OP (OP_START_NUMBER), y correr `php artisan migrate:fresh --seed` con APP_ENV=production para arrancar SIN datos de ejemplo (advierte que migrate:fresh borra todo y solo se hace una vez, antes de empezar).

Aceptación: los 4 archivos existen y están en español claro; CLAUDE.md ≤ ~90 líneas y AGENTS.md ≤ 60; ningún comando, variable ni ruta inventada; `git diff --stat` muestra cambios solo en esos 4 archivos.
Al terminar, escribe un resumen corto: qué archivos cambiaste y cualquier cosa del proyecto que no coincidía con estas instrucciones (por ejemplo, una variable o comando que no existía).
```

### Tarea 4 original (historia, no pegar)

```
Proyecto: sistema "Frutas y Verduras El Rey" (Laravel 13 + Inertia/Vue 3 + Vuetify 4 + PostgreSQL 17). Lee docs/DIAGNOSTICO.md, docs/DECISIONES.md, routes/web.php, routes/console.php, app/Services/BackupService.php e instrucciones/00-kickoff-sistema-el-rey.md. Solo edita archivos .md; no toques código.
1. README.md: reemplázalo por completo (hoy es el README genérico de Laravel). En español: qué es el sistema (3 líneas); requisitos (PHP 8.4 con pdo_pgsql, bcmath, gd y zip; Composer; Node 20+; PostgreSQL 17 con la extensión unaccent); instalación (composer install, npm ci, cp .env.example .env, php artisan key:generate, crear las BD frutverdrey y frutverdrey_test, php artisan migrate --seed, npm run build, php artisan serve); usuarios de prueba: abuelo / rey2026 (admin, PIN 1234), empleado / venta2026 (vendedor), contador / contador2026 (solo lectura), con el aviso de cambiarlos antes de usarlo en serio; comandos útiles (php artisan test, backup:run, documents:warm-pdfs, schedule:run); enlaces a docs/.
2. CLAUDE.md: actualízalo sin perder las reglas que siguen vigentes. Cambios: (a) arriba aclara que la fuente de verdad ahora es el prompt de modo autónomo del 2026-11-17 más docs/DECISIONES.md, y que el kickoff queda como historia; (b) quita "esperar confirmación de Farid entre partes" y pon el flujo actual: Claude planifica y revisa, y Codex escribe el código (binario en ~/.vscode/extensions/openai.chatgpt-*/bin/macos-aarch64/codex, `codex exec -s workspace-write`; los prompts se guardan en docs/codex-prompts.md); (c) la regla 5 cambia: ahora SÍ hay factura fiscal SAR (series kind=invoice, una por CAI, formato 000-001-01-00000001, bloqueo por agotada o vencida, avisos, plantilla resources/views/documents/invoice.blade.php); la orden de pedido sigue sin decir nunca "FACTURA"; (d) agrega las piezas nuevas en la arquitectura: AccountsService (libro derivado, FIFO), FreightService (viajes fijos idempotentes), ReportService (Excel/ZIP), BackupService, estado de venta 'converted' (orden facturada, no suma), login por username, middleware role:, AmountInWords; (e) en Comandos, los usuarios sembrados nuevos (ver punto 1) en vez de admin@elrey.test; (f) UI: dirección visual "pulpería ceibeña" (verde hoja #14532D, turquesa #0E7C86, mango #F5B700, tinta #1D3557), Atkinson Hyperlegible + Bricolage Grotesque locales, sin Tailwind. Máximo ~90 líneas.
3. AGENTS.md: reemplaza el bloque de "laravel-boost-guidelines" por un AGENTS.md corto para Codex: las reglas de negocio no negociables (copiadas de CLAUDE.md), las convenciones (UI en español sencillo, código en inglés, dinero con App\Support\Money, nunca borrar ventas ni abonos, tests con Pest) y los comandos de verificación.
4. Crea docs/INSTALACION.md para dejarlo en la PC del negocio: Chrome en modo app (`--app=http://127.0.0.1:8000 --kiosk-printing`) con acceso directo en el escritorio; arrancar el servidor al iniciar la PC (Windows: Programador de tareas con `php artisan serve`; Linux: un servicio systemd de ejemplo); el programador de Laravel (cron `* * * * * php artisan schedule:run` o una tarea de Windows cada minuto) para el respaldo diario de las 18:00 y el calentado de PDFs de las 02:00; respaldos: dónde quedan (storage/app/backups/*.dump), cómo copiarlos a una USB, cómo restaurar con `pg_restore --clean --if-exists -d frutverdrey archivo.dump`, y la variable PG_DUMP_PATH si pg_dump no está en el PATH (Windows); un UPS recomendado; checklist antes de usarlo en serio: cambiar las contraseñas y el PIN, poner la razón social real, apagar el CAI de ejemplo y registrar el real, confirmar el número inicial de la serie OP (OP_START_NUMBER) y correr `php artisan migrate:fresh --seed` SIN los datos de ejemplo (APP_ENV=production).
Aceptación: Markdown claro, en español, sin inventar comandos que no existan en el proyecto.
```

## Tarea 5: corrida larga autónoma (la que hay que pegar)

Objetivo: que Codex avance lo más posible sin volver a Claude entre bloques. Codex deja todo
anotado en `docs/BITACORA-CODEX.md`; Claude revisa solo al final (o si Codex se detiene).

```
Proyecto: sistema de ventas "Frutas y Verduras El Rey" (La Ceiba, Honduras). Laravel 13 + Inertia 2 + Vue 3 (<script setup>) + Vuetify 4, PostgreSQL 17.
ANTES DE EMPEZAR lee completos: CLAUDE.md, AGENTS.md, instrucciones/CONTEXTO.md, docs/DECISIONES.md y docs/DIAGNOSTICO.md. Las "Reglas de negocio no negociables" de CLAUDE.md mandan sobre todo lo de abajo.

MODO DE TRABAJO (importante):
- Trabaja de forma AUTÓNOMA y continua: haz los bloques A → F en orden, sin pedir confirmación entre ellos. No te detengas a preguntar cosas que puedas decidir con sentido común y con las reglas del proyecto; decide, y anota la decisión en la bitácora.
- Después de CADA bloque ejecuta `php artisan test` y `npm run build`. Si algo falla, arréglalo antes de seguir. No pases al siguiente bloque con tests rojos.
- Lleva una bitácora en docs/BITACORA-CODEX.md (créala). Por bloque: qué cambiaste (archivos), decisiones que tomaste, qué quedó pendiente y el resultado de tests/build (número de tests). Frases cortas.
- SOLO detente y vuelve con el usuario si: (1) necesitas una decisión del negocio que no está en DECISIONES.md (precios, textos fiscales, papel, razón social); (2) la única solución viola una regla no negociable; (3) necesitas una dependencia nueva de npm/composer; (4) necesitas una migración que borre o cambie datos existentes. En esos casos, anótalo en la bitácora bajo "BLOQUEOS", sáltate esa parte y SIGUE con lo demás. Solo termina la corrida cuando todos los bloques estén hechos o bloqueados.
- Cambios quirúrgicos. No reescribas archivos enteros que funcionan. Conserva los nombres de rutas existentes. UI en español sencillo; código, tablas y columnas en inglés.
- Puedes tocar controladores, requests, servicios, páginas Vue y tests cuando el bloque lo pida. Migraciones: solo ADITIVAS (agregar columnas/índices/tablas), nunca destructivas. No ejecutes migrate:fresh fuera de la BD de pruebas.
- NO toques: la lógica de numeración (DocumentNumberService), la inmutabilidad de Sale, ni el cálculo de montos (Money), salvo para corregir un bug demostrado con un test que falle primero.

BLOQUE A — Revisión de UI "como el abuelo" (lo más importante).
Recorre TODAS las páginas de resources/js/Pages (Home, Auth/Login, Ventas/Index, Nueva, Show, Clientes/Index, Show, Cobros, Fletes, Productos, Reportes, Ajustes, Usuarios), su AppLayout y los componentes que usan. Imagina a un señor de 60 años con una tablet de 1024 px y a un empleado en una PC de 1366 px. Por cada pantalla revisa y corrige:
  1. Textos: sin jerga técnica ni inglés visible ("status", "converted", "void", "seller", null, undefined, fechas ISO, montos sin "L"). Usa las etiquetas de @/lib/format.js; si falta un helper de etiqueta, agrégalo ahí.
  2. Botones: grandes, con ícono Y texto; la acción principal destacada; acciones peligrosas (anular, deshacer abono, borrar usuario/producto) siempre con ConfirmDialog y texto que diga qué va a pasar.
  3. Estados vacíos (EmptyState con una frase y un botón para la siguiente acción), estados de carga (botón con :loading mientras se envía, sin doble envío) y errores de validación visibles junto al campo.
  4. Teclado: Enter envía el formulario principal; Esc cierra diálogos; autofocus en el primer campo de cada diálogo; en Ventas/Nueva se debe poder buscar producto, poner cantidad y agregar la línea sin ratón.
  5. Responsivo: nada se sale de la pantalla a 1024 px; las tablas anchas tienen scroll horizontal o columnas ocultables; ningún botón importante queda escondido.
  6. Consistencia: mismos nombres para lo mismo en todas las pantallas (p. ej. siempre "Pedido" y "Factura", "Abono", "Quién me debe").
  7. Sin efectos pesados (nada de backdrop-filter, animaciones largas ni sombras gigantes).
Anota en la bitácora una lista "pantalla → problemas encontrados → qué arreglaste".

BLOQUE B — Mensajes de validación y errores en español.
Verifica que existan traducciones al español (lang/es/validation.php, auth.php, passwords.php, pagination.php) con nombres de atributos amigables ("rtn" → "RTN o identidad", "customer_id" → "cliente", etc.) y que APP_LOCALE=es esté en .env.example. Si faltan, créalas a mano (sin paquetes). Revisa las Form Requests: mensajes claros como "Escriba el nombre del cliente". Verifica que las páginas de error (403, 404, 419, 500) muestren algo entendible en español con un botón "Volver al inicio" (resources/views/errors/*.blade.php o manejo en Inertia), sin dependencias nuevas.

BLOQUE C — Pruebas de permisos y flujos críticos (Pest).
Revisa tests/ y agrega lo que falte, sin duplicar:
  - Por rol: el contador solo puede ver (cada POST/PUT/PATCH/DELETE devuelve 403); el vendedor no entra a Usuarios ni Ajustes; un invitado es enviado al login.
  - Anular venta sin PIN o con PIN incorrecto falla; con PIN de admin funciona y guarda motivo, usuario y fecha.
  - Factura: sin RTN desde L 10,000 falla; serie vencida o agotada salta a la siguiente; sin serie utilizable da un mensaje claro (no un error 500).
  - Convertir pedido → factura dos veces no duplica la factura ni la deuda.
  - Abonos: abono mayor al saldo (decide el comportamiento según DECISIONES.md y anótalo), deshacer abono, FIFO.
  - Reportes: los totales usan Sale::issued() (las anuladas y las convertidas no suman) en un rango que cruza la medianoche de Tegucigalpa.
Si un test revela un bug, arréglalo de forma mínima y anótalo.

BLOQUE D — Robustez y rendimiento.
  - Busca consultas N+1 en los controladores de listados (Ventas, Clientes, Cobros, Fletes, Reportes) y usa with()/withSum() donde haga falta.
  - Los listados largos deben ir paginados o limitados, con búsqueda que use unaccent (ya disponible).
  - Revisa que ninguna acción de venta, abono o anulación pueda borrar registros (busca ->delete(), forceDelete, cascadeOnDelete sobre sales/sale_items/pagos).
  - Revisa que todas las fechas mostradas usen America/Tegucigalpa.
Anota en la bitácora lo que encontraste aunque no lo cambies.

BLOQUE E — Impresión y documentos.
Revisa las Blade de resources/views/documents (factura, orden de pedido, estado de cuenta) en tamaño carta y media carta: que nada se corte, que el total en letras y los datos fiscales salgan, que la OP nunca diga "FACTURA" y que la copia diga "COPIA". Usa solo CSS compatible con dompdf (tablas, sin flex/grid). Agrega o ajusta tests que rendericen cada plantilla con datos de ejemplo y verifiquen textos clave.

BLOQUE F — Documento de entrega.
Crea docs/ENTREGA.md en español sencillo para Farid: qué hace el sistema (por pantalla), cómo instalarlo y correrlo (apunta a docs/INSTALACION.md en lugar de repetir), usuarios de prueba y que deben cambiarse, decisiones clave (resumen de DECISIONES.md con enlace), lo que quedó pendiente (nota de crédito, validar plantilla SAR con el contador, razón social, número inicial de OP, papel, precios reales) y la lista de BLOQUEOS de esta corrida. Máximo ~2 páginas.

AL TERMINAR: corre por última vez `php artisan test` y `npm run build`, pon el resultado al inicio de docs/BITACORA-CODEX.md y responde con un resumen de 5–10 líneas: bloques hechos, número de tests, archivos más tocados y BLOQUEOS (si hay).
```

## Tarea 6: identidad visual con el logo real (la que hay que pegar)

Plan de Claude (2026-11-18, v2 con los logos que agregó Farid en
`frutas-y-verduras-el-rey-logos-svg/`). Reemplaza la v1 «rótulo de mercado», que inventaba una
corona SVG.

**Diagnóstico.** La UI se ve como Vuetify por defecto: barra verde y cajón blanco, todo en tarjetas
iguales, título repetido en barra y página, `mdi-crown` como logo. El logo real (círculo con una
caja de madera con guineo, zanahoria y piña, y «FRUTAS Y VERDURAS / EL REY» en serif condensada)
ya da identidad: hay que dejarlo mandar.

**Hallazgos sobre los archivos.** Verde de marca `#2D7A3E` y negro `#1A1A1A`. Las versiones
*-color, horizontal, stacked, icon y wordmark* traen un filtro `feTurbulence` + `feDisplacementMap`
(borde rugoso tipo sello): es caro de dibujar, la vista previa de macOS ni siquiera mostró el
grande, y dompdf no soporta filtros. Claude probó quitar `<defs><filter…></defs>` y los atributos
`filter="url(…)"`: se ven nítidos y pesan ~10 % menos. `mono`, `inverse` y `reversed` ya vienen
sin filtro. Los textos del logo son trazos (no dependen de fuentes).

**Decisiones de diseño.**
- Color: el verde de marca `#2D7A3E` pasa a ser `primary` (blanco encima: 5.3:1, AA). `#1E5A2C`
  (hoja oscura) para el cajón y el estado presionado. Se quedan turquesa, mango, tinta y chile.
  Desaparece `#14532D` para no tener dos verdes que compiten.
- Tipografía: Bricolage (con eje óptico `opsz.css`, ya instalado) para títulos y montos; Atkinson
  para lo demás. El logo trae su propia serif: el contraste serif-logo / grotesca-UI es buscado.
- Audacia en un solo lugar: los rótulos del Inicio (marco pintado) + el logo. **Quité el toldo a
  rayas de la v1**: con el logo real ya hay identidad en el marco y sería un accesorio de más.
  El talonario perforado del resumen de venta se queda porque comunica «esto es el pedido».
- Documentos impresos: logo `mono` pequeño en el encabezado de OP, factura y estado de cuenta.
  Cambia la apariencia de reimpresiones viejas (no sus datos): se versiona la caché a `print-v3`.

```
Proyecto: sistema de ventas "Frutas y Verduras El Rey" (La Ceiba, Honduras). Laravel 13 + Inertia 2 + Vue 3 (<script setup>) + Vuetify 4, dompdf 3.
ANTES DE EMPEZAR lee: CLAUDE.md, AGENTS.md, instrucciones/CONTEXTO.md, docs/DECISIONES.md y la regla 10 de CLAUDE.md. Las reglas no negociables mandan.

MODO: autónomo, bloques A → G en orden, sin pedir confirmación. Tras cada bloque: `php artisan test` y `npm run build`; no sigas con algo rojo. Bitácora: sección "Tarea 6" en docs/BITACORA-CODEX.md (archivos, decisiones, resultados). BLOQUEOS solo por: dependencia nueva, migración, regla no negociable o decisión de negocio; anótalos y sigue.
ALCANCE: apariencia y marca. NO cambies lógica, props de páginas, rutas, controladores, modelos, migraciones, Money, DocumentNumberService ni la inmutabilidad de Sale. En las Blade de documentos SOLO se agrega el logo (bloque F). No agregues dependencias npm/composer.
PROHIBIDO en la UI: degradados decorativos (solo se permite el perforado del talonario), fondos crema, backdrop-filter, sombras difusas grandes, animaciones de entrada, elevación al pasar el ratón, MAYÚSCULAS sostenidas con espaciado, "A · B" en textos visibles nuevos, "→" en botones, fuentes monoespaciadas, colores fuera de los tokens.

BLOQUE A — Archivos de marca (public/brand/).
Origen: frutas-y-verduras-el-rey-logos-svg/ (NO lo modifiques ni lo borres; tampoco el .zip).
1. Crea public/brand/ con versiones limpias. Para las que traen filtro, quita el bloque <defs> que contiene <filter …>…</filter> y todos los atributos filter="url(#…)"; no toques los <path>. Ejemplo que funcionó: perl -0pe 's#<defs>\s*<filter.*?</filter>\s*</defs>##gs; s# filter="url\([^)]*\)"##g'. Confirma con grep que no queda "<filter" ni "feTurbulence".
   - logo-horizontal.svg  (de horizontal-color)
   - logo-icon.svg        (de icon-color)
   - logo-stacked.svg     (de stacked-color)
   - logo-inverse.svg     (copia de inverse: blanco, cuadrado, para fondos verdes)
   - logo-mono.svg        (copia de mono, para documentos)
   - logo-horizontal-inverse.svg: logo-horizontal con TODOS los fill/color (#2D7A3E y #1A1A1A) cambiados a #FFFFFF, para el cajón oscuro.
   Quita width/height fijos del <svg> raíz (deja viewBox) para que escalen por CSS; conserva role="img" y pon aria-label="Frutas y Verduras El Rey".
2. Favicon: public/favicon.svg = logo-icon.svg. En resources/views/app.blade.php agrega <link rel="icon" type="image/svg+xml" href="/favicon.svg"> antes del favicon.ico (que se queda).
3. Componente resources/js/Components/BrandLogo.vue: <img> con alt "Frutas y Verduras El Rey", prop variant ('horizontal' | 'horizontal-inverse' | 'icon' | 'stacked' | 'inverse') y prop height (número en px). Ancho automático. Sin lógica extra.

BLOQUE B — Tokens y base (resources/css/app.css, resources/js/plugins/vuetify.js, resources/js/app.js).
1. app.js: cambia `import '@fontsource-variable/bricolage-grotesque';` por `import '@fontsource-variable/bricolage-grotesque/opsz.css';`. Verifica que el font-family siga siendo 'Bricolage Grotesque Variable'.
2. Tokens en :root: --marca #2D7A3E, --hoja-oscura #1E5A2C, --turquesa #0E7C86, --mango #F5B700, --tinta #1D3557, --chile #B42318, --texto #1B2320, --suave #52615A, --fondo #EEF3F0, --linea #CFDAD4; --radio-rotulo 6px, --radio-tarjeta 10px, --radio-boton 8px. Reemplaza los usos de --hoja por --marca (o deja --hoja como alias de --marca) y elimina #14532D de todo resources/js y resources/css.
3. Vuetify: primary '#2D7A3E', success '#2D7A3E'. Defaults: VBtn rounded 'md'; VCard rounded con 10px, elevation 0, border.
4. Títulos (.page-title, h2 de sección, .text-h*): font-optical-sizing auto; .page-title 2rem/800; h2 de sección 1.35rem/700, igual en todas las páginas.
5. Botones que se aprietan: .v-btn--variant-flat y --variant-elevated con color de tema llevan repisa sólida box-shadow 0 3px 0 rgba(0,0,0,.28); en :active translateY(2px) y 0 1px 0. Outlined con borde de 2px.
6. Tablas: encabezado con fondo surface-variant en tipo oración, filas de al menos 52px, hover rgba(45,122,62,.06), montos a la derecha con tabular-nums.
7. Conserva :focus-visible mango de 3px y el bloque prefers-reduced-motion.

BLOQUE C — Marco de la app (AppLayout.vue).
1. Cajón: fondo var(--hoja-oscura); arriba BrandLogo variant horizontal-inverse height 64 enlazado a Inicio (reemplaza el v-icon mdi-crown y los textos brand-name/brand-sub). Texto blanco al 92 %, íconos al 80 %. Ítem activo: fondo mango sólido con texto tinta, peso 700. Hover rgba(255,255,255,.08). Divisor sutil antes de Ajustes y Usuarios. "Entró como … / Salir" con botón outlined blanco.
2. Barra superior: fondo surface, texto tinta, borde inferior 1px var(--linea), alto 72px, flat. En lgAndUp NO repite el título (la página tiene su h1): a la izquierda "Volver" si aplica; a la derecha la fecha larga de hoy (Intl con timeZone 'America/Tegucigalpa', o longDate de @/lib/format.js) y el botón "Inicio". En pantallas menores: "Menú", BrandLogo variant icon height 40 y el título.

BLOQUE D — Inicio y Login.
1. Home.vue, rótulos: color sólido (pedido --marca, factura --turquesa, quién me debe --mango, fletes --tinta), radio var(--radio-rotulo), marco pintado interior box-shadow inset 0 0 0 8px <color>, inset 0 0 0 10px rgba(255,255,255,.55) (en mango: rgba(29,53,87,.45)), más repisa sólida 0 5px 0 de un tono ~15 % más oscuro. Etiqueta Bricolage 800 2.1rem line-height 1. Ícono 52px arriba a la izquierda. La tecla 1–4 como ficha redonda de 34px (blanco al 90 %, número en tinta) arriba a la derecha. Sin translateY en hover: oscurece el fondo ~8 % con variables --rotulo y --rotulo-hover por clase; en :active baja 3px y reduce la repisa. Foco visible.
2. Home.vue, "Hoy / Por cobrar / Próximos fletes": un solo panel blanco (borde 1px, radio 10px) en columnas separadas por reglas verticales de 1px; en < 900px se apilan con reglas horizontales. Cada columna: h2 1.1rem en tinta, cifra en Bricolage 800 2rem tabular (venta de hoy en --marca, por cobrar en --chile) y la frase debajo. Fletes como lista con la fecha en negrita.
3. Login.vue: fondo --marca. Arriba de la tarjeta BrandLogo variant inverse height 200 (en pantallas de menos de 700px de alto, 140). Quita el v-icon y el h1 "El Rey" (el logo ya lo dice) y deja el texto "La Ceiba, Honduras" pequeño en blanco al 85 % debajo del logo. La tarjeta queda igual.

BLOQUE E — Pantallas de trabajo.
1. Ventas/Nueva.vue, resumen (.summary): talonario con borde superior perforado (fila de círculos del color de fondo con radial-gradient en un ::before de 10px), total en Bricolage 800 2.75rem tabular, sticky top 88px en lgAndUp. No toques el script.
2. h2.step "¿Para quién es?" y "¿Qué lleva?" con ficha redonda numerada (1, 2) en --marca y número blanco. Es una secuencia real; no numeres nada más.
3. Recorre resources/js/Pages y Components: elimina hex sueltos fuera de los tokens, radios y sombras distintos; .page-title/.page-sub y h2 de sección uniformes. Cobros conserva el borde izquierdo mango y el monto en chile. Chips de estado con statusColor de format.js.
4. EmptyState.vue: alineado a la izquierda en pantallas anchas, ícono 40px dentro de un cuadrado redondeado (8px) de fondo surface-variant, título Bricolage 1.35rem, frase y botón. Sin ilustraciones.

BLOQUE F — Logo en documentos impresos (order, invoice, statement en resources/views/documents).
1. Agrega el logo mono (public/brand/logo-mono.svg) al encabezado de las tres plantillas, como <img> con data URI base64 calculada en Blade (base64_encode(file_get_contents(public_path('brand/logo-mono.svg')))), así funciona igual en el visor HTML y en dompdf sin depender de chroot ni de URLs remotas. Tamaño: 22 mm de alto en carta y 16 mm en media carta (usa print-style.blade.php). Solo CSS compatible con dompdf: tabla de dos celdas (logo | datos del emisor), sin flex ni grid.
2. Los datos del emisor siguen saliendo del snapshot; no cambies textos fiscales. La OP sigue sin decir "FACTURA" en ningún lado (el logo tampoco lo dice).
3. Cambia print-v2 por print-v3 en app/Services/DocumentPdfService.php (docblock y sprintf) y en config/documents.php (revisa cualquier otro uso con grep -rn print-v2 app config) para que no se sirvan PDFs viejos sin logo. No borres la caché antigua. Ajusta los tests que dependan de "print-v2".
4. Test (Pest) en tests/Feature/PrintLayoutTest.php o uno nuevo: el HTML de cada plantilla contiene 'data:image/svg+xml;base64,' y generar el PDF de una factura y de una OP no lanza excepción, en carta y en media carta.
5. Si dompdf no dibuja bien el SVG (error, PDF vacío, o tarda más de 3 s por documento), NO lo fuerces: quita el logo de los PDF, déjalo solo en la UI, vuelve a print-v2 y anótalo en BLOQUEOS.
6. Agrega en docs/DECISIONES.md: "Logo en documentos: versión mono en el encabezado; afecta la apariencia de reimpresiones antiguas, no sus datos; caché print-v3."

BLOQUE G — Revisión final.
1. Busca en resources/js y resources/css: "backdrop-filter", "linear-gradient", "uppercase", "letter-spacing" > 0.02em, "mdi-crown", "#14532D", "→". Corrige.
2. grep -r "<filter\|feTurbulence" public/brand → debe dar vacío.
3. Contraste: texto sobre mango en tinta; texto sobre --marca, --turquesa, --tinta y --hoja-oscura en blanco.
4. Revisión de CSS a 1024 px y 1366 px: el logo del cajón no se corta, la barra no se encima, el Inicio no se desborda.
5. `php artisan test` y `npm run build` verdes. Responde en 5–10 líneas: bloques hechos, archivos tocados, número de tests, BLOQUEOS.
```

## Tarea 6b: logo en documentos con PNG (la que hay que pegar)

Plan de Claude (2026-11-18), tras revisar la Tarea 6. Revisión: 116/116 pruebas, 638 aserciones,
build OK; originales del logo intactos; sin `backdrop-filter` ni hex sueltos en páginas.

**Diagnóstico del bloqueo F.** `logo-mono.svg` no trae `width`/`height`, solo
`viewBox="0 0 4358 4358"`; dompdf lo toma como imagen de 4358 × 4358 px y choca con su límite.
Claude probó en un PDF aparte: (a) con `width="240" height="240"` el error desaparece, pero
php-svg-lib **no escala los trazos** y el logo se sale de la hoja: descartado. (b) Un **PNG** de
600 px dibujó el logo nítido y del tamaño pedido. GD está instalado; no hace falta dependencia.
Claude ya generó `public/brand/logo-print.png` (400 × 400, fondo transparente, 38 KB, hecho con
`qlmanage` desde `logo-mono.svg`). Lo que falló antes en media carta: el estado de cuenta pasó a
dos páginas al sumar el encabezado; por eso el logo va **al lado** de los datos del emisor.

```
Proyecto: sistema de ventas "Frutas y Verduras El Rey". Laravel 13 + dompdf 3.
ANTES DE EMPEZAR lee: CLAUDE.md, AGENTS.md, instrucciones/CONTEXTO.md y docs/DECISIONES.md (decisión 24).
MODO: autónomo, sin pedir confirmación. Al terminar: `php artisan test` y `npm run build` verdes. Bitácora: sección "Tarea 6b" en docs/BITACORA-CODEX.md.
ALCANCE: SOLO las tres Blade de resources/views/documents (order, invoice, statement), print-style.blade.php si hace falta, la versión de caché y sus pruebas. NO cambies datos, textos fiscales, snapshots, Money, DocumentNumberService, la inmutabilidad de Sale, la UI Vue, rutas ni migraciones. Sin dependencias nuevas. NO modifiques public/brand/logo-print.png ni los SVG.

1. Logo: en el encabezado de las tres plantillas, <img> con data URI calculada una sola vez en el @php de arriba:
   $logo = 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('brand/logo-print.png')));
   Alto 22 mm en carta y 16 mm en media carta (regla en print-style.blade.php). Debe ir EN LA MISMA FILA que los datos del emisor: tabla de dos celdas (logo a la izquierda con ancho fijo | nombre, actividad, dirección, etc. como están ahora), para no sumar altura. Solo CSS que dompdf entiende: tablas, sin flex ni grid. Sin alt visible si la imagen carga.
2. Los datos del emisor siguen saliendo del snapshot, sin cambios de texto. La OP sigue sin decir "FACTURA".
3. Caché: cambia print-v2 por print-v3 en app/Services/DocumentPdfService.php (docblock y sprintf) y en cualquier otro uso (grep -rn "print-v2" app config tests). Ajusta los tests. La invalidación al anular debe cubrir ambos papeles en print-v3. No borres la caché antigua.
4. Pruebas Pest (PrintLayoutTest o DocumentPdfTest):
   - El HTML de order, invoice y statement contiene 'data:image/png;base64,'.
   - El PDF de una OP, una factura y un estado de cuenta, en carta y media carta, se genera sin excepción y el texto del PDF NO contiene "Image dimensions" ni "exceed".
   - Las pruebas existentes de "una página por documento de ejemplo" y de dimensiones siguen pasando sin aflojarlas. Si el estado de cuenta en media carta pasa a 2 páginas, baja el logo a 13 mm SOLO en media carta; si aún así no cabe, quita el logo solo del estado de cuenta en media carta y anótalo.
5. Revisión visual real: genera los PDF de ejemplo (OP y factura, ambos papeles) en una carpeta temporal fuera del proyecto, conviértelos a imagen (sips -s format png archivo.pdf --out archivo.png) e inspecciónalos: logo visible, nítido, sin salirse, sin tapar el número ni el CAI. Bórralos al final.
6. docs/DECISIONES.md: reemplaza la decisión 24 por "Logo en documentos con PNG (public/brand/logo-print.png, 400 px, derivado de logo-mono.svg) porque php-svg-lib de dompdf no escala trazos SVG; afecta la apariencia de reimpresiones antiguas, no sus datos; caché print-v3." Actualiza también la línea de print-v2 en instrucciones/CONTEXTO.md.
7. Responde en 5–8 líneas: archivos tocados, número de tests, resultado visual, BLOQUEOS.
```

## Tarea 7: montos exactos en pantalla y aviso de contraseñas de prueba (la que hay que pegar)

Plan de Claude (2026-11-18), tras cerrar la Tarea 6b (116/116, 715 aserciones, build OK).

**Hallazgo A (regla 6).** `Ventas/Nueva.vue` calcula la vista previa con `float`:
`round2 = Math.round((n + Number.EPSILON) * 100) / 100`. El servidor usa `Money` (bcmath, half-up).
Claude comparó por fuerza bruta cantidades 0.01–50.00 × precios 0.01–30.00: **52 238 combinaciones
muestran un centavo distinto** al que se emite. Ejemplos: 0.15 × 13.70 → pantalla 2.05, factura
2.06; 0.09 × 26.50 → 2.38 / 2.39; 0.15 × 14.50 → 2.17 / 2.18. El ISV 15 % falla igual
(base 13.70 → 2.05 / 2.06; 14.50 → 2.17 / 2.18). El vendedor le dice al cliente un total que
no coincide con la factura, y los avisos de L 10,000 y de límite de crédito pueden fallar en el borde.
`PaymentDialog.vue` también resta saldos con `Number`.

**Hallazgo B.** Los usuarios sembrados traen contraseñas conocidas (`rey2026`, `venta2026`,
`contador2026`, PIN `1234`); CLAUDE.md pide cambiarlas antes de operar, pero nada lo recuerda.

```
Proyecto: sistema de ventas "Frutas y Verduras El Rey". Laravel 13 + Inertia 2 + Vue 3 (<script setup>) + Vuetify 4.
ANTES DE EMPEZAR lee: CLAUDE.md, AGENTS.md, instrucciones/CONTEXTO.md, docs/DECISIONES.md y app/Support/Money.php.
MODO: autónomo, bloques A → C en orden, sin pedir confirmación. Tras cada bloque: `php artisan test`, `npm run build` y (desde A) `node --test resources/js/lib/`; no sigas con algo rojo. Bitácora: sección "Tarea 7" en docs/BITACORA-CODEX.md. BLOQUEOS solo por: dependencia nueva, migración, regla no negociable o decisión de negocio; anótalos y sigue.
ALCANCE: NO cambies Money.php, SaleService, DocumentNumberService, la inmutabilidad de Sale, migraciones, rutas ni plantillas de documentos. Sin dependencias npm/composer (node --test viene con Node). Los cálculos que se guardan siguen siendo del servidor; esto solo corrige lo que se MUESTRA.

BLOQUE A — Dinero exacto en el navegador.
1. Crea resources/js/lib/money.js (ESM, sin dependencias) que trabaje con ENTEROS, igual que Money.php:
   - toCents(v): acepta string o número ("13.7", "13.70", 13.7, "", null → 0), negativos incluidos; parsea el TEXTO decimal (usa String(v)), nunca multiplica un float por 100. Más de 2 decimales: redondea half-up a centavos (mismo criterio que Money::round, también para negativos según Money.php).
   - toQty(v): cantidad en centésimas (la columna quantity es decimal(10,2)), mismo parseo.
   - lineTotalCents(qty, price): (qtyCentésimas × priceCentavos) es un entero en 1/10000; redondea half-up a centavos. Usa BigInt o asegúrate de no pasar Number.MAX_SAFE_INTEGER.
   - taxCents(baseCents, rate) con rate como string '0.15' / '0.18' → entero de tasa en centésimas, misma regla half-up.
   - addCents(...), fromCents(c) → string "2.06" (para mostrar con money() de format.js).
2. resources/js/lib/money.test.js con node:test y node:assert. Casos obligatorios (deben dar lo de la factura): 0.15×13.70=2.06, 0.09×26.50=2.39, 0.15×14.50=2.18, 0.15×17.90=2.69; taxCents(1370,'0.15')=206, taxCents(1450,'0.15')=218; toCents("13.7")=1370, toCents("")=0, toCents("-5.5")=-550; un total grande (999 × 99999.99) sin pérdida. Si hace falta, agrega "type": "module" solo al test con extensión .mjs en vez de tocar package.json.
3. Prueba cruzada con el servidor: un test Pest (tests/Unit/MoneyParityTest.php o similar) que genere ~200 pares deterministas (incluye los ejemplos de arriba) con Money::lineTotal y Money::tax y escriba o compare un JSON de referencia en tests/fixtures/money-cases.json; el test de node lee ese mismo JSON y compara. Ambos deben coincidir caso por caso.
4. Ventas/Nueva.vue: reemplaza round2 / lineTotal / totals por money.js (todo en centavos y convertido a texto solo al mostrar). overLimit y needsId comparan en centavos (10000 → 1000000). No cambies el envío del formulario ni otras partes del script.
5. PaymentDialog.vue: el saldo "después" con centavos (addCents / resta). Busca otros cálculos de dinero en resources/js (grep Number\(|parseFloat|toFixed) y corrígelos solo si SUMAN, RESTAN o COMPARAN dinero; comparar > 0 para mostrar u ocultar algo puede quedarse. Reportes/Index.vue `avg` es solo informativo: pásalo a centavos si es trivial, si no déjalo y anótalo.

BLOQUE B — Aviso de contraseñas de prueba.
1. Crea app/Support/DefaultCredentials.php: lista de usuarios sembrados y sus valores por defecto (los de database/seeders/AdminUserSeeder.php, incluyendo ADMIN_PASSWORD/ADMIN_PIN de env si se usaron). Método que devuelve los usernames ACTIVOS cuya contraseña sigue siendo la de prueba (Hash::check) y si el PIN del admin sigue siendo el de prueba. Nunca devuelve ni registra las contraseñas.
2. Solo para admin: UserController (página Usuarios) y SettingsController (Ajustes) pasan un prop `default_credentials` con esa lista. No lo calcules en HandleInertiaRequests ni en Inicio (Hash::check es lento a propósito).
3. En Usuarios y Ajustes: v-alert type warning arriba, en español sencillo: "Estos usuarios todavía tienen la contraseña de prueba: abuelo, empleado. Cámbielas antes de usar el sistema con clientes." (y "El PIN de anular sigue siendo el de prueba" si aplica). En Ajustes, con botón "Ir a Usuarios". Sin aviso si la lista está vacía.
4. Pest: con usuarios sembrados el admin ve el prop con los tres nombres; tras cambiar la contraseña de uno, ese ya no aparece; vendedor y contador no reciben el prop (o reciben 403 donde ya lo hacían).

BLOQUE C — Cierre.
1. docs/DECISIONES.md: "Montos en pantalla con enteros (resources/js/lib/money.js), mismo redondeo que Money; prueba de paridad con JSON compartido." y "Aviso de contraseñas de prueba en Usuarios y Ajustes, solo admin."
2. Actualiza instrucciones/CONTEXTO.md (estado y comando `node --test resources/js/lib/`) y agrega ese comando a la sección de comandos de CLAUDE.md y AGENTS.md.
3. `php artisan test`, `node --test resources/js/lib/` y `npm run build` verdes. Responde en 5–10 líneas: bloques hechos, archivos tocados, número de tests (PHP y node), BLOQUEOS.
```

## Tarea 8: fallos del recorrido en navegador (hecha y revisada)

Plan de Claude (2026-11-18). Recorrido automatizado con Chrome sin ventana (Playwright desde la
carpeta temporal, sin dependencias en el proyecto) contra una base aparte `frutverdrey_demo`,
servida en el puerto 8001. **Pasó:** permisos y menú de los 3 roles en todas las páginas
(1024 y 1366 px), sin scroll horizontal ni errores JS; teclas 1–4; Enter en el buscador de
productos; doble clic y Ctrl+Enter repetido crean un solo documento; cliente preseleccionado
desde su ficha; conversión sin CAI vigente (el diálogo conserva el RTN y explica el error);
anular (cancelar, Esc, PIN malo, PIN bueno, conserva número y motivo); búsqueda sin acentos en
Clientes y Quién me debe; abono con teclado y Deshacer con confirmación; vistas semana y mes de Fletes.

**Falló (en orden de gravedad):**
- **A. Se pierden líneas al guardar una venta.** Con cantidad «abc» o «2,5» en Papas y «2» en
  Repollo, el pedido se emitió solo con Repollo y sin ningún aviso. Solo el diálogo decía
  «1 producto». Causa: `activeRows()` en `Ventas/Nueva.vue` filtra `Number(r.quantity) > 0`,
  y `Number("2,5")` da NaN, aunque `save()` sí cambia la coma por punto.
- **B. El abono deshecho desaparece de la cuenta.** La base lo conserva (`status=voided`), pero
  `AccountsService::entriesQuery()` solo lee los movimientos activos y la ficha del cliente no lo
  muestra. La guía de revisión pide que siga visible.
- **C. Editar un flete no guarda.** Al cambiar las Notas de un viaje existente (id 15, cobro a
  cuenta del Hotel Costa Atlántida) y pulsar Guardar: `PUT /fletes/15` responde 303, las notas
  quedan vacías en la base y el diálogo no se cierra, sin errores visibles. No se terminó de
  diagnosticar. Sospecha: un error de validación en un campo sin `error-messages` (status,
  purpose, charge_to_account) o un campo extra del `...f` de `openEdit`.
- **D. Montos cortados.** En Reportes, «Vendió L 1,037,150.00» se corta en la tarjeta verde
  a 1024 y a 1366 px. En la vista semana de Fletes a 1366 px, la tarjeta del sábado corta
  «L 1,500.00 a c…» y el chip «Pendiente». A 1024 px, cada día vacío ocupa ~220 px de alto.

**Sin verificar todavía** (quedan para revisión humana en `docs/REVISION-MANUAL.md`): Tab, Enter y
Esc en los formularios de Productos, Usuarios, Clientes y Fletes; descargas de Excel y ZIP
con revisión de totales; los flujos interactivos a 1366 px; la impresión.

```
Proyecto: sistema de ventas "Frutas y Verduras El Rey". Laravel 13 + Inertia 2 + Vue 3 (<script setup>) + Vuetify 4, PostgreSQL.
ANTES DE EMPEZAR lee: CLAUDE.md, AGENTS.md, instrucciones/CONTEXTO.md, docs/DECISIONES.md y resources/js/lib/money.js.
MODO: autónomo, bloques A → E en orden, sin pedir confirmación. Tras cada bloque: `php artisan test`, `node --test resources/js/lib/` y `npm run build`; no sigas con algo rojo. Bitácora: sección "Tarea 8" en docs/BITACORA-CODEX.md. BLOQUEOS solo por: dependencia nueva, migración, regla no negociable o decisión de negocio; anótalos y sigue.
ALCANCE: NO cambies Money.php, SaleService, DocumentNumberService, la inmutabilidad de Sale, migraciones ni plantillas de documentos. Sin dependencias npm/composer. Pruebas PHP solo en frutverdrey_test. No toques la base frutverdrey.

BLOQUE A — Nunca perder una línea de venta (lo más importante).
Hoy Ventas/Nueva.vue descarta en silencio las líneas con cantidad inválida: activeRows() filtra Number(r.quantity) > 0, así que "abc", "-1" y "2,5" (coma decimal) desaparecen y la venta se emite sin ellas.
1. En resources/js/lib/money.js (o un lib/quantity.js nuevo) agrega parseDecimalInput(v) → { empty: bool, valid: bool, value: string|null }: recorta espacios; "" o null → empty; acepta coma O punto como separador decimal ("2,5" → "2.50", "1.000" NO es mil: trátalo como 1.00 o inválido, decide y anótalo); máximo 2 decimales; rechaza letras, negativos y más de un separador. toQty/toCents deben usar el mismo criterio para que "2,5" valga 2.50 también en los totales.
2. Nueva.vue: separa tres casos en las filas (planilla y búsqueda):
   - vacía (cantidad vacía): se ignora, como hoy en el talonario;
   - válida > 0: entra en activeRows() y en los totales;
   - con algo escrito pero inválido, o 0: NO se ignora. Marca el campo con error ("Escriba una cantidad, por ejemplo 2 o 2.5") y askSave() no abre la confirmación: muestra "Revise las cantidades marcadas en rojo." Lo mismo para un precio escrito pero inválido.
   El payload envía la cantidad normalizada con punto. lineError() debe seguir apuntando a la fila correcta (hoy usa el índice dentro de activeRows()).
3. Tests node: "2,5" → 2.50; "2.5" → 2.50; " 3 " → 3.00; "abc", "-1", "1,2,3", "2.555" inválidos; "" vacío. Test Pest: POST ventas.store con quantity "abc", "0" y "-1" responde 422 con error en lines.N.quantity (agrégalo si no existe).

BLOQUE B — Movimientos anulados visibles en la cuenta del cliente.
La base guarda los abonos y las deudas anteriores anulados (status=voided, void_reason, voided_at, voided_by), pero la ficha del cliente y el estado de cuenta solo muestran los activos.
1. NO cambies entriesQuery() ni ningún cálculo de saldo, antigüedad o FIFO: los anulados siguen sin sumar.
2. Para la tabla "Movimientos de la cuenta" de Clientes/Show.vue (y el estado de cuenta imprimible si usa la misma lista), agrega los movimientos anulados del cliente como filas informativas: fecha, tipo, monto tachado, chip "Anulado", motivo y quién lo anuló; sin cargo ni abono en las columnas que suman, sin botón Anular, y el saldo de esa fila igual al de la fila anterior. Ordénalos por fecha junto a los demás.
3. Pest: tras anular un abono, el saldo vuelve a ser el anterior Y la respuesta de clientes.show incluye ese movimiento con estado anulado y su motivo.

BLOQUE C — Editar un flete existente no guarda.
Reproducido en el navegador: abrir un viaje existente en Fletes (con cliente y cobro a cuenta), cambiar Notas y pulsar Guardar → PUT /fletes/{id} responde 303, las notas no cambian en la base y el diálogo queda abierto sin mostrar errores. No está diagnosticado.
1. Reprodúcelo PRIMERO con un test Pest que haga PUT fletes.update con el MISMO payload que arma openEdit() + saveFreight() en Fletes/Index.vue (incluye los campos que llegan por el spread ...f: id, freight_schedule_id, customized, created_at, etc., y los formatos reales de trip_date y departure_time que manda la página). Encuentra el campo que falla.
2. Corrígelo de raíz: openEdit() copia solo los campos del formulario (los de `blank`), con trip_date como YYYY-MM-DD y departure_time como HH:MM; y/o ajusta la validación si el formato real es válido.
3. En el diálogo, cualquier error de un campo sin casilla propia (status, purpose, charge_to_account u otro) debe verse en un v-alert dentro del diálogo; nunca más un fallo silencioso. Revisa lo mismo en los diálogos de viajes fijos y motoristas.
4. Pest: editar notas de un viaje generado por un viaje fijo persiste y marca customized=true; cancelar un viaje con cobro a cuenta lo saca del saldo del cliente.

BLOQUE D — Textos y montos cortados.
1. Reportes/Index.vue: el monto grande de "Vendió" se corta en su tarjeta a 1024 y 1366 px (L 1,037,150.00). Debe caber completo hasta L 99,999,999.99 en ambos anchos: tamaño de letra que se adapte (clamp) o salto de línea limpio; nunca recorte ni puntos suspensivos en montos. Revisa las otras tarjetas de esa fila con el mismo criterio.
2. Fletes/Index.vue, vista semana a 1366 px: la tarjeta de viaje recorta el texto del cobro ("L 1,500.00 a c…") y el chip "Pendiente". Que el texto salte de línea y los chips quepan. A 1024 px (días apilados), un día sin viajes no debe ocupar ~220 px: altura mínima compacta. El botón "+ Anotar" de cada día debe tener tamaño normal (no size="small"), por la regla 10.
3. Busca en resources/js otros montos con text-overflow: ellipsis, white-space: nowrap u overflow: hidden que puedan cortar dinero y corrígelos con el mismo criterio. Anota en la bitácora cuáles revisaste.

BLOQUE E — Cierre.
1. docs/DECISIONES.md: "Cantidades y precios aceptan coma o punto decimal; una casilla escrita pero inválida bloquea el guardado; las vacías se ignoran." y "Los movimientos anulados se muestran en la cuenta del cliente, marcados y sin sumar."
2. docs/REVISION-MANUAL.md: no marques casillas. Agrega en "Pantallas y teclado": "Escribir 2,5 y abc como cantidad: la primera vale 2.50, la segunda se marca en rojo y no deja guardar."
3. Actualiza instrucciones/CONTEXTO.md. `php artisan test`, `node --test resources/js/lib/` y `npm run build` verdes. Responde en 5–10 líneas: bloques hechos, causa encontrada en C, archivos tocados, número de tests (PHP y node), BLOQUEOS.
```

## Tarea 9: puesta en marcha — revisión del sistema, prueba de respaldo y cuadre de reportes (la que hay que pegar)

Plan de Claude (2026-11-18). Todo el código pendiente ya está hecho; lo que falta para operar
depende de la PC real. Esta tarea da herramientas para comprobarla sin saber de sistemas:
1. `php artisan system:check` y un panel en Ajustes con semáforo (BD, pg_dump/pg_restore,
   zona horaria, datos del negocio, CAI, contraseñas de prueba, último respaldo, programador).
2. `php artisan backup:verify`: restaura un respaldo en una base APARTE y confirma que sirve.
   Nunca toca la base operativa.
3. Pruebas de cuadre: Excel y ZIP coinciden con el resumen de Reportes.
Sin migraciones ni dependencias. Pegar después de revisar la Tarea 8 (ya revisada).

```
Proyecto: sistema de ventas "Frutas y Verduras El Rey". Laravel 13 + Inertia 2 + Vue 3 (<script setup>) + Vuetify 4, PostgreSQL 17.
ANTES DE EMPEZAR lee: CLAUDE.md, AGENTS.md, instrucciones/CONTEXTO.md, docs/DECISIONES.md, docs/INSTALACION.md, app/Services/BackupService.php, app/Support/DefaultCredentials.php, app/Models/DocumentSeries.php, app/Services/ReportService.php y tests/Feature/ReportTest.php.
MODO: autónomo, bloques A → E en orden, sin pedir confirmación. Tras cada bloque: `php artisan test`, `node --test resources/js/lib/` y `npm run build`; no sigas con algo rojo. Bitácora: sección "Tarea 9" en docs/BITACORA-CODEX.md. BLOQUEOS solo por: dependencia nueva, migración, regla no negociable o decisión de negocio; anótalos y sigue.
ALCANCE: sin migraciones ni dependencias npm/composer. NO cambies Money.php, SaleService, DocumentNumberService, AccountsService (cálculos), la inmutabilidad de Sale ni las plantillas de documentos. Pruebas PHP solo en frutverdrey_test. NUNCA conectes, restaures, borres ni crees nada sobre la base operativa frutverdrey.

BLOQUE A — Revisión del sistema (servicio + comando).
1. app/Services/SystemCheckService.php con run(): array de checks {key, label, status: ok|warn|fail, detail, fix}. label/detail/fix en español sencillo; fix dice qué hacer ("Abra Ajustes → Negocio y escriba la razón social").
   Checks:
   - database: conexión y extensión unaccent instalada (fail si no).
   - pg_tools: pg_dump y pg_restore ejecutables (PG_DUMP_PATH; agrega PG_RESTORE_PATH con valor por omisión 'pg_restore', documentado en .env.example). Compara la versión mayor de pg_dump con la del servidor (SHOW server_version_num): menor que el servidor → fail.
   - timezone: config('app.timezone') y SHOW TimeZone de la sesión PostgreSQL = America/Tegucigalpa (warn si no).
   - business: razón social real (no vacía ni "por confirmar"), RTN, dirección (warn por cada uno que falte).
   - invoice_series: hay una serie de factura utilizable (DocumentSeries::usableInvoiceSeries) → ok; ninguna → fail ("No se puede facturar"). Si hay alertas (fiscalAlerts) → warn con el texto de la alerta. Serie OP activa → si no, fail.
   - default_credentials: reutiliza DefaultCredentials::inspect(); usuarios activos con contraseña o PIN de prueba → warn con los nombres. NUNCA incluyas contraseñas ni hashes en la salida.
   - last_backup: el respaldo más nuevo de BackupService::list(); ninguno → fail; más de 26 h → warn; muestra fecha y tamaño.
   - backup_verified: lee el resultado de la última verificación (Bloque B); nunca verificado o falló → warn; ok con fecha.
   - scheduler: el programador escribe un latido (ver punto 2); sin latido o de hace más de 10 min → warn ("El programador de tareas no está corriendo: el respaldo de las 6 p. m. no se hará solo") con el enlace a la sección de INSTALACION.md.
   - storage: storage/app, storage/app/backups y config('documents.cache_path') existen o se pueden crear y son escribibles (fail si no).
   - app_mode: APP_DEBUG=true o APP_ENV distinto de production → warn (no fail: en desarrollo es normal).
   - build: public/build/manifest.json existe (fail si no: "Falta ejecutar npm run build").
2. routes/console.php: Schedule::call que cada 5 minutos escribe la hora actual (ISO, Honduras) en storage/app/scheduler-heartbeat. Ese es el único cambio en el programador.
3. Comando `system:check` (app/Console/Commands/SystemCheck.php): tabla con ✔/!/✘, etiqueta, detalle y qué hacer; código de salida 1 si hay algún fail, 0 si no. Opción --json.
4. Pest (tests/Feature/SystemCheckTest.php): cada check probado en ok y en warn/fail con datos controlados (sin respaldos → fail; respaldo viejo → warn; latido viejo → warn; sin serie de factura → fail; usuario con contraseña sembrada → warn sin que aparezca la contraseña). Para pg_tools usa una ruta inexistente en PG_DUMP_PATH → fail. El comando devuelve 1 con un fail.

BLOQUE B — Probar un respaldo sin tocar la base del negocio.
1. BackupService: la carpeta sale de config('backup.directory', storage_path('app/backups')) (crea config/backup.php). En TODOS los tests de respaldo usa una carpeta temporal: prune() borra archivos y no puede tocar respaldos reales.
2. Agrega BackupService::verify(?string $file = null): array. Pasos:
   a. archivo = el indicado (solo nombre, dentro de la carpeta de respaldos; rechaza rutas con / o ..) o el más nuevo.
   b. base destino = nombre de la base configurada + '_restore_check'. GUARDAS (lanza excepción y no ejecuta nada): el destino es igual a la base configurada, igual a 'frutverdrey', o no termina en '_restore_check'.
   c. DROP DATABASE IF EXISTS destino; CREATE DATABASE destino (con las credenciales de la conexión; si no hay permiso, error claro: "El usuario de PostgreSQL no puede crear bases; vea INSTALACION.md").
   d. pg_restore --no-owner --no-privileges --exit-on-error -d destino archivo (Process, PGPASSWORD en el entorno, timeout 600).
   e. Conexión temporal en tiempo de ejecución a destino: cuenta filas de sales, sale_items, customers, account_movements, freights, users y toma max(issued_at) de sales. Falta una tabla → falla.
   f. DROP DATABASE destino salvo opción keep. Siempre en finally, también si falló.
   g. Guarda el resultado en {carpeta}/last-verify.json: {file, verified_at, ok, counts, error}. Lo lee el check backup_verified.
3. Comando `backup:verify {file?} {--keep}`: muestra el archivo, los conteos y "El respaldo sirve" o el error; código 1 si falla.
4. Ajustes (solo admin): agrega un botón "Probar el último respaldo" (POST ajustes.backup.verify, nueva ruta dentro del grupo role:admin). Confirmación antes ("Esto tarda unos segundos y no cambia sus datos"). Resultado en flash.status / flash.error. Muestra la fecha de la última prueba junto a la lista de respaldos.
5. Pest: las guardas rechazan destino = base configurada, 'frutverdrey' y nombres sin sufijo, sin ejecutar ningún proceso; nombres de archivo con ../ rechazados. Prueba de ida y vuelta real (backup de frutverdrey_test a carpeta temporal → verify → ok, conteos iguales a los de la base de prueba, la base _restore_check ya no existe al final), marcada como skipped con motivo si pg_dump/pg_restore no están o no hay permiso para crear bases. La ruta POST es solo para admin (agrega el caso a AccessPermissionsTest).

BLOQUE C — Panel "Revisión del sistema" en Ajustes.
1. SettingsController::index envía los checks (SystemCheckService::run) como prop diferida o perezosa para que Ajustes no se vuelva lenta (Inertia::defer o lazy + recarga parcial).
2. Ajustes/Index.vue: tarjeta arriba de todo, "¿Está todo listo para trabajar?". Una fila por check: ícono y color (verde ok, mango warn, rojo fail) Y texto (no solo color), etiqueta, detalle y, si no está ok, el "qué hacer". Resumen arriba: "Todo listo" o "Hay N cosas por revisar". Botón grande "Revisar de nuevo" (recarga parcial de esa prop). Sigue la paleta y fuentes ya definidas; sin backdrop-filter.
3. Inicio (solo admin): si hay algún fail, un v-alert discreto "Hay algo que revisar en Ajustes" con botón "Ver". No ejecutes los checks pesados (procesos pg_dump) en cada carga de Inicio: usa un caché de 10 minutos para el resumen (cuenta de fails/warns) y nunca para los detalles de Ajustes.
4. Pest: admin ve la prop en Ajustes; vendedor y contador reciben 403 como hoy; Inicio de admin incluye el aviso cuando hay un fail y el de vendedor no.

BLOQUE D — Los reportes cuadran.
Amplía tests/Feature/ReportTest.php (no dupliques lo que ya prueba). Arma un período con: factura contado, factura crédito, pedido contado, pedido convertido en factura, venta anulada, abono válido, abono anulado, deuda anterior y un flete cobrado a cuenta; incluye una venta a las 23:59 del último día y otra a las 00:00 del día siguiente (fuera del rango).
1. Lee el Excel generado con PhpSpreadsheet y comprueba: suma de la columna total de ventas activas en la hoja de ventas = total del resumen; suma de la hoja diaria = total del resumen; contado + crédito = total; abonos válidos de la hoja de abonos = abonos del resumen y el anulado aparece marcado "ANULADO (no suma)"; hoja de saldos = AccountsService::totalReceivable(); la anulada y la convertida aparecen en la hoja de ventas con su estado y no suman; la venta de las 00:00 del día siguiente no aparece.
2. ZIP: un PDF por documento del rango, incluida la anulada con su sufijo y sin la del día siguiente; nombres únicos.
3. Si alguna prueba revela una diferencia real en ReportService, corrígela con el cambio mínimo, explícala en la bitácora y agrega el caso. Si la corrección tocara una regla no negociable, anótala como BLOQUEO.

BLOQUE E — Documentación y cierre.
1. docs/INSTALACION.md: sección "Antes de abrir el negocio" con `php artisan system:check` y `php artisan backup:verify`, permisos que necesita el usuario de PostgreSQL para crear la base de prueba (CREATEDB) y cómo comprobar el programador (latido). En Windows y en macOS/Linux.
2. docs/REVISION-MANUAL.md (sin marcar casillas): en "PC definitiva" agrega "system:check sin ✘", "backup:verify dice El respaldo sirve" y "Después de 10 minutos, el check del programador está en verde".
3. docs/DECISIONES.md: "Revisión del sistema en Ajustes y system:check; los checks no cambian datos." y "backup:verify restaura solo en <base>_restore_check y la borra al terminar; nunca restaura sobre la base del negocio."
4. Agrega `php artisan system:check` y `php artisan backup:verify` a los comandos de CLAUDE.md y AGENTS.md. Actualiza instrucciones/CONTEXTO.md.
5. `php artisan test`, `node --test resources/js/lib/` y `npm run build` verdes. Responde en 5–10 líneas: bloques hechos, resultado real de `php artisan system:check` en esta máquina (copia la tabla), si la prueba de ida y vuelta corrió o quedó skipped y por qué, diferencias encontradas en D, archivos tocados, número de tests, BLOQUEOS.
```

## Tarea 10: nota de crédito SAR (pegar DESPUÉS de que Claude revise la Tarea 9)

Plan de Claude (2026-11-18). Hoy una factura entregada solo se puede **anular**; el SAR usa la
**nota de crédito** (NC) para devolver mercadería o corregir una factura ya entregada.
Diseño:
- **Tablas propias** `credit_notes` y `credit_note_items` (migración aditiva, sin tocar `sales`).
  Si se guardara en `sales`, `Sale::issued()` sumaría la NC como venta positiva en reportes,
  Inicio y cuentas.
- Numeración: la NC tiene su propio CAI, o sea otra `document_series` con `kind=credit_note`.
  Se reutiliza `DocumentNumberService::assign()` tal cual.
- Solo sobre **facturas emitidas** (no pedidos, que se anulan; tampoco anuladas ni convertidas).
  Se arma por líneas de la factura, con cantidad ≤ lo que falta devolver de cada línea y al
  mismo precio. Pide PIN de admin igual que anular.
- Efecto: factura a **crédito** → la NC entra como abono en `entriesQuery()` y baja la deuda
  (FIFO la aplica sola). Factura de **contado** → no toca cuentas; Reportes la muestra como
  "devuelto en efectivo". Reportes: total de NC y **ventas netas**; Excel con hoja 6 "Notas de crédito".
- Una factura con NC activas no se puede anular: primero se anulan sus NC.
- **Supuestos fiscales por confirmar con el contador (BLOQUEO informativo, no detiene la tarea):**
  código de tipo de documento de la NC (no se fija en código: lo escribe el admin al registrar
  el CAI, igual que hoy), textos obligatorios de la plantilla, si se permiten NC por descuento sin
  devolución (en v1 no) y plazo máximo para emitir la NC después de la factura (en v1 no hay límite).

```
Proyecto: sistema de ventas "Frutas y Verduras El Rey". Laravel 13 + Inertia 2 + Vue 3 (<script setup>) + Vuetify 4, PostgreSQL 17.
ANTES DE EMPEZAR lee: CLAUDE.md, AGENTS.md, instrucciones/CONTEXTO.md, docs/DECISIONES.md, app/Models/Sale.php, app/Models/DocumentSeries.php, app/Services/SaleService.php, app/Services/DocumentNumberService.php, app/Services/AccountsService.php, app/Services/DocumentPdfService.php, app/Services/ReportService.php, app/Http/Controllers/SettingsController.php, resources/views/documents/invoice.blade.php y resources/js/Pages/Ventas/Show.vue.
MODO: autónomo, bloques A → G en orden, sin pedir confirmación. Tras cada bloque: `php artisan test`, `node --test resources/js/lib/` y `npm run build`; no sigas con algo rojo. Bitácora: sección "Tarea 10" en docs/BITACORA-CODEX.md. BLOQUEOS solo por: dependencia nueva, regla no negociable o decisión de negocio; anótalos y sigue.
ALCANCE Y PERMISOS: esta tarea SÍ autoriza UNA migración aditiva (crear credit_notes y credit_note_items; ninguna columna nueva ni cambio en tablas existentes). Ejecútala solo en frutverdrey_test (la corre `php artisan test`); NO ejecutes migrate sobre la base operativa frutverdrey ni sobre ninguna otra. Sin dependencias npm/composer. NO cambies Money.php, DocumentNumberService, el cálculo de Sale::issued() ni la inmutabilidad de Sale. En SaleService solo se permite: hacer pública la constante de tasas (TAX_RATES) y agregar en void() el bloqueo del Bloque C.5. invoice.blade.php y order.blade.php no se tocan.

BLOQUE A — Series de nota de crédito.
1. DocumentSeries: KIND_CREDIT_NOTE = 'credit_note'. Agrega isFiscal() (invoice o credit_note) y úsalo donde la lógica es "tiene CAI" (formatNumber con formato 000-001-XX-00000001, rangeLabel, alertas, refreshStatus, mensajes de agotada/vencida en DocumentNumberService NO: ahí solo cambia isInvoice() por isFiscal() en el mensaje si hace falta, sin tocar el bloqueo ni el flujo). Agrega usableCreditNoteSeries() y scopeActiveCreditNotes(), simétricos a los de factura. fiscalAlerts() incluye las series de NC con su nombre ("Notas de crédito: quedan 12 números").
2. SettingsController::storeCai acepta kind (invoice|credit_note, por omisión invoice). El cruce de rangos se valida dentro del mismo kind, establecimiento, punto y tipo. code 'NC-...' y title 'NOTA DE CRÉDITO' para NC. doc_type_code lo escribe el admin (2 dígitos) como hoy; no lo fijes en código.
3. Ajustes/Index.vue: en el diálogo de registrar CAI, un selector grande "¿Para qué es este CAI?" con "Facturas" y "Notas de crédito". El listado de series muestra el tipo. Texto de ayuda: "El código de tipo de documento está en la resolución del CAI; si no lo sabe, pregúntele al contador."
4. Pest: registrar un CAI de NC, formato del número, cruce de rangos solo dentro del mismo kind, alertas de NC.

BLOQUE B — Datos y modelo.
1. Migración create_credit_notes: id; sale_id (FK sales, restrictOnDelete; la factura de origen); document_series_id (FK, restrict); number; full_number unique; issued_at timestampTz; issue_date date; reason_type (return|error|other); reason text; customer_id (FK nullable, nullOnDelete); customer_name_snapshot; customer_rtn_snapshot nullable; issuer_snapshot json; fiscal_snapshot json (CAI, rango, fecha límite y los datos de la factura de origen: full_number, issue_date, CAI de la factura); refund_method (account|cash) fijado por las condiciones de la factura (credit → account, cash → cash); subtotal_exempt, subtotal_exonerated, subtotal_g15, subtotal_g18, tax_15, tax_18, total numeric(12,2); status (issued|voided); void_reason, voided_at, voided_by; print_count; user_id; timestamps. unique(document_series_id, number); índices issue_date, (status, issue_date), (customer_id, issue_date), sale_id.
   create_credit_note_items: id; credit_note_id (FK, restrict, sin cascade); sale_item_id (FK sale_items, restrict); description, unit, tax_type_snapshot y unit_price copiados de la línea de la factura; quantity numeric(12,2); line_total numeric(12,2); timestamps.
   down() solo dropIfExists de las dos tablas nuevas.
2. Modelos CreditNote y CreditNoteItem con la MISMA protección que Sale: emitida es inmutable salvo status→voided (con motivo, fecha y usuario) y print_count; deleting lanza excepción (reutiliza ImmutableSaleException o crea ImmutableDocumentException). scopeIssued(). Relaciones: sale, series, customer, items, user, voidedBy. Sale: hasMany creditNotes.
3. Pest: no se puede editar ni borrar una NC emitida ni sus líneas; sí anular y contar impresiones.

BLOQUE C — CreditNoteService::issue() y void().
1. issue(Sale $invoice, array $lines, string $reasonType, string $reason, User $user): dentro de DB::transaction, bloquea la factura con lockForUpdate. Valida y lanza excepciones con mensaje en español:
   - es factura (series kind invoice) y status issued; pedidos → "Los pedidos no llevan nota de crédito: anúlelo.";
   - al menos una línea con cantidad > 0;
   - cada sale_item pertenece a esa factura y quantity ≤ cantidad original − suma de quantity de NC ACTIVAS sobre esa línea ("De Papas solo quedan 3.00 por devolver");
   - motivo obligatorio (mínimo 5 caracteres);
   - hay serie de NC utilizable; si no: "No hay CAI vigente para notas de crédito. Registre uno en Ajustes."
2. Cálculo: line_total = quantity × unit_price de la línea de la factura, half-up a 2 decimales con App\Support\Money. Mismo tratamiento de impuesto que la factura: tax_type_snapshot de la línea y, si la factura fue exonerada, lo gravado va a subtotal_exonerated, igual que SaleService::issue. Impuestos con SaleService::TAX_RATES. Si la NC devuelve todas las cantidades pendientes de la factura, sus subtotales, impuestos y total deben ser exactamente lo que falta de la factura (sin diferencias de centavos): calcula la última NC como "saldo de la factura" − NC anteriores cuando quede todo devuelto, y pruébalo.
3. Número con DocumentNumberService::assign(serie de NC). Snapshots: emisor actual (como SaleService), cliente y RTN de la factura (no del cliente de hoy), fiscal_snapshot con CAI/rango/fecha límite de la serie de NC y número, fecha y CAI de la factura. AuditLog como las ventas.
4. void(CreditNote, reason, user): solo issued → voided con motivo, fecha y usuario; el número no se reutiliza; invalida la caché del PDF; AuditLog.
5. SaleService::void(): si la factura tiene NC activas, lanza "Esta factura tiene notas de crédito. Anúlelas primero." (único cambio de lógica en SaleService).
6. Pest: devolución parcial y total; no pasar del pendiente (incluida la suma de dos NC); NC anulada libera su cantidad; pedido, factura anulada y factura convertida rechazadas; sin CAI de NC → error claro; exonerada; dos emisiones concurrentes no repiten número (patrón de DocumentNumberingTest); la última NC cuadra al centavo; anular factura con NC activa bloqueado.

BLOQUE D — Cuentas por cobrar y reportes.
1. AccountsService::entriesQuery(): agrega un UNION ALL con las NC issued y refund_method = account: customer_id, issue_date, debit 0, credit total. No cambies nada más del cálculo: FIFO y antigüedad la aplican como un abono. ledger(): fila "Nota de crédito" con su número y la factura de origen; con includeVoided, las NC anuladas como filas informativas (igual que los abonos anulados).
2. ReportService: summary() agrega credit_notes_total, credit_notes_cash (devuelto en efectivo), credit_notes_account (rebajado de cuentas) y net_total = total de ventas − credit_notes_total, solo NC issued dentro del rango (issue_date de la NC, rango inclusivo, hora de Honduras). Excel: hoja 6 "Notas de crédito" (número, fecha, factura de origen, cliente, RTN, motivo, forma: "Rebaja de cuenta"/"Efectivo", subtotales, impuestos, total, estado; las anuladas marcadas "ANULADA (no suma)" y fuera del total). El ZIP incluye los PDF de las NC del rango en una carpeta notas-credito/. No cambies cómo se calculan las otras hojas.
3. Reportes/Index.vue: en las tarjetas, "Notas de crédito" y "Ventas netas" (con el mismo criterio de montos sin cortes de la Tarea 8). Inicio: el resumen del día sigue mostrando ventas; si hubo NC hoy, una línea "Devoluciones de hoy: L …".
4. Pest: saldo del cliente baja con NC de factura a crédito y vuelve al anular la NC; NC de contado no cambia el saldo; resumen, net_total y la hoja 6 cuadran con las NC del rango; NC anulada no suma pero aparece; una NC de hoy sobre una factura del mes pasado cuenta en el rango de hoy.

BLOQUE E — Documento impreso.
1. resources/views/documents/credit_note.blade.php con print-style.blade.php y las mismas reglas de dompdf (tablas, sin flex/grid), logo como en factura. Título "NOTA DE CRÉDITO" (nunca "FACTURA" como título), número, CAI, rango autorizado y fecha límite de la serie de NC (desde el snapshot), "Documento de origen: Factura N.º … del dd/mm/aaaa", motivo, cliente y RTN, líneas, subtotales separados (exento, exonerado, gravado 15 y 18), impuestos, total, total en letras (AmountInWords), "Original"/"Copia". Todo desde snapshots.
2. DocumentPdfService: soporta CreditNote con caché en print-v3/{paper}/notas-credito/{yyyy}/{mm}/; forget() al anular. Carta y media carta horizontal. Un documento largo (30 líneas) no se superpone con el pie.
3. Rutas (lectura para todos los roles autenticados): notas-credito.show, notas-credito.documento, notas-credito.pdf. Escritura en role:admin,seller: POST ventas.credit-notes.store (/ventas/{sale}/nota-credito) y POST notas-credito.void (/notas-credito/{creditNote}/anular), las dos con PIN de admin igual que ventas.void; notas-credito.print para el contador de impresión.
4. Pest: contenido del HTML (título, CAI de NC, factura de origen, totales, letras), nunca la palabra FACTURA como título; dimensiones de papel como PrintLayoutTest; permisos en AccessPermissionsTest (contador lee y no escribe; sin PIN o PIN malo → error).

BLOQUE F — Pantallas.
1. Ventas/Show.vue (solo facturas issued, admin y vendedor): botón grande "Devolución (nota de crédito)" con ícono. Diálogo: tabla de líneas con lo vendido, lo ya devuelto y una casilla de cantidad (vacía = no devolver; mismo parser de coma/punto de la Tarea 8 y el mismo bloqueo si está escrita pero es inválida o pasa lo pendiente); botón "Devolver todo"; motivo (opciones "Devolvió producto", "Error en la factura", "Otro") + texto; total en vivo con resources/js/lib/money.js; aviso según la condición: "Se rebaja de la cuenta de <cliente>" o "Devuelva L … en efectivo". Confirmación con resumen y PIN de admin. Al guardar, ir a la NC.
   En la misma página: lista "Notas de crédito de esta factura" (número, fecha, total, estado, enlace). Si hay NC activas, el botón Anular factura queda deshabilitado con la explicación.
2. NotasCredito/Show.vue: patrón de Ventas/Show (imprimir original y copia, PDF, anular con motivo y PIN, enlace a la factura). Ventas/Index.vue: filtro o pestaña "Notas de crédito" (paginada, búsqueda por número o cliente sin acentos). Clientes/Show.vue: las filas de NC en movimientos.
3. Teclado y reglas de la Tarea 8: Enter guarda, Esc cierra, doble clic no emite dos NC (protección contra doble envío y prueba Pest de dos POST seguidos con la misma carga → la segunda falla por cantidad pendiente).

BLOQUE G — Documentación y cierre.
1. docs/DECISIONES.md: decisión nueva "Nota de crédito" (tablas propias, solo facturas, por líneas y cantidades, PIN de admin, crédito → rebaja de cuenta / contado → efectivo, factura con NC activa no se anula, hoja 6 del Excel) y reemplaza el ⚠️ de la decisión 7. Sección "Por confirmar con el contador": código de tipo de documento de la NC, textos obligatorios de la plantilla, NC por descuento sin devolución (no está en v1) y plazo para emitirla.
2. CLAUDE.md y AGENTS.md: "Excel de cinco hojas" → seis; quita "No hay nota de crédito" de pendientes; agrega CreditNoteService a la arquitectura. docs/ENTREGA.md y docs/REVISION-MANUAL.md (sin marcar casillas): agrega emitir, imprimir y anular una NC, y revisar con el contador el impreso.
3. Actualiza instrucciones/CONTEXTO.md. `php artisan test`, `node --test resources/js/lib/` y `npm run build` verdes. Responde en 5–10 líneas: bloques hechos, migración creada (nombre del archivo), archivos tocados, número de tests (PHP y node), BLOQUEOS (incluye los supuestos fiscales).
```
