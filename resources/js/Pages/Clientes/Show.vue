<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import CustomerDialog from '@/Components/CustomerDialog.vue';
import PaymentDialog from '@/Components/PaymentDialog.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { money, date, termsLabel, statusLabel, statusColor } from '@/lib/format.js';

const props = defineProps({ customer: Object, balance: String, open: Object, ledger: Array, recentSales: Array, canOperate: Boolean });

const editOpen = ref(false);
const payOpen = ref(false);
const payType = ref('payment');
const startPay = (t) => { payType.value = t; payOpen.value = true; };
const voidTarget = ref(null);
const voiding = ref(false);
function voidMovement() {
    voiding.value = true;
    router.post(route('movimientos.void', voidTarget.value.movement_id), { reason: 'Anulado desde la cuenta del cliente' }, {
        preserveScroll: true, onFinish: () => { voiding.value = false; voidTarget.value = null; },
    });
}
const aging = [
    { key: '0_30', label: 'De este mes (0 a 30 días)' },
    { key: '31_60', label: 'De 31 a 60 días' },
    { key: '61_90', label: 'De 61 a 90 días' },
    { key: '90_plus', label: 'Más de 90 días' },
];
const statementUrl = route('clientes.statement', props.customer.id);
</script>

<template>
    <AppLayout :title="customer.name" :back="route('clientes.index')">
        <section class="head">
            <div>
                <h1 class="page-title">{{ customer.name }}</h1>
                <p class="page-sub">
                    <span v-if="customer.phone"><v-icon icon="mdi-phone" size="small" /> {{ customer.phone }} · </span>
                    Normalmente paga {{ termsLabel(customer.default_terms).toLowerCase() }}
                    <span v-if="customer.rtn"> · RTN {{ customer.rtn }}</span>
                </p>
            </div>
            <div class="balance" :class="{ owes: Number(balance) > 0 }">
                <div class="muted">{{ Number(balance) < 0 ? 'Tiene a favor' : 'Debe' }}</div>
                <div class="balance-amount money">{{ money(Math.abs(Number(balance))) }}</div>
            </div>
        </section>

        <div class="d-flex flex-wrap ga-2 mb-6">
            <v-btn v-if="canOperate" color="primary" size="x-large" prepend-icon="mdi-cash-plus" @click="startPay('payment')">Anotar abono</v-btn>
            <v-btn v-if="canOperate" variant="outlined" prepend-icon="mdi-basket-plus" @click="router.visit(route('ventas.create', { cliente: customer.id }))">Nuevo pedido para este cliente</v-btn>
            <v-btn variant="outlined" prepend-icon="mdi-printer" :href="statementUrl" target="_blank">Imprimir estado de cuenta</v-btn>
            <v-btn v-if="canOperate" variant="outlined" prepend-icon="mdi-notebook-plus" @click="startPay('opening_balance')">Anotar deuda de antes</v-btn>
            <v-btn v-if="canOperate" variant="text" prepend-icon="mdi-pencil" @click="editOpen = true">Cambiar datos</v-btn>
        </div>

        <v-row>
            <v-col cols="12" lg="5">
                <v-card class="pa-4 mb-4">
                    <h2 class="sec">¿Desde cuándo debe?</h2>
                    <div v-for="a in aging" :key="a.key" class="aging-row" :class="{ zero: Number(open.aging[a.key]) === 0 }">
                        <span>{{ a.label }}</span><strong class="money">{{ money(open.aging[a.key]) }}</strong>
                    </div>
                </v-card>
                <v-card class="pa-4 mb-4">
                    <h2 class="sec">Lo que falta pagar</h2>
                    <p class="muted text-body-2">Los abonos se aplican primero a lo más viejo.</p>
                    <div v-if="!open.documents.length" class="muted">No tiene nada pendiente.</div>
                    <div v-for="(d, i) in open.documents" :key="i" class="doc-row">
                        <div>
                            <strong>{{ d.kind }} {{ d.description }}</strong>
                            <div class="muted text-body-2">{{ date(d.date) }} · hace {{ d.days }} {{ d.days === 1 ? 'día' : 'días' }}</div>
                        </div>
                        <div class="text-right">
                            <strong class="money">{{ money(d.pending) }}</strong>
                            <div v-if="d.pending !== d.amount" class="muted text-body-2 money">de {{ money(d.amount) }}</div>
                        </div>
                    </div>
                </v-card>
            </v-col>

            <v-col cols="12" lg="7">
                <v-card class="pa-4 mb-4">
                    <h2 class="sec">Movimientos de la cuenta</h2>
                    <EmptyState v-if="!ledger.length" icon="mdi-notebook-outline" title="Sin movimientos" text="Aquí salen las ventas a crédito, los abonos y las deudas de antes." />
                    <v-table v-else density="comfortable">
                        <thead><tr><th>Fecha</th><th>Qué</th><th class="text-right">Cargo</th><th class="text-right">Abono</th><th class="text-right">Saldo</th><th></th></tr></thead>
                        <tbody>
                            <tr v-for="r in ledger" :key="r.key">
                                <td>{{ date(r.date) }}</td>
                                <td>
                                    <a v-if="r.sale_id" href="#" @click.prevent="router.visit(route('ventas.show', r.sale_id))">{{ r.kind }} {{ r.description }}</a>
                                    <div v-else-if="r.status === 'voided'">
                                        {{ r.kind }} <v-chip color="error" size="small">Anulado</v-chip>
                                        <div><s class="money">{{ money(r.amount) }}</s></div>
                                        <div class="text-body-2">{{ r.void_reason }} · Por: {{ r.voided_by || 'Usuario no disponible' }}</div>
                                    </div>
                                    <span v-else>{{ r.kind }}<span v-if="r.description" class="muted"> · {{ r.description }}</span></span>
                                </td>
                                <td class="text-right money">{{ Number(r.debit) ? money(r.debit) : '' }}</td>
                                <td class="text-right money text-primary">{{ Number(r.credit) ? money(r.credit) : '' }}</td>
                                <td class="text-right money font-weight-bold">{{ money(r.balance) }}</td>
                                <td><v-btn v-if="r.movement_id && r.status !== 'voided' && canOperate" size="small" variant="text" color="error" @click="voidTarget = r" prepend-icon="mdi-cancel">Anular</v-btn></td>
                            </tr>
                        </tbody>
                    </v-table>
                </v-card>
                <v-card class="pa-4">
                    <h2 class="sec">Últimas compras</h2>
                    <div v-if="!recentSales.length" class="muted">Todavía no ha comprado.</div>
                    <v-table v-else density="comfortable">
                        <tbody>
                            <tr v-for="s in recentSales" :key="s.id" class="clickable" :class="{ 'row-dim': s.status !== 'issued' }" @click="router.visit(route('ventas.show', s.id))">
                                <td><strong>{{ s.full_number }}</strong></td>
                                <td>{{ date(s.date) }}</td>
                                <td>{{ termsLabel(s.terms) }}</td>
                                <td class="text-right money">{{ money(s.total) }}</td>
                                <td><v-chip size="small" :color="statusColor(s.status)">{{ statusLabel(s.status) }}</v-chip></td>
                            </tr>
                        </tbody>
                    </v-table>
                </v-card>
            </v-col>
        </v-row>

        <CustomerDialog v-model="editOpen" :customer="customer" />
        <PaymentDialog v-model="payOpen" :customer="{ ...customer, balance }" :type="payType" />
        <ConfirmDialog :model-value="!!voidTarget" title="¿Anular este movimiento?" confirm-text="Sí, anular" color="error" :loading="voiding"
            @update:model-value="(v) => !v && (voidTarget = null)" @confirm="voidMovement">
            <span v-if="voidTarget">{{ voidTarget.kind }} de {{ money(voidTarget.credit !== '0.00' ? voidTarget.credit : voidTarget.debit) }} del {{ date(voidTarget.date) }}. Queda registrado como anulado y el saldo se corrige.</span>
        </ConfirmDialog>
    </AppLayout>
</template>

<style scoped>
.head { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-end; gap: 16px; margin-bottom: 20px; }
.balance { text-align: right; }
.balance-amount { font-family: var(--display); font-size: 2.6rem; font-weight: 800; line-height: 1; color: var(--marca); }
.balance.owes .balance-amount { color: var(--chile); }
.sec { font-family: var(--display); font-size: 1.35rem; font-weight: 700; margin: 0 0 10px; }
.aging-row, .doc-row { display: flex; justify-content: space-between; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--linea); }
.aging-row:last-child, .doc-row:last-child { border-bottom: 0; }
.aging-row.zero { color: var(--suave); }
.clickable { cursor: pointer; }
a { color: var(--turquesa); font-weight: 700; }
</style>
