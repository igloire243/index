import { reactive } from 'vue';
import { api, hooks } from '../api';
import { loginWithPasskey } from '../lib/passkeys';

export const session = reactive({
    user: null,
    ready: false,
    toast: null,
});

let toastTimer;
export function toast(message, kind = 'info') {
    session.toast = { message, kind };
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => (session.toast = null), kind === 'error' ? 7000 : 3500);
}

const browserTimezone = () => {
    try {
        return Intl.DateTimeFormat().resolvedOptions().timeZone;
    } catch {
        return null;
    }
};

// Les rappels sont envoyés à l'heure locale de l'utilisateur : on garde son fuseau à jour.
async function syncTimezone() {
    const tz = browserTimezone();
    if (tz && session.user && session.user.timezone !== tz) {
        try {
            session.user = await api('profile', { method: 'PUT', body: { timezone: tz } });
        } catch {
            /* fuseau inconnu du serveur : on garde l'ancien */
        }
    }
}

export async function loadSession() {
    try {
        session.user = await api('me');
        await syncTimezone();
    } catch {
        session.user = null;
    } finally {
        session.ready = true;
    }
}

export async function login(email, password) {
    session.user = await api('login', { method: 'POST', body: { email, password } });
    await syncTimezone();
}

export async function loginWithFace() {
    session.user = await loginWithPasskey();
    await syncTimezone();
}

export async function logout() {
    try {
        await api('logout', { method: 'POST' });
    } finally {
        session.user = null;
    }
}

hooks.onUnauthorized = () => {
    session.user = null;
};
