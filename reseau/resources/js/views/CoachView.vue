<script setup>
import { computed, onMounted, ref } from 'vue';
import EmptyState from '../components/EmptyState.vue';
import Icon from '../components/Icon.vue';
import Meter from '../components/Meter.vue';
import { buildSuggestions, contactMeters, monthlyNetworkProgress, seededRandom, TRAITS } from '../lib/coach';
import { isoDate } from '../lib/dates';
import { projectToNudge } from '../lib/projects';
import { useRouter } from 'vue-router';
import { contacts, loadContacts, openDetail, openForm, saveContact } from '../stores/contacts';
import { finance, loadFinance } from '../stores/finance';
import { loadProjects, projects } from '../stores/projects';
import { session, setNetworkGoal, toast } from '../stores/session';

const router = useRouter();
const HIDDEN_KEY = 'reseau.coach.hidden';
const hidden = ref(readHidden());
const showAll = ref(false);

function readHidden() {
    try {
        const raw = JSON.parse(localStorage.getItem(HIDDEN_KEY) || '{}');
        return Object.fromEntries(Object.entries(raw).filter(([, until]) => until > Date.now()));
    } catch {
        return {};
    }
}

function hide(id, days) {
    hidden.value = { ...hidden.value, [id]: Date.now() + days * 86400000 };
    try {
        localStorage.setItem(HIDDEN_KEY, JSON.stringify(hidden.value));
    } catch {
        /* ignoré */
    }
}

onMounted(() => {
    loadContacts().catch((e) => toast(e.message, 'error'));
    loadFinance().catch(() => {}); // facultatif : sans finances, le Coach ne parle que des contacts
    loadProjects().catch(() => {});
});

const nudge = computed(() => (projects.loaded ? projectToNudge(projects.list) : null));
const nudgeText = { echeance: 'arrive à échéance bientôt', retard: 'a dépassé son échéance', stagnation: 'n’a pas avancé depuis un moment' };

const all = computed(() => (contacts.loaded ? buildSuggestions(contacts.list, { pos: contacts.pos, hidden: hidden.value, debts: finance.debts }) : []));
const shown = computed(() => (showAll.value ? all.value : all.value.slice(0, 5)));
const meters = computed(() => contactMeters(contacts.list));

/* Objectif mensuel */
const editingGoal = ref(false);
const goalInput = ref('');
const goal = computed(() => session.user?.network_goal_monthly ?? null);
const progress = computed(() => monthlyNetworkProgress(contacts.list));
const monthLabel = computed(() => new Date(progress.value.month + '-01').toLocaleDateString('fr-FR', { month: 'long' }));
// Élision : « d'octobre », pas « de octobre ».
const monthLabelPrefixed = computed(() => (/^[aeiouh]/i.test(monthLabel.value) ? `d’${monthLabel.value}` : `de ${monthLabel.value}`));

function startEditGoal() {
    goalInput.value = goal.value ?? '';
    editingGoal.value = true;
}
async function saveGoal() {
    const n = goalInput.value === '' ? null : Number(goalInput.value);
    try {
        await setNetworkGoal(n);
        editingGoal.value = false;
    } catch (e) {
        toast(e.message, 'error');
    }
}

// Une anecdote différente chaque jour, à s'entraîner à raconter.
const anecdote = computed(() => {
    const list = contacts.list.filter((c) => c.anecdote);
    return list.length ? list[Math.floor(seededRandom(isoDate() + 'a')() * list.length)] : null;
});

function run(action) {
    if (action.type === 'route') router.push(action.to);
    else if (action.type === 'detail') openDetail(action.id);
    else if (action.type === 'edit') openForm(action.id);
    else openForm(null, action.domains ? { domains: action.domains } : null);
}

async function done(s) {
    try {
        const c = s.touch && contacts.list.find((x) => x.id === s.contactId);
        if (c) await saveContact({ ...c, last_contact: isoDate() }, c.id);
        hide(s.id, 14);
        toast('Bien joué.');
    } catch (e) {
        toast(e.message, 'error');
    }
}
</script>

