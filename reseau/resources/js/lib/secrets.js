export const TYPES = {
    site: { label: 'Compte en ligne', icon: 'globe', userLabel: 'Identifiant ou e-mail', secretLabel: 'Mot de passe', urlLabel: 'Adresse du site', generate: 'password' },
    bank: { label: 'Compte bancaire', icon: 'wallet', userLabel: 'N° de compte ou de client', secretLabel: 'Mot de passe ou code d’accès', urlLabel: 'Site ou application de la banque', generate: 'password' },
    card: { label: 'Carte bancaire', icon: 'card', userLabel: 'Titulaire ou 4 derniers chiffres', secretLabel: 'Code PIN', urlLabel: null, generate: 'pin' },
    pin: { label: 'Code PIN', icon: 'key', userLabel: 'À quoi sert ce code ?', secretLabel: 'Code', urlLabel: null, generate: 'pin' },
    pattern: { label: 'Schéma', icon: 'grid', userLabel: 'Appareil concerné', secretLabel: 'Schéma de déverrouillage', urlLabel: null, generate: null },
    wifi: { label: 'Wi-Fi', icon: 'wifi', userLabel: 'Nom du réseau', secretLabel: 'Mot de passe Wi-Fi', urlLabel: null, generate: 'password' },
    note: { label: 'Note sécurisée', icon: 'file', userLabel: null, secretLabel: null, urlLabel: null, generate: null },
    other: { label: 'Autre', icon: 'lock', userLabel: 'Identifiant', secretLabel: 'Secret', urlLabel: 'Adresse', generate: 'password' },
};

const SETS = {
    lower: 'abcdefghijkmnopqrstuvwxyz', // sans l (L) ni o, trop proches de 1 et 0
    upper: 'ABCDEFGHJKLMNPQRSTUVWXYZ',
    digits: '23456789',
    symbols: '!@#$%&*?-_=+',
};

const secureRandom = (max) => {
    // Tirage uniforme sans biais (rejet des valeurs qui fausseraient le modulo).
    const limit = Math.floor(0x100000000 / max) * max;
    const buf = new Uint32Array(1);
    do crypto.getRandomValues(buf);
    while (buf[0] >= limit);
    return buf[0] % max;
};

/** Mot de passe aléatoire ; contient au moins un caractère de chaque famille choisie. */
export function generatePassword({ length = 20, lower = true, upper = true, digits = true, symbols = true } = {}, random = secureRandom) {
    const families = Object.entries({ lower, upper, digits, symbols }).filter(([, on]) => on).map(([k]) => SETS[k]);
    if (!families.length) families.push(SETS.lower);
    const size = Math.min(128, Math.max(families.length, Math.floor(length)));
    const pool = families.join('');
    const chars = families.map((set) => set[random(set.length)]);
    while (chars.length < size) chars.push(pool[random(pool.length)]);
    for (let i = chars.length - 1; i > 0; i--) {
        const j = random(i + 1);
        [chars[i], chars[j]] = [chars[j], chars[i]];
    }
    return chars.join('');
}

export function generatePin(length = 4, random = secureRandom) {
    const size = Math.min(12, Math.max(4, Math.floor(length)));
    return Array.from({ length: size }, () => random(10)).join('');
}

/** Estimation grossière en bits d'entropie, avec pénalité pour les répétitions et les suites évidentes. */
export function entropyBits(value) {
    const s = String(value ?? '');
    if (!s) return 0;
    let pool = 0;
    if (/[a-z]/.test(s)) pool += 26;
    if (/[A-Z]/.test(s)) pool += 26;
    if (/\d/.test(s)) pool += 10;
    if (/[^A-Za-z0-9]/.test(s)) pool += 32;
    const distinct = new Set(s).size;
    let bits = Math.min(s.length, distinct * 2) * Math.log2(Math.max(pool, 2));
    if (/^(.)\1+$/.test(s)) bits = Math.log2(Math.max(pool, 2));
    if (/^(0123|1234|2345|3456|4567|5678|6789|9876|8765|7654|6543|5432|4321|3210|abcd|azerty|qwerty|password|motdepasse)/i.test(s)) bits = Math.min(bits, 20);
    return Math.round(bits);
}

export function strength(value) {
    const bits = entropyBits(value);
    if (!value) return { level: 0, label: '', bits };
    if (bits < 40) return { level: 1, label: 'Faible', bits };
    if (bits < 60) return { level: 2, label: 'Moyen', bits };
    if (bits < 80) return { level: 3, label: 'Bon', bits };
    return { level: 4, label: 'Très fort', bits };
}

/** Un schéma est la liste des points touchés, de 1 à 9 (ligne par ligne), ex. « 1-5-9-6 ». */
export const patternToString = (dots) => dots.join('-');
export const patternFromString = (s) => String(s ?? '').split('-').map(Number).filter((n) => Number.isInteger(n) && n >= 1 && n <= 9).filter((n, i, a) => a.indexOf(n) === i);
export const patternValid = (dots) => dots.length >= 4;

export const dotCenter = (n) => ({ x: ((n - 1) % 3) * 100 + 50, y: Math.floor((n - 1) / 3) * 100 + 50 });

const norm = (s) => String(s ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

export function filterSecrets(list, { query = '', type = '' } = {}) {
    const q = norm(query).trim();
    return list
        .filter((s) => (!type || s.type === type) && (!q || norm(`${s.title} ${s.username ?? ''} ${s.url ?? ''}`).includes(q)))
        .sort((a, b) => Number(b.favorite) - Number(a.favorite) || a.title.localeCompare(b.title, 'fr'));
}

/** Copie dans le presse-papiers ; l'effacement automatique est géré par l'appelant. */
export async function copyText(text) {
    try {
        await navigator.clipboard.writeText(text);
        return true;
    } catch {
        const el = document.createElement('textarea');
        el.value = text;
        el.setAttribute('readonly', '');
        el.style.cssText = 'position:fixed;opacity:0';
        document.body.appendChild(el);
        el.select();
        let ok = false;
        try {
            ok = document.execCommand('copy');
        } catch {
            ok = false;
        }
        el.remove();
        return ok;
    }
}
