<script setup>
import { computed, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Icon from '../components/Icon.vue';
import Wizard from '../components/Wizard.vue';
import { describePasskeyError, hasLocalPasskey, passkeysSupported } from '../lib/passkeys';
import { login, loginWithFace } from '../stores/session';

const route = useRoute();
const router = useRouter();
const email = ref('');
const password = ref('');
const error = ref('');
const errorKey = ref(0);
const busy = ref(false);
const faceAvailable = passkeysSupported() && hasLocalPasskey();

const steps = computed(() => [
    { key: 'email', title: 'Adresse e-mail', valid: /^\S+@\S+\.\S+$/.test(email.value.trim()), hint: 'Saisis une adresse e-mail valide pour continuer.' },
    { key: 'password', title: 'Mot de passe', valid: password.value.length > 0 },
]);

function goAfterLogin() {
    const target = typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/') ? route.query.redirect : '/contacts';
    router.replace(target);
}

async function faceLogin() {
    error.value = '';
    busy.value = true;
    try {
        await loginWithFace();
        goAfterLogin();
    } catch (e) {
        error.value = describePasskeyError(e);
        errorKey.value++;
    } finally {
        busy.value = false;
    }
}

async function submit() {
    error.value = '';
    busy.value = true;
    try {
        await login(email.value.trim(), password.value);
        goAfterLogin();
    } catch (e) {
        error.value = e.message;
        errorKey.value++; // relance l'animation de secousse
        password.value = '';
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <main class="mx-auto flex min-h-dvh max-w-sm flex-col justify-center px-6 py-10">
        <img src="/icon-192.png" alt="" class="rise mx-auto mb-6 size-20 rounded-2xl shadow" />
        <h1 class="rise mb-1 text-center text-2xl font-semibold tracking-tight" style="--i: 1">Réseau</h1>
        <p class="muted rise mb-8 text-center" style="--i: 2">Mes contacts, ma santé, mes habitudes.</p>

        <div class="rise card" style="--i: 3">
            <Wizard :steps="steps" :busy="busy" submit-label="Se connecter" @submit="submit" @cancel="() => {}">
                <template #default="{ step }">
                    <div v-if="step.key === 'email'">
                        <label class="label" for="email">Adresse e-mail</label>
                        <input id="email" v-model="email" class="input" type="email" autocomplete="username" required autofocus />
                    </div>
                    <div v-else>
                        <p class="muted mb-2">{{ email }}</p>
                        <!-- Champ présent pour les gestionnaires de mots de passe -->
                        <input class="sr-only" tabindex="-1" readonly autocomplete="username" :value="email" aria-hidden="true" />
                        <label class="label" for="password">Mot de passe</label>
                        <input id="password" v-model="password" class="input" type="password" autocomplete="current-password" required />
                        <p v-if="error" :key="errorKey" class="shake mt-3 rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950 dark:text-red-300" role="alert">{{ error }}</p>
                    </div>
                </template>
            </Wizard>
        </div>

        <div v-if="faceAvailable" class="rise mt-4" style="--i: 4">
            <button type="button" class="btn w-full" :disabled="busy" @click="faceLogin"><Icon name="face" :size="20" /> Face ID / empreinte</button>
            <p v-if="error && !email" :key="errorKey" class="shake mt-3 rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950 dark:text-red-300" role="alert">{{ error }}</p>
        </div>
    </main>
</template>
