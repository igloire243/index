<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { DOCUMENT_CATEGORIES } from '../../lib/documents';
import { contacts } from '../../stores/contacts';
import { toast } from '../../stores/session';
import { saveDossier } from '../../stores/vault';
import Sheet from '../Sheet.vue';
import Wizard from '../Wizard.vue';

const props = defineProps({
    modelValue: Boolean,
    dossier: { type: Object, default: null },
    contactId: { type: Number, default: null },
    document: { type: Boolean, default: false }, // ouvert depuis l'onglet « Documents » : ajoute l'étape catégorie
});
const emit = defineEmits(['update:modelValue', 'saved']);
const open = computed({ get: () => props.modelValue, set: (v) => emit('update:modelValue', v) });

const form = reactive({ contact_id: '', subject: '', title: '', summary: '', category: '', document_number: '', issued_by: '', issue_date: '', expiry_date: '' });
const busy = ref(false);
const isDocument = computed(() => props.document || !!props.dossier?.category);

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
            category: d?.category ?? '',
            document_number: d?.document_number ?? '',
            issued_by: d?.issued_by ?? '',
            issue_date: d?.issue_date ?? '',
            expiry_date: d?.expiry_date ?? '',
        });
    },
);

const contactName = computed(() => contacts.list.find((c) => c.id === Number(form.contact_id))?.name ?? '');
const sortedContacts = computed(() => [...contacts.list].sort((a, b) => a.name.localeCompare(b.name, 'fr')));
const steps = computed(() => [
    { key: 'who', title: 'Concerne' },
    { key: 'what', title: isDocument.value ? 'Document' : 'Dossier', valid: form.title.trim().length > 0, hint: `Donne un titre au ${isDocument.value ? 'document' : 'dossier'} pour continuer.` },
    ...(isDocument.value ? [{ key: 'admin', title: 'Détails', valid: form.category !== '', hint: 'Choisis une catégorie pour continuer.' }] : []),
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
                category: isDocument.value ? form.category || null : null,
                document_number: isDocument.value ? form.document_number.trim() || null : null,
                issued_by: isDocument.value ? form.issued_by.trim() || null : null,
                issue_date: isDocument.value ? form.issue_date || null : null,
                expiry_date: isDocument.value ? form.expiry_date || null : null,
            },
            props.dossier?.id ?? null,
        );
        open.value = false;
        toast(props.dossier ? (isDocument.value ? 'Document mis à jour.' : 'Dossier mis à jour.') : isDocument.value ? 'Document créé.' : 'Dossier créé.');
        emit('saved', saved);
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Sheet v-model="open" :title="dossier ? (isDocument ? 'Modifier le document' : 'Modifier le dossier') : isDocument ? 'Nouveau document' : 'Nouveau dossier'">
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

                <template v-else-if="step.key === 'admin'">
                    <div>
                        <label class="label" for="do-category">Catégorie *</label>
                        <select id="do-category" v-model="form.category" class="input">
                            <option value="">Choisir…</option>
                            <option v-for="(c, key) in DOCUMENT_CATEGORIES" :key="key" :value="key">{{ c.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label" for="do-number">Numéro de pièce</label>
                        <input id="do-number" v-model="form.document_number" class="input" maxlength="120" autocomplete="off" />
                        <p class="muted mt-1 text-xs">Chiffré dans la base de données, comme le résumé.</p>
                    </div>
                    <div>
                        <label class="label" for="do-issuer">Émis par</label>
                        <input id="do-issuer" v-model="form.issued_by" class="input" maxlength="160" autocomplete="off" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="label" for="do-issue-date">Date d’émission</label>
                            <input id="do-issue-date" v-model="form.issue_date" class="input" type="date" />
                        </div>
                        <div>
                            <label class="label" for="do-expiry-date">Date d’expiration</label>
                            <input id="do-expiry-date" v-model="form.expiry_date" class="input" type="date" />
                        </div>
                    </div>
                    <p v-if="form.expiry_date" class="muted text-xs">Un rappel sera envoyé 30 jours avant cette date.</p>
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
