<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import EmptyState from '../../components/EmptyState.vue';
import Icon from '../../components/Icon.vue';
import DossierForm from '../../components/vault/DossierForm.vue';
import { norm } from '../../lib/contacts';
import { contacts } from '../../stores/contacts';
import { toast } from '../../stores/session';
import { loadDossiers, vault } from '../../stores/vault';

const route = useRoute();
const router = useRouter();
const query = ref('');
const formOpen = ref(false);
const loading = ref(true);

const contactId = computed(() => (route.query.contact ? Number(route.query.contact) : null));
const contact = computed(() => contacts.list.find((c) => c.id === contactId.value) ?? null);

// Un dossier avec une catégorie est un document personnel : il vit dans l'onglet « Documents », pas ici.
const plainDossiers = computed(() => vault.dossiers.filter((d) => !d.category));
const shown = computed(() => {
    const q = norm(query.value);
    return plainDossiers.value.filter((d) => (!contactId.value || d.contact_id === contactId.value) && (!q || norm(`${d.title} ${d.subject ?? ''}`).includes(q)));
});

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
            <input v-model="query" class="input" type="search" placeholder="Rechercher un dossier…" aria-label="Rechercher un dossier" />
            <button type="button" class="btn btn-primary shrink-0" @click="formOpen = true"><Icon name="plus" :size="18" /> Dossier</button>
        </div>

        <div v-if="contactId" class="flex items-center gap-2">
            <span class="chip chip-on">Dossiers de {{ contact?.name ?? 'ce contact' }}</span>
            <button type="button" class="btn btn-sm" @click="router.replace('/coffre')">Voir tous</button>
        </div>

        <EmptyState v-if="!loading && !shown.length" illustration="vault" :title="plainDossiers.length ? 'Aucun résultat' : 'Aucun dossier pour l’instant'">
            <p v-if="!plainDossiers.length">Crée un dossier sur une personne ou un sujet, puis ajoute-y des photos, vidéos, audios et documents.</p>
            <template v-if="!plainDossiers.length" #actions><button type="button" class="btn btn-primary" @click="formOpen = true">Créer un dossier</button></template>
        </EmptyState>

        <TransitionGroup name="list" tag="ul" class="relative space-y-3">
            <li v-for="(d, i) in shown" :key="d.id" class="rise" :style="{ '--i': Math.min(i, 8) }">
                <RouterLink :to="`/coffre/${d.id}`" class="card flex items-center gap-3 transition-transform active:scale-[.99]">
                    <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950 dark:text-indigo-300"><Icon name="folder" /></span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-semibold">{{ d.title }}</span>
                        <span class="muted block truncate">{{ [d.subject, `${d.files_count} fichier${d.files_count > 1 ? 's' : ''}`].filter(Boolean).join(' · ') }}</span>
                    </span>
                    <span class="muted shrink-0 text-xs">{{ day(d.updated_at) }}</span>
                </RouterLink>
            </li>
        </TransitionGroup>

        <DossierForm v-model="formOpen" :contact-id="contactId" @saved="(d) => router.push(`/coffre/${d.id}`)" />
    </div>
</template>
