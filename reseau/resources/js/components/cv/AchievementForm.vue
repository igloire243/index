<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { saveAchievement } from '../../stores/cv';
import { toast } from '../../stores/session';
import Sheet from '../Sheet.vue';
import Wizard from '../Wizard.vue';

const props = defineProps({ modelValue: Boolean, achievement: { type: Object, default: null }, experienceId: { type: Number, default: null } });
const emit = defineEmits(['update:modelValue']);
const open = computed({ get: () => props.modelValue, set: (v) => emit('update:modelValue', v) });

const form = reactive({ title: '', date: '', description: '' });
const busy = ref(false);

watch(
    () => props.modelValue,
    (isOpen) => isOpen && Object.assign(form, { title: props.achievement?.title ?? '', date: props.achievement?.date ?? '', description: props.achievement?.description ?? '' }),
);

const steps = computed(() => [{ key: 'achievement', title: 'Réalisation', valid: form.title.trim().length > 0, hint: 'Donne un titre pour continuer.' }]);

async function submit() {
    busy.value = true;
    try {
        await saveAchievement(
            { experience_id: props.experienceId, title: form.title.trim(), date: form.date || null, description: form.description.trim() || null },
            props.achievement?.id ?? null,
        );
        open.value = false;
        toast(props.achievement ? 'Mise à jour.' : 'Ajoutée.');
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Sheet v-model="open" :title="achievement ? 'Modifier la réalisation' : 'Nouvelle réalisation'">
        <Wizard :steps="steps" :busy="busy" submit-label="Enregistrer" @submit="submit" @cancel="open = false">
            <template #default>
                <div>
                    <label class="label" for="ac-title">Titre *</label>
                    <input id="ac-title" v-model="form.title" class="input" maxlength="160" placeholder="Employé du mois, certification…" autocomplete="off" data-autofocus />
                </div>
                <div>
                    <label class="label" for="ac-date">Date</label>
                    <input id="ac-date" v-model="form.date" class="input" type="date" />
                </div>
                <div>
                    <label class="label" for="ac-desc">Description</label>
                    <textarea id="ac-desc" v-model="form.description" class="input" rows="3" maxlength="2000" />
                </div>
            </template>
        </Wizard>
    </Sheet>
</template>
