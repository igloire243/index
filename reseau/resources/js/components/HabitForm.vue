<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { WEEKDAYS } from '../lib/dates';
import { CATEGORIES } from '../lib/health';
import { saveHabit } from '../stores/health';
import { toast } from '../stores/session';
import Sheet from './Sheet.vue';

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

const toggleDay = (n) => {
    form.days = form.days.includes(n) ? form.days.filter((d) => d !== n) : [...form.days, n].sort();
};

async function submit() {
    if (!form.days.length) return toast('Choisis au moins un jour.', 'error');
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
        <form class="space-y-3" @submit.prevent="submit">
            <div>
                <label class="label" for="h-name">Nom *</label>
                <input id="h-name" v-model="form.name" class="input" required maxlength="120" placeholder="Boire un grand verre d’eau" />
            </div>
            <div>
                <label class="label" for="h-cat">Catégorie</label>
                <select id="h-cat" v-model="form.category" class="input">
                    <option v-for="(label, key) in CATEGORIES" :key="key" :value="key">{{ label }}</option>
                </select>
            </div>
            <fieldset>
                <legend class="label">Jours</legend>
                <div class="flex gap-1.5" role="group" aria-label="Jours de la semaine">
                    <button
                        v-for="d in WEEKDAYS"
                        :key="d.n"
                        type="button"
                        class="btn flex-1 !px-0"
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
            <div class="grid grid-cols-2 items-end gap-3">
                <div>
                    <label class="label" for="h-time">Heure</label>
                    <input id="h-time" v-model="form.time" class="input" type="time" />
                </div>
                <label class="flex min-h-11 items-center gap-2 text-sm" :class="{ 'opacity-50': !form.time }">
                    <input v-model="form.reminder" type="checkbox" class="size-5 rounded" :disabled="!form.time" />
                    Me le rappeler
                </label>
            </div>
            <div>
                <label class="label" for="h-notes">Notes</label>
                <textarea id="h-notes" v-model="form.notes" class="input" rows="2" maxlength="500" />
            </div>
            <div class="flex justify-end gap-2 pt-1">
                <button type="button" class="btn" @click="open = false">Annuler</button>
                <button type="submit" class="btn btn-primary" :disabled="busy">Enregistrer</button>
            </div>
        </form>
    </Sheet>
</template>
