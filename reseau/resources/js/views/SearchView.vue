<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import EmptyState from '../components/EmptyState.vue';
import Icon from '../components/Icon.vue';
import { TYPES as SECRET_TYPES } from '../lib/secrets';
import { hasResults, search } from '../lib/search';
import { contacts, loadContacts, openDetail } from '../stores/contacts';
import { finance, loadFinance } from '../stores/finance';
import { secrets as secretsStore } from '../stores/secrets';
import { vault } from '../stores/vault';

const router = useRouter();
const input = ref(null);
const query = ref('');

onMounted(() => {
    loadContacts().catch(() => {});
    loadFinance().catch(() => {});
    input.value?.focus();
});

const results = computed(() =>
    search(
        { contacts: contacts.list, dossiers: vault.unlocked ? vault.dossiers : [], secrets: vault.unlocked ? secretsStore.list : [], accounts: finance.accounts, debts: finance.debts, assets: finance.assets },
        query.value,
    ),
);

function go(item) {
    query.value = '';
    if (item.kind === 'contact') openDetail(item.id);
    else if (item.kind === 'dossier') router.push(`/coffre/${item.id}`);
    else if (item.kind === 'secret') router.push('/coffre/mots-de-passe');
    else if (item.kind === 'account') router.push('/finances/fonds');
    else if (item.kind === 'debt') router.push(item.direction === 'owed_to_me' ? '/finances/creances' : '/finances/dettes');
    else if (item.kind === 'asset') router.push('/finances/biens');
}

const sections = computed(() => [
    { key: 'contacts', title: 'Contacts', icon: 'users', items: results.value.contacts },
    { key: 'dossiers', title: 'Coffre · dossiers', icon: 'folder', items: results.value.dossiers },
    { key: 'secrets', title: 'Coffre · mots de passe', icon: 'lock', items: results.value.secrets.map((s) => ({ ...s, subtitle: SECRET_TYPES[s.subtitle]?.label ?? s.subtitle })) },
    { key: 'finances', title: 'Finances', icon: 'wallet', items: results.value.finances },
]);
</script>

<template>
    <div class="space-y-4">
        <div class="relative">
            <input
                ref="input"
                v-model="query"
                class="input pr-10"
                type="search"
                placeholder="Chercher un contact, un dossier, une fiche financière…"
                aria-label="Recherche"
                autocomplete="off"
            />
            <button v-if="query" type="button" class="absolute top-1/2 right-2 -translate-y-1/2 rounded-full p-1.5 text-slate-400" aria-label="Effacer" @click="query = ''">
                <Icon name="x" :size="16" />
            </button>
        </div>

        <p v-if="!vault.unlocked && query" class="muted text-xs">Le Coffre est verrouillé : ses dossiers et mots de passe ne sont pas cherchés.</p>

        <template v-if="query.trim()">
            <EmptyState v-if="!hasResults(results)" illustration="coach" small title="Aucun résultat">
                <p>Essaie un autre mot, ou vérifie l’orthographe.</p>
            </EmptyState>

            <section v-for="s in sections.filter((s) => s.items.length)" :key="s.key" class="space-y-2">
                <h2 class="muted text-xs font-semibold tracking-wide uppercase">{{ s.title }}</h2>
                <ul class="space-y-2">
                    <li v-for="item in s.items" :key="s.key + item.id">
                        <button type="button" class="card flex w-full items-center gap-3 py-3 text-left transition-transform active:scale-[.99]" @click="go(item)">
                            <span class="grid size-10 shrink-0 place-items-center rounded-2xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950 dark:text-indigo-300"><Icon :name="s.icon" :size="18" /></span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-semibold">{{ item.title }}</span>
                                <span v-if="item.subtitle" class="muted block truncate">{{ item.subtitle }}</span>
                            </span>
                        </button>
                    </li>
                </ul>
            </section>
        </template>

        <EmptyState v-else illustration="contacts" small title="Cherche dans toute l’application">
            <p>Un nom, un domaine, un lieu, un titre de dossier, une banque…</p>
        </EmptyState>
    </div>
</template>
