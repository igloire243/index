<script setup>
import { onMounted, ref } from 'vue';
import { api } from '../api';
import { fmtFull } from '../lib/dates';
import { passkeysSupported, setLocalPasskey } from '../lib/passkeys';
import { toast } from '../stores/session';
import Icon from './Icon.vue';
import PasskeyForm from './PasskeyForm.vue';

const supported = passkeysSupported();
const list = ref([]);
const formOpen = ref(false);

async function load() {
    try {
        list.value = await api('passkeys');
    } catch (e) {
        toast(e.message, 'error');
    }
}
onMounted(load);

async function remove(p) {
    if (!confirm(`Retirer « ${p.name} » ? Cet appareil ne pourra plus se connecter avec Face ID / empreinte.`)) return;
    try {
        await api(`passkeys/${p.id}`, { method: 'DELETE' });
        list.value = list.value.filter((x) => x.id !== p.id);
        if (!list.value.length) setLocalPasskey(false);
        toast('Appareil retiré.');
    } catch (e) {
        toast(e.message, 'error');
    }
}
</script>

<template>
    <section class="card space-y-3" aria-label="Face ID / empreinte">
        <h2 class="font-semibold">Face ID / empreinte</h2>
        <p class="muted">Connecte-toi d’un geste, sans mot de passe, avec la reconnaissance faciale ou l’empreinte de ton téléphone. À activer sur chaque appareil, après t’être connecté une première fois avec ton mot de passe.</p>

        <p v-if="!supported" class="rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-950 dark:text-amber-200">
            Indisponible ici : cette fonction exige une connexion sécurisée (HTTPS) et un nom de domaine, pas une adresse IP.
        </p>
        <template v-else>
            <TransitionGroup name="list" tag="ul" class="relative space-y-2">
                <li v-for="p in list" :key="p.id" class="flex items-center gap-3 rounded-xl bg-slate-50 p-3 dark:bg-slate-800">
                    <Icon name="face" />
                    <div class="min-w-0 flex-1 text-sm">
                        <div class="font-medium break-words">{{ p.name }}</div>
                        <div class="muted">Ajouté le {{ fmtFull(p.created_at.slice(0, 10)) }}<template v-if="p.last_used_at"> · utilisé le {{ fmtFull(p.last_used_at.slice(0, 10)) }}</template></div>
                    </div>
                    <button type="button" class="btn btn-danger size-10 !p-0" :aria-label="`Retirer ${p.name}`" @click="remove(p)"><Icon name="trash" :size="18" /></button>
                </li>
            </TransitionGroup>
            <button type="button" class="btn w-full" @click="formOpen = true"><Icon name="plus" :size="18" /> Ajouter cet appareil</button>
        </template>

        <PasskeyForm v-model="formOpen" @saved="load" />
    </section>
</template>
