<script setup>
import { computed } from 'vue';
import SceneCoach from './illustrations/SceneCoach.vue';
import SceneAssets from './illustrations/SceneAssets.vue';
import SceneContacts from './illustrations/SceneContacts.vue';
import SceneFinance from './illustrations/SceneFinance.vue';
import SceneHealth from './illustrations/SceneHealth.vue';
import SceneLogin from './illustrations/SceneLogin.vue';
import SceneMap from './illustrations/SceneMap.vue';
import SceneMeals from './illustrations/SceneMeals.vue';
import SceneProgress from './illustrations/SceneProgress.vue';
import SceneSecurity from './illustrations/SceneSecurity.vue';
import SceneVault from './illustrations/SceneVault.vue';

const SCENES = {
    login: SceneLogin,
    contacts: SceneContacts,
    map: SceneMap,
    coach: SceneCoach,
    health: SceneHealth,
    meals: SceneMeals,
    progress: SceneProgress,
    security: SceneSecurity,
    finance: SceneFinance,
    assets: SceneAssets,
    vault: SceneVault,
};

const props = defineProps({ name: { type: String, required: true } });
const scene = computed(() => SCENES[props.name]);
</script>

<template>
    <!-- Décoration : le texte voisin porte le sens, donc masquée pour les lecteurs d'écran. -->
    <svg viewBox="0 0 320 210" class="h-auto w-full select-none" aria-hidden="true" focusable="false">
        <component :is="scene" />
    </svg>
</template>
