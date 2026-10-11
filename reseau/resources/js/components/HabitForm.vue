<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { WEEKDAYS } from '../lib/dates';
import { CATEGORIES } from '../lib/health';
import { saveHabit } from '../stores/health';
import { toast } from '../stores/session';
import Sheet from './Sheet.vue';
import Wizard from './Wizard.vue';

const props = defineProps({ modelValue: Boolean, habit: { type: Object, default: null } });
const emit = defineEmits(['update:modelValue']);

const form = reactive({ name: '', category: 'autre', days: [1, 2, 3, 4, 5, 6, 7], time: '', reminder: false, notes: '' });
const busy = ref(false);
const open = computed({ get: () => props.modelValue, set: (v) => emit('update:modelValue', v) });

watch(
    () => props.modelValue,
    (isOpen) => {
        if (!isOpen) return;
        const h = props.habit;
        Object.assign(form, {
            name: h?.name ?? '',
            category: h?.category ?? 'autre',
            days: h ? [...h.days] : [1, 2, 3, 4, 5, 6, 7],
            time: h?.time ?? '',
            reminder: h?.reminder ?? false,
            notes: h?.notes ?? '',
        });
    },
);

const steps = computed(() => [
    { key: 'habit', title: 'Habitude', valid: form.name.trim().length > 0, hint: 'Donne un nom à cette habitude pour continuer.' },
    { key: 'when', title: 'Quand ?', valid: form.days.length > 0, hint: 'Choisis au moins un jour.' },
    { key: 'extra', title: 'Rappel' },
]);

const toggleDay = (n) => {
    form.days = form.days.includes(n) ? form.days.filter((d) => d !== n) : [...form.days, n].sort();
};
const summary = computed(() => {
    const days = form.days.length === 7 ? 'tous les jours' : form.days.map((n) => WEEKDAYS[n - 1].name.slice(0, 3)).join(', ');
    return `${days}${form.time ? ' à ' + form.time : ''}`;
});

async function submit() {
    busy.value = true;
    try {
        await saveHabit(
            {
                name: form.name,
                category: form.category,
                days: form.days,
                time: form.time || null,
                reminder: !!form.time && form.reminder,
                notes: form.notes || null,
                start_date: props.habit?.start_date,
                end_date: props.habit?.end_date,
                program_key: props.habit?.program_key,
            },
            props.habit?.id ?? null,
        );
        open.value = false;
        toast(props.habit ? 'Habitude mise à jour.' : 'Habitude ajoutée.');
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Sheet v-model="open" :title="habit ? 'Modifier l’habitude' : 'Nouvelle habitude'">
        <Wizard :steps="steps" :busy="busy" submit-label="Enregistrer l’habitude" @submit="submit" @cancel="open = false">
            <template #default="{ step }">
                <template v-if="step.key === 'habit'">
                    <div>
                        <label class="label" for="h-name">Nom *</label>
                        <input id="h-name" v-model="form.name" class="input" required maxlength="120" placeholder="Boire un grand verre d’eau" autocomplete="off" />
                    </div>
                    <div>
                        <span class="label">Catégorie</span>
                        <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="Catégorie">
                            <button
                                v-for="(label, key) in CATEGORIES"
                                :key="key"
                                type="button"
                                role="radio"
                                :aria-checked="form.category === key"
                                class="chip"
                                :class="{ 'chip-on': form.category === key }"
                                @click="form.category = key"
                            >
                                {{ label }}
                            </button>
                        </div>
                    </div>
                </template>

                <template v-else-if="step.key === 'when'">
                    <fieldset>
                        <legend class="label">Jours</legend>
                        <div class="flex gap-1.5" role="group" aria-label="Jours de la semaine">
                            <button
                                v-for="d in WEEKDAYS"
                                :key="d.n"
                                type="button"
                                class="btn flex-1 !px-0 transition-colors"
                                :class="{ 'btn-primary': form.days.includes(d.n) }"
                                :aria-pressed="form.days.includes(d.n)"
                                :aria-label="d.name"
                                @click="toggleDay(d.n)"
                            >
                                {{ d.short }}
                            </button>
                        </div>
                        <div class="mt-2 flex gap-2">
                            <button type="button" class="btn btn-sm" @click="form.days = [1, 2, 3, 4, 5, 6, 7]">Tous les jours</button>
                            <button type="button" class="btn btn-sm" @click="form.days = [1, 2, 3, 4, 5]">Jours ouvrés</button>
                        </div>
                    </fieldset>
                    <div>
                        <label class="label" for="h-time">Heure <span class="muted">(facultatif)</span></label>
                        <input id="h-time" v-model="form.time" class="input" type="time" />
                    </div>
                </template>

                <template v-else>
                    <label class="flex min-h-11 items-center gap-3 rounded-xl border border-slate-200 px-3 dark:border-slate-700" :class="{ 'opacity-50': !form.time }">
                        <input v-model="form.reminder" type="checkbox" class="size-5 rounded" :disabled="!form.time" />
                        <span class="text-sm">Me le rappeler par notification{{ form.time ? '' : ' (indique d’abord une heure)' }}</span>
                    </label>
                    <div>
                        <label class="label" for="h-notes">Notes</label>
                        <textarea id="h-notes" v-model="form.notes" class="input" rows="2" maxlength="500" />
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-3 text-sm dark:bg-slate-800">
                        <div class="font-semibold">{{ form.name }}</div>
                        <div class="muted">{{ CATEGORIES[form.category] }} · {{ summary }}</div>
                    </div>
                </template>
            </template>
        </Wizard>
    </Sheet>
</template>
