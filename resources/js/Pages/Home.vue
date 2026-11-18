<script setup>
import { computed, onMounted, onUnmounted } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { money, longDate, date } from '@/lib/format.js';

const props = defineProps({ today: Object, receivable: String, debtors: Number, nextTrips: Array });
const page = usePage();
const user = computed(() => page.props.auth.user);
const canSell = computed(() => user.value?.role !== 'accountant');
const alerts = computed(() => page.props.fiscalAlerts ?? []);

const hour = Number(new Intl.DateTimeFormat('en-US', { timeZone: 'America/Tegucigalpa', hour: 'numeric', hourCycle: 'h23' }).format(new Date()));
const greeting = hour < 12 ? 'Buenos días' : hour < 18 ? 'Buenas tardes' : 'Buenas noches';

const tiles = computed(() => [
    { key: 'pedido', label: 'Nuevo pedido', hint: 'Anotar lo que lleva un cliente', icon: 'mdi-basket-plus', cls: 't-hoja', to: route('ventas.create'), show: canSell.value },
    { key: 'factura', label: 'Nueva factura', hint: 'Factura con CAI para el SAR', icon: 'mdi-receipt-text-plus', cls: 't-turquesa', to: route('ventas.create', { tipo: 'factura' }), show: canSell.value },
    { key: 'deben', label: 'Quién me debe', hint: 'Cobrar y anotar abonos', icon: 'mdi-hand-coin', cls: 't-mango', to: route('cobros.index'), show: true },
    { key: 'fletes', label: 'Fletes', hint: 'Calendario de viajes', icon: 'mdi-truck', cls: 't-tinta', to: route('fletes.index'), show: canSell.value },
].filter((t) => t.show));

// Atajo: teclas 1–4 abren los botones grandes.
function onKey(e) {
    if (e.target.closest('input, textarea, [contenteditable]') || e.ctrlKey || e.metaKey || e.altKey) return;
    const t = tiles.value[Number(e.key) - 1];
    if (t) router.visit(t.to);
}
onMounted(() => window.addEventListener('keydown', onKey));
onUnmounted(() => window.removeEventListener('keydown', onKey));

const firstName = computed(() => (user.value?.name || '').split(' ')[0]);
</script>

<template>
    <AppLayout title="Inicio">
        <header class="mb-6">
            <h1 class="page-title">{{ greeting }}, {{ firstName }}.</h1>
            <p class="page-sub">Hoy es {{ longDate(today.date) }}. ¿Qué quiere hacer?</p>
        </header>

        <v-alert v-for="(a, i) in alerts" :key="i" :type="a.level" class="mb-3" prominent>
            {{ a.message }}
        </v-alert>

        <nav class="tiles" aria-label="Tareas del día">
            <Link v-for="(t, i) in tiles" :key="t.key" :href="t.to" class="tile" :class="t.cls">
                <v-icon :icon="t.icon" size="52" />
                <span class="tile-label">{{ t.label }}</span>
                <span class="tile-hint">{{ t.hint }}</span>
                <kbd v-if="i < 4" class="tile-key" aria-hidden="true">{{ i + 1 }}</kbd>
            </Link>
        </nav>

        <section class="today">
            <div class="today-block">
                <h2>Hoy</h2>
                <div class="today-amount sales-amount">{{ money(today.total) }}</div>
                <p v-if="today.count">{{ today.count }} {{ today.count === 1 ? 'venta' : 'ventas' }}. De contado entraron <strong class="money">{{ money(today.cash) }}</strong>.</p>
                <p v-else>Todavía no hay ventas hoy.</p>
            </div>
            <div class="today-block">
                <h2>Por cobrar</h2>
                <div class="today-amount debt-amount">{{ money(receivable) }}</div>
                <p v-if="debtors">{{ debtors }} {{ debtors === 1 ? 'cliente le debe' : 'clientes le deben' }}. <Link :href="route('cobros.index')">Ver quiénes</Link></p>
                <p v-else>Nadie le debe nada.</p>
            </div>
            <div v-if="canSell" class="today-block">
                <h2>Próximos fletes</h2>
                <ul v-if="nextTrips.length" class="trips">
                    <li v-for="t in nextTrips" :key="t.id">
                        <strong>{{ date(t.date) }}</strong> a {{ t.destination }}<span v-if="t.driver"> con {{ t.driver }}</span><span v-if="t.for_whom"> (para {{ t.for_whom }})</span>
                    </li>
                </ul>
                <p v-else>No hay fletes en los próximos 7 días.</p>
            </div>
        </section>

        <div class="d-flex flex-wrap ga-3 mt-6">
            <v-btn variant="outlined" prepend-icon="mdi-format-list-bulleted" @click="router.visit(route('ventas.index'))">Ventas hechas</v-btn>
            <v-btn variant="outlined" prepend-icon="mdi-account-group" @click="router.visit(route('clientes.index'))">Clientes</v-btn>
            <v-btn v-if="canSell" variant="outlined" prepend-icon="mdi-carrot" @click="router.visit(route('productos.index'))">Productos y precios</v-btn>
            <v-btn variant="outlined" prepend-icon="mdi-chart-box" @click="router.visit(route('reportes.index'))">Reportes</v-btn>
        </div>
    </AppLayout>
