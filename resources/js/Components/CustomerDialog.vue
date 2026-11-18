<script setup>
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';

const open = defineModel({ type: Boolean, default: false });
const props = defineProps({ customer: { type: Object, default: null } });

const blank = { name: '', phone: '', rtn: '', address: '', default_terms: 'cash', credit_limit: '', credit_days: '', notes: '', active: true };
const form = useForm({ ...blank });
watch(open, (v) => {
    if (!v) return;
    form.clearErrors();
    Object.assign(form, { ...blank, ...(props.customer ?? {}) });
    ['credit_limit', 'credit_days', 'phone', 'rtn', 'address', 'notes'].forEach((k) => { if (form[k] === null) form[k] = ''; });
});

function submit() {
    if (form.processing) return;
    const data = (d) => ({ ...d, credit_limit: d.credit_limit || null, credit_days: d.credit_days || null });
    const opts = { preserveScroll: true, onSuccess: () => (open.value = false) };
    props.customer ? form.transform(data).put(route('clientes.update', props.customer.id), opts) : form.transform(data).post(route('clientes.store'), opts);
}
</script>

<template>
    <v-dialog v-model="open" max-width="640">
        <v-card class="pa-2">
            <form @submit.prevent="submit">
                <v-card-title class="text-h5">{{ customer ? 'Datos del cliente' : 'Cliente nuevo' }}</v-card-title>
                <v-card-text>
                    <v-text-field v-model="form.name" label="Nombre" autofocus :error-messages="form.errors.name" />
                    <v-row dense>
                        <v-col cols="12" sm="6"><v-text-field v-model="form.phone" label="Teléfono" inputmode="tel" :error-messages="form.errors.phone" /></v-col>
                        <v-col cols="12" sm="6"><v-text-field v-model="form.rtn" label="RTN (para facturas)" :error-messages="form.errors.rtn" /></v-col>
                    </v-row>
                    <v-text-field v-model="form.address" label="Dirección" :error-messages="form.errors.address" />
                    <div class="muted text-body-2 mb-1">Normalmente paga:</div>
                    <v-btn-toggle v-model="form.default_terms" mandatory divided variant="outlined" color="primary" class="mb-4">
                        <v-btn value="cash" prepend-icon="mdi-cash">De contado</v-btn>
                        <v-btn value="credit" prepend-icon="mdi-notebook-edit">A crédito</v-btn>
                    </v-btn-toggle>
                    <v-row v-if="form.default_terms === 'credit'" dense>
                        <v-col cols="12" sm="6"><v-text-field v-model="form.credit_limit" label="Le fío hasta (opcional)" prefix="L" inputmode="decimal" hint="Solo avisa, no bloquea" persistent-hint :error-messages="form.errors.credit_limit" /></v-col>
                        <v-col cols="12" sm="6"><v-text-field v-model="form.credit_days" label="Días para pagar (opcional)" inputmode="numeric" :error-messages="form.errors.credit_days" /></v-col>
                    </v-row>
                    <v-textarea v-model="form.notes" label="Notas" class="mt-2" :error-messages="form.errors.notes" />
                    <v-switch v-if="customer" v-model="form.active" label="Cliente activo (si lo apaga, no sale en las listas)" :error-messages="form.errors.active" />
                </v-card-text>
                <v-card-actions class="px-4 pb-4">
                    <v-btn variant="outlined" @click="open = false" prepend-icon="mdi-close">Cancelar</v-btn>
                    <v-spacer />
                    <v-btn color="primary" variant="flat" :loading="form.processing" type="submit" prepend-icon="mdi-content-save">Guardar</v-btn>
                </v-card-actions>
            </form>
        </v-card>
    </v-dialog>
</template>
