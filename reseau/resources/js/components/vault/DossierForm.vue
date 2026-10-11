<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { contacts } from '../../stores/contacts';
import { toast } from '../../stores/session';
import { saveDossier } from '../../stores/vault';
import Sheet from '../Sheet.vue';
import Wizard from '../Wizard.vue';

const props = defineProps({ modelValue: Boolean, dossier: { type: Object, default: null }, contactId: { type: Number, default: null } });
const emit = defineEmits(['update:modelValue', 'saved']);
const open = computed({ get: () => props.modelValue, set: (v) => emit('update:modelValue', v) });

const form = reactive({ contact_id: '', subject: '', title: '', summary: '' });
const busy = ref(false);

watch(
    () => props.modelValue,
    (isOpen) => {
        if (!isOpen) return;
        const d = props.dossier;
        Object.assign(form, {
            contact_id: d?.contact_id ?? props.contactId ?? '',
            subject: d?.subject ?? '',
            title: d?.title ?? '',
            summary: d?.summary ?? '',
        });
    },
);

const contactName = computed(() => contacts.list.find((c) => c.id === Number(form.contact_id))?.name ?? '');
const sortedContacts = computed(() => [...contacts.list].sort((a, b) => a.name.localeCompare(b.name, 'fr')));
const steps = computed(() => [
    { key: 'who', title: 'Concerne' },
    { key: 'what', title: 'Dossier', valid: form.title.trim().length > 0, hint: 'Donne un titre au dossier pour continuer.' },
    { key: 'notes', title: 'Résumé' },
]);

async function submit() {
    busy.value = true;
    try {
        const saved = await saveDossier(
            {
                title: form.title.trim(),
                subject: form.subject.trim() || null,
                contact_id: form.contact_id === '' ? null : Number(form.contact_id),
                summary: form.summary.trim() || null,
            },
            props.dossier?.id ?? null,
        );
        open.value = false;
        toast(props.dossier ? 'Dossier mis à jour.' : 'Dossier créé.');
        emit('saved', saved);
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Sheet v-model="open" :title="dossier ? 'Modifier le dossier' : 'Nouveau dossier'">
        <Wizard :steps="steps" :busy="busy" submit-label="Enregistrer le dossier" @submit="submit" @cancel="open = false">
            <template #default="{ step }">
                <template v-if="step.key === 'who'">
                    <div>
                        <label class="label" for="do-contact">Contact concerné</label>
                        <select id="do-contact" v-model="form.contact_id" class="input">
                            <option value="">Aucun contact en particulier</option>
                            <option v-for="c in sortedContacts" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div v-if="form.contact_id === ''">
                        <label class="label" for="do-subject">Ou une personne / un sujet</label>
                        <input id="do-subject" v-model="form.subject" class="input" maxlength="120" placeholder="Un nom, une société, un sujet…" autocomplete="off" />
                    </div>
                </template>

                <template v-else-if="step.key === 'what'">
                    <div>
                        <label class="label" for="do-title">Titre *</label>
                        <input id="do-title" v-model="form.title" class="input" maxlength="160" placeholder="Vérifications, contrat, rendez-vous…" autocomplete="off" />
                    </div>
                    <p class="muted text-xs">Tu pourras ensuite y ajouter photos, vidéos, enregistrements audio et documents.</p>
                </template>

                <template v-else>
                    <div>
                        <label class="label" for="do-summary">Résumé et notes confidentielles</label>
                        <textarea id="do-summary" v-model="form.summary" class="input" rows="5" maxlength="20000" />
                        <p class="muted mt-1 text-xs">Ce texte est chiffré dans la base de données.</p>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-3 text-sm dark:bg-slate-800">
                        <div class="font-semibold">{{ form.title || 'Sans titre' }}</div>
                        <div class="muted">{{ contactName || form.subject || 'Sans personne associée' }}</div>
                    </div>
                </template>
            </template>
        </Wizard>
    </Sheet>
</template>
