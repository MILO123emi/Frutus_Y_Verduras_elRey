<script setup>
import { ref, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PaymentDialog from '@/Components/PaymentDialog.vue';
import { money } from '@/lib/format.js';

const props = defineProps({ debtors: Object, total: String, q: String });
const canOperate = usePage().props.auth.user?.role !== 'accountant';
const search = ref(props.q || '');
const loading = ref(false);
let debounce;
function reload(page = 1) {
    loading.value = true;
    router.get(route('cobros.index'), { q: search.value, page }, { preserveState: true, preserveScroll: true, replace: true, onFinish: () => (loading.value = false) });
}
function searchLater() { clearTimeout(debounce); debounce = setTimeout(() => reload(), 350); }
onUnmounted(() => clearTimeout(debounce));
const payFor = ref(null);
const payOpen = ref(false);
const pay = (d) => { payFor.value = d; payOpen.value = true; };
</script>

<template>
    <AppLayout title="Quién me debe">
        <div class="d-flex flex-wrap align-end ga-3 mb-5">
            <div class="flex-grow-1">
                <h1 class="page-title">Quién me debe</h1>
                <p class="page-sub" v-if="debtors.total">
                    {{ debtors.total }} {{ debtors.total === 1 ? 'cliente debe' : 'clientes deben' }} en total
                    <strong class="money">{{ money(total) }}</strong>. Los que más deben van primero.
                </p>
            </div>
            <v-text-field v-model="search" :loading="loading" @update:model-value="searchLater" label="Buscar cliente" prepend-inner-icon="mdi-magnify" clearable hide-details style="max-width:340px" />
        </div>

        <v-card v-if="!debtors.total">
            <EmptyState icon="mdi-emoticon-happy-outline" title="Nadie le debe nada"
                text="Cuando venda a crédito, aquí aparecerá quién debe y cuánto."><v-btn v-if="canOperate" color="primary" prepend-icon="mdi-basket-plus" @click="router.visit(route('ventas.create'))">Nuevo pedido</v-btn></EmptyState>
        </v-card>

        <div v-else class="debtors">
            <div v-for="d in debtors.data" :key="d.id" class="debtor">
                <a href="#" class="debtor-name" @click.prevent="router.visit(route('clientes.show', d.id))">{{ d.name }}</a>
                <span v-if="d.phone" class="muted debtor-phone"><v-icon icon="mdi-phone" size="small" /> {{ d.phone }}</span>
                <span class="debtor-amount money">{{ money(d.balance) }}</span>
                <div class="debtor-actions">
                    <v-btn v-if="canOperate" color="primary" prepend-icon="mdi-cash-plus" @click="pay(d)">Anotar abono</v-btn>
                    <v-btn variant="outlined" prepend-icon="mdi-file-document" @click="router.visit(route('clientes.show', d.id))">Ver cuenta</v-btn>
                </div>
            </div>
            <v-pagination v-if="debtors.last_page > 1" :model-value="debtors.current_page" :length="debtors.last_page" :total-visible="5" :disabled="loading" @update:model-value="reload" />
        </div>

        <PaymentDialog v-model="payOpen" :customer="payFor" />
    </AppLayout>
</template>

<style scoped>
.debtors { display: flex; flex-direction: column; gap: 10px; }
.debtor {
    display: grid; grid-template-columns: 1fr auto; grid-template-areas: "name amount" "phone actions";
    gap: 6px 16px; align-items: center; background: rgb(var(--v-theme-surface)); border: 1px solid var(--linea);
    border-left: 6px solid var(--mango); border-radius: var(--radio-tarjeta); padding: 16px 18px;
}
.debtor-name { grid-area: name; font-family: var(--display); font-size: 1.35rem; font-weight: 700; color: inherit; text-decoration: none; }
.debtor-name:hover { text-decoration: underline; }
.debtor-phone { grid-area: phone; }
.debtor-amount { grid-area: amount; font-family: var(--display); font-size: 1.6rem; font-weight: 800; color: var(--chile); text-align: right; }
.debtor-actions { grid-area: actions; display: flex; gap: 8px; justify-content: flex-end; flex-wrap: wrap; }
@media (max-width: 640px) {
    .debtor { grid-template-columns: 1fr; grid-template-areas: "name" "amount" "phone" "actions"; }
    .debtor-amount { text-align: left; }
    .debtor-actions { justify-content: flex-start; }
}
</style>
