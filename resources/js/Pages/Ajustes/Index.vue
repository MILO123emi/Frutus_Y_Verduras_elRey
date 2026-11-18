<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { date } from '@/lib/format.js';

const props = defineProps({
    default_credentials: { type: Object, default: () => ({ usernames: [], admin_pin: false }) },
    business: { type: Object, default: null },
    series: { type: Array, default: () => [] },
    backups: { type: Array, default: () => [] },
});

const businessForm = useForm({
    trade_name: props.business?.trade_name ?? '',
    legal_name: props.business?.legal_name ?? '',
    rtn: props.business?.rtn ?? '',
    activity: props.business?.activity ?? '',
    address: props.business?.address ?? '',
    phone: props.business?.phone ?? '',
    email: props.business?.email ?? '',
    paper_size: props.business?.print_settings?.paper_size || 'letter',
});
const missingInvoiceDetails = computed(() => !props.business?.legal_name?.trim() || !props.business?.rtn?.trim());
const businessConfirmOpen = ref(false);
function saveBusiness() {
    if (businessForm.processing) return;
    businessForm.put(route('ajustes.business'), {
        preserveScroll: true,
        onSuccess: () => (businessConfirmOpen.value = false),
        onError: () => (businessConfirmOpen.value = false),
    });
}

const invoiceSeries = computed(() => props.series.filter((s) => s.kind === 'invoice').sort((a, b) => b.id - a.id));
const orderSeries = computed(() => props.series.find((s) => s.kind === 'order' && s.origin === 'system'));
function seriesState(series) {
    if (series.status === 'inactive') return { label: 'Apagado', color: 'secondary' };
    if (series.status === 'exhausted') return { label: 'Se terminó', color: 'error' };
    if (series.status === 'expired') return { label: 'Vencido', color: 'error' };
    return series.usable ? { label: 'En uso', color: 'primary' } : { label: 'No disponible', color: 'warning' };
}
function deadlineText(days) {
    if (days === null || days === undefined) return '';
    if (days < 0) return 'vencido';
    if (days === 0) return 'vence hoy';
    return `vence en ${days} ${days === 1 ? 'día' : 'días'}`;
}

const seriesTarget = ref(null);
const updatingSeries = ref(false);
const seriesErrors = ref({});
function confirmSeries(series) {
    seriesErrors.value = {};
    seriesTarget.value = series;
}
function updateSeries() {
    if (!seriesTarget.value || updatingSeries.value) return;
    const series = seriesTarget.value;
    updatingSeries.value = true;
    router.put(route('ajustes.series.update', series.id), {
        status: series.status === 'active' ? 'inactive' : 'active',
        range_alert_threshold: series.range_alert_threshold,
        deadline_alert_days: series.deadline_alert_days,
    }, {
        preserveScroll: true,
        onSuccess: () => (seriesTarget.value = null),
        onError: (errors) => (seriesErrors.value = errors),
        onFinish: () => (updatingSeries.value = false),
    });
}

const caiOpen = ref(false);
const caiForm = useForm({
    cai: '',
    establishment: '000',
    emission_point: '001',
    doc_type_code: '01',
    number_from: '',
    number_to: '',
    issue_deadline: '',
    range_alert_threshold: 50,
    deadline_alert_days: 15,
});
const uppercaseCai = computed({
    get: () => caiForm.cai,
    set: (value) => (caiForm.cai = (value ?? '').toUpperCase()),
});
const firstNumber = computed(() => [
    String(caiForm.establishment ?? '').padStart(3, '0'),
    String(caiForm.emission_point ?? '').padStart(3, '0'),
    String(caiForm.doc_type_code ?? '').padStart(2, '0'),
    String(caiForm.number_from ?? '').padStart(8, '0'),
].join('-'));
function openCai() {
    caiForm.reset();
    caiForm.clearErrors();
    caiOpen.value = true;
}
function saveCai() {
    if (caiForm.processing) return;
    caiForm.post(route('ajustes.cai.store'), {
        preserveScroll: true,
        onSuccess: () => (caiOpen.value = false),
    });
}

const backingUp = ref(false);
function createBackup() {
    if (backingUp.value) return;
    backingUp.value = true;
    router.post(route('ajustes.backup'), {}, {
        preserveScroll: true,
        onFinish: () => (backingUp.value = false),
    });
}
const backupDate = (value) => new Date(value).toLocaleString('es-HN', { timeZone: 'America/Tegucigalpa' });
const backupSize = (bytes) => (bytes / (1024 * 1024)).toFixed(1);
</script>

