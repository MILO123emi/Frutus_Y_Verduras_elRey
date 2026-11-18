<script setup>
import { toCents, fromCents, divideCents } from '@/lib/money.js';
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import RangeFilter from '@/Components/RangeFilter.vue';
import { money, date } from '@/lib/format.js';

const props = defineProps({ summary: Object, filters: Object });
const s = computed(() => props.summary);
const params = computed(() => ({ range: props.filters.range, from: props.filters.from, to: props.filters.to }));
const onRange = (r) => router.get(route('reportes.index'), r, { preserveScroll: true });
const excelUrl = computed(() => route('reportes.excel', params.value));
const zipUrl = computed(() => route('reportes.zip', params.value));
const avg = computed(() => fromCents(s.value.count ? divideCents(toCents(s.value.total), s.value.count) : 0));
</script>

<template>
    <AppLayout title="Reportes">
        <h1 class="page-title">Reportes</h1>
        <p class="page-sub mb-4">Del {{ date(filters.from) }} al {{ date(filters.to) }}. Las ventas anuladas no se cuentan.</p>

        <v-card class="pa-4 mb-5"><RangeFilter :range="filters.range" :from="filters.from" :to="filters.to" @change="onRange" /></v-card>

        <section class="figures">
            <div class="fig main">
                <div class="fig-label">Vendió</div>
                <div class="fig-value money">{{ money(s.total) }}</div>
                <div class="muted">en {{ s.count }} {{ s.count === 1 ? 'venta' : 'ventas' }} · promedio {{ money(avg) }}</div>
            </div>
            <div class="fig">
                <div class="fig-label">De contado</div><div class="fig-value money">{{ money(s.cash) }}</div>
                <div class="fig-label mt-2">A crédito</div><div class="fig-value money">{{ money(s.credit) }}</div>
            </div>
            <div class="fig">
                <div class="fig-label">Cobró en abonos</div><div class="fig-value money">{{ money(s.collected) }}</div>
                <div class="fig-label mt-2">Le deben hoy</div><div class="fig-value money text-error">{{ money(s.receivable) }}</div>
            </div>
            <div class="fig">
                <div class="fig-label">Facturas con CAI</div><div class="fig-value">{{ s.invoices }}</div>
                <div class="muted">por {{ money(s.invoices_total) }} · ISV {{ money(s.tax) }}</div>
                <div class="muted mt-1">{{ s.voided }} anuladas en el período</div>
            </div>
        </section>

        <v-card class="pa-5 my-5 accountant">
            <h2 class="sec">Para el contador</h2>
            <p class="mb-4">Descargue todo el período y entrégueselo al contador en una memoria USB o por correo.</p>
            <div class="d-flex flex-wrap ga-3">
                <v-btn color="primary" size="x-large" prepend-icon="mdi-microsoft-excel" :href="excelUrl">Descargar Excel</v-btn>
                <v-btn v-if="s.count || s.voided" variant="outlined" size="x-large" prepend-icon="mdi-folder-zip" :href="zipUrl">Excel + todas las hojas en PDF (.zip)</v-btn>
            </div>
            <p class="muted text-body-2 mt-3 mb-0">El Excel trae: todas las ventas, el libro de ventas de facturas, ventas por día, abonos y saldos. El .zip puede tardar si son muchas ventas.</p>
        </v-card>

        <v-row>
            <v-col cols="12" md="6">
                <v-card class="pa-4">
                    <h2 class="sec">Lo que más se vendió</h2>
                    <p v-if="!s.top_products.length" class="muted">No hay ventas en estas fechas.</p>
                    <div v-for="(p, i) in s.top_products" :key="i" class="rank-row">
                        <div><strong>{{ p.name }}</strong><div class="muted text-body-2">{{ p.quantities }}</div></div>
                        <span class="money">{{ money(p.amount) }}</span>
                    </div>
                </v-card>
            </v-col>
            <v-col cols="12" md="6">
                <v-card class="pa-4">
                    <h2 class="sec">Los clientes que más compraron</h2>
                    <p v-if="!s.top_customers.length" class="muted">No hay ventas a clientes con nombre en estas fechas.</p>
                    <div v-for="(c, i) in s.top_customers" :key="i" class="rank-row">
                        <div><strong>{{ c.name }}</strong><div class="muted text-body-2">{{ c.count }} {{ c.count === 1 ? 'compra' : 'compras' }}</div></div>
                        <span class="money">{{ money(c.total) }}</span>
                    </div>
                </v-card>
            </v-col>
        </v-row>
    </AppLayout>
</template>

<style scoped>
.figures { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 14px; }
.fig { min-width: 0; container-type: inline-size; overflow-wrap: anywhere; background: rgb(var(--v-theme-surface)); border: 1px solid var(--linea); border-radius: var(--radio-tarjeta); padding: 16px 18px; }
.fig.main { background: var(--marca); color: rgb(var(--v-theme-surface)); border-color: var(--marca); }
.fig.main .muted { color: rgba(255,255,255,.85); }
.fig-label { font-size: 1rem; color: inherit; opacity: .8; }
.fig-value { font-family: var(--display); font-size: clamp(1rem, 8cqi, 1.7rem); font-weight: 800; line-height: 1.25; }
.fig.main .fig-value { font-size: clamp(1rem, 9cqi, 2.3rem); }
.sec { font-family: var(--display); font-size: 1.35rem; font-weight: 700; margin: 0 0 8px; }
.accountant { border-left: 6px solid var(--turquesa); }
.rank-row { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--linea); }
.rank-row:last-child { border-bottom: 0; }
</style>
