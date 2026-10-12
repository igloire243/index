<script setup>
import { computed, ref, watch } from 'vue';
import { downloadBlob, exportVault } from '../../lib/export';
import { strength } from '../../lib/secrets';
import { toast } from '../../stores/session';
import Icon from '../Icon.vue';
import Sheet from '../Sheet.vue';
import Wizard from '../Wizard.vue';

const props = defineProps({ modelValue: Boolean });
const emit = defineEmits(['update:modelValue']);
const open = computed({ get: () => props.modelValue, set: (v) => emit('update:modelValue', v) });

const password = ref('');
const confirm = ref('');
const busy = ref(false);
const done = ref(false);

watch(
    () => props.modelValue,
    (isOpen) => {
        if (!isOpen) return;
        password.value = '';
        confirm.value = '';
        done.value = false;
    },
);

const level = computed(() => strength(password.value));
const steps = computed(() => [
    { key: 'mdp', title: 'Mot de passe', valid: password.value.length >= 10, hint: 'Choisis un mot de passe d’au moins 10 caractères, pour cette sauvegarde uniquement.' },
    { key: 'confirmation', title: 'Confirmation', valid: confirm.value.length > 0 && confirm.value === password.value, hint: 'Retape le même mot de passe pour confirmer.' },
]);

async function submit() {
    busy.value = true;
    try {
        const { blob, name } = await exportVault(password.value);
        downloadBlob(blob, name);
        done.value = true;
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Sheet v-model="open" title="Exporter une sauvegarde">
        <div v-if="done" class="space-y-4">
            <p class="text-sm">
                Le fichier a été téléchargé. Il contient tes dossiers et tes mots de passe, chiffrés avec le mot de passe que tu viens de choisir —
                <strong>garde-le précieusement, il ne se retrouve nulle part sur le serveur.</strong>
            </p>
            <p class="text-sm">
                Pour le rouvrir plus tard, utilise
                <a class="text-indigo-600 underline dark:text-indigo-400" href="/dechiffrer.html" target="_blank" rel="noopener">l’outil de déchiffrement</a>
                — une page qui fonctionne dans n’importe quel navigateur, même hors connexion. Enregistre-la aussi de ton côté.
            </p>
            <button type="button" class="btn btn-primary w-full" @click="open = false">Fermer</button>
        </div>

        <Wizard v-else :steps="steps" :busy="busy" submit-label="Télécharger la sauvegarde" @submit="submit" @cancel="open = false">
            <template #default="{ step }">
                <template v-if="step.key === 'mdp'">
                    <p class="muted text-sm">
                        Choisis un mot de passe pour protéger ce fichier. Il est indépendant du mot de passe de ton compte : sans lui, cette
                        sauvegarde-ci est illisible, même par toi.
                    </p>
                    <div>
                        <label class="label" for="ex-pw">Mot de passe de cette sauvegarde</label>
                        <input id="ex-pw" v-model="password" class="input font-mono" type="password" autocomplete="new-password" maxlength="255" data-autofocus />
                    </div>
                    <div v-if="password" class="flex gap-1" aria-hidden="true">
                        <span v-for="i in 4" :key="i" class="h-1.5 flex-1 rounded-full transition-colors" :class="i <= level.level ? ['', 'bg-red-500', 'bg-amber-500', 'bg-lime-500', 'bg-emerald-500'][level.level] : 'bg-slate-200 dark:bg-slate-700'" />
                    </div>
                </template>
                <template v-else>
                    <div>
                        <label class="label" for="ex-confirm">Retape le mot de passe</label>
                        <input id="ex-confirm" v-model="confirm" class="input font-mono" type="password" autocomplete="new-password" maxlength="255" />
                    </div>
                    <div class="flex items-start gap-2 rounded-2xl bg-slate-50 p-3 text-sm dark:bg-slate-800">
                        <Icon name="lock" :size="16" class="mt-0.5 shrink-0" />
                        <span>Le fichier contiendra tes dossiers (avec leurs fichiers) et tous tes mots de passe, en clair une fois déchiffré.</span>
                    </div>
                </template>
            </template>
        </Wizard>
    </Sheet>
</template>
