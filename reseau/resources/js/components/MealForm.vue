<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { fmtLong } from '../lib/dates';
import { SLOTS } from '../lib/health';
import { addMeal } from '../stores/health';
import { toast } from '../stores/session';
import Sheet from './Sheet.vue';
import Wizard from './Wizard.vue';

const props = defineProps({ modelValue: Boolean, date: { type: String, default: null } });
const emit = defineEmits(['update:modelValue']);
const open = computed({ get: () => props.modelValue, set: (v) => emit('update:modelValue', v) });

const form = reactive({ slot: 'lunch', description: '' });
const busy = ref(false);

const IDEAS = {
    breakfast: ['Bouillie de maïs et fruits', 'Pain, œufs et avocat', 'Yaourt, flocons d’avoine et banane', 'Omelette et pain complet'],
    lunch: ['Riz, poisson et légumes', 'Poulet, plantain et salade', 'Pondu et poisson', 'Haricots, riz et légumes verts'],
    snack: ['Banane et arachides', 'Yaourt et fruits secs', 'Fruit de saison', 'Pain et beurre de cacahuète'],
    dinner: ['Soupe de légumes et pain', 'Poisson grillé et légumes', 'Riz et lentilles', 'Omelette et salade'],
};
const ICONS = { breakfast: '☀️', lunch: '🍲', snack: '🍎', dinner: '🌙' };

watch(
    () => props.modelValue,
    (isOpen) => isOpen && Object.assign(form, { slot: 'lunch', description: '' }),
);

const steps = computed(() => [
    { key: 'slot', title: 'Moment' },
    { key: 'what', title: 'Repas', valid: form.description.trim().length > 0, hint: 'Décris le repas pour continuer.' },
]);

async function submit() {
    busy.value = true;
    try {
        await addMeal({ date: props.date, slot: form.slot, description: form.description.trim() });
        open.value = false;
        toast('Repas ajouté.');
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Sheet v-model="open" :title="date ? `Repas du ${fmtLong(date)}` : 'Nouveau repas'">
        <Wizard :steps="steps" :busy="busy" submit-label="Ajouter le repas" @submit="submit" @cancel="open = false">
            <template #default="{ step }">
                <div v-if="step.key === 'slot'" class="grid grid-cols-2 gap-2" role="radiogroup" aria-label="Moment de la journée">
                    <button
                        v-for="(label, key) in SLOTS"
                        :key="key"
                        type="button"
                        role="radio"
                        :aria-checked="form.slot === key"
                        data-autofocus
                        class="rounded-2xl border-2 p-4 text-center transition-all"
                        :class="form.slot === key ? 'scale-[1.03] border-indigo-600 bg-indigo-50 dark:border-indigo-400 dark:bg-indigo-950/50' : 'border-slate-200 dark:border-slate-700'"
                        @click="form.slot = key"
                    >
                        <span class="block text-2xl" aria-hidden="true">{{ ICONS[key] }}</span>
                        <span class="block text-sm font-medium">{{ label }}</span>
                    </button>
                </div>
                <template v-else>
                    <div>
                        <label class="label" for="meal-desc">{{ SLOTS[form.slot] }} : que vas-tu manger ?</label>
                        <input id="meal-desc" v-model="form.description" class="input" maxlength="200" autocomplete="off" />
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button v-for="idea in IDEAS[form.slot]" :key="idea" type="button" class="chip" @click="form.description = idea">{{ idea }}</button>
                    </div>
                </template>
            </template>
        </Wizard>
    </Sheet>
</template>
