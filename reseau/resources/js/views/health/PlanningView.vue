<script setup>
import { computed, ref } from 'vue';
import { RouterLink } from 'vue-router';
import HabitForm from '../../components/HabitForm.vue';
import Icon from '../../components/Icon.vue';
import { WEEKDAYS, addDays, fmtShort, isoDate, parseISO, weekStart } from '../../lib/dates';
import { CATEGORIES, isDue, logKey } from '../../lib/health';
import { archiveHabit, health, toggleHabit } from '../../stores/health';
import { toast } from '../../stores/session';

const STATE_LABELS = { off: 'non prévu', done: 'fait', todo: 'à faire', future: 'à venir' };
const today = isoDate();
const anchor = ref(weekStart(today));
const days = computed(() => Array.from({ length: 7 }, (_, i) => addDays(anchor.value, i)));
const habits = computed(() => [...health.habits].sort((a, b) => (a.time || '99:99').localeCompare(b.time || '99:99') || a.name.localeCompare(b.name, 'fr')));

const formOpen = ref(false);
const editing = ref(null);
const edit = (h = null) => {
    editing.value = h;
    formOpen.value = true;
};

function cell(h, d) {
    if (!isDue(h, d)) return 'off';
    if (health.logs.has(logKey(h.id, d))) return 'done';
    return d > today ? 'future' : 'todo';
}

async function toggle(h, d) {
    if (cell(h, d) === 'off' || d > today) return;
    try {
        await toggleHabit(h, d);
    } catch (e) {
        toast(e.message, 'error');
    }
}

async function archive(h) {
    if (!confirm(`Retirer « ${h.name} » du planning ? L’historique est conservé.`)) return;
    try {
        await archiveHabit(h.id);
        toast('Habitude retirée.');
    } catch (e) {
        toast(e.message, 'error');
    }
}

const dayLabel = (d) => parseISO(d).getDate();
const weekLabel = computed(() => `${fmtShort(days.value[0])} – ${fmtShort(days.value[6])}`);
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <button type="button" class="btn size-11 !p-0" aria-label="Semaine précédente" @click="anchor = addDays(anchor, -7)"><Icon name="left" /></button>
            <div class="text-center">
                <div class="font-semibold">Semaine du {{ weekLabel }}</div>
                <button v-if="anchor !== weekStart(today)" type="button" class="text-xs text-indigo-600 underline dark:text-indigo-400" @click="anchor = weekStart(today)">Cette semaine</button>
            </div>
            <button type="button" class="btn size-11 !p-0" aria-label="Semaine suivante" @click="anchor = addDays(anchor, 7)"><Icon name="right" /></button>
        </div>

        <div v-if="habits.length" class="card overflow-x-auto !p-2">
            <table class="w-full border-separate border-spacing-y-1 text-sm">
                <thead>
                    <tr>
                        <th class="sr-only">Habitude</th>
                        <th v-for="(d, i) in days" :key="d" class="w-9 px-0.5 text-center text-xs font-medium" :class="d === today ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-500'">
                            {{ WEEKDAYS[i].short }}<br />{{ dayLabel(d) }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="h in habits" :key="h.id">
                        <th scope="row" class="max-w-40 min-w-28 pr-2 text-left font-normal">
                            <span class="line-clamp-2 leading-tight">{{ h.name }}</span>
                        </th>
                        <td v-for="d in days" :key="d" class="text-center">
                            <button
                                type="button"
                                class="mx-auto grid size-8 place-items-center rounded-full"
                                :class="{
                                    'bg-emerald-500 text-white': cell(h, d) === 'done',
                                    'border-2 border-slate-300 dark:border-slate-600': cell(h, d) === 'todo',
                                    'border border-dashed border-slate-300 dark:border-slate-700': cell(h, d) === 'future',
                                    'cursor-default': cell(h, d) === 'off' || cell(h, d) === 'future',
                                }"
                                :aria-label="`${h.name}, ${fmtShort(d)} : ${STATE_LABELS[cell(h, d)]}`"
                                :aria-pressed="cell(h, d) === 'done'"
                                :disabled="cell(h, d) === 'off' || cell(h, d) === 'future'"
                                @click="toggle(h, d)"
                            >
                                <Transition name="pop"><Icon v-if="cell(h, d) === 'done'" name="check" :size="16" /></Transition>
                                <span v-if="cell(h, d) === 'off'" class="size-1 rounded-full bg-slate-300 dark:bg-slate-700" />
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex items-center justify-between">
            <h2 class="font-semibold">Mes habitudes</h2>
            <button type="button" class="btn btn-primary btn-sm" @click="edit()"><Icon name="plus" :size="16" /> Ajouter</button>
        </div>

        <div v-if="!habits.length && health.loaded" class="card text-center">
            <p class="mb-3">Ton planning est vide.</p>
            <RouterLink to="/sante/programmes" class="btn btn-primary">Choisir un programme</RouterLink>
        </div>

        <TransitionGroup name="list" tag="ul" class="relative space-y-2">
            <li v-for="h in habits" :key="h.id" class="card flex items-center gap-3 !p-3">
                <div class="min-w-0 flex-1">
                    <div class="font-medium break-words">{{ h.name }}</div>
                    <div class="muted">
                        {{ CATEGORIES[h.category] }} · {{ h.days.length === 7 ? 'tous les jours' : h.days.map((n) => WEEKDAYS[n - 1].name.slice(0, 3)).join(', ') }}<template v-if="h.time">
                            · {{ h.time }}</template
                        ><template v-if="h.reminder"> · rappel</template>
                    </div>
                </div>
                <button type="button" class="btn size-10 !p-0" aria-label="Modifier" @click="edit(h)"><Icon name="edit" :size="18" /></button>
                <button type="button" class="btn btn-danger size-10 !p-0" aria-label="Retirer" @click="archive(h)"><Icon name="trash" :size="18" /></button>
            </li>
        </TransitionGroup>

        <RouterLink to="/sante/programmes" class="muted block text-center underline">Choisir un programme prêt à l’emploi</RouterLink>

        <HabitForm v-model="formOpen" :habit="editing" />
    </div>
</template>
