<script setup>
import { RouterLink, RouterView, useRoute } from 'vue-router';

const route = useRoute();
const onProjects = () => route.name === 'coach-projets' || route.name === 'coach-projet';
</script>

<template>
    <div class="space-y-4">
        <nav class="flex gap-2" aria-label="Sections du Coach">
            <RouterLink to="/coach" class="chip" :class="{ 'chip-on': route.name === 'coach' }" :aria-current="route.name === 'coach' ? 'page' : undefined">Suggestions</RouterLink>
            <RouterLink to="/coach/projets" class="chip" :class="{ 'chip-on': onProjects() }" :aria-current="onProjects() ? 'page' : undefined">Projets</RouterLink>
            <RouterLink to="/coach/cv" class="chip" :class="{ 'chip-on': route.name === 'coach-cv' }" :aria-current="route.name === 'coach-cv' ? 'page' : undefined">CV</RouterLink>
        </nav>

        <RouterView v-slot="{ Component }">
            <Transition name="page" mode="out-in">
                <component :is="Component" />
            </Transition>
        </RouterView>
    </div>
</template>
