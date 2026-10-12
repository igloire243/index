<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { LANGUAGE_LEVELS } from '../../lib/cv';
import { saveLanguage } from '../../stores/cv';
import { toast } from '../../stores/session';
import Sheet from '../Sheet.vue';
import Wizard from '../Wizard.vue';

const props = defineProps({ modelValue: Boolean, language: { type: Object, default: null } });
const emit = defineEmits(['update:modelValue']);
const open = computed({ get: () => props.modelValue, set: (v) => emit('update:modelValue', v) });

const form = reactive({ name: '', level: 'intermediaire' });
const busy = ref(false);

watch(
    () => props.modelValue,
    (isOpen) => isOpen && Object.assign(form, { name: props.language?.name ?? '', level: props.language?.level ?? 'intermediaire' }),
);

const steps = computed(() => [{ key: 'lang', title: 'Langue', valid: form.name.trim().length > 0, hint: 'Donne un nom pour continuer.' }]);

async function submit() {
    busy.value = true;
    try {
        await saveLanguage({ name: form.name.trim(), level: form.level }, props.language?.id ?? null);
        open.value = false;
        toast(props.language ? 'Mise à jour.' : 'Ajoutée.');
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Sheet v-model="open" :title="language ? 'Modifier la langue' : 'Nouvelle langue'">
        <Wizard :steps="steps" :busy="busy" submit-label="Enregistrer" @submit="submit" @cancel="open = false">
            <template #default>
                <div>
                    <label class="label" for="la-name">Langue *</label>
                    <input id="la-name" v-model="form.name" class="input" maxlength="60" placeholder="Anglais, swahili, lingala…" autocomplete="off" data-autofocus />
                </div>
                <div>
                    <label class="label" for="la-level">Niveau</label>
                    <select id="la-level" v-model="form.level" class="input">
                        <option v-for="(label, key) in LANGUAGE_LEVELS" :key="key" :value="key">{{ label }}</option>
                    </select>
                </div>
            </template>
        </Wizard>
    </Sheet>
</template>
