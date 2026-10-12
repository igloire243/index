<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { SKILL_TYPES } from '../../lib/cv';
import { saveSkill } from '../../stores/cv';
import { toast } from '../../stores/session';
import Sheet from '../Sheet.vue';
import Wizard from '../Wizard.vue';

const props = defineProps({ modelValue: Boolean, skill: { type: Object, default: null }, type: { type: String, default: 'competence' } });
const emit = defineEmits(['update:modelValue']);
const open = computed({ get: () => props.modelValue, set: (v) => emit('update:modelValue', v) });

const form = reactive({ type: 'competence', name: '', level: '', notes: '' });
const busy = ref(false);

watch(
    () => props.modelValue,
    (isOpen) => {
        if (!isOpen) return;
        const s = props.skill;
        Object.assign(form, { type: s?.type ?? props.type, name: s?.name ?? '', level: s?.level ?? '', notes: s?.notes ?? '' });
    },
);

const steps = computed(() => [{ key: 'skill', title: SKILL_TYPES[form.type]?.label ?? 'Compétence', valid: form.name.trim().length > 0, hint: 'Donne un nom pour continuer.' }]);

async function submit() {
    busy.value = true;
    try {
        await saveSkill({ type: form.type, name: form.name.trim(), level: form.level === '' ? null : Number(form.level), notes: form.notes.trim() || null }, props.skill?.id ?? null);
        open.value = false;
        toast(props.skill ? 'Mis à jour.' : 'Ajouté.');
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Sheet v-model="open" :title="skill ? 'Modifier' : `Nouvelle ${SKILL_TYPES[form.type]?.label.toLowerCase() ?? 'compétence'}`">
        <Wizard :steps="steps" :busy="busy" submit-label="Enregistrer" @submit="submit" @cancel="open = false">
            <template #default>
                <div v-if="!skill" class="grid grid-cols-3 gap-2" role="radiogroup" aria-label="Type">
                    <button
                        v-for="(t, key) in SKILL_TYPES" :key="key" type="button" role="radio" :aria-checked="form.type === key"
                        class="rounded-2xl border-2 p-2.5 text-center text-sm transition-colors"
                        :class="form.type === key ? 'border-indigo-600 bg-indigo-50 dark:border-indigo-400 dark:bg-indigo-950/50' : 'border-slate-200 dark:border-slate-700'"
                        @click="form.type = key"
                    >
                        {{ t.label }}
                    </button>
                </div>
                <div>
                    <label class="label" for="sk-name">Nom *</label>
                    <input id="sk-name" v-model="form.name" class="input" maxlength="120" autocomplete="off" data-autofocus />
                </div>
                <div>
                    <label class="label" for="sk-level">Niveau (facultatif)</label>
                    <select id="sk-level" v-model="form.level" class="input">
                        <option value="">Non précisé</option>
                        <option v-for="n in 5" :key="n" :value="n">{{ n }} / 5</option>
                    </select>
                </div>
                <div>
                    <label class="label" for="sk-notes">Notes</label>
                    <textarea id="sk-notes" v-model="form.notes" class="input" rows="2" maxlength="500" />
                </div>
            </template>
        </Wizard>
    </Sheet>
</template>
