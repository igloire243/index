<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import EmptyState from '../../components/EmptyState.vue';
import Icon from '../../components/Icon.vue';
import DossierForm from '../../components/vault/DossierForm.vue';
import { categoryLabel, DOCUMENT_CATEGORIES, expiryStatus, filterDocuments } from '../../lib/documents';
import { toast } from '../../stores/session';
import { loadDossiers, vault } from '../../stores/vault';

const router = useRouter();
const query = ref('');
const category = ref('');
const formOpen = ref(false);
const loading = ref(true);

const documents = computed(() => vault.dossiers.filter((d) => d.category));
const shown = computed(() => filterDocuments(documents.value, { query: query.value, category: category.value }));
const presentCategories = computed(() => Object.keys(DOCUMENT_CATEGORIES).filter((c) => documents.value.some((d) => d.category === c)));

const day = (iso) => new Date(iso).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' });

async function refresh() {
    loading.value = true;
    try {
        await loadDossiers();
    } catch (e) {
        if (e.code !== 'vault_locked') toast(e.message, 'error');
    } finally {
        loading.value = false;
    }
}
onMounted(refresh);
watch(() => vault.unlocked, (u) => u && refresh());
</script>

<template>
    <div class="space-y-3">
        <div class="flex items-center gap-2">
            <input v-model="query" class="input" type="search" placeholder="Rechercher un document…" aria-label="Rechercher un document" />
            <button type="button" class="btn btn-primary shrink-0" @click="formOpen = true"><Icon name="plus" :size="18" /> Document</button>
        </div>

        <div v-if="presentCategories.length > 1" class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1" role="group" aria-label="Filtrer par catégorie">
            <button type="button" class="chip" :class="{ 'chip-on': !category }" @click="category = ''">Tout</button>
            <button v-for="c in presentCategories" :key="c" type="button" class="chip" :class="{ 'chip-on': category === c }" @click="category = category === c ? '' : c">{{ categoryLabel(c) }}</button>
        </div>

        <EmptyState v-if="!loading && !shown.length" illustration="vault" :title="documents.length ? 'Aucun résultat' : 'Aucun document pour l’instant'">
            <p v-if="!documents.length">Acte de naissance, diplôme, carte d’identité, passeport, bulletin, carte grise… classe tes documents administratifs par catégorie, où que tu sois.</p>
            <template v-if="!documents.length" #actions><button type="button" class="btn btn-primary" @click="formOpen = true">Ajouter un document</button></template>
        </EmptyState>

        <TransitionGroup name="list" tag="ul" class="relative space-y-3">
            <li v-for="(d, i) in shown" :key="d.id" class="rise" :style="{ '--i': Math.min(i, 8) }">
                <RouterLink :to="`/coffre/${d.id}`" class="card flex items-center gap-3 transition-transform active:scale-[.99]">
                    <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950 dark:text-indigo-300"><Icon :name="DOCUMENT_CATEGORIES[d.category]?.icon ?? 'file'" /></span>
                    <span class="min-w-0 flex-1">
                        <span class="flex items-center gap-2">
                            <span class="min-w-0 truncate font-semibold">{{ d.title }}</span>
                            <span v-if="expiryStatus(d) === 'expired'" class="tag shrink-0 bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300">Expiré</span>
                            <span v-else-if="expiryStatus(d) === 'soon'" class="tag shrink-0 bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300">Expire bientôt</span>
                        </span>
                        <span class="muted block truncate">{{ [categoryLabel(d.category), `${d.files_count} fichier${d.files_count > 1 ? 's' : ''}`].filter(Boolean).join(' · ') }}</span>
                    </span>
                    <span class="muted shrink-0 text-xs">{{ day(d.updated_at) }}</span>
                </RouterLink>
            </li>
        </TransitionGroup>

        <DossierForm v-model="formOpen" document @saved="(d) => router.push(`/coffre/${d.id}`)" />
    </div>
</template>
