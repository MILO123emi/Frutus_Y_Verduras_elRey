<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useDisplay } from 'vuetify';
import BrandLogo from '@/Components/BrandLogo.vue';
import { longDate, todayIso } from '@/lib/format.js';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';

const props = defineProps({
    title: { type: String, default: '' },
    back: { type: String, default: '' }, // ruta a la que vuelve el botón "Volver"
});

const page = usePage();
const { lgAndUp } = useDisplay();
const drawer = ref(lgAndUp.value);
const user = computed(() => page.props.auth.user);
const role = computed(() => user.value?.role);

const nav = computed(() =>
    [
        { title: 'Inicio', icon: 'mdi-home-variant', route: 'home' },
        { title: 'Nuevo pedido', icon: 'mdi-basket-plus', route: 'ventas.create', roles: ['admin', 'seller'] },
        { title: 'Nueva factura', icon: 'mdi-receipt-text-plus', route: 'ventas.create', params: { tipo: 'factura' }, roles: ['admin', 'seller'] },
        { title: 'Ventas hechas', icon: 'mdi-format-list-bulleted', route: 'ventas.index' },
        { title: 'Quién me debe', icon: 'mdi-hand-coin', route: 'cobros.index' },
        { title: 'Clientes', icon: 'mdi-account-group', route: 'clientes.index' },
        { title: 'Fletes', icon: 'mdi-truck', route: 'fletes.index', roles: ['admin', 'seller'] },
        { title: 'Productos y precios', icon: 'mdi-carrot', route: 'productos.index', roles: ['admin', 'seller'] },
        { title: 'Reportes', icon: 'mdi-chart-box', route: 'reportes.index' },
        { title: 'Ajustes', icon: 'mdi-cog', route: 'ajustes.index', roles: ['admin'] },
        { title: 'Usuarios', icon: 'mdi-account-key', route: 'usuarios.index', roles: ['admin'] },
    ].filter((i) => !i.roles || i.roles.includes(role.value)),
);

const isActive = (item) => route().current(item.route, item.params) && (item.params ? true : !route().params.tipo);

// Mensajes de "listo" / error del servidor, con "Deshacer" cuando aplica.
const snack = ref(false);
const snackText = ref('');
const snackColor = ref('primary');
const undo = ref(null);
watch(
    () => page.props.flash,
    (f) => {
        if (f?.status || f?.error) {
            snackText.value = f.error || f.status;
            snackColor.value = f.error ? 'error' : 'primary';
            undo.value = f.undo || null;
            snack.value = true;
        }
    },
    { immediate: true, deep: true },
);
const undoOpen = ref(false);
const undoing = ref(false);
function doUndo() {
    if (undoing.value || !undo.value) return;
    undoing.value = true;
    router.post(undo.value.url, { reason: 'Deshecho al momento' }, { preserveScroll: true, onSuccess: () => { snack.value = false; undo.value = null; }, onFinish: () => { undoing.value = false; undoOpen.value = false; } });
}
</script>

<template>
    <v-app>
        <Head :title="title" />
        <v-navigation-drawer v-model="drawer" :permanent="lgAndUp" width="280" class="app-drawer">
            <Link :href="route('home')" class="brand">
                <BrandLogo variant="horizontal-inverse" :height="64" />
            </Link>
            <v-list nav density="comfortable" class="px-2">
                <v-list-item
                    v-for="item in nav"
                    :key="item.title"
                    :prepend-icon="item.icon"
                    :title="item.title"
                    :active="isActive(item)"
                    rounded="md"
                    :class="['nav-item', { 'admin-start': item.route === 'ajustes.index' }]"
                    @click="router.visit(route(item.route, item.params)); if (!lgAndUp) drawer = false"
                />
            </v-list>
            <template #append>
                <div class="pa-4 text-body-2 drawer-user">
                    Entró como <strong>{{ user?.name }}</strong>
                    <v-btn block color="white" variant="outlined" class="mt-2" prepend-icon="mdi-logout" @click="router.post(route('logout'))">
                        Salir
                    </v-btn>
                </div>
            </template>
        </v-navigation-drawer>

        <v-app-bar color="surface" flat height="72" class="app-bar">
            <template v-if="!lgAndUp">
                <v-btn variant="text" prepend-icon="mdi-menu" @click="drawer = !drawer">Menú</v-btn>
                <BrandLogo variant="icon" :height="40" />
                <v-app-bar-title class="bar-title">{{ title || 'Inicio' }}</v-app-bar-title>
            </template>
            <v-btn v-if="back && lgAndUp" variant="text" prepend-icon="mdi-arrow-left" @click="router.visit(back)">Volver</v-btn>
            <v-spacer v-if="lgAndUp" />
            <span v-if="lgAndUp" class="mr-4">{{ longDate(todayIso()) }}</span>
            <v-btn v-if="lgAndUp || !route().current('home')" variant="tonal" prepend-icon="mdi-home-variant" class="mr-3" @click="router.visit(route('home'))">Inicio</v-btn>
        </v-app-bar>

        <v-main>
            <div class="page">
                <v-btn v-if="back && !lgAndUp" variant="text" prepend-icon="mdi-arrow-left" class="mb-4" @click="router.visit(back)">Volver</v-btn>
                <slot />
            </div>
        </v-main>

        <ConfirmDialog v-model="undoOpen" title="¿Deshacer este movimiento?" text="El movimiento quedará anulado y se volverá a calcular la deuda del cliente." confirm-text="Sí, deshacer" color="error" :loading="undoing" @confirm="doUndo" />

        <v-snackbar v-model="snack" :color="snackColor" :timeout="undo ? 12000 : 6000" multi-line>
            <span class="text-body-1">{{ snackText }}</span>
            <template #actions>
                <v-btn v-if="undo" variant="outlined" prepend-icon="mdi-undo" @click="undoOpen = true">{{ undo.label }}</v-btn>
                <v-btn variant="text" @click="snack = false">Cerrar</v-btn>
            </template>
        </v-snackbar>
    </v-app>
</template>

<style scoped>
.app-drawer { background: var(--hoja-oscura); color: rgba(255,255,255,.92); }
.brand { display: flex; align-items: center; justify-content: center; padding: 20px 16px; text-decoration: none; }
.nav-item { border-radius: var(--radio-boton); }
.nav-item :deep(.v-list-item-title) { font-size: 1.05rem !important; font-weight: 600; }
.nav-item :deep(.v-icon) { opacity: .8; }
.nav-item:hover { background: rgba(255,255,255,.08); }
.nav-item.v-list-item--active { background: var(--mango); color: var(--tinta); }
.nav-item.v-list-item--active :deep(.v-list-item-title) { font-weight: 700; }
.nav-item.v-list-item--active :deep(.v-list-item__overlay) { opacity: 0; }
.admin-start { border-top: 1px solid rgba(255,255,255,.25); margin-top: 12px; }
.drawer-user { color: rgba(255,255,255,.92); }
.app-bar { border-bottom: 1px solid var(--linea); color: var(--tinta); }
.bar-title :deep(.v-toolbar-title__placeholder) { font-family: var(--display); font-weight: 700; font-size: 1.45rem; }
.page { max-width: 1280px; margin: 0 auto; padding: 24px 20px 64px; }
@media (max-width: 600px) { .page { padding: 16px 12px 48px; } }
</style>
