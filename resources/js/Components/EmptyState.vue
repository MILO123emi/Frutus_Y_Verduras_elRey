<script setup>
defineProps({
    icon: { type: String, default: 'mdi-basket-outline' },
    title: { type: String, required: true },
    text: { type: String, default: '' },
});
</script>

<template>
    <div class="empty">
        <span class="empty-icon"><v-icon :icon="icon" size="40" color="secondary" /></span>
        <p class="empty-title">{{ title }}</p>
        <p v-if="text" class="muted">{{ text }}</p>
        <slot />
    </div>
</template>

<style scoped>
.empty { text-align: left; padding: 32px 16px; }
.empty-icon { display: inline-grid; place-items: center; width: 64px; height: 64px; border-radius: var(--radio-boton); background: rgb(var(--v-theme-surface-variant)); }
.empty-title { font-family: var(--display); font-size: 1.35rem; font-weight: 700; margin: 12px 0 4px; }
.empty p { max-width: 46ch; margin-left: 0; margin-right: 0; }
</style>
