<script setup>
import { computed, reactive, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { fuzzyMatch } from '@/lib/fuzzy.js';
import { money } from '@/lib/format.js';

const props = defineProps({ products: Array, units: Array });
const search = ref('');
const showInactive = ref(false);
const list = computed(() => props.products.filter((p) => (showInactive.value || p.active) && fuzzyMatch(p.name, search.value)));
const taxItems = [
    { value: 'exempt', title: 'Exento (sin ISV) — la mayoría de verduras' },
    { value: 'g15', title: 'Paga ISV 15%' },
    { value: 'g18', title: 'Paga ISV 18%' },
];
const headers = [{ title: 'Producto', key: 'name' }, { title: 'Unidad normal', key: 'default_unit.name' }, { title: 'Precio de referencia', key: 'reference_price', align: 'end' }, { title: 'Impuesto', key: 'tax_type' }, { title: 'Acciones', key: 'actions', sortable: false }];
const taxShort = { exempt: 'Exento', g15: 'ISV 15%', g18: 'ISV 18%' };

const dlg = reactive({ open: false, id: null });
const form = useForm({ name: '', default_unit_id: null, reference_price: '', tax_type: 'exempt', active: true });
function edit(p) {
    form.clearErrors();
    Object.assign(form, p ? { name: p.name, default_unit_id: p.default_unit_id, reference_price: p.reference_price ?? '', tax_type: p.tax_type, active: p.active }
        : { name: '', default_unit_id: null, reference_price: '', tax_type: 'exempt', active: true });
    Object.assign(dlg, { open: true, id: p?.id ?? null });
}
function save() {
    if (form.processing) return;
    const t = form.transform((d) => ({ ...d, reference_price: d.reference_price === '' ? null : d.reference_price }));
    const opts = { preserveScroll: true, onSuccess: () => (dlg.open = false) };
    dlg.id ? t.put(route('productos.update', dlg.id), opts) : t.post(route('productos.store'), opts);
}
</script>

<template>
    <AppLayout title="Productos y precios">
        <div class="d-flex flex-wrap align-end ga-3 mb-4">
            <div class="flex-grow-1">
                <h1 class="page-title">Productos y precios</h1>
                <p class="page-sub">El precio de aquí se sugiere solo si al cliente nunca se le ha vendido ese producto. Si no, se usa el último precio que se le cobró.</p>
            </div>
            <v-btn color="primary" prepend-icon="mdi-plus" @click="edit(null)">Producto nuevo</v-btn>
        </div>
        <v-card>
            <div class="d-flex flex-wrap ga-3 pa-4 align-center">
                <v-text-field v-model="search" label="Buscar producto" prepend-inner-icon="mdi-magnify" clearable hide-details style="max-width:380px" />
                <v-switch v-model="showInactive" label="Mostrar los que ya no se venden" hide-details />
            </div>
            <v-data-table :headers="headers" :items="list" :items-per-page="25" items-per-page-text="Filas por página">
                <template #item.name="{ item }"><strong :class="{ muted: !item.active }">{{ item.name }}</strong></template>
                <template #item.reference_price="{ item }">{{ item.reference_price ? money(item.reference_price) : '—' }}</template>
                <template #item.tax_type="{ item }"><v-chip size="small" :color="item.tax_type === 'exempt' ? 'primary' : 'warning'">{{ taxShort[item.tax_type] }}</v-chip></template>
                <template #item.actions="{ item }"><v-btn variant="text" prepend-icon="mdi-pencil" @click="edit(item)">Cambiar</v-btn></template>
                <template #no-data>
            <EmptyState icon="mdi-carrot" title="No encuentro ese producto" text="Puede agregarlo con «Producto nuevo»."><v-btn color="primary" prepend-icon="mdi-plus" @click="edit(null)">Producto nuevo</v-btn></EmptyState>
                </template>
            </v-data-table>
        </v-card>

        <v-dialog v-model="dlg.open" max-width="560">
            <v-card class="pa-2">
                <form @submit.prevent="save">
                    <v-card-title class="text-h5">{{ dlg.id ? 'Cambiar producto' : 'Producto nuevo' }}</v-card-title>
                    <v-card-text>
                        <v-text-field v-model="form.name" label="Nombre" autofocus :error-messages="form.errors.name" />
                        <v-select v-model="form.default_unit_id" :items="units.filter((u) => u.active || u.id === form.default_unit_id)" item-title="name" item-value="id" label="Unidad en que se vende normalmente" clearable :error-messages="form.errors.default_unit_id" />
                        <v-text-field v-model="form.reference_price" label="Precio de referencia" prefix="L" inputmode="decimal" :error-messages="form.errors.reference_price" />
                        <v-select v-model="form.tax_type" :items="taxItems" label="Impuesto" :error-messages="form.errors.tax_type" />
                        <v-switch v-if="dlg.id" v-model="form.active" label="Se sigue vendiendo" :error-messages="form.errors.active" />
                    </v-card-text>
                    <v-card-actions class="px-4 pb-4">
                        <v-btn variant="outlined" @click="dlg.open = false" prepend-icon="mdi-close">Cancelar</v-btn>
                        <v-spacer />
                        <v-btn color="primary" variant="flat" :loading="form.processing" type="submit" prepend-icon="mdi-content-save">Guardar</v-btn>
                    </v-card-actions>
                </form>
            </v-card>
        </v-dialog>
    </AppLayout>
</template>
