<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import Icon from '../components/Icon.vue';
import { parseVcf } from '../lib/contacts';
import { isoDate } from '../lib/dates';
import { currentSubscription, disablePush, enablePush, pushSupported, sendTestPush } from '../lib/push';
import { contacts, deleteAllContacts, importContacts, loadContacts } from '../stores/contacts';
import { logout, session, toast } from '../stores/session';

const router = useRouter();
const fileInput = ref(null);
const subscribed = ref(false);
const busy = ref(false);
const supported = pushSupported();

onMounted(async () => {
    loadContacts().catch(() => {});
    try {
        subscribed.value = !!(await currentSubscription()) && Notification.permission === 'granted';
    } catch {
        subscribed.value = false;
    }
});

async function signOut() {
    await logout();
    router.replace('/login');
}

async function toggleReminders() {
    busy.value = true;
    try {
        if (subscribed.value) {
            await disablePush();
            subscribed.value = false;
            toast('Rappels désactivés sur cet appareil.');
        } else {
            await enablePush();
            subscribed.value = true;
            toast('Rappels activés sur cet appareil.');
        }
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        busy.value = false;
    }
}

async function test() {
    busy.value = true;
    try {
        const { sent } = await sendTestPush();
        toast(sent ? 'Notification envoyée.' : 'Aucun appareil n’a reçu la notification.', sent ? 'info' : 'error');
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        busy.value = false;
    }
}

async function onFile(e) {
    const file = e.target.files[0];
    e.target.value = '';
    if (!file) return;
    if (file.size > 20 * 1024 * 1024) return toast('Fichier trop volumineux.', 'error');
    try {
        const text = await file.text();
        let list;
        if (/\.vcf$/i.test(file.name) || /^\s*BEGIN:VCARD/i.test(text)) list = parseVcf(text);
        else {
            const data = JSON.parse(text);
            list = Array.isArray(data) ? data : data?.contacts;
            if (!Array.isArray(list)) throw new Error('format');
        }
        let added = 0;
        for (let i = 0; i < list.length; i += 1000) added += (await importContacts(list.slice(i, i + 1000))).added;
        toast(`${added} contact${added > 1 ? 's' : ''} ajouté${added > 1 ? 's' : ''}.`);
    } catch (err) {
        toast(err.message === 'format' || err instanceof SyntaxError ? 'Fichier illisible : choisis un .vcf ou une sauvegarde .json.' : err.message, 'error');
    }
}

function exportJson() {
    const blob = new Blob([JSON.stringify({ app: 'reseau', exported_at: new Date().toISOString(), contacts: contacts.list }, null, 2)], { type: 'application/json' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `reseau-contacts-${isoDate()}.json`;
    document.body.append(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(a.href), 2000);
}

async function wipe() {
    if (!confirm('Effacer TOUS tes contacts ? Pense à exporter une sauvegarde avant.')) return;
    try {
        await deleteAllContacts();
        toast('Contacts effacés.');
    } catch (e) {
        toast(e.message, 'error');
    }
}
</script>

<template>
    <div class="space-y-4">
        <section class="card space-y-3">
            <h2 class="font-semibold">Compte</h2>
            <p class="text-sm">
                {{ session.user?.name }}<br /><span class="muted">{{ session.user?.email }} · fuseau {{ session.user?.timezone }}</span>
            </p>
            <button type="button" class="btn w-full" @click="signOut"><Icon name="logout" :size="18" /> Se déconnecter</button>
        </section>

        <section class="card space-y-3">
            <h2 class="font-semibold">Rappels</h2>
            <p class="muted">Reçois une notification à l’heure de tes habitudes, même quand l’application est fermée. À activer sur chaque appareil.</p>
            <p v-if="!supported" class="rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-950 dark:text-amber-200">
                Ce navigateur ne gère pas les notifications. Sur iPhone, ajoute d’abord l’application à l’écran d’accueil (Partager → Sur l’écran d’accueil).
            </p>
            <template v-else>
                <button type="button" class="btn w-full" :class="subscribed ? '' : 'btn-primary'" :disabled="busy" @click="toggleReminders">
                    <Icon name="bell" :size="18" /> {{ subscribed ? 'Désactiver les rappels' : 'Activer les rappels' }}
                </button>
                <button v-if="subscribed" type="button" class="btn w-full" :disabled="busy" @click="test">Envoyer une notification de test</button>
            </template>
        </section>

        <section class="card space-y-3">
            <h2 class="font-semibold">Contacts</h2>
            <p class="muted">{{ contacts.list.length }} contact{{ contacts.list.length > 1 ? 's' : '' }} enregistré{{ contacts.list.length > 1 ? 's' : '' }}.</p>
            <input ref="fileInput" type="file" accept=".json,.vcf,application/json,text/vcard,text/x-vcard" class="hidden" @change="onFile" />
            <button type="button" class="btn w-full" @click="fileInput.click()"><Icon name="upload" :size="18" /> Importer (.vcf Google Contacts ou .json)</button>
            <button type="button" class="btn w-full" @click="exportJson"><Icon name="download" :size="18" /> Exporter une sauvegarde (.json)</button>
            <button type="button" class="btn btn-danger w-full" :disabled="!contacts.list.length" @click="wipe"><Icon name="trash" :size="18" /> Tout effacer</button>
        </section>
    </div>
</template>
