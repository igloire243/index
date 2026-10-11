<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { allDomains, norm, parseDomains } from '../lib/contacts';
import { contacts, geocode, locate, reverseGeocode, saveContact, ui } from '../stores/contacts';
import { toast } from '../stores/session';
import Sheet from './Sheet.vue';
import Wizard from './Wizard.vue';

const empty = () => ({
    name: '', phone: '', email: '', domains: '', role: '', place: '', lat: null, lng: null,
    how: '', anecdote: '', notes: '', favor: '', last_contact: '',
});
const form = reactive(empty());
const busy = ref(false);
const geoStatus = ref('');

const open = computed({
    get: () => ui.form.open,
    set: (v) => (ui.form.open = v),
});
const editing = computed(() => contacts.list.find((c) => c.id === ui.form.id) || null);

watch(
    () => ui.form.open,
    (isOpen) => {
        if (!isOpen) return;
        const c = editing.value;
        Object.assign(form, empty(), c ? { ...c, domains: c.domains.join(', '), favor: c.favor || '', last_contact: c.last_contact || '' } : {});
        if (!c && ui.form.prefill?.domains) form.domains = ui.form.prefill.domains.join(', ');
        for (const k of ['phone', 'email', 'role', 'place', 'how', 'anecdote', 'notes']) form[k] ??= '';
        updateGeoStatus();
    },
);

const steps = computed(() => [
    { key: 'identity', title: 'Identité', valid: form.name.trim().length > 0, hint: 'Indique un nom pour continuer.' },
    { key: 'where', title: 'Joindre' },
    { key: 'story', title: 'Histoire' },
    { key: 'relation', title: 'Relation' },
]);

const current = computed(() => new Set(parseDomains(form.domains).map(norm)));
const suggestions = computed(() => allDomains(contacts.list));
const domainList = computed(() => parseDomains(form.domains));

function toggleDomain(d) {
    const list = parseDomains(form.domains).filter((x) => norm(x) !== d.key);
    if (!current.value.has(d.key)) list.push(d.label);
    form.domains = list.join(', ');
}

function updateGeoStatus() {
    geoStatus.value = Number.isFinite(form.lat) ? `Localisé (${form.lat.toFixed(3)}, ${form.lng.toFixed(3)})` : 'Pas encore localisé';
}

// Modifier le lieu invalide les coordonnées : elles seront recalculées à l'enregistrement.
function onPlaceInput() {
    form.lat = form.lng = null;
    updateGeoStatus();
}

async function geocodePlace(announce) {
    const q = form.place.trim();
    if (!q) {
        if (announce) toast('Écris d’abord un lieu.');
        return;
    }
    geoStatus.value = 'Recherche…';
    try {
        const hit = await geocode(q);
        if (hit) Object.assign(form, hit);
        else if (announce) toast('Lieu introuvable. Essaie avec la ville.');
    } catch {
        if (announce) toast('Recherche impossible (connexion ?).', 'error');
    }
    updateGeoStatus();
}

async function useHere() {
    geoStatus.value = 'Recherche de ta position…';
    const p = await locate();
    if (!p) {
        updateGeoStatus();
        toast(contacts.posError, 'error');
        return;
    }
    form.lat = p.lat;
    form.lng = p.lng;
    updateGeoStatus();
    if (!form.place.trim()) {
        try {
            form.place = await reverseGeocode(p.lat, p.lng);
        } catch {
            /* le lieu reste vide */
        }
    }
}

