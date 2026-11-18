<script setup>
import { reactive, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { roleLabels } from '@/lib/format.js';

defineProps({
    users: { type: Array, required: true },
    default_credentials: { type: Object, default: () => ({ usernames: [], admin_pin: false }) },
});

const dialog = reactive({ open: false, id: null, name: '', active: true });
const showPassword = ref(false);
const confirmOpen = ref(false);
const form = useForm({ name: '', username: '', role: 'seller', password: '', pin: '', active: true });
const roles = [
    { value: 'admin', description: 'Puede hacer todo, incluido anular y cambiar ajustes.' },
    { value: 'seller', description: 'Puede vender, cobrar y manejar fletes.' },
    { value: 'accountant', description: 'Solo puede ver y descargar reportes.' },
];

function edit(user = null) {
    form.reset();
    form.clearErrors();
    if (user) {
        Object.assign(form, { name: user.name, username: user.username, role: user.role, active: user.active });
    }
    showPassword.value = false;
    confirmOpen.value = false;
    Object.assign(dialog, { open: true, id: user?.id ?? null, name: user?.name ?? '', active: user?.active ?? true });
}

watch(() => dialog.open, (open) => {
    if (!open) {
        form.reset('password', 'pin');
        showPassword.value = false;
        confirmOpen.value = false;
    }
});

watch(() => form.role, (role) => {
    if (role !== 'admin') {
        form.pin = '';
        form.clearErrors('pin');
    }
});

function save() {
    if (form.processing || confirmOpen.value) return;
    if (dialog.id !== null && dialog.active && !form.active) {
        confirmOpen.value = true;
        return;
    }
    submit();
}

function submit() {
    if (form.processing) return;
    const options = {
        preserveScroll: true,
        onSuccess: () => (dialog.open = false),
        onFinish: () => (confirmOpen.value = false),
    };
    form.transform((data) => ({
        ...data,
        password: data.password || null,
        pin: data.role === 'admin' ? (data.pin || null) : null,
    }));
    if (dialog.id !== null) {
        form.put(route('usuarios.update', dialog.id), options);
    } else {
        form.post(route('usuarios.store'), options);
    }
}
</script>

<template>
    <AppLayout title="Usuarios">
        <v-alert v-if="default_credentials.usernames.length || default_credentials.admin_pin" type="warning" class="mb-4">
            <p v-if="default_credentials.usernames.length" class="mb-0">Estos usuarios todavía tienen la contraseña de prueba: {{ default_credentials.usernames.join(', ') }}. Cámbielas antes de usar el sistema con clientes.</p>
            <p v-if="default_credentials.admin_pin" class="mb-0">El PIN de anular sigue siendo el de prueba. Cámbielo antes de usar el sistema con clientes.</p>
        </v-alert>
        <div class="d-flex flex-wrap align-end ga-3 mb-4">
            <div class="flex-grow-1">
                <h1 class="page-title">Usuarios</h1>
                <p class="page-sub">Quién puede entrar al sistema. Si un empleado se va, apáguelo aquí; sus ventas quedan guardadas.</p>
            </div>
            <v-btn color="primary" prepend-icon="mdi-account-plus" @click="edit()">Usuario nuevo</v-btn>
        </div>

        <v-card>
            <h2 class="sec pa-4">Personas con acceso</h2>
            <v-table v-if="users.length" density="comfortable">
                <thead>
                    <tr><th>Nombre</th><th>Usuario</th><th>Qué puede hacer</th><th>Estado</th><th>Tiene PIN</th><th><span class="sr-only">Acciones</span></th></tr>
                </thead>
                <tbody>
                    <tr v-for="user in users" :key="user.id" :class="{ 'row-dim': !user.active }">
                        <td><strong>{{ user.name }}</strong></td>
                        <td>{{ user.username }}</td>
                        <td>{{ roleLabels[user.role] }}</td>
                        <td><v-chip size="small" :color="user.active ? 'primary' : 'default'">{{ user.active ? 'Activo' : 'Apagado' }}</v-chip></td>
                        <td>{{ user.role === 'admin' ? (user.has_pin ? 'Sí' : 'No') : '—' }}</td>
                        <td class="text-right"><v-btn variant="text" prepend-icon="mdi-pencil" :aria-label="`Cambiar a ${user.name}`" @click="edit(user)">Cambiar</v-btn></td>
                    </tr>
                </tbody>
            </v-table>
            <EmptyState v-else icon="mdi-account-group-outline" title="Todavía no hay usuarios" text="Agregue uno con «Usuario nuevo»." />
        </v-card>

        <v-dialog v-model="dialog.open" max-width="640" :persistent="form.processing || confirmOpen">
            <v-card class="pa-2">
                <form @submit.prevent="save">
                    <v-card-title class="text-h5">{{ dialog.id !== null ? 'Cambiar usuario' : 'Usuario nuevo' }}</v-card-title>
                    <v-card-text>
                        <fieldset :disabled="form.processing" class="fields">
                            <v-text-field v-model="form.name" label="Nombre" autofocus autocomplete="name" maxlength="120" required :error-messages="form.errors.name" />
                            <v-text-field v-model="form.username" label="Usuario" hint="Sin espacios, ej. maria" persistent-hint autocomplete="off" autocapitalize="none" :spellcheck="false" maxlength="40" required :error-messages="form.errors.username" />
                            <v-radio-group v-model="form.role" label="Qué puede hacer" class="mt-4" :error-messages="form.errors.role">
                                <v-radio v-for="role in roles" :key="role.value" :value="role.value" class="mb-2">
                                    <template #label>
                                        <div class="py-1">
                                            <strong>{{ roleLabels[role.value] }}</strong>
                                            <div class="muted">{{ role.description }}</div>
                                        </div>
                                    </template>
                                </v-radio>
                            </v-radio-group>
                            <v-text-field
                                v-model="form.password"
                                :label="dialog.id !== null ? 'Contraseña nueva (déjela vacía para no cambiarla)' : 'Contraseña'"
                                :type="showPassword ? 'text' : 'password'"
                                autocomplete="new-password"
                                hint="Al menos 6 caracteres"
                                persistent-hint
                                :required="dialog.id === null"
                                minlength="6"
                                :error-messages="form.errors.password"
                            />
                            <v-btn type="button" variant="text" class="mb-4" :prepend-icon="showPassword ? 'mdi-eye-off' : 'mdi-eye'" :aria-pressed="showPassword" @click="showPassword = !showPassword">
                                {{ showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña' }}
                            </v-btn>
                            <v-text-field
                                v-if="form.role === 'admin'"
                                v-model="form.pin"
                                :label="dialog.id !== null ? 'PIN nuevo (opcional)' : 'PIN (opcional)'"
                                type="password"
                                inputmode="numeric"
                                autocomplete="new-password"
                                pattern="[0-9]{4,6}"
                                maxlength="6"
                                hint="4 a 6 números; sirve para autorizar anulaciones"
                                persistent-hint
                                :error-messages="form.errors.pin"
                            />
                            <p v-if="dialog.id !== null && form.role === 'admin'" class="muted text-body-2 mb-4">Deje el PIN vacío para conservar el actual.</p>
                            <v-switch v-if="dialog.id !== null" v-model="form.active" label="Usuario activo (puede entrar al sistema)" :error-messages="form.errors.active" />
                        </fieldset>
                    </v-card-text>
                    <v-card-actions class="px-4 pb-4">
                        <v-btn type="button" variant="outlined" prepend-icon="mdi-close" :disabled="form.processing" @click="dialog.open = false">Cancelar</v-btn>
                        <v-spacer />
                        <v-btn type="submit" color="primary" variant="flat" prepend-icon="mdi-content-save" :loading="form.processing">Guardar</v-btn>
                    </v-card-actions>
                </form>
            </v-card>
        </v-dialog>

        <ConfirmDialog v-model="confirmOpen" :title="`¿Apagar a ${dialog.name}?`" text="Ya no podrá entrar." confirm-text="Sí, apagar" color="error" :loading="form.processing" @confirm="submit" />
    </AppLayout>
</template>

<style scoped>
.sec { font-family: var(--display); font-size: 1.35rem; font-weight: 700; margin: 0; }
.fields { border: 0; padding: 0; min-width: 0; }
.sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
</style>
