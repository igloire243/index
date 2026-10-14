<script setup>
import { computed, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import EmptyState from '../components/EmptyState.vue';
import Icon from '../components/Icon.vue';
import Illustration from '../components/Illustration.vue';
import { AGE_BRACKETS, answerLifeGoal, DOMAINS, fetchPublicLifeGoals, groupByBracket, nextStatus, setRespondentName, STATUSES } from '../lib/lifeGoals';

const route = useRoute();
const token = route.params.token;

const state = ref('chargement'); // chargement | pret | erreur
const error = ref('');
const from = ref('');
const goals = ref([]);
const myAnswers = reactive({}); // { [life_goal_id]: status }
const name = ref('');
const savingName = ref(false);
const nameSaved = ref(false);

async function load() {
    try {
        const data = await fetchPublicLifeGoals(token);
        from.value = data.from;
        goals.value = data.goals;
        name.value = data.respondent_name ?? '';
        for (const a of data.answers) myAnswers[a.life_goal_id] = a.status;
        state.value = 'pret';
    } catch (e) {
        error.value = e.status === 404 ? 'Ce lien n’existe pas ou plus.' : e.message;
        state.value = 'erreur';
    }
}
load();

const groups = computed(() => groupByBracket(goals.value).filter((g) => g.items.length));

async function saveName() {
    savingName.value = true;
    try {
        await setRespondentName(token, name.value.trim() || null);
        nameSaved.value = true;
    } catch {
        /* pas bloquant : la personne peut continuer sans nom */
    } finally {
        savingName.value = false;
    }
}

async function cycle(goal) {
    const next = nextStatus(myAnswers[goal.id] ?? 'none');
    myAnswers[goal.id] = next; // optimiste : réagit tout de suite au doigt
    try {
        await answerLifeGoal(token, goal.id, { status: next });
    } catch {
        myAnswers[goal.id] = nextStatus(nextStatus(next)); // revient en arrière (cycle inverse) si l’envoi échoue
    }
}

const statusIcon = { none: 'x', planned: 'calendar', done: 'check' };
const statusClass = { none: 'border-slate-300 text-slate-400 dark:border-slate-600', planned: 'border-amber-500 bg-amber-50 text-amber-600 dark:bg-amber-950', done: 'border-emerald-600 bg-emerald-600 text-white' };
</script>

<template>
    <main class="mx-auto min-h-dvh max-w-sm px-6 py-10">
        <div v-if="state === 'chargement'" class="muted pt-10 text-center">Chargement…</div>

        <EmptyState v-else-if="state === 'erreur'" illustration="coach" title="Lien indisponible">
            <p>{{ error }}</p>
        </EmptyState>

        <div v-else class="space-y-5">
            <div class="text-center">
                <Illustration illustration="coach" class="mx-auto max-w-40" />
                <h1 class="mt-2 text-lg font-semibold">Accomplissements de {{ from }}</h1>
                <p class="muted text-sm">Pour chaque ligne, coche où tu en es, toi : fait, prévu, ou jamais pensé. Pas besoin de compte — reviens sur ce lien quand tu veux pour changer tes réponses.</p>
            </div>

            <div class="card space-y-2">
                <label class="label" for="pl-name">Ton nom (facultatif, pour que {{ from }} sache que c’est toi)</label>
                <div class="flex gap-2">
                    <input id="pl-name" v-model="name" class="input" maxlength="120" autocomplete="off" @blur="saveName" @keyup.enter="saveName" />
                    <span v-if="nameSaved" class="grid size-10 shrink-0 place-items-center text-emerald-600 dark:text-emerald-400"><Icon name="check" :size="18" /></span>
                </div>
            </div>

            <section v-for="g in groups" :key="g.bracket" class="space-y-2">
                <h2 class="font-semibold">{{ g.label }}</h2>
                <ul class="space-y-2">
                    <li v-for="item in g.items" :key="item.id" class="card flex items-center gap-3 py-2.5">
                        <button
                            type="button" class="grid size-9 shrink-0 place-items-center rounded-full border-2 transition-colors" :class="statusClass[myAnswers[item.id] ?? 'none']"
                            :aria-label="`Toi : ${STATUSES[myAnswers[item.id] ?? 'none']} — changer`" :title="STATUSES[myAnswers[item.id] ?? 'none']" @click="cycle(item)"
                        >
                            <Icon :name="statusIcon[myAnswers[item.id] ?? 'none']" :size="15" />
                        </button>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium">{{ item.title }}</span>
                            <span class="muted block truncate text-xs">{{ DOMAINS[item.domain] }} · {{ from }} : {{ STATUSES[item.status].toLowerCase() }}</span>
                        </span>
                    </li>
                </ul>
            </section>

            <p v-if="!groups.length" class="muted text-center text-sm">{{ from }} n’a encore rien noté.</p>
        </div>
    </main>
</template>
