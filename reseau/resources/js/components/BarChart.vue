<script setup>
defineProps({
    bars: { type: Array, required: true }, // [{ label, ratio (0..1 ou null), title }]
    label: { type: String, default: 'Histogramme' },
});
</script>

<template>
    <div class="flex h-28 items-end gap-1" role="img" :aria-label="label">
        <div v-for="(b, i) in bars" :key="i" class="flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-1" :title="b.title">
            <div class="flex w-full flex-1 items-end rounded-t bg-slate-100 dark:bg-slate-800">
                <div
                    class="w-full rounded-t"
                    :class="b.ratio === 1 ? 'bg-emerald-500' : 'bg-indigo-500'"
                    :style="{ height: b.ratio == null ? '0%' : Math.max(b.ratio * 100, b.ratio > 0 ? 6 : 0) + '%' }"
                />
            </div>
            <span class="text-[9px] text-slate-500 dark:text-slate-400">{{ b.label }}</span>
        </div>
    </div>
</template>
