<script setup>
import { onMounted, ref } from 'vue';
import { RouterLink, RouterView, useRoute } from 'vue-router';
import EmptyState from '../../components/EmptyState.vue';
import Icon from '../../components/Icon.vue';
import Meter from '../../components/Meter.vue';
import { fmtSize } from '../../lib/vault';
import { loadContacts } from '../../stores/contacts';
import { toast } from '../../stores/session';
import { loadVaultStatus, lockVault, unlockVault, vault } from '../../stores/vault';

const route = useRoute();
const password = ref('');
const busy = ref(false);
const error = ref('');

onMounted(() => {
    loadVaultStatus().catch((e) => toast(e.message, 'error'));
    loadContacts().catch(() => {});
});

async function unlock() {
    if (!password.value) return;
    busy.value = true;
    error.value = '';
    try {
        await unlockVault(password.value);
        password.value = '';
    } catch (e) {
        error.value = e.message;
        password.value = '';
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div v-if="!vault.loaded" class="muted py-10 text-center">Chargement…</div>

    <div v-else-if="!vault.unlocked" class="space-y-4">
        <EmptyState illustration="vault" title="Coffre verrouillé">
            <p>Tes dossiers et tes mots de passe sont chiffrés sur le serveur. Confirme ton mot de passe pour les ouvrir.</p>
        </EmptyState>
        <form class="card mx-auto max-w-sm space-y-3" @submit.prevent="unlock">
            <div>
                <label class="label" for="vault-pw">Mot de passe du compte</label>
                <input id="vault-pw" v-model="password" class="input" type="password" autocomplete="current-password" data-autofocus />
            </div>
            <p v-if="error" class="text-sm text-red-600 dark:text-red-400" role="alert">{{ error }}</p>
            <button type="submit" class="btn btn-primary w-full" :disabled="busy || !password">
                <Icon name="lock" :size="18" /> {{ busy ? 'Vérification…' : 'Ouvrir le coffre' }}
            </button>
            <p class="muted text-xs">Le coffre se referme tout seul après quelques minutes d’inactivité, ou quand l’application reste en arrière-plan.</p>
        </form>
    </div>

    <div v-else class="space-y-4">
        <div class="card space-y-2">
            <Meter label="Espace utilisé" :value="`${fmtSize(vault.used)} sur ${fmtSize(vault.quota)}`" :ratio="vault.quota ? vault.used / vault.quota : 0" />
            <div class="flex items-center justify-between gap-2">
                <p class="muted text-xs">Fichiers chiffrés avant d’être écrits sur le disque.</p>
                <button type="button" class="btn btn-sm shrink-0" @click="lockVault().catch((e) => toast(e.message, 'error'))">
                    <Icon name="lock" :size="14" /> Verrouiller
                </button>
            </div>
        </div>

        <nav v-if="route.name === 'coffre' || route.name === 'coffre-secrets'" class="flex gap-2" aria-label="Sections du coffre">
            <RouterLink to="/coffre" class="chip" :class="{ 'chip-on': route.name === 'coffre' }" :aria-current="route.name === 'coffre' ? 'page' : undefined">Dossiers</RouterLink>
            <RouterLink to="/coffre/mots-de-passe" class="chip" :class="{ 'chip-on': route.name === 'coffre-secrets' }" :aria-current="route.name === 'coffre-secrets' ? 'page' : undefined">Mots de passe</RouterLink>
        </nav>

        <RouterView v-slot="{ Component }">
            <Transition name="page" mode="out-in">
                <component :is="Component" />
            </Transition>
        </RouterView>

        <p class="muted pt-2 text-xs">
            Ne conserve que des informations que tu as le droit de détenir. Les données concernant d’autres personnes sont encadrées par la loi de ton pays :
            garde-les pour un usage légitime et supprime-les quand elles ne servent plus.
        </p>
    </div>
</template>
