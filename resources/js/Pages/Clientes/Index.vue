<script setup>
import { ref, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import CustomerDialog from '@/Components/CustomerDialog.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { money, termsLabel } from '@/lib/format.js';

const props = defineProps({ customers: Object, filters: Object });
const canOperate = usePage().props.auth.user?.role !== 'accountant';
const search = ref(props.filters.q || '');
const showInactive = ref(props.filters.inactive);
const loading = ref(false);
let debounce;
function reload(page = 1) {
    loading.value = true;
    router.get(route('clientes.index'), { q: search.value, inactive: showInactive.value ? 1 : 0, page }, {
        preserveState: true, preserveScroll: true, replace: true,
        onFinish: () => (loading.value = false),
    });
}
function searchLater() { clearTimeout(debounce); debounce = setTimeout(() => reload(), 350); }
onUnmounted(() => clearTimeout(debounce));
const dialog = ref(false);
const headers = [
    { title: 'Nombre', key: 'name', sortable: false },
    { title: 'Teléfono', key: 'phone', sortable: false },
    { title: 'Paga', key: 'default_terms', sortable: false },
    { title: 'Debe', key: 'balance', sortable: false, align: 'end', value: (c) => Number(c.balance) },
];
const open = (_e, { item }) => router.visit(route('clientes.show', item.id));
</script>

<template>
    <AppLayout title="Clientes">
        <div class="d-flex flex-wrap align-end ga-3 mb-4">
            <div class="flex-grow-1">
                <h1 class="page-title">Clientes</h1>
                <p class="page-sub">{{ customers.total }} clientes. Toque uno para ver su cuenta.</p>
            </div>
            <v-btn v-if="canOperate" color="primary" prepend-icon="mdi-account-plus" @click="dialog = true">Cliente nuevo</v-btn>
        </div>
        <v-card>
            <div class="d-flex flex-wrap ga-3 pa-4 align-center">
                <v-text-field v-model="search" @update:model-value="searchLater" label="Buscar por nombre, teléfono o RTN" prepend-inner-icon="mdi-magnify" clearable hide-details style="max-width:420px" />
                <v-switch v-model="showInactive" @update:model-value="reload()" label="Mostrar inactivos" hide-details />
            </div>
            <v-data-table-server :headers="headers" :items="customers.data" :items-length="customers.total" :items-per-page="25" :page="customers.current_page" :loading="loading" :items-per-page-options="[25]" items-per-page-text="Filas por página" hover @click:row="open" @update:page="reload">
                <template #item.name="{ item }"><strong :class="{ muted: !item.active }">{{ item.name }}</strong><span v-if="!item.active" class="muted"> (inactivo)</span></template>
                <template #item.default_terms="{ item }">{{ termsLabel(item.default_terms) }}</template>
                <template #item.balance="{ item }">
                    <span class="money" :class="Number(item.balance) > 0 ? 'text-error font-weight-bold' : 'muted'">{{ Number(item.balance) > 0 ? money(item.balance) : '—' }}</span>
                </template>
                <template #no-data><EmptyState icon="mdi-account-search" title="No encuentro ese cliente" text="Revise cómo lo escribió o agréguelo como cliente nuevo."><v-btn v-if="canOperate" prepend-icon="mdi-account-plus" color="primary" @click="dialog = true">Cliente nuevo</v-btn></EmptyState></template>
            </v-data-table-server>
        </v-card>
        <CustomerDialog v-model="dialog" />
    </AppLayout>
</template>

<style scoped>
:deep(tbody tr) { cursor: pointer; }
</style>
