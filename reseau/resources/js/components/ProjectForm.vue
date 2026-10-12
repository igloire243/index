<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { contacts } from '../stores/contacts';
import { saveProject } from '../stores/projects';
import { toast } from '../stores/session';
import Sheet from './Sheet.vue';
import Wizard from './Wizard.vue';

const props = defineProps({ modelValue: Boolean, project: { type: Object, default: null } });
const emit = defineEmits(['update:modelValue', 'saved']);
const open = computed({ get: () => props.modelValue, set: (v) => emit('update:modelValue', v) });

const form = reactive({ title: '', description: '', contact_id: '', target_date: '' });
const busy = ref(false);

watch(
    () => props.modelValue,
    (isOpen) => {
        if (!isOpen) return;
        const p = props.project;
        Object.assign(form, {
            title: p?.title ?? '', description: p?.description ?? '', contact_id: p?.contact_id ?? '', target_date: p?.target_date ?? '',
        });
    },
);

const sortedContacts = computed(() => [...contacts.list].sort((a, b) => a.name.localeCompare(b.name, 'fr')));
const steps = computed(() => [
    { key: 'idee', title: 'Idée', valid: form.title.trim().length > 0, hint: 'Donne un titre à ton projet ou ton idée pour continuer.' },
    { key: 'qui', title: 'Qui peut aider' },
]);

async function submit() {
    busy.value = true;
    try {
        const saved = await saveProject(
            { title: form.title.trim(), description: form.description.trim() || null, contact_id: form.contact_id === '' ? null : Number(form.contact_id), target_date: form.target_date || null },
            props.project?.id ?? null,
        );
        open.value = false;
        toast(props.project ? 'Projet mis à jour.' : 'Projet ajouté.');
        emit('saved', saved);
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Sheet v-model="open" :title="project ? 'Modifier le projet' : 'Nouveau projet'">
        <Wizard :steps="steps" :busy="busy" submit-label="Enregistrer" @submit="submit" @cancel="open = false">
            <template #default="{ step }">
                <template v-if="step.key === 'idee'">
                    <div>
                        <label class="label" for="pf-title">Titre *</label>
                        <input id="pf-title" v-model="form.title" class="input" maxlength="160" placeholder="Ouvrir un salon de coiffure, apprendre l’anglais…" autocomplete="off" data-autofocus />
                    </div>
                    <div>
                        <label class="label" for="pf-desc">L’idée, en quelques mots</label>
                        <textarea id="pf-desc" v-model="form.description" class="input" rows="4" maxlength="10000" placeholder="Pourquoi ce projet, à quoi il ressemblerait…" />
                    </div>
                </template>

                <template v-else>
                    <div>
                        <label class="label" for="pf-contact">Quelqu’un qui peut t’aider</label>
                        <select id="pf-contact" v-model="form.contact_id" class="input">
                            <option value="">Personne en particulier</option>
                            <option v-for="c in sortedContacts" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label" for="pf-date">Échéance (facultative)</label>
                        <input id="pf-date" v-model="form.target_date" class="input" type="date" />
                    </div>
                </template>
            </template>
        </Wizard>
    </Sheet>
</template>
