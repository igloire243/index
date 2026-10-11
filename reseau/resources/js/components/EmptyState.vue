<script setup>
import Illustration from './Illustration.vue';

defineProps({ illustration: { type: String, required: true }, title: String, small: Boolean });
</script>

<template>
    <div class="rise mx-auto flex max-w-sm flex-col items-center py-4 text-center">
        <Illustration :name="illustration" :class="small ? 'max-w-48' : 'max-w-72'" />
        <h2 v-if="title" class="mt-3 text-base font-semibold">{{ title }}</h2>
        <div class="muted mt-1 space-y-1"><slot /></div>
        <div class="mt-4 flex flex-wrap justify-center gap-2"><slot name="actions" /></div>
    </div>
</template>
