<script setup>
import { reactive, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import RangeFilter from '@/Components/RangeFilter.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { fuzzyFilter } from '@/lib/fuzzy.js';
import { money, date, termsLabel, statusLabel, statusColor } from '@/lib/format.js';

const props = defineProps({
    sales: { type: Object, required: true },
    customers: { type: Array, default: () => [] },
    aggregates: { type: Object, required: true },
    filters: { type: Object, required: true },
});

const canSell = usePage().props.auth.user?.role !== 'accountant';
const f = reactive({ ...props.filters });
const page = ref(props.sales.current_page);
const loading = ref(false);
let debounce = null;

const headers = [
    { title: 'Número', key: 'full_number', sortable: false },
    { title: 'Fecha', key: 'issue_date', sortable: false },
    { title: 'Cliente', key: 'customer', sortable: false },
    { title: 'Pago', key: 'terms', sortable: false },
    { title: 'Total', key: 'total', align: 'end', sortable: false },
    { title: 'Estado', key: 'status', align: 'center', sortable: false },
];

function reload() {
    loading.value = true;
    router.get(route('ventas.index'), {
        range: f.range, from: f.from, to: f.to, estado: f.estado, tipo: f.tipo,
        customer_id: f.customer_id, q: f.q, per_page: f.per_page, page: page.value,
    }, {
        preserveState: true, preserveScroll: true, replace: true,
        only: ['sales', 'aggregates', 'filters'],
        onSuccess: () => { page.value = props.sales.current_page; },
        onFinish: () => { loading.value = false; },
    });
}
const applyNow = () => { page.value = 1; reload(); };
const applyDebounced = () => { clearTimeout(debounce); debounce = setTimeout(applyNow, 350); };
const onRange = (r) => { Object.assign(f, { from: null, to: null }, r); applyNow(); };
const onPage = (p) => { page.value = p; reload(); };
const onPerPage = (n) => { f.per_page = n; applyNow(); };
const openSale = (_e, { item }) => router.visit(route('ventas.show', item.id));
const rowProps = ({ item }) => ({ class: item.status !== 'issued' ? 'row-dim' : '' });
</script>

<template>
    <AppLayout title="Ventas hechas">
        <div class="d-flex flex-wrap align-center ga-3 mb-4">
            <div class="flex-grow-1">
                <h1 class="page-title">Ventas hechas</h1>
                <p class="page-sub">Pedidos y facturas. Toque una fila para verla o imprimirla otra vez.</p>
            </div>
            <v-btn v-if="canSell" color="primary" prepend-icon="mdi-basket-plus" @click="router.visit(route('ventas.create'))">Nuevo pedido</v-btn>
        </div>

        <v-card class="pa-4 mb-4">
            <RangeFilter :range="f.range" :from="f.from" :to="f.to" @change="onRange" />
            <v-row dense class="mt-3">
                <v-col cols="12" md="4">
                    <v-text-field v-model="f.q" label="Buscar por número o cliente" prepend-inner-icon="mdi-magnify" clearable hide-details
                        @update:model-value="applyDebounced" />
                </v-col>
                <v-col cols="12" md="3">
                    <v-autocomplete v-model="f.customer_id" :items="customers" item-title="name" item-value="id" :custom-filter="fuzzyFilter"
                        label="Cliente" clearable hide-details @update:model-value="applyNow" />
                </v-col>
                <v-col cols="6" md="2">
                    <v-select v-model="f.tipo" :items="[{ title: 'Todo', value: 'todos' }, { title: 'Solo pedidos', value: 'pedidos' }, { title: 'Solo facturas', value: 'facturas' }]"
                        label="Tipo" hide-details @update:model-value="applyNow" />
                </v-col>
                <v-col cols="6" md="3">
                    <v-select v-model="f.estado" :items="[{ title: 'Todas', value: 'todas' }, { title: 'Activas', value: 'activas' }, { title: 'Anuladas', value: 'anuladas' }]"
                        label="Estado" hide-details @update:model-value="applyNow" />
                </v-col>
            </v-row>
        </v-card>

        <v-card>
            <v-data-table-server
                :headers="headers"
                :items="sales.data"
                :items-length="sales.total"
                :items-per-page="f.per_page"
                :items-per-page-options="[25, 50, 100]"
                items-per-page-text="Filas por página"
                :page="page"
                :loading="loading"
                :row-props="rowProps"
                hover
                @update:page="onPage"
                @update:items-per-page="onPerPage"
                @click:row="openSale"
            >
                <template #item.full_number="{ item }">
                    <v-icon :icon="item.is_invoice ? 'mdi-receipt-text' : 'mdi-basket'" size="small" class="mr-1" :color="item.is_invoice ? 'secondary' : 'primary'" />
                    <strong>{{ item.full_number }}</strong>
                </template>
                <template #item.issue_date="{ item }">{{ date(item.issue_date) }}</template>
                <template #item.terms="{ item }">{{ termsLabel(item.terms) }}</template>
                <template #item.total="{ item }"><span class="money">{{ money(item.total) }}</span></template>
                <template #item.status="{ item }"><v-chip :color="statusColor(item.status)" size="small">{{ statusLabel(item.status) }}</v-chip></template>
                <template #no-data>
                    <EmptyState title="No hay ventas con estos filtros" text="Pruebe con otras fechas o borre la búsqueda." icon="mdi-calendar-search" />
                </template>
            </v-data-table-server>
            <div class="totals-bar">
                {{ aggregates.active_count }} {{ aggregates.active_count === 1 ? 'venta activa' : 'ventas activas' }}
                suman <strong class="money">{{ money(aggregates.active_total) }}</strong>
                <span v-if="aggregates.voided_count" class="muted"> · {{ aggregates.voided_count }} anuladas o facturadas no suman</span>
            </div>
        </v-card>
    </AppLayout>
</template>

<style scoped>
.totals-bar { padding: 14px 18px; border-top: 1px solid var(--linea); font-size: 1.1rem; }
:deep(tbody tr) { cursor: pointer; }
</style>
