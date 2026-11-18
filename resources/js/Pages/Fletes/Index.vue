<script setup>
import { computed, reactive, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { fuzzyFilter } from '@/lib/fuzzy.js';
import { money, purposeLabels } from '@/lib/format.js';

const props = defineProps({
    view: String, anchor: String, from: String, to: String, today: String,
    freights: Array, drivers: Array, schedules: Array, customers: Array,
});

const weekdays = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
const parse = (iso) => new Date(iso + 'T12:00:00');
const iso = (d) => new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
const addDays = (s, n) => { const d = parse(s); d.setDate(d.getDate() + n); return iso(d); };

const days = computed(() => {
    const out = [];
    for (let d = props.from; d <= props.to; d = addDays(d, 1)) out.push(d);
    return out;
});
const byDay = computed(() => {
    const m = {};
    props.freights.forEach((f) => (m[f.trip_date] ||= []).push(f));
    return m;
});
const title = computed(() => {
    const a = parse(props.from), b = parse(props.to);
    if (props.view === 'month') return parse(props.anchor).toLocaleDateString('es-HN', { month: 'long', year: 'numeric' });
    return `Semana del ${a.getDate()} de ${a.toLocaleDateString('es-HN', { month: 'long' })} al ${b.getDate()} de ${b.toLocaleDateString('es-HN', { month: 'long' })}`;
});
const inMonth = (d) => props.view !== 'month' || parse(d).getMonth() === parse(props.anchor).getMonth();

function go(params) { router.get(route('fletes.index'), { vista: props.view === 'month' ? 'mes' : 'semana', fecha: props.anchor, ...params }, { preserveScroll: true }); }
const move = (dir) => go({ fecha: props.view === 'month' ? (() => { const d = parse(props.anchor); d.setMonth(d.getMonth() + dir, 1); return iso(d); })() : addDays(props.anchor, 7 * dir) });

// ---------- Flete (crear / editar) ----------
const destinations = ['San Pedro Sula', 'Tegucigalpa', 'La Ceiba', 'Tela', 'Olanchito', 'Trujillo'];
const blank = { trip_date: props.today, driver_id: null, destination: 'San Pedro Sula', purpose: 'business', for_whom: '', customer_id: null,
    vehicle: '', departure_time: '', notes: '', charge_amount: '', charge_to_account: false, status: 'scheduled' };
const dlg = reactive({ open: false, id: null, fromSchedule: false });
const form = useForm({ ...blank });

function openNew(day) {
    form.clearErrors();
    Object.assign(form, { ...blank, trip_date: day || props.today });
    Object.assign(dlg, { open: true, id: null, fromSchedule: false });
}
function openEdit(f) {
    form.clearErrors();
    Object.assign(form, Object.fromEntries(Object.entries(blank).map(([key, fallback]) => [key, f[key] ?? fallback])), {
        trip_date: String(f.trip_date).slice(0, 10), departure_time: (f.departure_time ?? '').slice(0, 5),
    });
    Object.assign(dlg, { open: true, id: f.id, fromSchedule: !!f.freight_schedule_id });
}
function onDriver(id) {
    const d = props.drivers.find((x) => x.id === id);
    if (d?.vehicle && !form.vehicle) form.vehicle = d.vehicle;
}
const cancelOpen = ref(false);
function saveFreight(status) {
    if (form.processing) return;
    if (typeof status === 'string' && ['scheduled', 'done', 'cancelled'].includes(status)) form.status = status;
    const opts = { preserveScroll: true, onSuccess: () => { dlg.open = false; cancelOpen.value = false; } };
    const t = form.transform((d) => ({ ...d, charge_amount: d.charge_amount === '' ? null : d.charge_amount }));
    dlg.id ? t.put(route('fletes.update', dlg.id), opts) : t.post(route('fletes.store'), opts);
}
const statusInfo = { scheduled: { t: 'Pendiente', c: 'secondary' }, done: { t: 'Hecho', c: 'primary' }, cancelled: { t: 'Cancelado', c: 'error' } };

// ---------- Viajes fijos y motoristas ----------
const sched = reactive({ open: false, id: null });
const scheduleBlank = { weekday: 3, driver_id: null, destination: 'San Pedro Sula', vehicle: '', departure_time: '', notes: '', active: true };
const schedForm = useForm({ ...scheduleBlank });
function editSchedule(s) {
    schedForm.clearErrors();
    Object.assign(schedForm, Object.fromEntries(Object.entries(scheduleBlank).map(([key, fallback]) => [key, s?.[key] ?? fallback])), { departure_time: (s?.departure_time ?? '').slice(0, 5) });
    Object.assign(sched, { open: true, id: s?.id ?? null });
}
function saveSchedule() {
    if (schedForm.processing) return;
    const opts = { preserveScroll: true, onSuccess: () => (sched.open = false) };
    sched.id ? schedForm.put(route('fletes.schedules.update', sched.id), opts) : schedForm.post(route('fletes.schedules.store'), opts);
}
const drv = reactive({ open: false, id: null });
const driverBlank = { name: '', phone: '', vehicle: '', active: true };
const drvForm = useForm({ ...driverBlank });
function editDriver(d) {
    drvForm.clearErrors();
    Object.assign(drvForm, Object.fromEntries(Object.entries(driverBlank).map(([key, fallback]) => [key, d?.[key] ?? fallback])));
    Object.assign(drv, { open: true, id: d?.id ?? null });
}
function saveDriver() {
    if (drvForm.processing) return;
    const opts = { preserveScroll: true, onSuccess: () => (drv.open = false) };
    drv.id ? drvForm.put(route('fletes.drivers.update', drv.id), opts) : drvForm.post(route('fletes.drivers.store'), opts);
}
const weekdayItems = weekdays.map((t, i) => ({ title: t, value: i + 1 }));
const activeDrivers = computed(() => props.drivers.filter((d) => d.active || d.id === form.driver_id));
</script>

<template>
    <AppLayout title="Fletes">
        <div class="d-flex flex-wrap align-center ga-3 mb-4">
            <div class="flex-grow-1">
                <h1 class="page-title">Fletes</h1>
                <p class="page-sub">Los viajes fijos salen solos. Toque un viaje para cambiarlo, o anote un flete extra.</p>
            </div>
            <v-btn color="primary" prepend-icon="mdi-plus" @click="openNew()">Anotar flete</v-btn>
        </div>

        <v-card class="pa-3 mb-4">
            <div class="d-flex flex-wrap align-center ga-2">
                <v-btn variant="outlined" prepend-icon="mdi-chevron-left" @click="move(-1)">Anterior</v-btn>
                <v-btn variant="outlined" @click="go({ fecha: today })" prepend-icon="mdi-calendar-today">Hoy</v-btn>
                <v-btn variant="outlined" append-icon="mdi-chevron-right" @click="move(1)">Siguiente</v-btn>
                <h2 class="cal-title flex-grow-1">{{ title }}</h2>
                <v-btn-toggle :model-value="view" mandatory divided variant="outlined" color="primary" density="comfortable">
                    <v-btn value="week" @click="go({ vista: 'semana' })" prepend-icon="mdi-calendar-week">Semana</v-btn>
                    <v-btn value="month" @click="go({ vista: 'mes' })" prepend-icon="mdi-calendar-month">Mes</v-btn>
                </v-btn-toggle>
            </div>
        </v-card>

        <!-- Semana -->
        <div v-if="view === 'week'" class="week">
            <section v-for="(d, i) in days" :key="d" class="day" :class="{ today: d === today }">
                <header class="day-head">
                    <span class="day-name">{{ weekdays[i] }}</span>
                    <span class="day-num">{{ parse(d).getDate() }}</span>
                </header>
                <button v-for="f in byDay[d] || []" :key="f.id" class="trip" :class="f.status" @click="openEdit(f)">
                    <span class="trip-dest">{{ f.destination }}</span>
                    <span v-if="f.departure_time" class="trip-line"><v-icon icon="mdi-clock-outline" size="16" /> {{ f.departure_time }}</span>
                    <span class="trip-line"><v-icon icon="mdi-steering" size="16" /> {{ f.driver || 'Sin motorista' }}</span>
                    <span v-if="f.for_whom || f.customer" class="trip-line"><v-icon icon="mdi-account" size="16" /> {{ f.customer || f.for_whom }}</span>
                    <span v-if="f.charge_amount" class="trip-line money"><v-icon icon="mdi-cash" size="16" /> {{ money(f.charge_amount) }}<span v-if="f.charge_to_account"> a cuenta</span></span>
                    <span class="trip-tags">
                        <v-chip size="x-small" :color="statusInfo[f.status].c">{{ statusInfo[f.status].t }}</v-chip>
                        <v-chip v-if="f.freight_schedule_id" size="x-small" variant="outlined">Fijo</v-chip>
                    </span>
                </button>
                <v-btn variant="text" prepend-icon="mdi-plus" block class="mt-auto" @click="openNew(d)">Anotar</v-btn>
            </section>
        </div>

        <!-- Mes -->
        <v-card v-else class="month pa-2">
            <div v-for="w in weekdays" :key="w" class="m-head">{{ w.slice(0, 3) }}</div>
            <div v-for="d in days" :key="d" class="m-cell" :class="{ out: !inMonth(d), today: d === today }" @dblclick="openNew(d)">
                <div class="m-num">{{ parse(d).getDate() }}</div>
                <button v-for="f in byDay[d] || []" :key="f.id" class="m-trip" :class="f.status" @click="openEdit(f)">
                    {{ f.departure_time ? f.departure_time + ' ' : '' }}{{ f.destination }}
                </button>
            </div>
        </v-card>

        <v-row class="mt-6">
            <v-col cols="12" md="7">
                <v-card class="pa-4">
                    <div class="d-flex align-center mb-2">
                        <h2 class="sec flex-grow-1">Viajes fijos de cada semana</h2>
                        <v-btn variant="tonal" prepend-icon="mdi-plus" @click="editSchedule(null)">Agregar</v-btn>
                    </div>
                    <p v-if="!schedules.length" class="muted">No hay viajes fijos.</p>
                    <div v-for="s in schedules" :key="s.id" class="list-row" :class="{ 'row-dim': !s.active }">
                        <div><strong>Cada {{ weekdays[s.weekday - 1].toLowerCase() }}</strong> a {{ s.destination }}
                            <span class="muted"><span v-if="s.departure_time"> · {{ s.departure_time }}</span><span v-if="s.driver"> · {{ s.driver.name }}</span><span v-if="!s.active"> · apagado</span></span></div>
                        <v-btn variant="text" prepend-icon="mdi-pencil" @click="editSchedule(s)">Cambiar</v-btn>
                    </div>
                </v-card>
            </v-col>
            <v-col cols="12" md="5">
                <v-card class="pa-4">
                    <div class="d-flex align-center mb-2">
                        <h2 class="sec flex-grow-1">Motoristas</h2>
                        <v-btn variant="tonal" prepend-icon="mdi-plus" @click="editDriver(null)">Agregar</v-btn>
                    </div>
                    <p v-if="!drivers.length" class="muted">Agregue los motoristas para elegirlos en cada viaje.</p>
                    <div v-for="d in drivers" :key="d.id" class="list-row" :class="{ 'row-dim': !d.active }">
                        <div><strong>{{ d.name }}</strong> <span class="muted"><span v-if="d.phone"> · {{ d.phone }}</span><span v-if="d.vehicle"> · {{ d.vehicle }}</span></span></div>
                        <v-btn variant="text" prepend-icon="mdi-pencil" @click="editDriver(d)">Cambiar</v-btn>
                    </div>
                </v-card>
            </v-col>
        </v-row>

        <!-- Diálogo flete -->
        <v-dialog v-model="dlg.open" max-width="680">
            <v-card class="pa-2">
                <form @submit.prevent="saveFreight()">
                    <v-card-title class="text-h5">{{ dlg.id ? 'Flete' : 'Anotar flete' }}</v-card-title>
                    <v-card-text>
                        <v-alert v-if="Object.keys(form.errors).length" type="error" class="mb-3">
                            <div v-for="(message, field) in form.errors" :key="field">{{ message }}</div>
                        </v-alert>
                        <v-alert v-if="dlg.fromSchedule" type="info" density="compact" class="mb-3">Es un viaje fijo. Lo que cambie aquí solo afecta este día.</v-alert>
                        <v-row dense>
                            <v-col cols="12" sm="6"><v-text-field autofocus v-model="form.trip_date" type="date" label="Día" :error-messages="form.errors.trip_date" /></v-col>
                            <v-col cols="12" sm="6"><v-text-field v-model="form.departure_time" type="time" label="Hora de salida (opcional)" :error-messages="form.errors.departure_time" /></v-col>
                            <v-col cols="12" sm="6"><v-combobox v-model="form.destination" :items="destinations" label="¿A dónde va?" :error-messages="form.errors.destination" /></v-col>
                            <v-col cols="12" sm="6"><v-select v-model="form.driver_id" :items="activeDrivers" item-title="name" item-value="id" label="Motorista" clearable @update:model-value="onDriver" :error-messages="form.errors.driver_id" /></v-col>
                        </v-row>
                        <div class="muted text-body-2 mb-1">¿Para qué es?</div>
                        <v-chip-group v-model="form.purpose" mandatory selected-class="text-white bg-primary" class="mb-2">
                            <v-chip v-for="(t, k) in purposeLabels" :key="k" :value="k" size="large" variant="outlined">{{ t }}</v-chip>
                        </v-chip-group>
                        <v-row dense>
                            <v-col v-if="form.purpose === 'customer'" cols="12" sm="6">
                                <v-autocomplete v-model="form.customer_id" :items="customers" item-title="name" item-value="id" :custom-filter="fuzzyFilter" label="Cliente" clearable :error-messages="form.errors.customer_id" />
                            </v-col>
                            <v-col cols="12" :sm="form.purpose === 'customer' ? 6 : 12">
                                <v-text-field v-model="form.for_whom" label="Para quién (ej. mi tío Juan)" :error-messages="form.errors.for_whom" />
                            </v-col>
                            <v-col cols="12" sm="6"><v-text-field v-model="form.vehicle" label="Vehículo (opcional)" :error-messages="form.errors.vehicle" /></v-col>
                            <v-col cols="12" sm="6"><v-text-field v-model="form.charge_amount" label="Se cobra (opcional)" prefix="L" inputmode="decimal" :error-messages="form.errors.charge_amount" /></v-col>
                        </v-row>
                        <v-switch v-if="form.customer_id && Number(form.charge_amount) > 0" v-model="form.charge_to_account"
                            :label="`Cargar ${money(form.charge_amount)} a la cuenta del cliente (queda debiendo)`" />
                        <v-textarea v-model="form.notes" label="Notas (qué lleva, dirección…)" :error-messages="form.errors.notes" />
                    </v-card-text>
                    <v-card-actions class="px-4 pb-4 flex-wrap ga-2">
                        <v-btn variant="outlined" @click="dlg.open = false" prepend-icon="mdi-close">Cerrar</v-btn>
                        <v-btn v-if="dlg.id && form.status !== 'cancelled'" color="error" variant="text" prepend-icon="mdi-cancel" :disabled="form.processing" @click="cancelOpen = true">Cancelar este viaje</v-btn>
                        <v-btn v-if="dlg.id && form.status === 'cancelled'" variant="text" prepend-icon="mdi-restore" :disabled="form.processing" @click="saveFreight('scheduled')">Volver a activar</v-btn>
                        <v-spacer />
                        <v-btn v-if="dlg.id && form.status === 'scheduled'" color="secondary" variant="tonal" prepend-icon="mdi-check" @click="saveFreight('done')">Marcar hecho</v-btn>
                        <v-btn color="primary" variant="flat" :loading="form.processing" type="submit" prepend-icon="mdi-content-save">Guardar</v-btn>
                    </v-card-actions>
                </form>
            </v-card>
        </v-dialog>

        <!-- Diálogo viaje fijo -->
        <v-dialog v-model="sched.open" max-width="560">
            <v-card class="pa-2">
                <form @submit.prevent="saveSchedule">
                    <v-card-title class="text-h5">Viaje fijo</v-card-title>
                    <v-card-text>
                        <v-alert v-if="Object.keys(schedForm.errors).length" type="error" class="mb-3">
                            <div v-for="(message, field) in schedForm.errors" :key="field">{{ message }}</div>
                        </v-alert>
                        <v-select autofocus v-model="schedForm.weekday" :items="weekdayItems" label="Día de la semana" :error-messages="schedForm.errors.weekday" />
                        <v-combobox v-model="schedForm.destination" :items="destinations" label="¿A dónde va?" :error-messages="schedForm.errors.destination" />
                        <v-select v-model="schedForm.driver_id" :items="drivers.filter((d) => d.active)" item-title="name" item-value="id" label="Motorista" clearable />
                        <v-row dense>
                            <v-col cols="6"><v-text-field v-model="schedForm.departure_time" type="time" label="Hora de salida" :error-messages="schedForm.errors.departure_time" /></v-col>
                            <v-col cols="6"><v-text-field v-model="schedForm.vehicle" label="Vehículo" :error-messages="schedForm.errors.vehicle" /></v-col>
                        </v-row>
                        <v-textarea v-model="schedForm.notes" label="Notas" :error-messages="schedForm.errors.notes" />
                        <v-switch v-model="schedForm.active" label="Activo (si lo apaga, deja de salir en el calendario)" :error-messages="schedForm.errors.active" />
                        <p v-if="sched.id" class="muted text-body-2">Los próximos viajes que no se han tocado se actualizan con estos datos.</p>
                    </v-card-text>
                    <v-card-actions class="px-4 pb-4">
                        <v-btn variant="outlined" @click="sched.open = false" prepend-icon="mdi-close">Cancelar</v-btn>
                        <v-spacer />
                        <v-btn color="primary" variant="flat" :loading="schedForm.processing" type="submit" prepend-icon="mdi-content-save">Guardar</v-btn>
                    </v-card-actions>
                </form>
            </v-card>
        </v-dialog>

        <!-- Diálogo motorista -->
        <v-dialog v-model="drv.open" max-width="500">
            <v-card class="pa-2">
                <form @submit.prevent="saveDriver">
                    <v-card-title class="text-h5">Motorista</v-card-title>
                    <v-card-text>
                        <v-alert v-if="Object.keys(drvForm.errors).length" type="error" class="mb-3">
                            <div v-for="(message, field) in drvForm.errors" :key="field">{{ message }}</div>
                        </v-alert>
                        <v-text-field v-model="drvForm.name" label="Nombre" autofocus :error-messages="drvForm.errors.name" />
                        <v-text-field v-model="drvForm.phone" label="Teléfono" inputmode="tel" :error-messages="drvForm.errors.phone" />
                        <v-text-field v-model="drvForm.vehicle" label="Vehículo que maneja" :error-messages="drvForm.errors.vehicle" />
                        <v-switch v-if="drv.id" v-model="drvForm.active" label="Activo" :error-messages="drvForm.errors.active" />
                    </v-card-text>
                    <v-card-actions class="px-4 pb-4">
                        <v-btn variant="outlined" @click="drv.open = false" prepend-icon="mdi-close">Cancelar</v-btn>
                        <v-spacer />
                        <v-btn color="primary" variant="flat" :loading="drvForm.processing" type="submit" prepend-icon="mdi-content-save">Guardar</v-btn>
                    </v-card-actions>
                </form>
            </v-card>
        </v-dialog>
        <ConfirmDialog v-model="cancelOpen" title="¿Cancelar este viaje?" text="Quedará marcado como cancelado y su cobro dejará de sumar a la cuenta del cliente." confirm-text="Sí, cancelar viaje" color="error" :loading="form.processing" @confirm="saveFreight('cancelled')" />
    </AppLayout>
</template>

<style scoped>
.cal-title { font-family: var(--display); font-size: 1.35rem; font-weight: 700; margin: 0 8px; text-transform: none; }

.week { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 10px; }
@media (max-width: 1100px) { .week { grid-template-columns: 1fr; } }
.day { background: rgb(var(--v-theme-surface)); border: 1px solid var(--linea); border-radius: var(--radio-tarjeta); padding: 10px; min-height: 220px; display: flex; flex-direction: column; gap: 8px; }
.day.today { border: 2px solid var(--mango); }
.day-head { display: flex; justify-content: space-between; align-items: baseline; }
.day-name { font-weight: 700; }
.day-num { font-family: var(--display); font-size: 1.6rem; font-weight: 800; color: var(--suave); }
.today .day-num { color: var(--marca); }
.trip { min-width: 0; max-width: 100%; white-space: normal; overflow-wrap: anywhere; text-align: left; display: flex; flex-direction: column; gap: 2px; padding: 10px; border-radius: var(--radio-tarjeta); background: rgba(14,124,134,.08); border: 0; border-left: 5px solid var(--turquesa); cursor: pointer; font: inherit; color: inherit; }
.trip:hover { background: rgba(14,124,134,.15); }
.trip.done { background: rgba(45,122,62,.08); border-left-color: var(--marca); }
.trip.cancelled { background: rgba(180,35,24,.08); border-left-color: var(--chile); text-decoration: line-through; color: var(--suave); }
.trip-dest { font-weight: 700; font-size: 1.05rem; }
.trip-line { font-size: .92rem; white-space: normal; overflow-wrap: anywhere; }
.trip-tags :deep(.v-chip) { max-width: 100%; height: auto; min-height: 24px; }
.trip-tags :deep(.v-chip__content) { white-space: normal; overflow: visible; text-overflow: clip; }
.trip-tags { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 4px; }
.month { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 4px; }
.m-head { font-weight: 700; text-align: center; padding: 6px; color: var(--suave); }
.m-cell { min-height: 96px; border: 1px solid var(--linea); border-radius: var(--radio-boton); padding: 4px; display: flex; flex-direction: column; gap: 3px; }
.m-cell.out { opacity: .45; }
.m-cell.today { border: 2px solid var(--mango); }
.m-num { font-weight: 700; font-size: .9rem; }
.m-trip { font: inherit; font-size: .8rem; text-align: left; border: 0; border-radius: var(--radio-rotulo); padding: 2px 5px; background: rgba(14,124,134,.08); cursor: pointer; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
.m-trip.cancelled { text-decoration: line-through; opacity: .6; }
.sec { font-family: var(--display); font-size: 1.35rem; font-weight: 700; margin: 0; }
.list-row { display: flex; justify-content: space-between; align-items: center; gap: 8px; padding: 8px 0; border-bottom: 1px solid var(--linea); }
.list-row:last-child { border-bottom: 0; }
@media (max-width: 1100px) { .day { min-height: 0; } }
</style>

