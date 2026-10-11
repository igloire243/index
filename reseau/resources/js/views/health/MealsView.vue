<script setup>
import { computed, ref, watch } from 'vue';
import EmptyState from '../../components/EmptyState.vue';
import Icon from '../../components/Icon.vue';
import MealForm from '../../components/MealForm.vue';
import { addDays, fmtLong, fmtShort, isoDate, weekStart } from '../../lib/dates';
import { SLOTS } from '../../lib/health';
import { copyWeek, health, loadMeals, removeMeal, setMealDone } from '../../stores/health';
import { toast } from '../../stores/session';

const today = isoDate();
const anchor = ref(weekStart(today));
const days = computed(() => Array.from({ length: 7 }, (_, i) => addDays(anchor.value, i)));
const slotOrder = Object.keys(SLOTS);
const mealOpen = ref(false);
const mealDate = ref(null);
const openMeal = (d) => {
    mealDate.value = d;
    mealOpen.value = true;
};

watch(
    anchor,
    (a) => loadMeals(a, addDays(a, 6)).catch((e) => toast(e.message, 'error')),
    { immediate: true },
);

const mealsOf = (d) => health.meals.filter((m) => m.date === d).sort((a, b) => slotOrder.indexOf(a.slot) - slotOrder.indexOf(b.slot) || a.id - b.id);
const weekMeals = computed(() => health.meals.filter((m) => m.date >= anchor.value && m.date <= days.value[6]));
const summary = computed(() => ({ planned: weekMeals.value.length, done: weekMeals.value.filter((m) => m.done_at).length }));

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

        <EmptyState v-if="summary.planned === 0 && health.loaded" illustration="meals" title="Aucun repas planifié cette semaine" small>
            <p>Ajoute tes repas jour par jour, ou copie ceux de la semaine précédente.</p>
        </EmptyState>

        <button type="button" class="btn w-full" @click="copyPrevious">Copier la semaine précédente</button>

        <section v-for="(d, di) in days" :key="d + anchor" class="card rise space-y-2" :class="{ 'ring-2 ring-indigo-500/40': d === today }" :style="{ '--i': di }" :aria-label="fmtLong(d)">
            <h3 class="font-semibold first-letter:uppercase">{{ fmtLong(d) }}</h3>

            <TransitionGroup v-if="mealsOf(d).length" name="list" tag="ul" class="relative space-y-1.5">
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
                        <Transition name="pop"><Icon v-if="m.done_at" name="check" :size="16" /></Transition>
                    </button>
                    <span class="min-w-0 flex-1 text-sm" :class="{ 'line-through opacity-60': m.done_at }">
                        <span class="tag mr-1">{{ SLOTS[m.slot] }}</span>{{ m.description }}
                    </span>
                    <button type="button" class="btn btn-danger size-9 !p-0" :aria-label="`Supprimer « ${m.description} »`" @click="remove(m)"><Icon name="x" :size="16" /></button>
                </li>
            </TransitionGroup>

            <button type="button" class="btn btn-sm w-full" @click="openMeal(d)"><Icon name="plus" :size="16" /> Ajouter un repas</button>
        </section>

        <MealForm v-model="mealOpen" :date="mealDate" />
    </div>
</template>
