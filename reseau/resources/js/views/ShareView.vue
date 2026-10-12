<script setup>
import { onBeforeUnmount, ref } from 'vue';
import { useRoute } from 'vue-router';
import EmptyState from '../components/EmptyState.vue';
import Icon from '../components/Icon.vue';
import Illustration from '../components/Illustration.vue';
import { fetchShareInfo, shareContentUrl } from '../lib/share';
import { fmtSize, KINDS } from '../lib/vault';

const route = useRoute();
const token = route.params.token;

const state = ref('chargement'); // chargement | pret | ouvert | erreur
const info = ref(null);
const error = ref('');
const mediaUrl = ref(''); // Blob local : évite une deuxième requête (qui consommerait le lien une seconde fois)

async function load() {
    try {
        info.value = await fetchShareInfo(token);
        state.value = 'pret';
    } catch (e) {
        error.value = e.status === 410 ? e.message : 'Ce lien n’existe pas ou plus.';
        state.value = 'erreur';
    }
}
load();

async function open() {
    state.value = 'ouvert';
    try {
        const res = await fetch(shareContentUrl(token));
        if (!res.ok) throw new Error((await res.json().catch(() => null))?.message || 'Erreur.');
        const blob = await res.blob();
        mediaUrl.value = URL.createObjectURL(blob);
    } catch (e) {
        error.value = e.message;
        state.value = 'erreur';
    }
}
onBeforeUnmount(() => mediaUrl.value && URL.revokeObjectURL(mediaUrl.value));
</script>

<template>
    <main class="mx-auto flex min-h-dvh max-w-sm flex-col justify-center px-6 py-10">
        <div v-if="state === 'chargement'" class="muted text-center">Chargement…</div>

        <EmptyState v-else-if="state === 'erreur'" illustration="vault" title="Lien indisponible">
            <p>{{ error }}</p>
        </EmptyState>

        <div v-else class="space-y-4 text-center">
            <Illustration illustration="vault" class="mx-auto max-w-56" />
            <div>
                <h1 class="text-lg font-semibold">{{ info.name }}</h1>
                <p class="muted">Partagé par {{ info.from }} · {{ KINDS[info.kind]?.label ?? 'Document' }} · {{ fmtSize(info.size) }}</p>
            </div>

            <template v-if="state === 'pret'">
                <p class="muted text-sm">Ce lien ne fonctionne qu’une seule fois. Il cessera de marcher dès que tu auras ouvert le document.</p>
                <button type="button" class="btn btn-primary w-full" @click="open"><Icon name="eye" :size="18" /> Voir le document</button>
            </template>

            <template v-else-if="mediaUrl">
                <img v-if="info.kind === 'image'" :src="mediaUrl" :alt="info.name" class="mx-auto max-h-[55dvh] rounded-2xl" />
                <video v-else-if="info.kind === 'video'" :src="mediaUrl" controls playsinline class="mx-auto max-h-[55dvh] rounded-2xl" />
                <audio v-else-if="info.kind === 'audio'" :src="mediaUrl" controls class="w-full" />
                <a v-else class="btn btn-primary w-full" :href="mediaUrl" :download="info.name"><Icon name="download" :size="18" /> Télécharger</a>
                <a v-if="info.kind === 'image' || info.kind === 'video'" class="btn w-full" :href="mediaUrl" :download="info.name"><Icon name="download" :size="18" /> Télécharger</a>
                <p class="muted text-xs">Ce lien a maintenant été utilisé et ne fonctionnera plus.</p>
            </template>

            <div v-else class="muted">Ouverture…</div>
        </div>
    </main>
</template>
