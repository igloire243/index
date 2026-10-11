import { api } from '../api';

/**
 * Face ID / empreinte via les clés d'accès (WebAuthn).
 * La clé secrète reste sur le téléphone et c'est le système qui vérifie la personne : l'application ne voit
 * jamais de donnée biométrique, seulement une signature.
 */

const FLAG = 'reseau.passkey';

export function b64uToBuf(s) {
    const pad = '='.repeat((4 - (s.length % 4)) % 4);
    const bin = atob((s + pad).replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from(bin, (c) => c.charCodeAt(0)).buffer;
}

export function bufToB64u(buf) {
    const bytes = new Uint8Array(buf);
    let bin = '';
    for (const b of bytes) bin += String.fromCharCode(b);
    return btoa(bin).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

export const passkeysSupported = () => typeof window !== 'undefined' && window.isSecureContext === true && 'PublicKeyCredential' in window && !!navigator.credentials;

// Ce téléphone a-t-il déjà une clé enregistrée ? Le bouton de connexion n'apparaît que dans ce cas.
export const hasLocalPasskey = () => {
    try {
        return localStorage.getItem(FLAG) === '1';
    } catch {
        return false;
    }
};
export const setLocalPasskey = (on) => {
    try {
        if (on) localStorage.setItem(FLAG, '1');
        else localStorage.removeItem(FLAG);
    } catch {
        /* ignoré */
    }
};

export function describePasskeyError(e) {
    switch (e?.name) {
        case 'NotAllowedError':
            return 'Vérification annulée ou expirée. Réessaie.';
        case 'InvalidStateError':
            return 'Cet appareil est déjà enregistré.';
        case 'SecurityError':
            return 'Face ID / empreinte exige HTTPS et un nom de domaine (pas une adresse IP).';
        case 'NotSupportedError':
            return 'Cet appareil ne propose pas Face ID / empreinte pour les clés d’accès.';
        default:
            return e?.message || 'Vérification impossible.';
    }
}

export const deviceLabel = () => {
    const ua = navigator.userAgent || '';
    if (/iPhone/i.test(ua)) return 'iPhone';
    if (/iPad/i.test(ua)) return 'iPad';
    if (/Android/i.test(ua)) return 'Téléphone Android';
    if (/Mac/i.test(ua)) return 'Mac';
    if (/Windows/i.test(ua)) return 'Ordinateur Windows';
    return 'Cet appareil';
};

/** Enregistre cet appareil (l'utilisateur doit être connecté). */
export async function registerPasskey(name) {
    const { publicKey: pk } = await api('passkeys/options', { method: 'POST' });
    const credential = await navigator.credentials.create({
        publicKey: {
            ...pk,
            challenge: b64uToBuf(pk.challenge),
            user: { ...pk.user, id: b64uToBuf(pk.user.id) },
            excludeCredentials: (pk.excludeCredentials || []).map((c) => ({ ...c, id: b64uToBuf(c.id) })),
        },
    });
    const saved = await api('passkeys', {
        method: 'POST',
        body: {
            name,
            clientDataJSON: bufToB64u(credential.response.clientDataJSON),
            attestationObject: bufToB64u(credential.response.attestationObject),
        },
    });
    setLocalPasskey(true);
    return saved;
}

/** Connexion : le téléphone demande le visage, l'empreinte ou le code, puis le serveur vérifie la signature. */
export async function loginWithPasskey() {
    const { publicKey: pk } = await api('passkey-login/options', { method: 'POST' });
    const credential = await navigator.credentials.get({ publicKey: { ...pk, challenge: b64uToBuf(pk.challenge) } });
    const r = credential.response;
    return api('passkey-login', {
        method: 'POST',
        body: {
            id: credential.id,
            clientDataJSON: bufToB64u(r.clientDataJSON),
            authenticatorData: bufToB64u(r.authenticatorData),
            signature: bufToB64u(r.signature),
            userHandle: r.userHandle ? bufToB64u(r.userHandle) : null,
        },
    });
}
