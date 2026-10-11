<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    value: { type: Number, default: null },
    decimals: { type: Number, default: 0 },
    duration: { type: Number, default: 800 },
});

const shown = ref(null);
let frame = 0;
const reduced = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

function animateTo(target) {
    cancelAnimationFrame(frame);
    if (target == null || reduced()) {
        shown.value = target;
        return;
    }
    const from = shown.value ?? 0;
    const start = performance.now();
    const tick = (now) => {
        const t = Math.min((now - start) / props.duration, 1);
        const eased = 1 - (1 - t) ** 3;
        shown.value = from + (target - from) * eased;
        if (t < 1) frame = requestAnimationFrame(tick);
    };
    frame = requestAnimationFrame(tick);
}

onMounted(() => animateTo(props.value));
watch(() => props.value, animateTo);
onBeforeUnmount(() => cancelAnimationFrame(frame));

const format = (n) =>
    n == null ? '–' : n.toLocaleString('fr-FR', { minimumFractionDigits: props.decimals, maximumFractionDigits: props.decimals });
</script>

<template>
    <span class="tabular-nums">{{ format(shown) }}</span>
</template>
