<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';

const props = defineProps({ label: String, value: String, ratio: { type: Number, default: 0 }, tone: { type: String, default: 'indigo' } });
const pct = computed(() => Math.round(Math.max(0, Math.min(1, props.ratio)) * 100));
const width = ref(0); // part de 0 puis grandit : la barre s'anime à l'affichage
onMounted(() => requestAnimationFrame(() => requestAnimationFrame(() => (width.value = pct.value))));
watch(pct, (v) => nextTick(() => (width.value = v)));
</script>

<template>
    <div>
        <div class="mb-1 flex justify-between gap-2 text-sm">
            <span>{{ label }}</span>
            <span class="muted">{{ value }}</span>
        </div>
        <div
            class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
            role="progressbar"
            :aria-label="label"
            aria-valuemin="0"
            aria-valuemax="100"
            :aria-valuenow="pct"
        >
            <div class="h-full rounded-full transition-[width] duration-700 ease-out" :class="tone === 'emerald' ? 'bg-emerald-500' : 'bg-indigo-500'" :style="{ width: width + '%' }" />
        </div>
    </div>
</template>
