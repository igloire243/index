<script setup>
import { computed, ref } from 'vue';
import { dotCenter } from '../../lib/secrets';

/**
 * Grille 3 × 3 pour saisir ou afficher un schéma de déverrouillage. Les points se touchent un à un (ou se glissent
 * d'un trait) ; chaque point est un vrai bouton, donc aussi utilisable au clavier.
 * v-model : tableau des points touchés, de 1 à 9, dans l'ordre.
 */
const props = defineProps({ modelValue: { type: Array, default: () => [] }, readonly: Boolean });
const emit = defineEmits(['update:modelValue']);

const pad = ref(null);
const dragging = ref(false);
const dots = Array.from({ length: 9 }, (_, i) => i + 1);

const add = (n) => {
    if (props.readonly || props.modelValue.includes(n)) return;
    emit('update:modelValue', [...props.modelValue, n]);
};
const clear = () => emit('update:modelValue', []);

const line = computed(() => props.modelValue.map((n) => `${dotCenter(n).x},${dotCenter(n).y}`).join(' '));

function onMove(event) {
    if (!dragging.value) return;
    const el = document.elementFromPoint(event.clientX, event.clientY)?.closest('[data-dot]');
    if (el && pad.value?.contains(el)) add(Number(el.dataset.dot));
}
const stop = () => (dragging.value = false);
</script>

<template>
    <div class="mx-auto w-full max-w-60 select-none">
        <div
            ref="pad"
            class="relative aspect-square touch-none rounded-3xl bg-slate-100 p-2 dark:bg-slate-800"
            role="group"
            aria-label="Grille du schéma, points numérotés de 1 à 9"
            @pointermove="onMove"
            @pointerup="stop"
            @pointercancel="stop"
            @pointerleave="stop"
        >
            <svg viewBox="0 0 300 300" class="pointer-events-none absolute inset-2 size-[calc(100%-1rem)]" aria-hidden="true">
                <polyline v-if="modelValue.length > 1" :points="line" fill="none" stroke="#6366f1" stroke-width="10" stroke-linecap="round" stroke-linejoin="round" opacity="0.55" />
            </svg>
            <div class="relative grid size-full grid-cols-3 grid-rows-3">
                <button
                    v-for="n in dots"
                    :key="n"
                    type="button"
                    :data-dot="n"
                    :disabled="readonly"
                    class="grid place-items-center disabled:cursor-default"
                    :aria-label="`Point ${n}${modelValue.includes(n) ? `, étape ${modelValue.indexOf(n) + 1}` : ''}`"
                    @pointerdown.prevent="(dragging = !readonly), add(n)"
                    @keydown.enter.prevent="add(n)"
                    @keydown.space.prevent="add(n)"
                >
                    <span
                        class="grid size-9 place-items-center rounded-full text-xs font-semibold transition-all duration-150"
                        :class="modelValue.includes(n) ? 'scale-110 bg-indigo-600 text-white shadow' : 'bg-white text-slate-400 dark:bg-slate-700 dark:text-slate-300'"
                    >
                        {{ modelValue.includes(n) ? modelValue.indexOf(n) + 1 : '' }}
                    </span>
                </button>
            </div>
        </div>
        <div v-if="!readonly" class="mt-2 flex items-center justify-between">
            <span class="muted text-xs">{{ modelValue.length }} point{{ modelValue.length > 1 ? 's' : '' }} (4 minimum)</span>
            <button type="button" class="btn btn-sm" :disabled="!modelValue.length" @click="clear">Effacer</button>
        </div>
    </div>
</template>
