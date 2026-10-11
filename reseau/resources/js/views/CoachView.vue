<script setup>
import { computed, onMounted, ref } from 'vue';
import Meter from '../components/Meter.vue';
import { buildSuggestions, contactMeters, TRAITS } from '../lib/coach';
import { seededRandom } from '../lib/coach';
import { isoDate } from '../lib/dates';
import { contacts, loadContacts, openDetail, openForm, saveContact } from '../stores/contacts';
import { toast } from '../stores/session';

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

onMounted(() => loadContacts().catch((e) => toast(e.message, 'error')));

const all = computed(() => (contacts.loaded ? buildSuggestions(contacts.list, { pos: contacts.pos, hidden: hidden.value }) : []));
const shown = computed(() => (showAll.value ? all.value : all.value.slice(0, 5)));
const meters = computed(() => contactMeters(contacts.list));

// Une anecdote différente chaque jour, à s'entraîner à raconter.
const anecdote = computed(() => {
    const list = contacts.list.filter((c) => c.anecdote);
    return list.length ? list[Math.floor(seededRandom(isoDate() + 'a')() * list.length)] : null;
});

function run(action) {
    if (action.type === 'detail') openDetail(action.id);
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
        <h2 class="text-base font-semibold">À faire aujourd’hui</h2>
        <p v-if="contacts.loaded && !all.length" class="muted">Rien d’urgent. Ton réseau est bien entretenu, beau travail.</p>

        <article v-for="s in shown" :key="s.id" class="card space-y-1.5">
            <span
                class="tag"
                :class="{
                    '!bg-indigo-100 dark:!bg-indigo-950': s.trait === 'reseau',
                    '!bg-amber-100 dark:!bg-amber-950': s.trait === 'anecdote',
                    '!bg-emerald-100 dark:!bg-emerald-950': s.trait === 'savoir',
                    '!bg-pink-100 dark:!bg-pink-950': s.trait === 'imprevu',
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
