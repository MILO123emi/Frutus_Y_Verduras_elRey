<script setup>
// Anotar un abono (o un saldo anterior) para un cliente.
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { money, todayIso } from '@/lib/format.js';
import { toCents, addCents, fromCents } from '@/lib/money.js';

const open = defineModel({ type: Boolean, default: false });
const props = defineProps({
    customer: { type: Object, default: null }, // { id, name, balance }
    type: { type: String, default: 'payment' }, // payment | opening_balance
});

const form = useForm({ type: props.type, amount: '', date: todayIso(), method: 'cash', reference: '', notes: '' });
watch(open, (v) => { if (v) { form.reset(); form.clearErrors(); form.type = props.type; form.date = todayIso(); } });

const isPayment = computed(() => form.type === 'payment');
const after = computed(() => {
    const b = toCents(props.customer?.balance), a = toCents(form.amount);
    return addCents(b, isPayment.value ? -a : a);
});
const methods = [
    { value: 'cash', title: 'Efectivo', icon: 'mdi-cash' },
    { value: 'transfer', title: 'Transferencia', icon: 'mdi-bank-transfer' },
    { value: 'check', title: 'Cheque', icon: 'mdi-checkbook' },
    { value: 'other', title: 'Otro', icon: 'mdi-dots-horizontal' },
];

function submit() {
    if (form.processing) return;
    form.post(route('movimientos.store', props.customer.id), { preserveScroll: true, onSuccess: () => (open.value = false) });
}
</script>

<template>
    <v-dialog v-model="open" max-width="560">
        <v-card v-if="customer" class="pa-2">
            <form @submit.prevent="submit">
                <v-card-title class="text-h5 text-wrap">
                    {{ isPayment ? 'Anotar abono de' : 'Deuda de antes del sistema de' }} {{ customer.name }}
                </v-card-title>
                <v-card-text>
                    <p v-if="isPayment" class="mb-4">Hoy debe <strong class="money">{{ money(customer.balance) }}</strong>.</p>
                    <p v-else class="mb-4">Use esto para anotar lo que este cliente ya debía en la libreta antes de usar el sistema.</p>
                    <v-text-field v-model="form.amount" label="¿Cuánto?" prefix="L" inputmode="decimal" autofocus
                       :error-messages="form.errors.amount" class="amount" />
                    <v-btn v-if="isPayment && Number(customer.balance) > 0" variant="text" size="small" class="mb-3 mt-n2"
                        @click="form.amount = customer.balance">Pagó todo ({{ money(customer.balance) }})</v-btn>
                    <template v-if="isPayment">
                        <div class="muted text-body-2 mb-1">¿Cómo pagó?</div>
                        <v-btn-toggle v-model="form.method" mandatory divided variant="outlined" color="primary" class="mb-4 flex-wrap" style="height:auto">
                            <v-btn v-for="m in methods" :key="m.value" :value="m.value" :prepend-icon="m.icon">{{ m.title }}</v-btn>
                        </v-btn-toggle>
                    </template>
                    <v-text-field v-model="form.date" type="date" label="Fecha" :error-messages="form.errors.date" />
                    <v-text-field v-model="form.notes" label="Nota (opcional)" :error-messages="form.errors.notes" />
                    <v-alert v-if="Number(form.amount) > 0" :type="after < 0 ? 'info' : 'success'" density="compact">
                        <template v-if="after < 0">Le quedaría a favor {{ money(fromCents(-after)) }} al cliente.</template>
                        <template v-else>Después de esto debe {{ money(fromCents(after)) }}.</template>
                    </v-alert>
                </v-card-text>
                <v-card-actions class="px-4 pb-4">
                    <v-btn variant="outlined" @click="open = false" prepend-icon="mdi-close">Cancelar</v-btn>
                    <v-spacer />
                    <v-btn color="primary" variant="flat" :loading="form.processing" prepend-icon="mdi-check" type="submit">
                        {{ isPayment ? 'Guardar abono' : 'Guardar deuda anterior' }}
                    </v-btn>
                </v-card-actions>
            </form>
        </v-card>
    </v-dialog>
</template>

<style scoped>
.amount :deep(input) { font-size: 1.6rem; font-weight: 700; }
</style>
