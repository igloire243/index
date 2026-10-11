<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { daysSince, fmtDist, hasCoords, phoneHref, smsHref, waHref } from '../lib/contacts';
import { fmtFull, isoDate } from '../lib/dates';
import { contacts, deleteContact, distTo, openForm, saveContact, startRoute, ui } from '../stores/contacts';
import { toast } from '../stores/session';
import Avatar from './Avatar.vue';
import Icon from './Icon.vue';
import Sheet from './Sheet.vue';

const RELANCE_DAYS = 90;
const router = useRouter();
const contact = computed(() => contacts.list.find((c) => c.id === ui.detailId) || null);
const open = computed({
    get: () => contact.value !== null,
    set: (v) => {
        if (!v) ui.detailId = null;
    },
});
const since = computed(() => daysSince(contact.value?.last_contact));
const dist = computed(() => (contact.value ? distTo(contact.value) : null));
const favorText = computed(() => ({ owed: 'Cette personne te doit un service', owe: 'Tu dois un service à cette personne' })[contact.value?.favor]);

function goRoute() {
    const id = contact.value.id;
    ui.detailId = null;
    startRoute(id);
    router.push('/contacts/carte'); // l'itinéraire s'affiche dans l'application, sur la carte
}

function goDossiers() {
    const id = contact.value.id;
    ui.detailId = null;
    router.push({ path: '/coffre', query: { contact: id } }); // le coffre demande le mot de passe avant d'afficher quoi que ce soit
}

async function markContacted() {
    try {
        await saveContact({ ...contact.value, last_contact: isoDate() }, contact.value.id);
        toast('Noté : contacté aujourd’hui.');
    } catch (e) {
        toast(e.message, 'error');
    }
}

async function remove() {
    if (!confirm(`Supprimer ${contact.value.name} ?`)) return;
    try {
        await deleteContact(contact.value.id);
        ui.detailId = null;
        toast('Contact supprimé.');
    } catch (e) {
        toast(e.message, 'error');
    }
}
</script>

<template>
    <Sheet v-model="open" :title="undefined">
        <div v-if="contact" class="space-y-4">
            <div class="flex items-center gap-4">
                <Avatar :name="contact.name" size="lg" />
                <div class="min-w-0">
                    <h2 class="text-xl font-semibold break-words">{{ contact.name }}</h2>
                    <div v-if="contact.role" class="muted">{{ contact.role }}</div>
                </div>
            </div>

            <div class="flex flex-wrap gap-1">
                <span v-for="d in contact.domains" :key="d" class="tag">{{ d }}</span>
                <span v-if="favorText" class="tag text-amber-700 dark:text-amber-400">{{ favorText }}</span>
                <span v-if="since != null && since >= RELANCE_DAYS" class="tag text-amber-700 dark:text-amber-400">À relancer</span>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <a v-if="contact.phone" class="btn btn-primary" :href="phoneHref(contact.phone)">Appeler</a>
                <a v-if="contact.phone" class="btn" :href="smsHref(contact.phone)">SMS</a>
                <a v-if="contact.phone" class="btn" :href="waHref(contact.phone)" target="_blank" rel="noopener">WhatsApp</a>
                <a v-if="contact.email" class="btn" :href="'mailto:' + encodeURI(contact.email)">E-mail</a>
                <button v-if="hasCoords(contact)" type="button" class="btn" @click="goRoute">Itinéraire</button>
                <button type="button" class="btn" @click="goDossiers"><Icon name="lock" :size="16" /> Dossier confidentiel</button>
            </div>

            <dl class="selectable space-y-3 text-sm">
                <div v-if="contact.phone"><dt class="muted">Téléphone</dt><dd>{{ contact.phone }}</dd></div>
                <div v-if="contact.email"><dt class="muted">E-mail</dt><dd class="break-all">{{ contact.email }}</dd></div>
                <div v-if="contact.place">
                    <dt class="muted">Lieu</dt>
                    <dd>{{ contact.place }}<span v-if="dist != null"> — à {{ fmtDist(dist) }}</span></dd>
                </div>
                <div v-if="contact.how"><dt class="muted">Comment on s’est connus</dt><dd>{{ contact.how }}</dd></div>
                <div v-if="contact.anecdote"><dt class="muted">Anecdote</dt><dd class="whitespace-pre-wrap">{{ contact.anecdote }}</dd></div>
                <div v-if="contact.notes"><dt class="muted">Notes</dt><dd class="whitespace-pre-wrap">{{ contact.notes }}</dd></div>
                <div>
                    <dt class="muted">Dernier contact</dt>
                    <dd>{{ contact.last_contact ? fmtFull(contact.last_contact) + (since != null ? ` (il y a ${since} j)` : '') : 'Jamais noté' }}</dd>
                </div>
            </dl>

            <div class="flex flex-wrap justify-between gap-2 pt-2">
                <button type="button" class="btn" @click="markContacted">Contacté aujourd’hui</button>
                <span class="flex gap-2">
                    <button type="button" class="btn" @click="openForm(contact.id)">Modifier</button>
                    <button type="button" class="btn btn-danger" @click="remove">Supprimer</button>
                </span>
            </div>
            <button type="button" class="btn w-full" @click="open = false">Fermer</button>
        </div>
    </Sheet>
</template>