<template>
    <AppLayout :title="'Ajustes'">
        <v-alert v-if="default_credentials.usernames.length || default_credentials.admin_pin" type="warning" class="mb-4">
            <p v-if="default_credentials.usernames.length" class="mb-0">Estos usuarios todavía tienen la contraseña de prueba: {{ default_credentials.usernames.join(', ') }}. Cámbielas antes de usar el sistema con clientes.</p>
            <p v-if="default_credentials.admin_pin" class="mb-0">El PIN de anular sigue siendo el de prueba. Cámbielo antes de usar el sistema con clientes.</p>
            <v-btn :href="route('usuarios.index')" variant="outlined" prepend-icon="mdi-account-group" class="mt-3">Ir a Usuarios</v-btn>
        </v-alert>
        <div class="mb-6">
            <h1 class="page-title">Ajustes</h1>
            <p class="page-sub">Datos del negocio, números para facturar y respaldos.</p>
        </div>

        <v-card class="pa-4 pa-sm-6 mb-6">
            <h2 class="sec">Datos del negocio</h2>
            <v-alert v-if="missingInvoiceDetails" type="warning" class="mb-5">
                Sin razón social y RTN no se pueden hacer facturas.
            </v-alert>
            <form @submit.prevent="businessConfirmOpen = true">
                <v-row>
                    <v-col cols="12" md="6">
                        <v-text-field v-model="businessForm.trade_name" label="Nombre del negocio" :error-messages="businessForm.errors.trade_name" />
                    </v-col>
                    <v-col cols="12" md="6">
                        <v-text-field v-model="businessForm.legal_name" label="Razón social" hint="Nombre legal del dueño o empresa; obligatorio para facturar" persistent-hint :error-messages="businessForm.errors.legal_name" />
                    </v-col>
                    <v-col cols="12" md="6">
                        <v-text-field v-model="businessForm.rtn" label="RTN (14 números)" inputmode="numeric" maxlength="14" :error-messages="businessForm.errors.rtn" />
                    </v-col>
                    <v-col cols="12" md="6">
                        <v-text-field v-model="businessForm.activity" label="Actividad del negocio" :error-messages="businessForm.errors.activity" />
                    </v-col>
                    <v-col cols="12">
                        <v-textarea v-model="businessForm.address" label="Dirección" :error-messages="businessForm.errors.address" />
                    </v-col>
                    <v-col cols="12" md="6">
                        <v-text-field v-model="businessForm.phone" label="Teléfono" type="tel" :error-messages="businessForm.errors.phone" />
                    </v-col>
                    <v-col cols="12" md="6">
                        <v-text-field v-model="businessForm.email" label="Correo electrónico" type="email" :error-messages="businessForm.errors.email" />
                    </v-col>
                </v-row>
                <fieldset class="paper-size mb-5">
                    <legend class="mb-2">Tamaño del papel</legend>
                    <v-btn-toggle v-model="businessForm.paper_size" mandatory color="primary" variant="outlined" divided aria-label="Tamaño del papel">
                        <v-btn value="letter" prepend-icon="mdi-file-outline">Carta</v-btn>
                        <v-btn value="half-letter" prepend-icon="mdi-file-document-outline">Media carta</v-btn>
                    </v-btn-toggle>
                    <p v-if="businessForm.errors.paper_size" class="text-error mt-2" role="alert">{{ businessForm.errors.paper_size }}</p>
                </fieldset>
                <v-btn type="submit" color="primary" variant="flat" size="x-large" prepend-icon="mdi-content-save" :loading="businessForm.processing">Guardar datos</v-btn>
            </form>
        </v-card>

        <v-card class="pa-4 pa-sm-6 mb-6">
            <h2 class="sec">Facturación (CAI)</h2>
            <p class="mb-5">Cada CAI que da el SAR trae un rango de números y una fecha límite. Cuando uno se termina o vence, el sistema pasa solo al siguiente que esté encendido y vigente.</p>
            <div v-if="invoiceSeries.length" class="mb-5">
                <article v-for="s in invoiceSeries" :key="s.id" class="series-block">
                    <div class="d-flex flex-wrap align-center justify-space-between ga-3 mb-3">
                        <h3 class="cai-number">CAI {{ s.cai }}</h3>
                        <v-chip :color="seriesState(s).color">{{ seriesState(s).label }}</v-chip>
                    </div>
                    <p class="number-label">Rango: {{ s.range_label }}</p>
                    <p class="number-label">Sigue: <strong>{{ s.next_label }}</strong></p>
                    <p>Quedan {{ s.remaining }} números</p>
                    <p>
                        Fecha límite {{ date(s.issue_deadline) }}
                        <span v-if="s.days_to_deadline !== null && s.days_to_deadline !== undefined"> · {{ deadlineText(s.days_to_deadline) }}</span>
                    </p>
                    <v-btn v-if="s.status === 'active' || s.status === 'inactive'" variant="outlined" class="mt-3"
                        :color="s.status === 'active' ? 'error' : 'primary'" :prepend-icon="s.status === 'active' ? 'mdi-power' : 'mdi-power-plug'"
                        :disabled="updatingSeries" @click="confirmSeries(s)">
                        {{ s.status === 'active' ? 'Apagar' : 'Encender' }}
                    </v-btn>
                </article>
            </div>
            <EmptyState v-else icon="mdi-receipt-text-outline" title="Todavía no hay CAI registrado" />
            <v-btn color="primary" variant="flat" prepend-icon="mdi-plus" size="x-large" @click="openCai">Registrar CAI nuevo</v-btn>
            <p v-if="orderSeries" class="muted text-body-2 mt-5">Pedidos: el próximo número es {{ orderSeries.next_label }}</p>
        </v-card>

        <v-card class="pa-4 pa-sm-6">
            <h2 class="sec">Respaldos</h2>
            <p class="mb-5">El sistema hace un respaldo cada día a las 6 pm. Conviene copiar el último a una memoria USB una vez por semana.</p>
            <v-btn color="primary" variant="flat" size="x-large" prepend-icon="mdi-database-plus" :loading="backingUp" @click="createBackup">Hacer respaldo ahora</v-btn>
            <ul v-if="backups.length" class="backup-list mt-5">
                <li v-for="b in backups" :key="b.name" class="backup-row">
                    <div>
                        <strong>{{ backupDate(b.date) }}</strong>
                        <p class="muted">{{ backupSize(b.size) }} MB</p>
                    </div>
                    <a :href="route('ajustes.backup.download', b.name)" class="download-link" :aria-label="`Descargar respaldo del ${backupDate(b.date)}`">
                        <v-btn tag="span" :tabindex="-1" :role="null" variant="outlined" color="primary" prepend-icon="mdi-download">Descargar</v-btn>
                    </a>
                </li>
            </ul>
            <p v-else class="muted mt-5">Todavía no hay respaldos guardados.</p>
        </v-card>

        <v-dialog v-model="caiOpen" max-width="760" :persistent="caiForm.processing">
            <v-card class="pa-2">
                <form @submit.prevent="saveCai">
                    <v-card-title class="text-h5 text-wrap">Registrar CAI nuevo</v-card-title>
                    <v-card-text>
                        <p class="mb-5">Copie los datos tal como aparecen en la autorización del SAR. Revise el rango y la fecha antes de guardar.</p>
                        <v-text-field v-model="uppercaseCai" label="CAI" hint="Formato XXXXXX-XXXXXX-XXXXXX-XXXXXX-XXXXXX-XX" persistent-hint autofocus :error-messages="caiForm.errors.cai" />
                        <v-row class="mt-2">
                            <v-col cols="12" sm="4">
                                <v-text-field v-model="caiForm.establishment" label="Establecimiento" inputmode="numeric" maxlength="3" :error-messages="caiForm.errors.establishment" />
                            </v-col>
                            <v-col cols="12" sm="4">
                                <v-text-field v-model="caiForm.emission_point" label="Punto de emisión" inputmode="numeric" maxlength="3" :error-messages="caiForm.errors.emission_point" />
                            </v-col>
                            <v-col cols="12" sm="4">
                                <v-text-field v-model="caiForm.doc_type_code" label="Tipo de documento" inputmode="numeric" maxlength="2" :error-messages="caiForm.errors.doc_type_code" />
                            </v-col>
                            <v-col cols="12" sm="6">
                                <v-text-field v-model="caiForm.number_from" label="Número inicial" type="number" inputmode="numeric" min="1" max="99999999" step="1" :error-messages="caiForm.errors.number_from" />
                            </v-col>
                            <v-col cols="12" sm="6">
                                <v-text-field v-model="caiForm.number_to" label="Número final" type="number" inputmode="numeric" min="1" max="99999999" step="1" :error-messages="caiForm.errors.number_to" />
                            </v-col>
                        </v-row>
                        <p class="number-preview mb-5" aria-live="polite">Primer número: <strong>{{ firstNumber }}</strong></p>
                        <v-text-field v-model="caiForm.issue_deadline" label="Fecha límite para facturar" type="date" :error-messages="caiForm.errors.issue_deadline" />
                        <v-row>
                            <v-col cols="12" sm="6">
                                <v-text-field v-model="caiForm.range_alert_threshold" label="Avisarme cuando queden" suffix="números" type="number" inputmode="numeric" min="0" step="1" :error-messages="caiForm.errors.range_alert_threshold" />
                            </v-col>
                            <v-col cols="12" sm="6">
                                <v-text-field v-model="caiForm.deadline_alert_days" label="Avisarme días antes del vencimiento" type="number" inputmode="numeric" min="0" step="1" :error-messages="caiForm.errors.deadline_alert_days" />
                            </v-col>
                        </v-row>
                    </v-card-text>
                    <v-card-actions class="px-4 pb-4 flex-wrap ga-3">
                        <v-btn variant="outlined" prepend-icon="mdi-close" :disabled="caiForm.processing" @click="caiOpen = false">Cancelar</v-btn>
                        <v-spacer />
                        <v-btn type="submit" color="primary" variant="flat" prepend-icon="mdi-content-save" :loading="caiForm.processing">Registrar CAI</v-btn>
                    </v-card-actions>
                </form>
            </v-card>
        </v-dialog>

        <!-- El diálogo compartido recibe los íconos mediante los valores locales de Vuetify. -->
        <v-defaults-provider :defaults="{ VBtn: { prependIcon: 'mdi-gesture-tap' } }">
            <ConfirmDialog v-model="businessConfirmOpen" title="¿Guardar los datos del negocio?" confirm-text="Sí, guardar" :loading="businessForm.processing" @confirm="saveBusiness">
                Estos datos se usarán en los documentos nuevos. Revise que estén correctos.
            </ConfirmDialog>
            <ConfirmDialog :model-value="!!seriesTarget" :title="seriesTarget?.status === 'active' ? '¿Apagar este CAI?' : '¿Encender este CAI?'"
                :confirm-text="seriesTarget?.status === 'active' ? 'Sí, apagar' : 'Sí, encender'" :color="seriesTarget?.status === 'active' ? 'error' : 'primary'"
                :loading="updatingSeries" @update:model-value="(value) => !value && (seriesTarget = null)" @confirm="updateSeries">
                <template v-if="seriesTarget">
                    <p class="cai-number mb-3">CAI {{ seriesTarget.cai }}</p>
                    <p>{{ seriesTarget.status === 'active' ? 'El sistema dejará de usar este CAI para facturar.' : 'El sistema podrá usar este CAI si quedan números y no ha vencido.' }}</p>
                    <v-alert v-for="(message, field) in seriesErrors" :key="field" type="error" class="mt-3">{{ message }}</v-alert>
                </template>
            </ConfirmDialog>
        </v-defaults-provider>
    </AppLayout>
</template>

<style scoped>
.sec { font-family: var(--display); font-size: 1.35rem; font-weight: 700; margin: 0 0 12px; }
.paper-size { border: 0; min-width: 0; padding: 0; }
.series-block { border: 1px solid var(--linea); border-radius: var(--radio-tarjeta); padding: 18px; margin-bottom: 16px; }
.series-block p + p { margin-top: 6px; }
.cai-number { font-size: 1rem; overflow-wrap: anywhere; }
.number-label, .number-preview { overflow-wrap: anywhere; font-variant-numeric: tabular-nums; }
.number-preview { background: var(--fondo); border-radius: var(--radio-boton); padding: 12px; }
.backup-list { list-style: none; padding: 0; }
.backup-row { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; padding: 16px 0; border-bottom: 1px solid var(--linea); }
.backup-row:last-child { border-bottom: 0; }
.download-link { display: inline-flex; border-radius: var(--radio-boton); text-decoration: none; }
</style>
