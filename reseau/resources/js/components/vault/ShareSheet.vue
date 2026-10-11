<script setup>
import { computed, ref, watch } from 'vue';
import { copyText } from '../../lib/secrets';
import { createShare, DURATIONS, listShares, revokeShare, shareUrl } from '../../lib/share';
import { toast } from '../../stores/session';
import Icon from '../Icon.vue';
import Sheet from '../Sheet.vue';

const props = defineProps({ modelValue: Boolean, file: { type: Object, default: null } });
const emit = defineEmits(['update:modelValue']);
const open = computed({ get: () => props.modelValue, set: (v) => emit('update:modelValue', v) });

const minutes = ref(1440);
const busy = ref(false);
const links = ref([]);
const loading = ref(false);
const justCreated = ref(null); // { id, url } : affiché une seule fois, le jeton n'est jamais redemandé ensuite

async function refresh() {
    if (!props.file) return;
    loading.value = true;
    try {
        links.value = await listShares(props.file.id);
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        loading.value = false;
    }
}
watch(() => [props.modelValue, props.file?.id], ([isOpen]) => {
    justCreated.value = null;
    if (isOpen) refresh();
});

async function create() {
    busy.value = true;
    try {
        const saved = await createShare(props.file.id, minutes.value);
        justCreated.value = { id: saved.id, url: shareUrl(saved.token) };
        await refresh();
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        busy.value = false;
    }
}

async function revoke(id) {
    try {
        await revokeShare(id);
        if (justCreated.value?.id === id) justCreated.value = null;
        await refresh();
        toast('Lien révoqué.');
    } catch (e) {
        toast(e.message, 'error');
    }
}

async function copy(url) {
    if (await copyText(url)) toast('Lien copié.');
    else toast('Copie impossible sur cet appareil.', 'error');
}

const left = (expiresAt) => {
    const ms = new Date(expiresAt).getTime() - Date.now();
    if (ms <= 0) return 'expire…';
    const h = Math.round(ms / 3600000);
    return h < 24 ? `expire dans ${h} h` : `expire dans ${Math.round(h / 24)} j`;
};
</script>

<template>
    <Sheet v-model="open" title="Partager ce fichier">
        <div class="space-y-4">
            <p class="muted">
                Crée un lien à usage unique : il s'ouvre une seule fois, puis cesse de fonctionner. La personne n'a accès qu'à ce fichier, pas au reste du Coffre.
            </p>

            <div class="flex gap-2" role="radiogroup" aria-label="Durée du lien">
                <button
                    v-for="d in DURATIONS"
                    :key="d.minutes"
                    type="button"
                    role="radio"
                    :aria-checked="minutes === d.minutes"
                    class="chip flex-1 justify-center"
                    :class="{ 'chip-on': minutes === d.minutes }"
                    @click="minutes = d.minutes"
                >
                    {{ d.label }}
                </button>
            </div>
            <button type="button" class="btn btn-primary w-full" :disabled="busy" @click="create">
                <Icon name="share" :size="18" /> {{ busy ? 'Création…' : 'Créer le lien' }}
            </button>

            <div v-if="justCreated" class="space-y-2 rounded-2xl bg-indigo-50 p-3 dark:bg-indigo-950/50">
                <p class="text-sm font-semibold">Lien créé — copie-le maintenant, il ne sera pas réaffiché :</p>
                <div class="flex items-center gap-2">
                    <code class="selectable min-w-0 flex-1 truncate rounded-lg bg-white px-2 py-1.5 text-xs dark:bg-slate-900">{{ justCreated.url }}</code>
                    <button type="button" class="btn btn-sm shrink-0" @click="copy(justCreated.url)"><Icon name="copy" :size="14" /> Copier</button>
                </div>
            </div>

            <div v-if="links.length" class="space-y-2">
                <h3 class="label">Liens actifs</h3>
                <ul class="space-y-2">
                    <li v-for="l in links" :key="l.id" class="flex items-center justify-between gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700">
                        <span class="muted">Créé le {{ new Date(l.created_at).toLocaleDateString('fr-FR') }} · {{ left(l.expires_at) }}</span>
                        <button type="button" class="btn btn-sm" @click="revoke(l.id)">Révoquer</button>
                    </li>
                </ul>
            </div>
            <p v-else-if="!loading" class="muted text-sm">Aucun lien actif pour l’instant.</p>

            <button type="button" class="btn w-full" @click="open = false">Fermer</button>
        </div>
    </Sheet>
</template>
