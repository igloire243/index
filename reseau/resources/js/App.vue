<script setup>
import { computed, watch } from 'vue';
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router';
import ContactForm from './components/ContactForm.vue';
import ContactSheet from './components/ContactSheet.vue';
import Icon from './components/Icon.vue';
import Toast from './components/Toast.vue';
import { openForm } from './stores/contacts';
import { session } from './stores/session';

const route = useRoute();
const router = useRouter();

const tabs = [
    { to: '/contacts', label: 'Contacts', icon: 'users' },
    { to: '/carte', label: 'Carte', icon: 'pin' },
    { to: '/domaines', label: 'Domaines', icon: 'grid' },
    { to: '/coach', label: 'Coach', icon: 'target' },
    { to: '/sante', label: 'Santé', icon: 'heart' },
];

const title = computed(() => route.meta.title || 'Réseau');
const isActive = (to) => route.path === to || route.path.startsWith(to + '/');

// Session expirée pendant l'utilisation : retour à l'écran de connexion.
watch(
    () => session.user,
    (user) => {
        if (!user && route.name !== 'login') router.replace({ name: 'login', query: { redirect: route.fullPath } });
    },
);
</script>

<template>
    <template v-if="session.user">
        <header
            class="sticky top-0 z-20 border-b border-slate-200 bg-slate-50 pt-[env(safe-area-inset-top)] dark:border-slate-800 dark:bg-slate-950"
        >
            <div class="mx-auto flex h-14 max-w-2xl items-center justify-between px-4">
                <h1 class="text-lg font-semibold tracking-tight">{{ title }}</h1>
                <RouterLink
                    to="/reglages"
                    class="grid size-11 place-items-center rounded-full text-slate-600 hover:bg-slate-200/60 dark:text-slate-300 dark:hover:bg-slate-800"
                    aria-label="Réglages"
                >
                    <Icon name="settings" />
                </RouterLink>
            </div>
        </header>

        <main class="mx-auto max-w-2xl px-4 py-4">
            <RouterView v-slot="{ Component }">
                <Transition name="page" mode="out-in">
                    <component :is="Component" />
                </Transition>
            </RouterView>
        </main>

        <Transition name="pop">
            <button
                v-if="route.meta.fab"
                type="button"
                class="fixed right-4 bottom-[calc(5rem+env(safe-area-inset-bottom))] z-20 grid size-14 place-items-center rounded-full bg-indigo-600 text-white shadow-lg transition-transform active:scale-90 dark:bg-indigo-500"
                aria-label="Ajouter un contact"
                @click="openForm()"
            >
                <Icon name="plus" :size="26" />
            </button>
        </Transition>

        <ContactSheet />
        <ContactForm />

        <nav
            class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white pb-[env(safe-area-inset-bottom)] dark:border-slate-800 dark:bg-slate-900"
            aria-label="Navigation principale"
        >
            <ul class="mx-auto grid max-w-2xl grid-cols-5">
                <li v-for="t in tabs" :key="t.to">
                    <RouterLink
                        :to="t.to"
                        class="flex h-16 flex-col items-center justify-center gap-0.5 text-[11px]"
                        :class="isActive(t.to) ? 'font-semibold text-indigo-600 dark:text-indigo-400' : 'text-slate-500 dark:text-slate-400'"
                        :aria-current="isActive(t.to) ? 'page' : undefined"
                    >
                        <Icon :name="t.icon" />
                        {{ t.label }}
                    </RouterLink>
                </li>
            </ul>
        </nav>
    </template>

    <RouterView v-else />
    <Toast />
</template>
