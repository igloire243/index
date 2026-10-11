<script setup>
import { computed, onMounted, ref } from 'vue';
import ContactRow from '../components/ContactRow.vue';
import Icon from '../components/Icon.vue';
import { allDomains, inDomain, matches, norm } from '../lib/contacts';
import { contacts, distTo, loadContacts, locate, openForm } from '../stores/contacts';
import { toast } from '../stores/session';

const query = ref('');
const domain = ref(null);
const sort = ref('name');

onMounted(() => loadContacts().catch((e) => toast(e.message, 'error')));

const domains = computed(() => allDomains(contacts.list));
const items = computed(() => {
    const q = norm(query.value);
    const byName = (a, b) => a.name.localeCompare(b.name, 'fr', { sensitivity: 'base' });
    const list = contacts.list.filter((c) => (!domain.value || inDomain(c, domain.value)) && matches(c, q));
    if (sort.value === 'distance') {
        return list.sort((a, b) => {
            const da = distTo(a);
            const db = distTo(b);
            if (da == null && db == null) return byName(a, b);
            if (da == null) return 1;
            if (db == null) return -1;
            return da - db;
        });
    }
    if (sort.value === 'recent') return list.sort((a, b) => b.created_at.localeCompare(a.created_at));
    if (sort.value === 'relance') return list.sort((a, b) => (a.last_contact || '').localeCompare(b.last_contact || '') || byName(a, b));
    return list.sort(byName);
});

async function onSort() {
    if (sort.value === 'distance' && !contacts.pos && !(await locate())) toast('Autorise la localisation pour trier par distance.', 'error');
}

async function onLocate() {
    toast((await locate()) ? 'Position mise à jour.' : 'Position indisponible.');
}

function toggleDomain(key) {
    domain.value = domain.value === key ? null : key;
}
</script>

<template>
    <div class="space-y-3">
        <input v-model="query" class="input" type="search" placeholder="Chercher un nom, un domaine, une ville…" autocomplete="off" aria-label="Rechercher" />

        <div v-if="domains.length" class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1" role="group" aria-label="Filtrer par domaine">
            <button
                v-for="d in domains"
                :key="d.key"
                type="button"
                class="chip"
                :class="{ 'chip-on': domain === d.key }"
                :aria-pressed="domain === d.key"
                @click="toggleDomain(d.key)"
            >
                {{ d.label }} <span class="opacity-60">{{ d.count }}</span>
            </button>
        </div>

        <div class="flex items-center justify-between gap-2">
            <span class="muted">{{ items.length }} contact{{ items.length > 1 ? 's' : '' }}</span>
            <div class="flex items-center gap-2">
                <button type="button" class="btn btn-sm" :class="{ 'text-indigo-600': contacts.pos }" aria-label="Utiliser ma position" @click="onLocate">
                    <Icon name="locate" :size="16" /> Position
                </button>
                <label class="muted flex items-center gap-1">
                    Trier
                    <select v-model="sort" class="rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900" @change="onSort">
                        <option value="name">par nom</option>
                        <option value="distance">par distance</option>
                        <option value="recent">récents</option>
                        <option value="relance">à relancer</option>
                    </select>
                </label>
            </div>
        </div>

        <ul class="space-y-2">
            <li v-for="c in items" :key="c.id"><ContactRow :contact="c" /></li>
        </ul>

        <div v-if="!items.length && contacts.loaded" class="py-10 text-center text-slate-500">
            <template v-if="!contacts.list.length">
                <p>Ton carnet est vide.</p>
                <p class="mb-4">Ajoute ton premier contact, ou importe ceux de Google Contacts (Réglages).</p>
                <button type="button" class="btn btn-primary" @click="openForm()">Ajouter un contact</button>
            </template>
            <p v-else>Aucun résultat.</p>
        </div>
    </div>
</template>
