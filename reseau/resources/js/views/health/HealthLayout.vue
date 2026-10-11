<script setup>
import { onMounted } from 'vue';
import { RouterLink, RouterView, useRoute } from 'vue-router';
import { loadHealth } from '../../stores/health';
import { toast } from '../../stores/session';

const route = useRoute();
const tabs = [
    { to: '/sante', name: 'sante', label: 'Aujourd’hui' },
    { to: '/sante/planning', name: 'sante-planning', label: 'Planning' },
    { to: '/sante/repas', name: 'sante-repas', label: 'Repas' },
    { to: '/sante/suivi', name: 'sante-suivi', label: 'Suivi' },
    { to: '/sante/programmes', name: 'sante-programmes', label: 'Programmes' },
];

onMounted(() => loadHealth().catch((e) => toast(e.message, 'error')));
</script>

<template>
    <div class="space-y-4">
        <nav class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1" aria-label="Santé">
            <RouterLink
                v-for="t in tabs"
                :key="t.name"
                :to="t.to"
                class="chip"
                :class="{ 'chip-on': route.name === t.name }"
                :aria-current="route.name === t.name ? 'page' : undefined"
            >
                {{ t.label }}
            </RouterLink>
        </nav>

        <RouterView />

        <p class="muted pt-2 text-xs">
            Cet outil t’aide à suivre tes habitudes. Il ne remplace pas un avis médical : en cas de perte ou de prise de poids involontaire, de fatigue
            persistante ou de douleur, consulte un professionnel de santé.
        </p>
    </div>
</template>
