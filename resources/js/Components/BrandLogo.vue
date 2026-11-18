<script setup>
defineProps({
    variant: { type: String, default: 'horizontal', validator: (value) => ['horizontal', 'horizontal-inverse', 'icon', 'stacked', 'inverse'].includes(value) },
    height: { type: Number, default: 64 },
});
</script>

<template>
    <img :src="`/brand/logo-${variant}.svg`" alt="Frutas y Verduras El Rey" :style="{ height: `${height}px` }" class="brand-logo" />
</template>

<style scoped>
.brand-logo { display: block; width: auto; max-width: 100%; object-fit: contain; }
</style>
