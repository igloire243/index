<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { GOALS } from '../lib/health';
import { health, saveGoal } from '../stores/health';
import { toast } from '../stores/session';
import Sheet from './Sheet.vue';
import Wizard from './Wizard.vue';

const props = defineProps({ modelValue: Boolean });
const emit = defineEmits(['update:modelValue']);
const open = computed({ get: () => props.modelValue, set: (v) => emit('update:modelValue', v) });

const form = reactive({ goal: 'maintain', target_weight_kg: '', target_date: '' });
const busy = ref(false);

const CHOICES = [
    { key: 'lose', title: 'Perdre du poids', text: 'Un rythme prudent : 0,25 à 0,5 kg par semaine.' },
    { key: 'gain', title: 'Reprendre du poids', text: 'Manger régulièrement et rebâtir de la force.' },
    { key: 'maintain', title: 'Retrouver la forme', text: 'Garder un poids stable et de bonnes habitudes.' },
];

watch(
    () => props.modelValue,
    (isOpen) => {
        if (!isOpen) return;
        const g = health.goal;
        form.goal = g?.goal ?? 'maintain';
        form.target_weight_kg = g?.target_weight_kg ?? '';
        form.target_date = g?.target_date ?? '';
    },
);

const needsTarget = computed(() => form.goal !== 'maintain');
const weight = computed(() => (form.target_weight_kg === '' ? null : Number(form.target_weight_kg)));
const steps = computed(() => [
    { key: 'goal', title: 'Objectif' },
    {
        key: 'target',
        title: 'Cible',
        valid: needsTarget.value ? weight.value != null && weight.value >= 20 && weight.value <= 400 : weight.value == null || (weight.value >= 20 && weight.value <= 400),
        hint: 'Indique un poids visé entre 20 et 400 kg.',
    },
]);

async function submit() {
    busy.value = true;
    try {
        await saveGoal({ goal: form.goal, target_weight_kg: weight.value, target_date: form.target_date || null });
        open.value = false;
        toast('Objectif enregistré.');
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Sheet v-model="open" title="Mon objectif">
        <Wizard :steps="steps" :busy="busy" submit-label="Enregistrer l’objectif" @submit="submit" @cancel="open = false">
            <template #default="{ step }">
                <div v-if="step.key === 'goal'" class="space-y-2" role="radiogroup" aria-label="Objectif">
                    <button
                        v-for="c in CHOICES"
                        :key="c.key"
                        type="button"
                        role="radio"
                        :aria-checked="form.goal === c.key"
                        data-autofocus
                        class="w-full rounded-2xl border-2 p-3 text-left transition-colors"
                        :class="form.goal === c.key ? 'border-indigo-600 bg-indigo-50 dark:border-indigo-400 dark:bg-indigo-950/50' : 'border-slate-200 dark:border-slate-700'"
                        @click="form.goal = c.key"
                    >
                        <span class="block font-semibold">{{ c.title }}</span>
                        <span class="muted block">{{ c.text }}</span>
                    </button>
                </div>
                <template v-else>
                    <p class="muted">{{ GOALS[form.goal] }}</p>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="label" for="g-weight">Poids visé (kg){{ needsTarget ? ' *' : '' }}</label>
                            <input id="g-weight" v-model="form.target_weight_kg" class="input" type="number" inputmode="decimal" step="0.1" min="20" max="400" />
                        </div>
                        <div>
                            <label class="label" for="g-date">Pour le <span class="muted">(facultatif)</span></label>
                            <input id="g-date" v-model="form.target_date" class="input" type="date" />
                        </div>
                    </div>
                </template>
            </template>
        </Wizard>
    </Sheet>
</template>
