<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { WEEKDAYS } from '../../lib/dates';
import { CATEGORIES, GOALS } from '../../lib/health';
import { adoptProgram, health, loadPrograms } from '../../stores/health';
import { toast } from '../../stores/session';

const router = useRouter();
const busy = ref(null);
const open = ref(null);

onMounted(() => loadPrograms().catch((e) => toast(e.message, 'error')));

const adopted = computed(() => {
    const map = {};
    for (const h of health.habits) if (h.program_key) (map[h.program_key] ??= new Set()).add(h.name);
    return map;
});
const remaining = (p) => p.habits.filter((h) => !adopted.value[p.key]?.has(h.name)).length;
const freq = (days) => (days.length === 7 ? 'tous les jours' : days.map((n) => WEEKDAYS[n - 1].name.slice(0, 3)).join(', '));

async function adopt(p) {
    busy.value = p.key;
    try {
        const { created } = await adoptProgram(p.key);
        toast(created ? `${created} habitude${created > 1 ? 's' : ''} ajoutée${created > 1 ? 's' : ''} à ton planning.` : 'Ce programme est déjà dans ton planning.');
        if (created) router.push('/sante/planning');
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        busy.value = null;
    }
}
</script>

<template>
    <div class="space-y-3">
        <p class="muted">Choisis un programme : ses habitudes sont ajoutées à ton planning, et tu peux ensuite les modifier librement.</p>

        <article v-for="p in health.programs" :key="p.key" class="card space-y-2">
            <div class="flex items-start justify-between gap-2">
                <h2 class="font-semibold">{{ p.name }}</h2>
                <span class="tag shrink-0">{{ GOALS[p.goal] }}</span>
            </div>
            <p class="text-sm">{{ p.summary }}</p>

            <button type="button" class="text-sm text-indigo-600 underline dark:text-indigo-400" :aria-expanded="open === p.key" @click="open = open === p.key ? null : p.key">
                {{ open === p.key ? 'Masquer' : 'Voir' }} les {{ p.habits.length }} habitudes
            </button>
            <ul v-if="open === p.key" class="space-y-1.5 text-sm">
                <li v-for="h in p.habits" :key="h.name" class="rounded-xl bg-slate-50 px-3 py-2 dark:bg-slate-800">
                    <div class="font-medium">{{ h.name }}</div>
                    <div class="muted">{{ CATEGORIES[h.category] }} · {{ freq(h.days) }}<template v-if="h.time"> · {{ h.time }}</template></div>
                    <div v-if="h.notes" class="muted mt-0.5">{{ h.notes }}</div>
                </li>
            </ul>

            <button type="button" class="btn btn-primary w-full" :disabled="busy === p.key || remaining(p) === 0" @click="adopt(p)">
                {{ remaining(p) === 0 ? 'Déjà dans mon planning' : remaining(p) < p.habits.length ? `Ajouter les ${remaining(p)} habitudes manquantes` : 'Adopter ce programme' }}
            </button>
        </article>
    </div>
</template>
