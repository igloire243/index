<script setup>
import { computed, nextTick, ref, watch } from 'vue';

/**
 * Formulaire en plusieurs étapes.
 *
 * steps : [{ key, title, valid?: boolean, hint?: string }]. Une étape dont `valid` vaut false bloque « Suivant »
 * (et la validation finale ramène à la première étape invalide). Le contenu de chaque étape est fourni par le slot
 * par défaut, qui reçoit { step, index }.
 */
const props = defineProps({
    steps: { type: Array, required: true },
    busy: Boolean,
    submitLabel: { type: String, default: 'Enregistrer' },
});
const emit = defineEmits(['submit', 'cancel']);

const index = ref(0);
const reached = ref(0);
const direction = ref('next');
const body = ref(null);

const current = computed(() => props.steps[index.value]);
const isLast = computed(() => index.value === props.steps.length - 1);
const isValid = (s) => s.valid !== false;
const canNext = computed(() => isValid(current.value));
const validBefore = (i) => props.steps.slice(0, i).every(isValid);
const progress = computed(() => ((index.value + 1) / props.steps.length) * 100);

function go(i) {
    if (i < 0 || i >= props.steps.length || i === index.value) return;
    if (i > index.value && !validBefore(i)) return;
    direction.value = i > index.value ? 'next' : 'prev';
    index.value = i;
    reached.value = Math.max(reached.value, i);
}

function onSubmit() {
    if (!isLast.value) return go(index.value + 1);
    const bad = props.steps.findIndex((s) => !isValid(s));
    if (bad >= 0) return go(bad);
    emit('submit');
}

// À chaque étape, le focus va au premier champ (clavier et lecteurs d'écran).
watch(index, async () => {
    await nextTick();
    setTimeout(() => body.value?.querySelector('input:not([type=hidden]):not([readonly]), select, textarea, [data-autofocus]')?.focus({ preventScroll: true }), 190);
});

defineExpose({ go });
</script>

<template>
    <form class="space-y-4" novalidate @submit.prevent="onSubmit">
        <div>
            <div class="h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800" aria-hidden="true">
                <div class="h-full rounded-full bg-indigo-500 transition-[width] duration-300 ease-out" :style="{ width: progress + '%' }" />
            </div>
            <ol class="mt-3 flex items-start justify-between gap-1" aria-label="Étapes">
                <li v-for="(s, i) in steps" :key="s.key" class="min-w-0 flex-1">
                    <button
                        type="button"
                        class="flex w-full flex-col items-center gap-1 text-center disabled:cursor-default"
                        :disabled="i > reached || (i > index && !validBefore(i))"
                        :aria-current="i === index ? 'step' : undefined"
                        @click="go(i)"
                    >
                        <span
                            class="grid size-7 place-items-center rounded-full text-xs font-semibold transition-colors duration-300"
                            :class="
                                i === index
                                    ? 'bg-indigo-600 text-white shadow ring-4 ring-indigo-500/20'
                                    : i < index || i <= reached
                                      ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300'
                                      : 'bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500'
                            "
                        >
                            {{ i + 1 }}
                        </span>
                        <span class="w-full truncate text-[11px] leading-tight" :class="i === index ? 'font-semibold' : 'text-slate-500 dark:text-slate-400'">{{ s.title }}</span>
                    </button>
                </li>
            </ol>
            <p class="sr-only" aria-live="polite">Étape {{ index + 1 }} sur {{ steps.length }} : {{ current.title }}</p>
        </div>

        <div ref="body" class="overflow-hidden px-0.5 py-0.5">
            <Transition :name="`step-${direction}`" mode="out-in">
                <div :key="index" class="space-y-3">
                    <slot :step="current" :index="index" />
                </div>
            </Transition>
        </div>

        <p v-if="!canNext && current.hint" class="text-sm text-amber-700 dark:text-amber-400" role="status">{{ current.hint }}</p>

        <div class="flex justify-between gap-2 pt-1">
            <button type="button" class="btn" @click="index === 0 ? emit('cancel') : go(index - 1)">
                {{ index === 0 ? 'Annuler' : 'Retour' }}
            </button>
            <button type="submit" class="btn btn-primary min-w-32" :disabled="!canNext || busy">
                {{ isLast ? (busy ? 'Enregistrement…' : submitLabel) : 'Suivant' }}
            </button>
        </div>
    </form>
</template>
