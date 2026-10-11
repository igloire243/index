<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import Icon from '../../components/Icon.vue';
import { addDays, fmtLong, isoDate } from '../../lib/dates';
import { CATEGORIES, DAY_PARTS, SLOTS, dayPart, dayProgress, dueHabits, logKey, streak } from '../../lib/health';
import { health, loadMeals, saveMeasurement, setMealDone, toggleHabit } from '../../stores/health';
import { toast } from '../../stores/session';

const today = isoDate();
const date = ref(today);
const isFuture = computed(() => date.value > today);

const due = computed(() => dueHabits(health.habits, date.value));
const progress = computed(() => dayProgress(health.habits, health.logs, date.value));
const groups = computed(() => DAY_PARTS.map((part) => ({ part, habits: due.value.filter((h) => dayPart(h) === part) })).filter((g) => g.habits.length));
const meals = computed(() => health.meals.filter((m) => m.date === date.value));
const remindersOff = computed(() => health.habits.some((h) => h.reminder) && 'Notification' in window && Notification.permission === 'default');

const isDone = (h) => health.logs.has(logKey(h.id, date.value));

async function toggle(h) {
    if (isFuture.value) return;
    try {
        await toggleHabit(h, date.value);
    } catch (e) {
        toast(e.message, 'error');
    }
}

async function toggleMeal(m) {
    try {
        await setMealDone(m, !m.done_at);
    } catch (e) {
        toast(e.message, 'error');
    }
}

watch(
    date,
    (d) => loadMeals(d, d).catch(() => {}),
    { immediate: true },
);

/* État du jour : poids, sommeil, énergie, humeur. */
const form = reactive({ weight_kg: '', sleep_hours: '', energy: null, mood: null });
const saving = ref(false);
const existing = computed(() => health.measurements.find((m) => m.date === date.value));
watch(
    [existing, date, () => health.loaded],
    () => {
        const m = existing.value;
        form.weight_kg = m?.weight_kg ?? '';
        form.sleep_hours = m?.sleep_hours ?? '';
        form.energy = m?.energy ?? null;
        form.mood = m?.mood ?? null;
    },
    { immediate: true },
);

