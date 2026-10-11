<script setup>
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { login } from '../stores/session';

const route = useRoute();
const router = useRouter();
const email = ref('');
const password = ref('');
const error = ref('');
const busy = ref(false);

async function submit() {
    error.value = '';
    busy.value = true;
    try {
        await login(email.value.trim(), password.value);
        const target = typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/') ? route.query.redirect : '/contacts';
        router.replace(target);
    } catch (e) {
        error.value = e.message;
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <main class="mx-auto flex min-h-dvh max-w-sm flex-col justify-center px-6 py-10">
        <img src="/icon-192.png" alt="" class="mx-auto mb-6 size-20 rounded-2xl shadow" />
        <h1 class="mb-1 text-center text-2xl font-semibold tracking-tight">Réseau</h1>
        <p class="muted mb-8 text-center">Mes contacts, ma santé, mes habitudes.</p>

        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label class="label" for="email">Adresse e-mail</label>
                <input id="email" v-model="email" class="input" type="email" autocomplete="username" required autofocus />
            </div>
            <div>
                <label class="label" for="password">Mot de passe</label>
                <input id="password" v-model="password" class="input" type="password" autocomplete="current-password" required />
            </div>
            <p v-if="error" class="rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950 dark:text-red-300" role="alert">{{ error }}</p>
            <button class="btn btn-primary w-full" :disabled="busy">{{ busy ? 'Connexion…' : 'Se connecter' }}</button>
        </form>
    </main>
</template>
