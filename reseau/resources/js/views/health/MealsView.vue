<script setup>
import { computed, reactive, ref, watch } from 'vue';
import Icon from '../../components/Icon.vue';
import { addDays, fmtLong, fmtShort, isoDate, weekStart } from '../../lib/dates';
import { SLOTS } from '../../lib/health';
import { addMeal, copyWeek, health, loadMeals, removeMeal, setMealDone } from '../../stores/health';
import { toast } from '../../stores/session';

const today = isoDate();
const anchor = ref(weekStart(today));
const days = computed(() => Array.from({ length: 7 }, (_, i) => addDays(anchor.value, i)));
const slotOrder = Object.keys(SLOTS);
const drafts = reactive({});

watch(
    anchor,
    (a) => loadMeals(a, addDays(a, 6)).catch((e) => toast(e.message, 'error')),
    { immediate: true },
);

const draft = (d) => (drafts[d] ??= { slot: 'lunch', description: '' });
const mealsOf = (d) => health.meals.filter((m) => m.date === d).sort((a, b) => slotOrder.indexOf(a.slot) - slotOrder.indexOf(b.slot) || a.id - b.id);
const weekMeals = computed(() => health.meals.filter((m) => m.date >= anchor.value && m.date <= days.value[6]));
const summary = computed(() => ({ planned: weekMeals.value.length, done: weekMeals.value.filter((m) => m.done_at).length }));

async function add(d) {
    const { slot, description } = draft(d);
    if (!description.trim()) return;
    try {
        await addMeal({ date: d, slot, description: description.trim() });
        draft(d).description = '';
    } catch (e) {
        toast(e.message, 'error');
    }
}

async function toggle(m) {
    if (m.date > today) return;
    try {
        await setMealDone(m, !m.done_at);
    } catch (e) {
        toast(e.message, 'error');
    }
}

async function remove(m) {
    try {
        await removeMeal(m.id);
    } catch (e) {
        toast(e.message, 'error');
    }
}

async function copyPrevious() {
    try {
        const { created } = await copyWeek(addDays(anchor.value, -7), anchor.value);
        toast(created ? `${created} repas copiés depuis la semaine précédente.` : 'Rien à copier (semaine précédente vide ou déjà copiée).');
    } catch (e) {
        toast(e.message, 'error');
    }
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <button type="button" class="btn size-11 !p-0" aria-label="Semaine précédente" @click="anchor = addDays(anchor, -7)"><Icon name="left" /></button>
            <div class="text-center">
                <div class="font-semibold">Semaine du {{ fmtShort(days[0]) }} au {{ fmtShort(days[6]) }}</div>
                <div class="muted">{{ summary.done }} / {{ summary.planned }} repas faits</div>
            </div>
            <button type="button" class="btn size-11 !p-0" aria-label="Semaine suivante" @click="anchor = addDays(anchor, 7)"><Icon name="right" /></button>
        </div>

        <button type="button" class="btn w-full" @click="copyPrevious">Copier la semaine précédente</button>

        <section v-for="d in days" :key="d" class="card space-y-2" :class="{ 'ring-2 ring-indigo-500/40': d === today }" :aria-label="fmtLong(d)">
            <h3 class="font-semibold first-letter:uppercase">{{ fmtLong(d) }}</h3>

            <ul v-if="mealsOf(d).length" class="space-y-1.5">
                <li v-for="m in mealsOf(d)" :key="m.id" class="flex items-center gap-2">
                    <button
                        type="button"
                        class="grid size-8 shrink-0 place-items-center rounded-full border-2"
                        :class="m.done_at ? 'border-emerald-500 bg-emerald-500 text-white' : 'border-slate-300 dark:border-slate-600'"
                        :aria-pressed="!!m.done_at"
                        :aria-label="`Marquer « ${m.description} » comme fait`"
                        :disabled="d > today"
                        @click="toggle(m)"
                    >
                        <Icon v-if="m.done_at" name="check" :size="16" />
                    </button>
                    <span class="min-w-0 flex-1 text-sm" :class="{ 'line-through opacity-60': m.done_at }">
                        <span class="tag mr-1">{{ SLOTS[m.slot] }}</span>{{ m.description }}
                    </span>
                    <button type="button" class="btn btn-danger size-9 !p-0" :aria-label="`Supprimer « ${m.description} »`" @click="remove(m)"><Icon name="x" :size="16" /></button>
                </li>
            </ul>

            <form class="flex gap-2" @submit.prevent="add(d)">
                <select v-model="draft(d).slot" class="input !w-auto shrink-0 !px-2 text-sm" :aria-label="`Repas du ${fmtLong(d)}`">
                    <option v-for="(label, key) in SLOTS" :key="key" :value="key">{{ label }}</option>
                </select>
                <input v-model="draft(d).description" class="input min-w-0" maxlength="200" placeholder="Que vas-tu manger ?" :aria-label="`Description du repas, ${fmtLong(d)}`" />
                <button class="btn btn-primary size-11 shrink-0 !p-0" aria-label="Ajouter le repas"><Icon name="plus" /></button>
            </form>
        </section>
    </div>
</template>
