<script setup>
import { RouterLink, RouterView, useRoute } from 'vue-router';

const route = useRoute();
const tabs = [
    { to: '/contacts', name: 'contacts', label: 'Liste' },
    { to: '/contacts/carte', name: 'carte', label: 'Carte' },
    { to: '/contacts/domaines', name: 'domaines', label: 'Domaines' },
];
</script>

<template>
    <div class="space-y-4">
        <nav class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1" aria-label="Contacts">
            <RouterLink v-for="t in tabs" :key="t.name" :to="t.to" class="chip" :class="{ 'chip-on': route.name === t.name }" :aria-current="route.name === t.name ? 'page' : undefined">
                {{ t.label }}
            </RouterLink>
        </nav>

        <RouterView v-slot="{ Component }">
            <Transition name="page" mode="out-in">
                <component :is="Component" />
            </Transition>
        </RouterView>
    </div>
</template>
