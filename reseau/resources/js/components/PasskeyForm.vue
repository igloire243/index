<script setup>
import { computed, ref, watch } from 'vue';
import { deviceLabel, describePasskeyError, registerPasskey } from '../lib/passkeys';
import { toast } from '../stores/session';
import Sheet from './Sheet.vue';
import Wizard from './Wizard.vue';

const props = defineProps({ modelValue: Boolean });
const emit = defineEmits(['update:modelValue', 'saved']);
const open = computed({ get: () => props.modelValue, set: (v) => emit('update:modelValue', v) });

const name = ref('');
const busy = ref(false);

watch(
    () => props.modelValue,
    (isOpen) => isOpen && (name.value = deviceLabel()),
);

const steps = computed(() => [
    { key: 'name', title: 'Appareil', valid: name.value.trim().length > 0, hint: 'Donne un nom à cet appareil.' },
    { key: 'confirm', title: 'Vérification' },
]);

async function submit() {
    busy.value = true;
    try {
        await registerPasskey(name.value.trim());
        open.value = false;
        emit('saved');
        toast('Face ID / empreinte activé sur cet appareil.');
    } catch (e) {
        toast(describePasskeyError(e), 'error');
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Sheet v-model="open" title="Face ID / empreinte">
        <Wizard :steps="steps" :busy="busy" submit-label="Activer" @submit="submit" @cancel="open = false">
            <template #default="{ step }">
                <div v-if="step.key === 'name'">
                    <label class="label" for="pk-name">Nom de cet appareil</label>
                    <input id="pk-name" v-model="name" class="input" maxlength="60" autocomplete="off" />
                    <p class="muted mt-2">Il te permettra de reconnaître l’appareil dans la liste, par exemple pour le retirer s’il est perdu.</p>
                </div>
                <div v-else class="space-y-2 text-sm">
                    <p>Ton téléphone va te demander de confirmer avec ton <b>visage</b>, ton <b>empreinte</b> ou le <b>code de l’appareil</b>.</p>
                    <p class="muted">C’est le téléphone lui-même qui vérifie que c’est toi. L’application ne reçoit ni ne conserve aucune donnée de ton visage ou de ton empreinte : elle garde seulement une clé publique, inutilisable sans ton appareil.</p>
                </div>
            </template>
        </Wizard>
    </Sheet>
</template>
