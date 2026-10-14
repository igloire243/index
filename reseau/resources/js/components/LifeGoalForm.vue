<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { AGE_BRACKETS, DOMAINS, STATUSES } from '../lib/lifeGoals';
import { saveLifeGoal } from '../stores/lifeGoals';
import { loadProjects, projects } from '../stores/projects';
import { toast } from '../stores/session';
import Sheet from './Sheet.vue';
import Wizard from './Wizard.vue';

const props = defineProps({ modelValue: Boolean, goal: { type: Object, default: null } });
const emit = defineEmits(['update:modelValue']);
const open = computed({ get: () => props.modelValue, set: (v) => emit('update:modelValue', v) });

const form = reactive({ title: '', domain: '', age_bracket: '', status: 'none', target_date: '', project_id: '', notes: '' });
const busy = ref(false);

watch(
    () => props.modelValue,
    (isOpen) => {
        if (!isOpen) return;
        const g = props.goal;
        Object.assign(form, {
            title: g?.title ?? '', domain: g?.domain ?? '', age_bracket: g?.age_bracket ?? '',
            status: g?.status ?? 'none', target_date: g?.target_date ?? '', project_id: g?.project_id ?? '', notes: g?.notes ?? '',
        });
        loadProjects().catch(() => {});
    },
);

const sortedProjects = computed(() => [...projects.list].filter((p) => p.status !== 'abandoned').sort((a, b) => a.title.localeCompare(b.title, 'fr')));
const steps = computed(() => [
    { key: 'what', title: 'Quoi', valid: form.title.trim().length > 0 && form.domain !== '' && form.age_bracket !== '', hint: 'Choisis un titre, un domaine et une tranche d’âge pour continuer.' },
    { key: 'details', title: 'Détails' },
]);

async function submit() {
    busy.value = true;
    try {
        await saveLifeGoal(
            {
                title: form.title.trim(), domain: form.domain, age_bracket: form.age_bracket, status: form.status,
                target_date: form.target_date || null, project_id: form.project_id === '' ? null : Number(form.project_id),
                notes: form.notes.trim() || null,
            },
            props.goal?.id ?? null,
        );
        open.value = false;
        toast(props.goal ? 'Mis à jour.' : 'Ajouté.');
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Sheet v-model="open" :title="goal ? 'Modifier l’accomplissement' : 'Nouvel accomplissement'">
        <Wizard :steps="steps" :busy="busy" submit-label="Enregistrer" @submit="submit" @cancel="open = false">
            <template #default="{ step }">
                <template v-if="step.key === 'what'">
                    <div>
                        <label class="label" for="lg-title">Titre *</label>
                        <input id="lg-title" v-model="form.title" class="input" maxlength="160" placeholder="Construire ma maison, apprendre le piano…" autocomplete="off" data-autofocus />
                    </div>
                    <div>
                        <label class="label" for="lg-domain">Domaine *</label>
                        <select id="lg-domain" v-model="form.domain" class="input">
                            <option value="">Choisir…</option>
                            <option v-for="(label, key) in DOMAINS" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label" for="lg-bracket">À quel âge ? *</label>
                        <select id="lg-bracket" v-model="form.age_bracket" class="input">
                            <option value="">Choisir…</option>
                            <option v-for="(label, key) in AGE_BRACKETS" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </div>
                </template>

                <template v-else>
                    <div>
                        <label class="label" for="lg-status">Où j’en suis</label>
                        <select id="lg-status" v-model="form.status" class="input">
                            <option v-for="(label, key) in STATUSES" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </div>
                    <div v-if="form.status === 'planned'">
                        <label class="label" for="lg-date">Échéance visée (facultative)</label>
                        <input id="lg-date" v-model="form.target_date" class="input" type="date" />
                    </div>
                    <div>
                        <label class="label" for="lg-project">Lier à un de mes projets</label>
                        <select id="lg-project" v-model="form.project_id" class="input">
                            <option value="">Aucun</option>
                            <option v-for="p in sortedProjects" :key="p.id" :value="p.id">{{ p.title }}</option>
                        </select>
                        <p class="muted mt-1 text-xs">Sa progression s’affichera ici, sans rien dupliquer.</p>
                    </div>
                    <div>
                        <label class="label" for="lg-notes">Notes</label>
                        <textarea id="lg-notes" v-model="form.notes" class="input" rows="2" maxlength="2000" />
                    </div>
                </template>
            </template>
        </Wizard>
    </Sheet>
</template>
