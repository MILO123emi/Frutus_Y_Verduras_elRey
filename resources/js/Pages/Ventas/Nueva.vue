<script setup>
import { computed, nextTick, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { fuzzyFilter, fuzzyMatch } from '@/lib/fuzzy.js';
import { money } from '@/lib/format.js';
import { parseDecimalInput, toCents, lineTotalCents, taxCents, addCents, fromCents } from '@/lib/money.js';

const props = defineProps({
    kind: { type: String, default: 'order' },
    products: { type: Array, default: () => [] },
    units: { type: Array, default: () => [] },
    customers: { type: Array, default: () => [] },
    invoiceReady: { type: Object, default: () => ({ ok: true }) },
    preselectCustomerId: { type: Number, default: null },
});

const page = usePage();
const isInvoice = computed(() => props.kind === 'invoice');
const docName = computed(() => (isInvoice.value ? 'factura' : 'pedido'));
const alerts = computed(() => (isInvoice.value ? page.props.fiscalAlerts ?? [] : []));
const unitName = (id) => props.units.find((u) => u.id === id)?.name ?? '';
const unitOptions = computed(() => props.units.map((u) => u.name));

// ---------- Cliente ----------
const customerId = ref(props.preselectCustomerId);
const customer = computed(() => props.customers.find((c) => c.id === customerId.value) ?? null);
const terms = ref('cash');
const rtn = ref('');
const notes = ref('');
const exo = reactive({ open: false, exempt_purchase_order: '', exonerated_record: '', sag_record: '' });

function onCustomerChange() {
    terms.value = customer.value?.default_terms ?? 'cash';
    rtn.value = customer.value?.rtn ?? '';
    activeRows().forEach(applyLastPrice);
}


// Cliente nuevo sin salir de la venta.
const newCustomer = reactive({ open: false, name: '', phone: '', rtn: '', default_terms: 'cash', errors: {}, saving: false });
function saveNewCustomer() {
    if (newCustomer.saving) return;
    newCustomer.saving = true;
    const name = newCustomer.name.trim();
    router.post(route('clientes.store'), { ...newCustomer, name, stay: true, open: undefined, errors: undefined, saving: undefined }, {
        preserveState: true,
        preserveScroll: true,
        onError: (e) => (newCustomer.errors = e),
        onSuccess: () => {
            router.reload({
                only: ['customers'],
                onSuccess: () => {
                    const c = [...props.customers].reverse().find((x) => x.name === name);
                    if (c) { customerId.value = c.id; onCustomerChange(); }
                    Object.assign(newCustomer, { open: false, name: '', phone: '', rtn: '', default_terms: 'cash', errors: {} });
                },
            });
        },
        onFinish: () => (newCustomer.saving = false),
    });
}

// ---------- Líneas ----------
const mode = ref(localStorage.getItem('elrey.mode') || 'planilla');
watch(mode, (m) => { try { localStorage.setItem('elrey.mode', m); } catch {} });

const makeRow = (p, extra = {}) => reactive({
    product_id: p?.id ?? null,
    description: p?.name ?? '',
    unit: unitName(p?.default_unit_id),
    quantity: '',
    unit_price: '',
    tax_type: p?.tax_type ?? 'exempt',
    reference_price: p?.reference_price ?? null,
    ...extra,
});

const planillaRows = reactive(props.products.map((p) => makeRow(p)));
const planillaFilter = ref('');
const visiblePlanilla = computed(() => planillaRows.filter((r) => !parseDecimalInput(r.quantity).empty || fuzzyMatch(r.description, planillaFilter.value)));

const searchLines = reactive([]);
const productPick = ref(null);
const searchText = ref('');
const searchInput = ref(null);

async function addSearchLine(product) {
    if (!product && !searchText.value.trim()) return;
    const row = makeRow(product, product ? {} : { description: searchText.value.trim() });
    searchLines.push(row);
    productPick.value = null;
    searchText.value = '';
    applyLastPrice(row);
    await nextTick();
    document.getElementById(`qty-s-${searchLines.length - 1}`)?.focus();
}
function removeSearchLine(i) { searchLines.splice(i, 1); }

async function addToCatalog(line) {
    const unit = props.units.find((u) => u.name === line.unit);
    const { data } = await window.axios.post(route('productos.store'), { name: line.description, unit_id: unit?.id ?? null }, { headers: { Accept: 'application/json' } });
    line.product_id = data.product.id;
}

// Precio sugerido: el último que se le cobró a este cliente; si no, el precio de referencia.
async function applyLastPrice(line) {
    if (!line.product_id) return;
    try {
        const { data } = await window.axios.get(route('ventas.last-price'), {
            params: { customer_id: customerId.value, product_id: line.product_id, unit: line.unit },
        });
        if (data.price != null) line.unit_price = data.price;
        else if (line.reference_price && !line.unit_price) line.unit_price = line.reference_price;
    } catch { /* el usuario puede escribir el precio */ }
}

const currentRows = () => mode.value === 'planilla' ? planillaRows : searchLines;
const validQuantity = (r) => parseDecimalInput(r.quantity).valid && toCents(r.quantity) > 0;
const activeRows = () => currentRows().filter((r) => validQuantity(r) && r.description);
function inputError(row, field) {
    const parsed = parseDecimalInput(row[field]);
    if (parsed.empty) return '';
    if (!parsed.valid || (field === 'quantity' && toCents(parsed.value) === 0)) {
        return field === 'quantity' ? 'Escriba una cantidad, por ejemplo 2 o 2.5' : 'Escriba un precio, por ejemplo 2 o 2.5';
    }
    return '';
}
const hasInvalidInputs = () => currentRows().some((r) => inputError(r, 'quantity') || inputError(r, 'unit_price'));
if (customerId.value) onCustomerChange();
const lineTotal = (r) => validQuantity(r) && !inputError(r, 'unit_price') ? lineTotalCents(r.quantity, r.unit_price) : 0;

const totals = computed(() => {
    let exempt = 0, g15 = 0, g18 = 0;
    for (const r of activeRows()) {
        const t = lineTotal(r);
        if (r.tax_type === 'g15') g15 = addCents(g15, t); else if (r.tax_type === 'g18') g18 = addCents(g18, t); else exempt = addCents(exempt, t);
    }
    const exonerated = isInvoice.value && exo.exonerated_record;
    const tax15 = exonerated ? 0 : taxCents(g15, '0.15');
    const tax18 = exonerated ? 0 : taxCents(g18, '0.18');
    return { count: activeRows().length, tax15, tax18, total: addCents(exempt, g15, g18, tax15, tax18) };
});
const missingPrice = computed(() => activeRows().filter((r) => !(toCents(r.unit_price) > 0)).length);
const overLimit = computed(() => {
    const lim = toCents(customer.value?.credit_limit);
    return terms.value === 'credit' && lim > 0 && totals.value.total > lim;
});
const needsId = computed(() => isInvoice.value && totals.value.total >= 1000000 && (!customerId.value || !rtn.value.trim()));

async function repeatLast() {
    const { data } = await window.axios.get(route('ventas.repeat'), { params: { customer_id: customerId.value } });
    if (!data.lines.length) {
        errorMsg.value = customer.value ? `${customer.value.name} no tiene pedidos anteriores.` : 'No hay pedidos anteriores de consumidor final.';
        return;
    }
    mode.value = 'busqueda';
    searchLines.splice(0);
    data.lines.forEach((l) => searchLines.push(reactive({ ...l, reference_price: null })));
}

// Enter en una casilla pasa a la siguiente (como en el papel, de izquierda a derecha).
function nextField(e) {
    const fields = [...document.querySelectorAll('.sale-lines input:not([type=hidden])')];
    const i = fields.indexOf(e.target);
    if (i >= 0 && fields[i + 1]) { e.preventDefault(); fields[i + 1].focus(); fields[i + 1].select?.(); }
}

// ---------- Guardar ----------
const errorMsg = ref('');
const serverError = ref('');
const formErrors = ref({});
const submittedRows = ref([]);
const lineError = (row, field) => inputError(row, field) || formErrors.value[`lines.${submittedRows.value.indexOf(row)}.${field}`];
const confirmOpen = ref(false);
const saving = ref(false);

function askSave() {
    errorMsg.value = '';
    if (hasInvalidInputs()) { errorMsg.value = 'Revise las cantidades marcadas en rojo.'; return; }
    if (!totals.value.count) { errorMsg.value = 'Escriba la cantidad de al menos un producto.'; return; }
    if (needsId.value) { errorMsg.value = 'Una factura de L 10,000 o más necesita el cliente y su RTN.'; return; }
    if (isInvoice.value && !props.invoiceReady.ok) return;
    confirmOpen.value = true;
}

function save() {
    if (saving.value) return;
    if (hasInvalidInputs()) { confirmOpen.value = false; errorMsg.value = 'Revise las cantidades marcadas en rojo.'; return; }
    submittedRows.value = activeRows();
    saving.value = true;
    serverError.value = '';
    formErrors.value = {};
    router.post(route('ventas.store'), {
        kind: props.kind,
        customer_id: customerId.value,
        customer_rtn: isInvoice.value ? rtn.value || null : null,
        terms: terms.value,
        notes: notes.value || null,
        exempt_purchase_order: exo.exempt_purchase_order || null,
        exonerated_record: exo.exonerated_record || null,
        sag_record: exo.sag_record || null,
        lines: submittedRows.value.map((r) => ({
            product_id: r.product_id, description: r.description, unit: r.unit,
            quantity: parseDecimalInput(r.quantity).value, unit_price: parseDecimalInput(r.unit_price).value ?? '0.00', tax_type: r.tax_type,
        })),
    }, {
        onError: (e) => { formErrors.value = e; serverError.value = e.general || Object.values(e)[0] || 'No se pudo guardar.'; confirmOpen.value = false; },
        onFinish: () => (saving.value = false),
    });
}

function onKeydown(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { e.preventDefault(); confirmOpen.value ? save() : askSave(); }
}
onMounted(() => window.addEventListener('keydown', onKeydown));
onUnmounted(() => window.removeEventListener('keydown', onKeydown));

const switchKind = () => router.visit(route('ventas.create', isInvoice.value ? {} : { tipo: 'factura' }));
</script>

<template>
    <AppLayout :title="isInvoice ? 'Nueva factura' : 'Nuevo pedido'">
        <div class="d-flex flex-wrap align-center ga-3 mb-4">
            <div class="flex-grow-1">
                <h1 class="page-title">{{ isInvoice ? 'Nueva factura' : 'Nuevo pedido' }}</h1>
                <p class="page-sub">
                    <template v-if="isInvoice">Factura con CAI. Número que sigue: <strong>{{ invoiceReady.next_number ?? '—' }}</strong>.</template>
                    <template v-else>Orden de pedido (no es factura). Después se puede convertir en factura.</template>
                </p>
            </div>
            <v-btn variant="outlined" :prepend-icon="isInvoice ? 'mdi-basket' : 'mdi-receipt-text'" @click="switchKind">
                {{ isInvoice ? 'Hacer pedido en vez de factura' : 'Hacer factura en vez de pedido' }}
            </v-btn>
        </div>

        <v-alert v-if="isInvoice && !invoiceReady.ok" type="error" prominent class="mb-4" title="Ahora no se pueden hacer facturas">
            {{ invoiceReady.reason }} Mientras tanto puede hacer un pedido y facturarlo después.
        </v-alert>
        <v-alert v-for="(a, i) in alerts" :key="i" :type="a.level" class="mb-3">{{ a.message }}</v-alert>
        <v-alert v-if="serverError" type="error" class="mb-4" closable @click:close="serverError = ''">{{ serverError }}</v-alert>

        <v-row>
            <v-col cols="12" lg="8">
                <!-- Paso 1: cliente -->
                <v-card class="pa-4 mb-4">
                    <h2 class="step"><span class="step-number">1</span> ¿Para quién es?</h2>
                    <v-row dense class="align-start">
                        <v-col cols="12" md="7">
                            <v-autocomplete
                                v-model="customerId" :error-messages="formErrors.customer_id"
                                :items="customers"
                                item-title="name"
                                item-value="id"
                                :custom-filter="fuzzyFilter"
                                label="Cliente"
                                placeholder="Consumidor final (sin nombre)"
                                persistent-placeholder
                                clearable
                                auto-select-first
                                prepend-inner-icon="mdi-account"
                                no-data-text="No lo encuentro. Use «Cliente nuevo»."
                                @update:model-value="onCustomerChange"
                            />
                        </v-col>
                        <v-col cols="12" md="5" class="d-flex ga-2 flex-wrap">
                            <v-btn variant="tonal" prepend-icon="mdi-account-plus" @click="newCustomer.open = true">Cliente nuevo</v-btn>
                            <v-btn variant="tonal" prepend-icon="mdi-history" @click="repeatLast">Repetir lo de la vez pasada</v-btn>
                        </v-col>
                    </v-row>

                    <v-row dense class="align-center">
                        <v-col cols="12" md="7">
                            <div class="muted text-body-2 mb-1">¿Cómo paga?</div>
                            <v-btn-toggle v-model="terms" mandatory divided color="primary" variant="outlined" class="w-100">
                                <v-btn value="cash" class="flex-grow-1" prepend-icon="mdi-cash">Contado</v-btn>
                                <v-btn value="credit" class="flex-grow-1" prepend-icon="mdi-notebook-edit">Crédito (queda debiendo)</v-btn>
                            </v-btn-toggle>
                        </v-col>
                        <v-col v-if="isInvoice" cols="12" md="5">
                            <v-text-field v-model="rtn" :error-messages="formErrors.customer_rtn" label="RTN o identidad del cliente" hint="Obligatorio si la factura es de L 10,000 o más" persistent-hint />
                        </v-col>
                    </v-row>

                    <v-alert v-if="terms === 'credit' && !customerId" type="warning" class="mt-3" density="compact">
                        Para dar crédito elija el cliente; si no, no se sabrá quién debe.
                    </v-alert>

                    <div v-if="isInvoice" class="mt-3">
                        <v-btn variant="text" :prepend-icon="exo.open ? 'mdi-chevron-up' : 'mdi-chevron-down'" @click="exo.open = !exo.open">
                            El cliente es exonerado (gobierno, ONG…)
                        </v-btn>
                        <v-row v-if="exo.open" dense class="mt-1">
                            <v-col cols="12" md="4"><v-text-field v-model="exo.exempt_purchase_order" label="Nº orden de compra exenta" /></v-col>
                            <v-col cols="12" md="4"><v-text-field v-model="exo.exonerated_record" label="Nº constancia de exonerado" hint="Si lo llena, no se cobra ISV" persistent-hint /></v-col>
                            <v-col cols="12" md="4"><v-text-field v-model="exo.sag_record" label="Nº registro SAG" /></v-col>
                        </v-row>
                    </div>
                </v-card>

                <!-- Paso 2: productos -->
                <v-card class="pa-4 mb-4 sale-lines">
                    <div class="d-flex flex-wrap align-center ga-3 mb-3">
                        <h2 class="step flex-grow-1 mb-0"><span class="step-number">2</span> ¿Qué lleva?</h2>
                        <v-btn-toggle v-model="mode" mandatory divided variant="outlined" color="primary" density="comfortable">
                            <v-btn value="planilla" prepend-icon="mdi-format-list-checks">Lista como el talonario</v-btn>
                            <v-btn value="busqueda" prepend-icon="mdi-magnify">Buscar producto</v-btn>
                        </v-btn-toggle>
                    </div>

                    <template v-if="mode === 'planilla'">
                        <v-text-field v-model="planillaFilter" label="Filtrar la lista (opcional)" prepend-inner-icon="mdi-filter-variant" clearable hide-details class="mb-3" />
                        <div class="table-scroll">
                            <v-table density="comfortable" fixed-header height="56vh">
                                <thead>
                                    <tr><th>Producto</th><th style="width:110px">Cantidad</th><th style="width:170px">Unidad</th><th style="width:150px">Precio</th><th class="text-right" style="width:130px">Total</th></tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(r, i) in visiblePlanilla" :key="r.product_id" :class="{ used: validQuantity(r) }">
                                        <td class="font-weight-bold">{{ r.description }}</td>
                                        <td><v-text-field v-model="r.quantity" :error-messages="lineError(r, 'quantity')" inputmode="decimal" hide-details="auto" density="compact" :aria-label="`Cantidad de ${r.description}`" @keydown.enter="nextField" @change="!r.unit_price && applyLastPrice(r)" /></td>
                                        <td><v-combobox v-model="r.unit" :error-messages="lineError(r, 'unit')" :items="unitOptions" hide-details="auto" density="compact" :aria-label="`Unidad de ${r.description}`" @update:model-value="applyLastPrice(r)" /></td>
                                        <td><v-text-field v-model="r.unit_price" :error-messages="lineError(r, 'unit_price')" inputmode="decimal" prefix="L" hide-details="auto" density="compact" :aria-label="`Precio de ${r.description}`" @keydown.enter="nextField" /></td>
                                        <td class="text-right money">{{ validQuantity(r) ? money(fromCents(lineTotal(r))) : '' }}</td>
                                    </tr>
                                </tbody>
                            </v-table>
                        </div>
                    </template>

                    <template v-else>
                        <v-autocomplete
                            ref="searchInput"
                            v-model="productPick"
                            v-model:search="searchText"
                            :items="products"
                            item-title="name"
                            return-object
                            :custom-filter="fuzzyFilter"
                            label="Escriba el producto y presione Enter"
                            prepend-inner-icon="mdi-magnify"
                            auto-select-first
                            autofocus
                            @update:model-value="(p) => p && addSearchLine(p)"
                        >
                            <template #no-data>
                                <v-list-item v-if="searchText" prepend-icon="mdi-plus" :title="`Agregar «${searchText}» (no está en la lista)`" @click="addSearchLine(null)" />
                            </template>
                        </v-autocomplete>

                        <div class="table-scroll">
                            <v-table density="comfortable">
                                <thead>
                                    <tr><th>Producto</th><th style="width:110px">Cantidad</th><th style="width:170px">Unidad</th><th style="width:150px">Precio</th><th class="text-right" style="width:120px">Total</th><th style="width:60px"></th></tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(r, i) in searchLines" :key="i">
                                        <td>
                                            <strong>{{ r.description }}</strong>
                                            <div v-if="!r.product_id"><v-btn size="small" variant="text" color="secondary" prepend-icon="mdi-plus" @click="addToCatalog(r)">Guardar en la lista de productos</v-btn></div>
                                        </td>
                                        <td><v-text-field :id="`qty-s-${i}`" v-model="r.quantity" :error-messages="lineError(r, 'quantity')" inputmode="decimal" hide-details="auto" density="compact" @keydown.enter="nextField" /></td>
                                        <td><v-combobox v-model="r.unit" :error-messages="lineError(r, 'unit')" :items="unitOptions" hide-details="auto" density="compact" @update:model-value="applyLastPrice(r)" /></td>
                                        <td><v-text-field v-model="r.unit_price" :error-messages="lineError(r, 'unit_price')" inputmode="decimal" prefix="L" hide-details="auto" density="compact" @keydown.enter.prevent="searchInput?.focus()" /></td>
                                        <td class="text-right money">{{ money(fromCents(lineTotal(r))) }}</td>
                                        <td><v-btn icon="mdi-close" size="small" variant="text" :aria-label="`Quitar ${r.description}`" @click="removeSearchLine(i)" /></td>
                                    </tr>
                                    <tr v-if="!searchLines.length">
                                        <td colspan="6" class="text-center muted py-6">Busque arriba el primer producto. Ejemplo: escriba «tom» para Tomate.</td>
                                    </tr>
                                </tbody>
                            </v-table>
                        </div>
                    </template>
                </v-card>
            </v-col>

            <!-- Total y guardar -->
            <v-col cols="12" lg="4">
                <v-card class="pa-5 summary">
                    <div class="muted">{{ totals.count }} {{ totals.count === 1 ? 'producto' : 'productos' }}</div>
                    <div class="total money">{{ money(fromCents(totals.total)) }}</div>
                    <div v-if="totals.tax15 || totals.tax18" class="muted mb-2">
                        Incluye ISV: {{ money(fromCents(addCents(totals.tax15, totals.tax18))) }}
                    </div>
                    <div class="mb-3">
                        <v-chip :color="terms === 'credit' ? 'warning' : 'primary'" size="large">
                            {{ terms === 'credit' ? 'A crédito' : 'De contado' }}
                        </v-chip>
                        <span v-if="customer" class="ml-2">{{ customer.name }}</span>
                        <span v-else class="ml-2 muted">Consumidor final</span>
                    </div>
                    <v-alert v-if="missingPrice" type="warning" density="compact" class="mb-3">
                        {{ missingPrice }} {{ missingPrice === 1 ? 'producto no tiene' : 'productos no tienen' }} precio.
                    </v-alert>
                    <v-alert v-if="overLimit" type="warning" density="compact" class="mb-3">
                        Pasa el límite de crédito de este cliente ({{ money(customer.credit_limit) }}).
                    </v-alert>
                    <v-textarea v-model="notes" :error-messages="formErrors.notes" label="Nota (opcional)" />
                    <v-btn block size="x-large" color="primary" prepend-icon="mdi-printer" :disabled="isInvoice && !invoiceReady.ok" @click="askSave">
                        Guardar e imprimir
                    </v-btn>
                    <p class="muted text-body-2 mt-2 mb-0 text-center">Atajo del teclado: Ctrl + Enter</p>
                </v-card>
            </v-col>
        </v-row>

        <ConfirmDialog
            v-model="confirmOpen"
            :title="`¿Guardar ${isInvoice ? 'la factura' : 'el pedido'}?`"
            :confirm-text="`Sí, guardar e imprimir`"
            :loading="saving"
            @confirm="save"
        >
            <p class="mb-2">
                {{ customer ? customer.name : 'Consumidor final' }} ·
                {{ totals.count }} {{ totals.count === 1 ? 'producto' : 'productos' }} ·
                {{ terms === 'credit' ? 'a crédito' : 'de contado' }}
            </p>
            <p class="text-h4 money mb-2">{{ money(fromCents(totals.total)) }}</p>
            <p v-if="isInvoice" class="muted mb-0">Una factura ya guardada no se puede cambiar; solo anular con el PIN del encargado.</p>
        </ConfirmDialog>

        <v-dialog v-model="newCustomer.open" max-width="560">
            <v-card class="pa-2">
                <form @submit.prevent="saveNewCustomer">
                    <v-card-title class="text-h5">Cliente nuevo</v-card-title>
                    <v-card-text>
                        <v-text-field v-model="newCustomer.name" label="Nombre" autofocus :error-messages="newCustomer.errors.name" />
                        <v-text-field v-model="newCustomer.phone" label="Teléfono (opcional)" inputmode="tel" :error-messages="newCustomer.errors.phone" />
                        <v-text-field v-model="newCustomer.rtn" label="RTN (opcional, para facturas)" :error-messages="newCustomer.errors.rtn" />
                        <div class="muted text-body-2 mb-1">Normalmente paga:</div>
                        <v-btn-toggle v-model="newCustomer.default_terms" mandatory divided variant="outlined" color="primary">
                            <v-btn value="cash" prepend-icon="mdi-cash">De contado</v-btn>
                            <v-btn value="credit" prepend-icon="mdi-notebook-edit">A crédito</v-btn>
                        </v-btn-toggle>
                    </v-card-text>
                    <v-card-actions class="px-4 pb-4">
                        <v-btn variant="outlined" @click="newCustomer.open = false" prepend-icon="mdi-close">Cancelar</v-btn>
                        <v-spacer />
                        <v-btn color="primary" variant="flat" :loading="newCustomer.saving" type="submit" prepend-icon="mdi-account-check">Guardar cliente</v-btn>
                    </v-card-actions>
                </form>
            </v-card>
        </v-dialog>

        <v-snackbar :model-value="!!errorMsg" color="error" @update:model-value="errorMsg = ''">
            {{ errorMsg }}
            <template #actions><v-btn variant="text" @click="errorMsg = ''" prepend-icon="mdi-close">Cerrar</v-btn></template>
        </v-snackbar>
    </AppLayout>
</template>

<style scoped>
.step { font-family: var(--display); font-size: 1.35rem; font-weight: 700; margin: 0 0 12px; }
.summary { position: relative; overflow: visible; margin-top: 10px; }
.summary::before { content: ''; position: absolute; top: -5px; left: 0; right: 0; height: 10px; background: radial-gradient(circle at 5px 5px, var(--fondo) 4px, transparent 4.5px) 0 0 / 16px 10px repeat-x; }
.step-number { display: inline-grid; place-items: center; width: 34px; height: 34px; margin-right: 6px; border-radius: 50%; background: var(--marca); color: rgb(var(--v-theme-on-primary)); }
@media (min-width: 1280px) { .summary { position: sticky; top: 88px; } }
.total { font-family: var(--display); font-size: 2.75rem; font-variant-numeric: tabular-nums; font-weight: 800; color: var(--marca); line-height: 1.1; margin: 4px 0 8px; }
tr.used td { background: rgba(245,183,0,.12); }
.table-scroll { overflow-x: auto; }
.table-scroll :deep(table) { min-width: 640px; }
</style>
