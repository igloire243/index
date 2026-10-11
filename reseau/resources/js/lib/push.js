import { api } from '../api';

export const pushSupported = () => 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;

function urlBase64ToUint8Array(base64) {
    const padding = '='.repeat((4 - (base64.length % 4)) % 4);
    const raw = atob((base64 + padding).replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from(raw, (c) => c.charCodeAt(0));
}

export async function currentSubscription() {
    if (!pushSupported()) return null;
    const reg = await navigator.serviceWorker.ready;
    return reg.pushManager.getSubscription();
}

/** Demande l'autorisation, abonne cet appareil aux notifications et enregistre l'abonnement sur le serveur. */
export async function enablePush() {
    const { public_key: key } = await api('push/key');
    if (!key) throw new Error('Les notifications ne sont pas configurées sur le serveur.');

    const permission = await Notification.requestPermission();
    if (permission !== 'granted') throw new Error('Notifications refusées : autorise-les dans les réglages du navigateur.');

    const reg = await navigator.serviceWorker.ready;
    const sub = (await reg.pushManager.getSubscription()) || (await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: urlBase64ToUint8Array(key) }));
    const json = sub.toJSON();
    await api('push/subscribe', { method: 'POST', body: { endpoint: json.endpoint, keys: json.keys } });
}

export async function disablePush() {
    const sub = await currentSubscription();
    if (!sub) return;
    await api('push/unsubscribe', { method: 'POST', body: { endpoint: sub.endpoint } });
    await sub.unsubscribe();
}

export const sendTestPush = () => api('push/test', { method: 'POST' });
