<script setup>
import { computed, onMounted, ref } from 'vue';
import EmptyState from '../components/EmptyState.vue';
import Icon from '../components/Icon.vue';
import LifeGoalForm from '../components/LifeGoalForm.vue';
import LifeGoalShareSheet from '../components/LifeGoalShareSheet.vue';
import { DOMAINS, filterGoals, groupByBracket, lifeGoalStats, nextStatus, STATUSES } from '../lib/lifeGoals';
import { progress, progressRatio } from '../lib/projects';
import { lifeGoals, loadLifeGoals, removeLifeGoal, saveLifeGoal, seedLifeGoals } from '../stores/lifeGoals';
import { toast } from '../stores/session';

const domain = ref('');
const formOpen = ref(false);
const shareOpen = ref(false);
const editing = ref(null);
const seeding = ref(false);

onMounted(() => loadLifeGoals().catch((e) => toast(e.message, 'error')));

const stats = computed(() => lifeGoalStats(lifeGoals.list));
const presentDomains = computed(() => Object.keys(DOMAINS).filter((d) => lifeGoals.list.some((g) => g.domain === d)));
const groups = computed(() => groupByBracket(filterGoals(lifeGoals.list, { domain: domain.value })).filter((g) => g.items.length));

const edit = (g = null) => {
    editing.value = g;
    formOpen.value = true;
};

async function cycle(g) {
    try {
        await saveLifeGoal({ status: nextStatus(g.status) }, g.id);
    } catch (e) {
        toast(e.message, 'error');
    }
}

async function remove(g) {
    if (!confirm(`Retirer « ${g.title} » de ta liste ?`)) return;
    try {
        await removeLifeGoal(g.id);
    } catch (e) {
        toast(e.message, 'error');
    }
}

async function loadDefaults() {
    seeding.value = true;
    try {
        await seedLifeGoals();
        toast('Liste de départ chargée.');
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        seeding.value = false;
    }
}

const statusIcon = { none: 'x', planned: 'calendar', done: 'check' };
const statusClass = { none: 'border-slate-300 text-slate-400 dark:border-slate-600', planned: 'border-amber-500 bg-amber-50 text-amber-600 dark:bg-amber-950', done: 'border-emerald-600 bg-emerald-600 text-white' };
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between gap-2">
            <div>
                <h1 class="text-xl font-bold">Accomplissements</h1>
                <p class="muted text-sm">{{ stats.done }} fait{{ stats.done > 1 ? 's' : '' }} · {{ stats.planned }} prévu{{ stats.planned > 1 ? 's' : '' }} · {{ stats.total }} au total</p>
            </div>
            <span class="flex shrink-0 gap-2">
                <button type="button" class="btn btn-sm" @click="shareOpen = true"><Icon name="share" :size="16" /></button>
                <button type="button" class="btn btn-primary btn-sm" @click="edit()"><Icon name="plus" :size="16" /> Ajouter</button>
            </span>
        </div>

        <EmptyState v-if="lifeGoals.loaded && !lifeGoals.list.length" illustration="coach" title="Ta liste est vide">
            <p>Pars d’une liste de départ (jalons de vie, en RDC et ailleurs), que tu pourras ensuite adapter à ta façon — retirer ce qui ne te parle pas, ajouter tes propres objectifs.</p>
            <template #actions>
                <button type="button" class="btn btn-primary" :disabled="seeding" @click="loadDefaults">{{ seeding ? 'Chargement…' : 'Charger la liste de départ' }}</button>
            </template>
        </EmptyState>

        <template v-else-if="lifeGoals.loaded">
            <div v-if="presentDomains.length > 1" class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1" role="group" aria-label="Filtrer par domaine">
                <button type="button" class="chip" :class="{ 'chip-on': !domain }" @click="domain = ''">Tout</button>
                <button v-for="d in presentDomains" :key="d" type="button" class="chip" :class="{ 'chip-on': domain === d }" @click="domain = domain === d ? '' : d">{{ DOMAINS[d] }}</button>
            </div>

            <button type="button" class="muted text-xs underline" :disabled="seeding" @click="loadDefaults">Recharger la liste de départ (ajoute ce qui manque, y compris ce que tu as retiré)</button>

            <section v-for="g in groups" :key="g.bracket" class="space-y-2">
                <h2 class="font-semibold">{{ g.label }}</h2>
                <ul class="space-y-2">
                    <li v-for="item in g.items" :key="item.id" class="card flex items-center gap-3 py-2.5">
                        <button
                            type="button" class="grid size-8 shrink-0 place-items-center rounded-full border-2 transition-colors" :class="statusClass[item.status]"
                            :aria-label="`Statut : ${STATUSES[item.status]} — changer`" :title="STATUSES[item.status]" @click="cycle(item)"
                        >
                            <Icon :name="statusIcon[item.status]" :size="14" />
                        </button>
                        <button type="button" class="min-w-0 flex-1 text-left" @click="edit(item)">
                            <span class="block truncate font-medium" :class="{ 'text-slate-400 line-through dark:text-slate-500': item.status === 'done' }">{{ item.title }}</span>
                            <span class="muted block truncate text-xs">
                                {{ DOMAINS[item.domain] }}
                                <template v-if="item.project"> · {{ item.project.title }}<template v-if="progress(item.project)"> ({{ progress(item.project).done }}/{{ progress(item.project).total }})</template></template>
                                <template v-if="item.status === 'planned' && item.target_date"> · prévu pour {{ new Date(item.target_date).toLocaleDateString('fr-FR', { month: 'short', year: 'numeric' }) }}</template>
                            </span>
                            <span v-if="item.project && progress(item.project)" class="mt-1 block h-1 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                <span class="block h-full rounded-full bg-indigo-500" :style="{ width: progressRatio(item.project) * 100 + '%' }" />
                            </span>
                        </button>
                        <button type="button" class="muted shrink-0" aria-label="Retirer" @click="remove(item)"><Icon name="x" :size="16" /></button>
                    </li>
                </ul>
            </section>
        </template>

        <div v-else class="muted py-10 text-center">Chargement…</div>

        <LifeGoalForm v-model="formOpen" :goal="editing" />
        <LifeGoalShareSheet v-model="shareOpen" />
    </div>
</template>
