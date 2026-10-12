<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { COMMON_DOMAINS } from '../lib/coach';
import { findTemplate, PROJECT_TEMPLATES } from '../lib/projectTemplates';
import { contacts } from '../stores/contacts';
import { toast } from '../stores/session';
import { addStep, saveProject } from '../stores/projects';
import { loadDossiers, vault } from '../stores/vault';
import Sheet from './Sheet.vue';
import Wizard from './Wizard.vue';

const props = defineProps({ modelValue: Boolean, project: { type: Object, default: null } });
const emit = defineEmits(['update:modelValue', 'saved']);
const open = computed({ get: () => props.modelValue, set: (v) => emit('update:modelValue', v) });

const form = reactive({ title: '', domain: '', description: '', contact_id: '', dossier_id: '', target_date: '' });
const templateKey = ref('');
const busy = ref(false);

watch(
    () => props.modelValue,
    (isOpen) => {
        if (!isOpen) return;
        const p = props.project;
        templateKey.value = '';
        Object.assign(form, {
            title: p?.title ?? '', domain: p?.domain ?? '', description: p?.description ?? '',
            contact_id: p?.contact_id ?? '', dossier_id: p?.dossier_id ?? '', target_date: p?.target_date ?? '',
        });
        if (vault.unlocked) loadDossiers().catch(() => {});
    },
);

function pickTemplate(key) {
    templateKey.value = key;
    const t = findTemplate(key);
    if (t && !form.title.trim()) {
        form.title = t.title;
        form.domain = t.domain;
        form.description = t.description;
    }
}

const sortedContacts = computed(() => [...contacts.list].sort((a, b) => a.name.localeCompare(b.name, 'fr')));
const steps = computed(() => [
    ...(props.project ? [] : [{ key: 'modele', title: 'Modèle' }]),
    { key: 'idee', title: 'Idée', valid: form.title.trim().length > 0, hint: 'Donne un titre à ton projet ou ton idée pour continuer.' },
    { key: 'qui', title: 'Qui peut aider' },
]);

async function submit() {
    busy.value = true;
    try {
        const saved = await saveProject(
            {
                title: form.title.trim(), domain: form.domain.trim() || null, description: form.description.trim() || null,
                contact_id: form.contact_id === '' ? null : Number(form.contact_id), dossier_id: form.dossier_id === '' ? null : Number(form.dossier_id),
                target_date: form.target_date || null,
            },
            props.project?.id ?? null,
        );

        const template = !props.project && templateKey.value ? findTemplate(templateKey.value) : null;
        if (template) {
            for (const title of template.steps) await addStep(saved.id, title); // dans l'ordre, l'une après l'autre
        }

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
                <template v-if="step.key === 'modele'">
                    <p class="muted text-sm">Pars d’un modèle avec des étapes déjà suggérées, ou commence d’une page blanche.</p>
                    <div class="grid grid-cols-2 gap-2" role="radiogroup" aria-label="Modèle de projet">
                        <button
                            type="button" role="radio" :aria-checked="templateKey === ''"
                            class="rounded-2xl border-2 p-3 text-left text-sm"
                            :class="templateKey === '' ? 'border-indigo-600 bg-indigo-50 dark:border-indigo-400 dark:bg-indigo-950/50' : 'border-slate-200 dark:border-slate-700'"
                            @click="pickTemplate('')"
                        >
                            Page blanche
                        </button>
                        <button
                            v-for="t in PROJECT_TEMPLATES" :key="t.key" type="button" role="radio" :aria-checked="templateKey === t.key"
                            class="rounded-2xl border-2 p-3 text-left text-sm"
                            :class="templateKey === t.key ? 'border-indigo-600 bg-indigo-50 dark:border-indigo-400 dark:bg-indigo-950/50' : 'border-slate-200 dark:border-slate-700'"
                            @click="pickTemplate(t.key)"
                        >
                            {{ t.title }}
                        </button>
                    </div>
                    <p v-if="templateKey" class="muted text-xs">{{ findTemplate(templateKey).steps.length }} étapes seront ajoutées automatiquement.</p>
                </template>

                <template v-else-if="step.key === 'idee'">
                    <div>
                        <label class="label" for="pf-title">Titre *</label>
                        <input id="pf-title" v-model="form.title" class="input" maxlength="160" placeholder="Ouvrir un salon de coiffure, apprendre l’anglais…" autocomplete="off" data-autofocus />
                    </div>
                    <div>
                        <label class="label" for="pf-domain">Domaine (facultatif)</label>
                        <input id="pf-domain" v-model="form.domain" class="input" list="pf-domains" maxlength="40" placeholder="commerce, santé, éducation…" autocomplete="off" />
                        <datalist id="pf-domains"><option v-for="d in COMMON_DOMAINS" :key="d" :value="d" /></datalist>
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
                    <div v-if="vault.unlocked && vault.dossiers.length">
                        <label class="label" for="pf-dossier">Lier un dossier du Coffre</label>
                        <select id="pf-dossier" v-model="form.dossier_id" class="input">
                            <option value="">Aucun</option>
                            <option v-for="d in vault.dossiers" :key="d.id" :value="d.id">{{ d.title }}</option>
                        </select>
                    </div>
                    <p v-else-if="!vault.unlocked" class="muted text-xs">Déverrouille le Coffre pour pouvoir lier un dossier à ce projet.</p>
                    <div>
                        <label class="label" for="pf-date">Échéance (facultative)</label>
                        <input id="pf-date" v-model="form.target_date" class="input" type="date" />
                    </div>
                </template>
            </template>
        </Wizard>
    </Sheet>
</template>
