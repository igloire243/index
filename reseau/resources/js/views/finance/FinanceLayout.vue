<script setup>
import { onMounted } from 'vue';
import { RouterLink, RouterView, useRoute } from 'vue-router';
import { loadContacts } from '../../stores/contacts';
import { loadFinance } from '../../stores/finance';
import { toast } from '../../stores/session';

const route = useRoute();
const tabs = [
    { to: '/finances', name: 'finances', label: 'Vue d’ensemble' },
    { to: '/finances/fonds', name: 'finances-fonds', label: 'Fonds' },
    { to: '/finances/creances', name: 'finances-creances', label: 'On me doit' },
    { to: '/finances/dettes', name: 'finances-dettes', label: 'Je dois' },
    { to: '/finances/biens', name: 'finances-biens', label: 'Biens' },
];

onMounted(() => {
    loadFinance().catch((e) => toast(e.message, 'error'));
    loadContacts().catch(() => {}); // pour relier une créance à un contact
});
</script>

<template>
    <div class="space-y-4">
        <nav class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1" aria-label="Finances">
            <RouterLink v-for="t in tabs" :key="t.name" :to="t.to" class="chip" :class="{ 'chip-on': route.name === t.name }" :aria-current="route.name === t.name ? 'page' : undefined">
                {{ t.label }}
            </RouterLink>
        </nav>

        <RouterView v-slot="{ Component }">
            <Transition name="page" mode="out-in">
                <component :is="Component" />
            </Transition>
        </RouterView>

        <p class="muted pt-2 text-xs">Ces chiffres sont ceux que tu saisis : l’application ne se connecte à aucune banque. C’est un outil de suivi personnel, pas un conseil financier.</p>
    </div>
</template>