</template>

<style scoped>
.tiles { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
@media (min-width: 1100px) { .tiles { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
@media (max-width: 520px) { .tiles { grid-template-columns: 1fr; } }
.tile {
    position: relative; display: flex; flex-direction: column; justify-content: flex-end; gap: 8px;
    min-height: 220px; padding: 24px; border-radius: var(--radio-rotulo); text-decoration: none;
    background: var(--rotulo); color: rgb(var(--v-theme-on-primary));
    box-shadow: inset 0 0 0 8px var(--rotulo), inset 0 0 0 10px var(--marco), 0 5px 0 var(--repisa);
    --marco: rgba(255,255,255,.55);
}
.tile:hover { background: var(--rotulo-hover); }
.tile:active { transform: translateY(3px); box-shadow: inset 0 0 0 8px var(--rotulo), inset 0 0 0 10px var(--marco), 0 2px 0 var(--repisa); }
.tile .v-icon { margin-bottom: auto; }
.tile-label { font-family: var(--display); font-weight: 800; font-size: 2.1rem; line-height: 1; overflow-wrap: anywhere; }
.tile-hint { font-size: 1.02rem; opacity: .92; }
.tile-key { position: absolute; top: 18px; right: 18px; display: grid; place-items: center; width: 34px; height: 34px; font: 700 1rem 'Atkinson Hyperlegible', sans-serif; background: rgba(255,255,255,.9); color: var(--tinta); border-radius: 50%; }
.t-hoja { --rotulo: var(--marca); }
.t-turquesa { --rotulo: var(--turquesa); }
.t-mango { --rotulo: var(--mango); color: var(--tinta); --marco: rgba(29,53,87,.45); }
.t-tinta { --rotulo: var(--tinta); }
.tile { --rotulo-hover: color-mix(in srgb, var(--rotulo), black 8%); --repisa: color-mix(in srgb, var(--rotulo), black 15%); }
.today { display: grid; grid-auto-flow: column; grid-auto-columns: minmax(0, 1fr); margin-top: 28px; background: rgb(var(--v-theme-surface)); border: 1px solid var(--linea); border-radius: var(--radio-tarjeta); }
.today-block { min-width: 0; padding: 18px 20px; }
.today-block + .today-block { border-left: 1px solid var(--linea); }
.today-block h2 { color: var(--tinta); font-size: 1.1rem; margin: 0 0 6px; }
.today-amount { font-family: var(--display); font-weight: 800; font-size: 2rem; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
.sales-amount { color: var(--marca); }
.debt-amount { color: var(--chile); }
.today-block p { margin: 0; font-size: 1.08rem; line-height: 1.5; }
.today-block a { color: var(--turquesa); font-weight: 700; }
.trips { margin: 0; padding-left: 18px; line-height: 1.6; }
@media (max-width: 899px) {
    .today { grid-auto-flow: row; }
    .today-block + .today-block { border-left: 0; border-top: 1px solid var(--linea); }
}
</style>
