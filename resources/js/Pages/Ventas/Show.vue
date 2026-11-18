<script setup>
import { computed, reactive, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { money, date, termsLabel, statusLabel, statusColor } from '@/lib/format.js';

const props = defineProps({
    sale: { type: Object, required: true },
    isInvoice: { type: Boolean, default: false },
    autoprint: { type: Boolean, default: false },
    canVoid: { type: Boolean, default: false },
    canConvert: { type: Boolean, default: false },
});

const canOperate = usePage().props.auth.user?.role !== 'accountant';
const docName = computed(() => (props.isInvoice ? 'Factura' : 'Pedido'));
const printFrame = ref(null);
const variant = ref('original');
const previewUrl = computed(() => route('ventas.documento', { sale: props.sale.id, variant: variant.value }));
const printUrl = route('ventas.documento', { sale: props.sale.id, print: 1 });
const pdfUrl = computed(() => route('ventas.pdf', { sale: props.sale.id, variant: variant.value }));

function printNow() {
    const win = printFrame.value?.contentWindow;
    if (!win) return;
    win.focus();
    win.print();
    if (canOperate) window.axios.post(route('ventas.print', props.sale.id)).catch(() => {});
}
const onPrintFrameLoad = () => props.autoprint && setTimeout(printNow, 300);

// Anular: motivo + PIN del encargado.
const voidForm = reactive({ open: false, reason: '', pin: '', errors: {}, loading: false });
function submitVoid() {
    if (voidForm.loading) return;
    voidForm.loading = true;
    voidForm.errors = {};
    router.post(route('ventas.void', props.sale.id), { reason: voidForm.reason, pin: voidForm.pin }, {
        preserveScroll: true,
        onError: (e) => (voidForm.errors = e),
        onSuccess: () => Object.assign(voidForm, { open: false, reason: '', pin: '' }),
        onFinish: () => (voidForm.loading = false),
    });
}

// Convertir pedido en factura.
const conv = reactive({ open: false, customer_rtn: props.sale.customer_rtn_snapshot ?? '', loading: false, errors: {} });
function convert() {
    if (conv.loading) return;
    conv.errors = {};
    conv.loading = true;
    router.post(route('ventas.convert', props.sale.id), { customer_rtn: conv.customer_rtn || null }, {
        onError: (errors) => (conv.errors = errors),
        onSuccess: () => (conv.open = false),
        onFinish: () => (conv.loading = false),
    });
}
</script>

<template>
    <AppLayout :title="`${docName} ${sale.full_number}`" :back="route('ventas.index')">
        <v-row>
            <v-col cols="12" lg="8">
                <v-card class="pa-2">
                    <div class="d-flex align-center flex-wrap ga-2 px-2 pb-2">
                        <span class="muted">Así se ve impreso:</span>
                        <v-spacer />
                        <v-btn-toggle v-model="variant" mandatory density="comfortable" divided variant="outlined" color="primary">
                            <v-btn value="original" prepend-icon="mdi-file-document">Hoja del cliente</v-btn>
                            <v-btn value="copy" prepend-icon="mdi-content-copy">Copia para guardar</v-btn>
                        </v-btn-toggle>
                    </div>
                    <iframe :src="previewUrl" title="Documento" class="doc-frame" />
                </v-card>
                <iframe ref="printFrame" :src="printUrl" title="Impresión" class="print-frame" @load="onPrintFrameLoad" />
            </v-col>

            <v-col cols="12" lg="4">
                <v-card class="pa-5 side">
                    <div class="muted">{{ docName }}</div>
                    <div class="number">{{ sale.full_number }}</div>
                    <v-chip :color="statusColor(sale.status)" class="mt-1">{{ statusLabel(sale.status) }}</v-chip>

                    <dl class="facts">
                        <dt>Cliente</dt><dd>{{ sale.customer_name_snapshot }}</dd>
                        <dt>Fecha</dt><dd>{{ date(sale.issue_date) }}</dd>
                        <dt>Pago</dt><dd>{{ termsLabel(sale.terms) }}</dd>
                        <dt>Total</dt><dd class="money font-weight-bold">{{ money(sale.total) }}</dd>
                        <dt>Impreso</dt><dd>{{ sale.print_count }} {{ sale.print_count === 1 ? 'vez' : 'veces' }}</dd>
                    </dl>

                    <v-alert v-if="sale.status === 'voided'" type="error" class="mb-3">
                        Anulada<span v-if="sale.voided_by"> por {{ sale.voided_by.name }}</span>. Motivo: {{ sale.void_reason }}
                    </v-alert>
                    <v-alert v-if="sale.converted_to" type="info" class="mb-3">
                        Este pedido ya se facturó en
                        <a href="#" @click.prevent="router.visit(route('ventas.show', sale.converted_to.id))">{{ sale.converted_to.full_number }}</a>.
                    </v-alert>
                    <v-alert v-if="sale.source_sale" type="info" class="mb-3">
                        Viene del pedido
                        <a href="#" @click.prevent="router.visit(route('ventas.show', sale.source_sale.id))">{{ sale.source_sale.full_number }}</a>.
                    </v-alert>

                    <div class="d-flex flex-column ga-2">
                        <v-btn size="x-large" color="primary" prepend-icon="mdi-printer" @click="printNow">Imprimir las 2 hojas</v-btn>
                        <v-btn v-if="canConvert" size="large" color="secondary" prepend-icon="mdi-receipt-text-check" @click="conv.open = true">Convertir en factura</v-btn>
                        <v-btn size="large" variant="outlined" prepend-icon="mdi-file-pdf-box" :href="pdfUrl">Descargar PDF</v-btn>
                        <v-btn v-if="canOperate" size="large" variant="outlined" prepend-icon="mdi-basket-plus" @click="router.visit(route('ventas.create'))">Hacer otro pedido</v-btn>
                        <v-btn v-if="canVoid" size="large" color="error" variant="text" prepend-icon="mdi-cancel" @click="voidForm.open = true">Anular…</v-btn>
                    </div>
                </v-card>
            </v-col>
        </v-row>

        <ConfirmDialog v-model="voidForm.open" :title="`¿Anular ${docName.toLowerCase()} ${sale.full_number}?`" confirm-text="Sí, anular" color="error" :loading="voidForm.loading" @confirm="submitVoid">
            <p class="mb-4">Queda marcado como anulado con el motivo. Deja de contar en ventas y deudas. El número no se vuelve a usar.</p>
            <v-textarea v-model="voidForm.reason" label="¿Por qué se anula?" autofocus :error-messages="voidForm.errors.reason" />
            <v-text-field v-model="voidForm.pin" label="PIN del encargado" type="password" inputmode="numeric" :error-messages="voidForm.errors.pin" />
        </ConfirmDialog>

        <ConfirmDialog v-model="conv.open" title="¿Convertir este pedido en factura?" confirm-text="Sí, hacer la factura" color="secondary" :loading="conv.loading" @confirm="convert">
            <p>Se hará una factura con CAI con los mismos productos, precios y cliente. El pedido queda marcado «Facturado» y ya no se cuenta dos veces.</p>
            <v-text-field v-model="conv.customer_rtn" label="RTN o identidad del cliente (si lo tiene)" autofocus :error-messages="conv.errors.customer_rtn" />
            <v-alert v-for="(message, field) in conv.errors" :key="field" type="error" class="mt-2">{{ message }}</v-alert>
        </ConfirmDialog>
    </AppLayout>
</template>

<style scoped>
.doc-frame { width: 100%; height: 74vh; border: 1px solid var(--linea); border-radius: var(--radio-boton); background: rgb(var(--v-theme-surface)); }
.print-frame { width: 0; height: 0; border: 0; position: absolute; }
.side { position: sticky; top: 92px; }
.number { font-family: var(--display); font-weight: 800; font-size: 1.7rem; line-height: 1.15; word-break: break-all; }
.facts { display: grid; grid-template-columns: auto 1fr; gap: 6px 16px; margin: 16px 0; }
.facts dt { color: var(--suave); }
.facts dd { margin: 0; }
.side a { color: var(--turquesa); font-weight: 700; }
</style>
