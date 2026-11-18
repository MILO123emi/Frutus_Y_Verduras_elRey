<script setup>
// Confirmación antes de acciones importantes: dice qué va a pasar, con dos botones claros.
const open = defineModel({ type: Boolean, default: false });
defineProps({
    title: { type: String, required: true },
    text: { type: String, default: '' },
    confirmText: { type: String, default: 'Sí, continuar' },
    color: { type: String, default: 'primary' },
    loading: { type: Boolean, default: false },
});
const emit = defineEmits(['confirm']);
</script>

<template>
    <v-dialog v-model="open" max-width="520" :persistent="loading">
        <v-card class="pa-2">
            <form @submit.prevent="!loading && emit('confirm')">
                <v-card-title class="text-h5 text-wrap">{{ title }}</v-card-title>
                <v-card-text class="text-body-1">
                    {{ text }}
                    <slot />
                </v-card-text>
                <v-card-actions class="px-4 pb-4">
                    <v-btn type="button" :autofocus="!$slots.default" variant="outlined" prepend-icon="mdi-arrow-left" :disabled="loading" @click="open = false">No, volver</v-btn>
                    <v-spacer />
                    <v-btn type="submit" :color="color" variant="flat" :loading="loading" prepend-icon="mdi-check">{{ confirmText }}</v-btn>
                </v-card-actions>
            </form>
        </v-card>
    </v-dialog>
</template>
