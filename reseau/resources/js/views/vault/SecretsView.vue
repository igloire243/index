<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import EmptyState from '../../components/EmptyState.vue';
import Icon from '../../components/Icon.vue';
import SecretForm from '../../components/vault/SecretForm.vue';
import SecretSheet from '../../components/vault/SecretSheet.vue';
import { filterSecrets, TYPES } from '../../lib/secrets';
import { loadSecrets, secrets } from '../../stores/secrets';
import { toast } from '../../stores/session';
import { vault } from '../../stores/vault';

const query = ref('');
const type = ref('');
const formOpen = ref(false);
const sheetOpen = ref(false);
const openId = ref(null);
const loading = ref(true);

const shown = computed(() => filterSecrets(secrets.list, { query: query.value, type: type.value }));
const presentTypes = computed(() => Object.keys(TYPES).filter((t) => secrets.list.some((s) => s.type === t)));

async function refresh() {
    loading.value = true;
    try {
        await loadSecrets();
    } catch (e) {
        if (e.code !== 'vault_locked') toast(e.message, 'error');
    } finally {
        loading.value = false;
    }
}
onMounted(refresh);
watch(() => vault.unlocked, (u) => u && refresh());

const view = (s) => {
    openId.value = s.id;
    sheetOpen.value = true;
};
</script>

<template>
    <div class="space-y-3">
        <div class="flex items-center gap-2">
            <input v-model="query" class="input" type="search" placeholder="Rechercher…" aria-label="Rechercher une fiche" autocomplete="off" />
            <button type="button" class="btn btn-primary shrink-0" @click="formOpen = true"><Icon name="plus" :size="18" /> Fiche</button>
        </div>

        <div v-if="presentTypes.length > 1" class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1" role="group" aria-label="Filtrer par type">
            <button type="button" class="chip" :class="{ 'chip-on': !type }" @click="type = ''">Tout</button>
            <button v-for="t in presentTypes" :key="t" type="button" class="chip" :class="{ 'chip-on': type === t }" @click="type = type === t ? '' : t">{{ TYPES[t].label }}</button>
        </div>

        <EmptyState v-if="!loading && !shown.length" illustration="vault" :title="secrets.list.length ? 'Aucun résultat' : 'Aucun mot de passe enregistré'">
            <p v-if="!secrets.list.length">Comptes en ligne, comptes bancaires, codes PIN, schémas, Wi-Fi… tout est chiffré et ne s’affiche qu’à ta demande.</p>
            <template v-if="!secrets.list.length" #actions><button type="button" class="btn btn-primary" @click="formOpen = true">Ajouter une fiche</button></template>
        </EmptyState>

        <TransitionGroup name="list" tag="ul" class="relative space-y-2">
            <li v-for="(s, i) in shown" :key="s.id" class="rise" :style="{ '--i': Math.min(i, 8) }">
                <button type="button" class="card flex w-full items-center gap-3 py-3 text-left transition-transform active:scale-[.99]" @click="view(s)">
                    <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950 dark:text-indigo-300"><Icon :name="TYPES[s.type].icon" /></span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-semibold">{{ s.title }}</span>
                        <span class="muted block truncate">{{ s.type === 'site' && s.username ? s.username : TYPES[s.type].label }}</span>
                    </span>
                    <Icon v-if="s.favorite" name="star" :size="18" class="shrink-0 text-amber-500" />
                </button>
            </li>
        </TransitionGroup>

        <SecretForm v-model="formOpen" @saved="(s) => view(s)" />
        <SecretSheet v-model="sheetOpen" :id="openId" />

        <p class="muted pt-1 text-xs">Chiffré sur le serveur avec la clé de l’application : une personne qui aurait à la fois la base de données et cette clé pourrait le lire. Ne remplace pas un gestionnaire spécialisé pour tes secrets les plus critiques.</p>
    </div>
</template>
