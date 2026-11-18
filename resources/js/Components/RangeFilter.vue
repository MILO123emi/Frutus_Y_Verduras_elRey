<script setup>
// Filtro de fechas compartido (Ventas, Reportes): atajos + rango personalizado.
import { ref, watch } from 'vue';

const props = defineProps({ range: String, from: String, to: String });
const emit = defineEmits(['change']);

const presets = [
    { value: 'hoy', title: 'Hoy' },
    { value: 'semana', title: 'Esta semana' },
    { value: 'mes', title: 'Este mes' },
    { value: 'mes_anterior', title: 'Mes pasado' },
    { value: 'custom', title: 'Otras fechas' },
];
const preset = ref(props.range || 'mes');
const from = ref(props.from);
const to = ref(props.to);
watch(() => [props.range, props.from, props.to], ([r, f, t]) => { preset.value = r; from.value = f; to.value = t; });

function pick(v) {
    preset.value = v;
    if (v !== 'custom') emit('change', { range: v });
}
const apply = () => emit('change', { range: 'custom', from: from.value, to: to.value });
</script>

<template>
    <div class="d-flex flex-wrap ga-2 align-center">
        <v-btn
            v-for="p in presets"
            :key="p.value"
            :variant="preset === p.value ? 'flat' : 'outlined'"
            :color="preset === p.value ? 'primary' : undefined"
            @click="pick(p.value)"
        >{{ p.title }}</v-btn>
        <template v-if="preset === 'custom'">
            <v-text-field v-model="from" type="date" label="Desde" hide-details style="max-width: 190px" />
            <v-text-field v-model="to" type="date" label="Hasta" hide-details style="max-width: 190px" />
            <v-btn color="primary" prepend-icon="mdi-magnify" @click="apply">Ver</v-btn>
        </template>
    </div>
</template>