async function submit() {
    busy.value = true;
    try {
        if (form.place.trim() && !Number.isFinite(form.lat)) await geocodePlace(false);
        await saveContact({ ...form, domains: parseDomains(form.domains) }, editing.value?.id ?? null);
        open.value = false;
        toast(editing.value ? 'Contact mis à jour.' : 'Contact ajouté.');
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Sheet v-model="open" :title="editing ? 'Modifier le contact' : 'Nouveau contact'">
        <Wizard :steps="steps" :busy="busy" submit-label="Enregistrer le contact" @submit="submit" @cancel="open = false">
            <template #default="{ step }">
                <template v-if="step.key === 'identity'">
                    <div>
                        <label class="label" for="c-name">Nom *</label>
                        <input id="c-name" v-model="form.name" class="input" required maxlength="120" autocapitalize="words" autocomplete="off" />
                    </div>
                    <div>
                        <label class="label" for="c-role">Rôle / profession</label>
                        <input id="c-role" v-model="form.role" class="input" placeholder="Avocat, plombier, directeur de banque…" maxlength="120" autocomplete="off" />
                    </div>
                    <div>
                        <label class="label" for="c-domains">Domaines <span class="muted">(séparés par des virgules)</span></label>
                        <input id="c-domains" v-model="form.domains" class="input" placeholder="juridique, santé, immobilier…" maxlength="300" autocomplete="off" />
                        <div v-if="suggestions.length" class="mt-2 flex flex-wrap gap-2">
                            <button v-for="d in suggestions" :key="d.key" type="button" class="chip" :class="{ 'chip-on': current.has(d.key) }" @click="toggleDomain(d)">
                                {{ d.label }}
                            </button>
                        </div>
                    </div>
                </template>

                <template v-else-if="step.key === 'where'">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="label" for="c-phone">Téléphone</label>
                            <input id="c-phone" v-model="form.phone" class="input" type="tel" inputmode="tel" placeholder="+243 …" maxlength="40" autocomplete="off" />
                        </div>
                        <div>
                            <label class="label" for="c-email">E-mail</label>
                            <input id="c-email" v-model="form.email" class="input" type="email" inputmode="email" maxlength="120" autocomplete="off" />
                        </div>
                    </div>
                    <div>
                        <label class="label" for="c-place">Lieu <span class="muted">(ville ou adresse)</span></label>
                        <input id="c-place" v-model="form.place" class="input" placeholder="Kinshasa, Gombe" maxlength="200" autocomplete="off" @input="onPlaceInput" />
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <button type="button" class="btn btn-sm" @click="geocodePlace(true)">Localiser</button>
                            <button type="button" class="btn btn-sm" @click="useHere">Ma position actuelle</button>
                            <span class="muted">{{ geoStatus }}</span>
                        </div>
                    </div>
                </template>

                <template v-else-if="step.key === 'story'">
                    <div>
                        <label class="label" for="c-how">Comment on s’est connus</label>
                        <input id="c-how" v-model="form.how" class="input" maxlength="200" autocomplete="off" />
                    </div>
                    <div>
                        <label class="label" for="c-anecdote">Anecdote / histoire à retenir</label>
                        <textarea id="c-anecdote" v-model="form.anecdote" class="input" rows="3" maxlength="1000" placeholder="Situation, tension, chute…" />
                    </div>
                    <div>
                        <label class="label" for="c-notes">Notes</label>
                        <textarea id="c-notes" v-model="form.notes" class="input" rows="2" maxlength="2000" />
                    </div>
                </template>

                <template v-else>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="label" for="c-favor">Services</label>
                            <select id="c-favor" v-model="form.favor" class="input">
                                <option value="">Rien en cours</option>
                                <option value="owed">Cette personne me doit un service</option>
                                <option value="owe">Je lui dois un service</option>
                            </select>
                        </div>
                        <div>
                            <label class="label" for="c-last">Dernier contact</label>
                            <input id="c-last" v-model="form.last_contact" class="input" type="date" />
                        </div>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-3 text-sm dark:bg-slate-800">
                        <div class="font-semibold">{{ form.name || 'Sans nom' }}</div>
                        <div v-if="form.role" class="muted">{{ form.role }}</div>
                        <div class="mt-1 flex flex-wrap gap-1"><span v-for="d in domainList" :key="d" class="tag !bg-white dark:!bg-slate-900">{{ d }}</span></div>
                        <div v-if="form.phone || form.place" class="muted mt-1">{{ [form.phone, form.place].filter(Boolean).join(' · ') }}</div>
                    </div>
                </template>
            </template>
        </Wizard>
    </Sheet>
</template>
