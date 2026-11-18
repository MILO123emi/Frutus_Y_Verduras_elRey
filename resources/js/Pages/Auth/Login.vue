<script setup>
import BrandLogo from '@/Components/BrandLogo.vue';
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({ status: String });
const form = useForm({ username: '', password: '', remember: true });
const show = ref(false);
const submit = () => form.post(route('login'), { onFinish: () => form.reset('password') });
</script>

<template>
    <v-app>
        <Head title="Entrar" />
        <v-main class="login-bg">
            <div class="login-wrap">
                <div class="login-brand">
                    <BrandLogo variant="inverse" :height="200" />
                    <p>La Ceiba, Honduras</p>
                </div>
                <v-card class="pa-6 pa-sm-8" max-width="460" width="100%">
                    <h2 class="page-title mb-1" style="font-size:1.6rem">Entrar al sistema</h2>
                    <p class="muted mb-6">Escriba su usuario y su contraseña.</p>
                    <v-alert v-if="status" type="success" class="mb-4">{{ status }}</v-alert>
                    <form @submit.prevent="submit">
                        <v-text-field
                            v-model="form.username"
                            label="Usuario"
                            prepend-inner-icon="mdi-account"
                            autocomplete="username"
                            autocapitalize="none"
                            autofocus
                            :error-messages="form.errors.username"
                        />
                        <v-text-field
                            v-model="form.password"
                            :type="show ? 'text' : 'password'"
                            label="Contraseña"
                            prepend-inner-icon="mdi-lock"
                            autocomplete="current-password"
                            :error-messages="form.errors.password"
                        >
                            <template #append-inner>
                                <v-btn size="small" variant="text" :prepend-icon="show ? 'mdi-eye-off' : 'mdi-eye'" @click="show = !show">{{ show ? 'Ocultar' : 'Ver' }}</v-btn>
                            </template>
                        </v-text-field>
                        <v-btn type="submit" color="primary" block size="x-large" :loading="form.processing" prepend-icon="mdi-login">
                            Entrar
                        </v-btn>
                    </form>
                    <p class="muted text-body-2 mt-6 mb-0">¿Olvidó su contraseña? Pídale al encargado que se la cambie en «Usuarios».</p>
                </v-card>
            </div>
        </v-main>
    </v-app>
</template>

<style scoped>
.login-bg { background: var(--marca); min-height: 100vh; }
.login-wrap { min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 24px 16px; gap: 24px; }
.login-brand { text-align: center; color: rgb(var(--v-theme-on-primary)); }
.login-brand p { margin: 6px 0 0; opacity: .85; font-size: 1.1rem; }
@media (max-height: 699px) { .login-brand :deep(.brand-logo) { height: 140px !important; } }
</style>