<template>
    <div class="space-y-3">
        <section class="card space-y-2" aria-label="Objectif du mois">
            <div class="flex items-center justify-between gap-2">
                <h2 class="font-semibold">Objectif {{ monthLabelPrefixed }}</h2>
                <button type="button" class="btn btn-sm" @click="startEditGoal">{{ goal ? 'Modifier' : 'Définir un objectif' }}</button>
            </div>
            <Meter
                v-if="goal"
                label="Contacts relancés"
                :value="`${Math.min(progress.count, goal)} / ${goal}`"
                :ratio="progress.count / goal"
                :tone="progress.count >= goal ? 'emerald' : 'indigo'"
            />
            <p v-else class="muted">Fixe-toi un nombre de contacts à relancer chaque mois, pour garder le rythme.</p>
            <p v-if="goal && progress.count >= goal" class="text-sm text-emerald-700 dark:text-emerald-400">Objectif atteint, bravo !</p>

            <form v-if="editingGoal" class="flex items-end gap-2 pt-1" @submit.prevent="saveGoal">
                <div class="flex-1">
                    <label class="label" for="coach-goal">Contacts à relancer par mois</label>
                    <input id="coach-goal" v-model="goalInput" class="input" type="number" inputmode="numeric" min="1" max="200" placeholder="ex. 5" />
                </div>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
                <button v-if="goal" type="button" class="btn btn-danger" @click="(goalInput = ''), saveGoal()">Retirer</button>
            </form>
        </section>

        <RouterLink v-if="nudge" :to="`/coach/projets/${nudge.project.id}`" class="card rise flex items-center gap-3 transition-transform active:scale-[.99]" :class="{ '!bg-amber-50 dark:!bg-amber-950/40': nudge.reason === 'retard' }">
            <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950 dark:text-indigo-300"><Icon name="target" /></span>
            <span class="min-w-0 flex-1">
                <span class="tag mb-1 inline-block">Projet</span>
                <span class="block font-semibold break-words">{{ nudge.project.title }}</span>
                <span class="muted block">{{ nudgeText[nudge.reason] }}</span>
            </span>
        </RouterLink>

        <h2 class="text-base font-semibold">À faire aujourd’hui</h2>
        <EmptyState v-if="contacts.loaded && !all.length" illustration="coach" title="Rien d’urgent" small>
            <p>Ton réseau est bien entretenu, beau travail.</p>
        </EmptyState>

        <article v-for="(s, i) in shown" :key="s.id" class="card rise space-y-1.5" :style="{ '--i': Math.min(i, 8) }">
            <span
                class="tag"
                :class="{
                    '!bg-indigo-100 dark:!bg-indigo-950': s.trait === 'reseau',
                    '!bg-amber-100 dark:!bg-amber-950': s.trait === 'anecdote',
                    '!bg-emerald-100 dark:!bg-emerald-950': s.trait === 'savoir',
                    '!bg-pink-100 dark:!bg-pink-950': s.trait === 'imprevu',
                    '!bg-sky-100 dark:!bg-sky-950': s.trait === 'finance',
                }"
                >{{ TRAITS[s.trait] }}</span
            >
            <h3 class="font-semibold break-words">{{ s.title }}</h3>
            <p class="text-sm">{{ s.text }}</p>
            <div class="flex flex-wrap gap-2 pt-1">
                <button v-if="s.action" type="button" class="btn btn-primary btn-sm" @click="run(s.action)">{{ s.action.label }}</button>
                <button type="button" class="btn btn-sm" @click="done(s)">Fait</button>
                <button type="button" class="btn btn-sm" @click="hide(s.id, 3)">Passer</button>
            </div>
        </article>

        <button v-if="all.length > 5" type="button" class="btn w-full" @click="showAll = !showAll">
            {{ showAll ? 'Voir moins' : `Voir les ${all.length - 5} autres` }}
        </button>

        <article v-if="anecdote" class="card space-y-1.5">
            <span class="tag !bg-amber-100 dark:!bg-amber-950">Anecdote du jour</span>
            <h3 class="font-semibold">{{ anecdote.name }}</h3>
            <p class="text-sm whitespace-pre-wrap">{{ anecdote.anecdote }}</p>
            <p class="muted">Entraîne-toi : raconte-la à voix haute en 30 secondes, avec une chute.</p>
        </article>

        <h2 class="pt-2 text-base font-semibold">Ton niveau</h2>
        <div class="card space-y-4">
            <Meter v-for="m in meters" :key="m.label" :label="m.label" :value="m.value" :ratio="m.ratio" />
        </div>
    </div>
</template>