async function saveState() {
    const data = {
        weight_kg: form.weight_kg === '' ? null : Number(form.weight_kg),
        sleep_hours: form.sleep_hours === '' ? null : Number(form.sleep_hours),
        energy: form.energy,
        mood: form.mood,
    };
    saving.value = true;
    try {
        await saveMeasurement(date.value, { ...data, waist_cm: existing.value?.waist_cm ?? null, notes: existing.value?.notes ?? null });
        toast('État enregistré.');
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <button type="button" class="btn size-11 !p-0" aria-label="Jour précédent" @click="date = addDays(date, -1)"><Icon name="left" /></button>
            <div class="text-center">
                <div class="font-semibold first-letter:uppercase">{{ fmtLong(date) }}</div>
                <button v-if="date !== today" type="button" class="text-xs text-indigo-600 underline dark:text-indigo-400" @click="date = today">Revenir à aujourd’hui</button>
            </div>
            <button type="button" class="btn size-11 !p-0" aria-label="Jour suivant" @click="date = addDays(date, 1)"><Icon name="right" /></button>
        </div>

        <RouterLink v-if="remindersOff" to="/reglages" class="card flex items-center gap-3 text-sm">
            <Icon name="bell" /> Active les rappels pour ne rien oublier.
        </RouterLink>

        <section class="card space-y-2" aria-label="Progression du jour">
            <div class="flex items-baseline justify-between">
                <h2 class="font-semibold">Ma journée</h2>
                <span class="muted">{{ progress.done }} / {{ progress.due }} fait{{ progress.done > 1 ? 's' : '' }}</span>
            </div>
            <div class="h-2.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800" role="progressbar" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="progress.due ? Math.round((progress.done / progress.due) * 100) : 0">
                <div class="h-full rounded-full bg-emerald-500 transition-all" :style="{ width: (progress.due ? (progress.done / progress.due) * 100 : 0) + '%' }" />
            </div>
            <p v-if="progress.due && progress.done === progress.due" class="text-sm text-emerald-700 dark:text-emerald-400">Journée complète, bravo !</p>
        </section>

        <section v-if="!health.habits.length && health.loaded" class="card text-center">
            <p class="mb-3">Tu n’as pas encore de programme.</p>
            <RouterLink to="/sante/programmes" class="btn btn-primary">Choisir un programme</RouterLink>
        </section>
        <p v-else-if="health.loaded && !due.length" class="muted text-center">Rien de prévu ce jour-là.</p>

        <section v-for="g in groups" :key="g.part" :aria-label="g.part">
            <h3 class="muted mb-2 font-semibold tracking-wide uppercase">{{ g.part }}</h3>
            <ul class="space-y-2">
                <li v-for="h in g.habits" :key="h.id">
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 rounded-2xl border p-3 text-left transition active:scale-[.99]"
                        :class="isDone(h) ? 'border-emerald-300 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950/40' : 'border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900'"
                        :aria-pressed="isDone(h)"
                        :disabled="isFuture"
                        @click="toggle(h)"
                    >
                        <span
                            class="grid size-8 shrink-0 place-items-center rounded-full border-2"
                            :class="isDone(h) ? 'border-emerald-500 bg-emerald-500 text-white' : 'border-slate-300 dark:border-slate-600'"
                        >
                            <Icon v-if="isDone(h)" name="check" :size="18" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block font-medium" :class="{ 'line-through opacity-60': isDone(h) }">{{ h.name }}</span>
                            <span class="muted block">
                                {{ CATEGORIES[h.category] }}<template v-if="h.time"> · {{ h.time }}</template><template v-if="h.reminder"> · rappel</template>
                            </span>
                        </span>
                        <span v-if="streak(h, health.logs, today) >= 2" class="flex shrink-0 items-center gap-0.5 text-sm font-semibold text-orange-600 dark:text-orange-400" :title="`Série de ${streak(h, health.logs, today)} jours`">
                            <Icon name="flame" :size="16" />{{ streak(h, health.logs, today) }}
                        </span>
                    </button>
                </li>
            </ul>
        </section>

        <section v-if="meals.length" aria-label="Repas du jour">
            <h3 class="muted mb-2 font-semibold tracking-wide uppercase">Repas prévus</h3>
            <ul class="space-y-2">
                <li v-for="m in meals" :key="m.id">
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 rounded-2xl border p-3 text-left"
                        :class="m.done_at ? 'border-emerald-300 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950/40' : 'border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900'"
                        :aria-pressed="!!m.done_at"
                        :disabled="isFuture"
                        @click="toggleMeal(m)"
                    >
                        <span class="grid size-8 shrink-0 place-items-center rounded-full border-2" :class="m.done_at ? 'border-emerald-500 bg-emerald-500 text-white' : 'border-slate-300 dark:border-slate-600'">
                            <Icon v-if="m.done_at" name="check" :size="18" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block font-medium" :class="{ 'line-through opacity-60': m.done_at }">{{ m.description }}</span>
                            <span class="muted block">{{ SLOTS[m.slot] }}</span>
                        </span>
                    </button>
                </li>
            </ul>
        </section>
        <RouterLink v-else to="/sante/repas" class="muted block text-center underline">Planifier mes repas</RouterLink>

        <section class="card space-y-3" aria-label="Mon état du jour">
            <h2 class="font-semibold">Mon état</h2>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label" for="w">Poids (kg)</label>
                    <input id="w" v-model="form.weight_kg" class="input" type="number" inputmode="decimal" step="0.1" min="20" max="400" />
                </div>
                <div>
                    <label class="label" for="s">Sommeil (h)</label>
                    <input id="s" v-model="form.sleep_hours" class="input" type="number" inputmode="decimal" step="0.5" min="0" max="24" />
                </div>
            </div>
            <fieldset v-for="f in [{ key: 'energy', label: 'Énergie' }, { key: 'mood', label: 'Humeur' }]" :key="f.key">
                <legend class="label">{{ f.label }}</legend>
                <div class="flex gap-2" role="radiogroup" :aria-label="f.label">
                    <button
                        v-for="n in 5"
                        :key="n"
                        type="button"
                        role="radio"
                        :aria-checked="form[f.key] === n"
                        class="btn flex-1 !px-0"
                        :class="{ 'btn-primary': form[f.key] === n }"
                        @click="form[f.key] = form[f.key] === n ? null : n"
                    >
                        {{ n }}
                    </button>
                </div>
            </fieldset>
            <button type="button" class="btn btn-primary w-full" :disabled="saving" @click="saveState">Enregistrer</button>
        </section>
    </div>
</template>
