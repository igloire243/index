<script setup>
import { computed, ref, watch } from 'vue';
import { AGE_BRACKETS, DOMAINS, lifeGoalShareUrl, showLifeGoalShare, STATUSES } from '../lib/lifeGoals';
import { copyText } from '../lib/secrets';
import { createLifeGoalShare, lifeGoals, loadLifeGoalShares, revokeLifeGoalShare } from '../stores/lifeGoals';
import { toast } from '../stores/session';
import Icon from './Icon.vue';
import Sheet from './Sheet.vue';

const props = defineProps({ modelValue: Boolean });
const emit = defineEmits(['update:modelValue']);
const open = computed({ get: () => props.modelValue, set: (v) => emit('update:modelValue', v) });

const label = ref('');
const busy = ref(false);
const loading = ref(false);
const justCreated = ref(null); // { id, url } : affiché une seule fois, le jeton n'est jamais redemandé ensuite
const viewing = ref(null); // réponses d'un partage, chargées à la demande

watch(
    () => props.modelValue,
    (isOpen) => {
        justCreated.value = null;
        viewing.value = null;
        if (isOpen) refresh();
    },
);

async function refresh() {
    loading.value = true;
    try {
        await loadLifeGoalShares();
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        loading.value = false;
    }
}

async function create() {
    busy.value = true;
    try {
        const saved = await createLifeGoalShare(label.value.trim() || null);
        justCreated.value = { id: saved.id, url: lifeGoalShareUrl(saved.token) };
        label.value = '';
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        busy.value = false;
    }
}

async function revoke(id) {
    if (!confirm('Révoquer ce lien ? La personne ne pourra plus le consulter ni le modifier.')) return;
    try {
        await revokeLifeGoalShare(id);
        if (justCreated.value?.id === id) justCreated.value = null;
        if (viewing.value?.id === id) viewing.value = null;
        toast('Lien révoqué.');
    } catch (e) {
        toast(e.message, 'error');
    }
}

async function view(id) {
    viewing.value = { id, loading: true };
    try {
        viewing.value = { id, loading: false, ...(await showLifeGoalShare(id)) };
    } catch (e) {
        viewing.value = null;
        toast(e.message, 'error');
    }
}

async function copy(url) {
    if (await copyText(url)) toast('Lien copié.');
    else toast('Copie impossible sur cet appareil.', 'error');
}
</script>

<template>
    <Sheet v-model="open" title="Partager mes accomplissements">
        <div class="space-y-4">
            <template v-if="!viewing">
                <p class="muted">
                    La personne verra ce que tu as déjà accompli, et pourra cocher ses propres réponses (fait, prévu, jamais pensé) sans créer de compte. Elle peut revenir modifier ses réponses tant que tu n’as pas révoqué le lien.
                </p>

                <form class="flex gap-2" @submit.prevent="create">
                    <input v-model="label" class="input" maxlength="120" placeholder="Pour qui ? (facultatif)" autocomplete="off" />
                    <button type="submit" class="btn btn-primary shrink-0" :disabled="busy"><Icon name="share" :size="16" /> {{ busy ? '…' : 'Créer' }}</button>
                </form>

                <div v-if="justCreated" class="space-y-2 rounded-2xl bg-indigo-50 p-3 dark:bg-indigo-950/50">
                    <p class="text-sm font-semibold">Lien créé — copie-le pour l’envoyer :</p>
                    <div class="flex items-center gap-2">
                        <code class="selectable min-w-0 flex-1 truncate rounded-lg bg-white px-2 py-1.5 text-xs dark:bg-slate-900">{{ justCreated.url }}</code>
                        <button type="button" class="btn btn-sm shrink-0" @click="copy(justCreated.url)"><Icon name="copy" :size="14" /> Copier</button>
                    </div>
                </div>

                <div v-if="lifeGoals.shares.length" class="space-y-2">
                    <h3 class="label">Liens envoyés</h3>
                    <ul class="space-y-2">
                        <li v-for="s in lifeGoals.shares" :key="s.id" class="rounded-xl border border-slate-200 p-2.5 text-sm dark:border-slate-700">
                            <div class="flex items-center justify-between gap-2">
                                <span class="min-w-0 truncate">
                                    <span class="font-medium">{{ s.label || s.respondent_name || 'Sans nom' }}</span>
                                    <span class="muted block text-xs">{{ s.answers_count }} réponse{{ s.answers_count > 1 ? 's' : '' }}</span>
                                </span>
                                <span class="flex shrink-0 gap-1.5">
                                    <button type="button" class="btn btn-sm" @click="view(s.id)">Voir</button>
                                    <button type="button" class="btn btn-sm" aria-label="Révoquer" @click="revoke(s.id)"><Icon name="x" :size="14" /></button>
                                </span>
                            </div>
                        </li>
                    </ul>
                </div>
                <p v-else-if="!loading" class="muted text-sm">Aucun lien envoyé pour l’instant.</p>
            </template>

            <template v-else>
                <button type="button" class="muted inline-flex items-center gap-1 text-sm" @click="viewing = null"><Icon name="left" :size="14" /> Retour</button>
                <div v-if="viewing.loading" class="muted py-6 text-center text-sm">Chargement…</div>
                <template v-else>
                    <h3 class="font-semibold">{{ viewing.respondent_name || viewing.label || 'Réponses' }}</h3>
                    <p v-if="!viewing.answers.length" class="muted text-sm">Rien coché pour l’instant.</p>
                    <ul v-else class="space-y-1.5">
                        <li v-for="a in viewing.answers" :key="a.id" class="flex items-center justify-between gap-2 text-sm">
                            <span class="min-w-0 truncate">
                                {{ a.life_goal.title }}
                                <span class="muted block text-xs">{{ DOMAINS[a.life_goal.domain] }} · {{ AGE_BRACKETS[a.life_goal.age_bracket] }}</span>
                            </span>
                            <span class="tag shrink-0">{{ STATUSES[a.status] }}</span>
                        </li>
                    </ul>
                </template>
            </template>

            <button type="button" class="btn w-full" @click="open = false">Fermer</button>
        </div>
    </Sheet>
</template>
