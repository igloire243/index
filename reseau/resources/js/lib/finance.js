import { daysBetween } from './dates';

export const ACCOUNT_TYPES = {
    cash: { label: 'Espèces', emoji: '💵' },
    bank: { label: 'Compte bancaire', emoji: '🏦' },
    mobile: { label: 'Mobile money', emoji: '📱' },
    savings: { label: 'Épargne', emoji: '🐖' },
    investment: { label: 'Placement', emoji: '📈' },
    crypto: { label: 'Cryptomonnaie', emoji: '🪙' },
    tontine: { label: 'Tontine / association', emoji: '🤝' },
    other: { label: 'Autre', emoji: '💼' },
};

// Types de biens : la liste sert aussi à repérer ceux qu'on ne possède pas encore.
export const ASSET_CATEGORIES = [
    { key: 'residence', label: 'Résidence principale', emoji: '🏠', why: 'Un logement à soi : stabilité, et souvent le plus gros bien d’un patrimoine.' },
    { key: 'locatif', label: 'Bien locatif', emoji: '🏘️', why: 'Un logement ou un local loué : il peut produire un revenu régulier.' },
    { key: 'terrain', label: 'Terrain / parcelle', emoji: '🌍', why: 'Un terrain se conserve et peut prendre de la valeur, ou servir à construire.' },
    { key: 'vehicule', label: 'Véhicule', emoji: '🚗', why: 'Voiture, moto ou camion : utile au quotidien ou à une activité.' },
    { key: 'commerce', label: 'Commerce / entreprise / parts', emoji: '🏪', why: 'Une activité ou des parts dans une société peuvent générer des revenus.' },
    { key: 'agricole', label: 'Agriculture / élevage', emoji: '🌾', why: 'Champ, plantation ou bétail : production et réserve de valeur.' },
    { key: 'equipement', label: 'Équipement professionnel', emoji: '🛠️', why: 'Le matériel qui te permet de travailler et de produire.' },
    { key: 'electronique', label: 'Électronique', emoji: '💻', why: 'Téléphone, ordinateur : des outils de travail à garder en bon état.' },
    { key: 'or', label: 'Or, bijoux et métaux', emoji: '🥇', why: 'Une valeur facile à transporter, conservée hors du système bancaire.' },
    { key: 'mobilier', label: 'Mobilier et électroménager', emoji: '🛋️', why: 'Ce qui équipe ta maison, utile à recenser pour une assurance.' },
    { key: 'placement', label: 'Placements (actions, obligations, parts)', emoji: '📊', why: 'Faire travailler une épargne dans la durée, avec des risques à comprendre.' },
    { key: 'crypto', label: 'Cryptomonnaies', emoji: '🪙', why: 'Un actif très volatil : à n’envisager qu’en connaissant le risque.' },
    { key: 'assurance', label: 'Assurance-vie / retraite', emoji: '🛡️', why: 'Une protection et une épargne à long terme pour toi et ta famille.' },
    { key: 'autre', label: 'Autre', emoji: '📦', why: 'Tout bien de valeur qui ne rentre pas dans les autres types.' },
];

export const categoryOf = (key) => ASSET_CATEGORIES.find((c) => c.key === key) || ASSET_CATEGORIES[ASSET_CATEGORIES.length - 1];

export const DIRECTIONS = { owed_to_me: 'On me doit', i_owe: 'Je dois' };

export const COMMON_CURRENCIES = ['USD', 'CDF', 'EUR', 'XAF', 'XOF', 'GBP', 'ZAR'];

export function fmtMoney(amount, currency, { decimals } = {}) {
    if (amount == null || Number.isNaN(Number(amount))) return '–';
    const n = Number(amount);
    const d = decimals ?? (Math.abs(n) >= 1000 || Number.isInteger(n) ? 0 : 2);
    try {
        return new Intl.NumberFormat('fr-FR', { style: 'currency', currency, minimumFractionDigits: d, maximumFractionDigits: d }).format(n);
    } catch {
        // Code qui n'est pas une devise ISO (USDT, par exemple).
        return `${n.toLocaleString('fr-FR', { minimumFractionDigits: d, maximumFractionDigits: d })} ${currency}`;
    }
}

/**
 * Convertit un montant vers la devise de référence. Les taux sont « 1 unité de référence = N unités de la devise »
 * (ex. 1 USD = 2 800 CDF). Renvoie null si le taux est inconnu.
 */
export function toBase(amount, currency, settings) {
    if (currency === settings.base_currency) return amount;
    const rate = Number(settings.rates?.[currency]);
    return rate > 0 ? amount / rate : null;
}

/** Convertit entre deux devises quelconques en passant par la devise de référence ; null si un taux manque. */
export function convert(amount, from, to, settings) {
    if (from === to) return amount;
    const base = toBase(amount, from, settings);
    if (base == null) return null;
    if (to === settings.base_currency) return base;
    const rate = Number(settings.rates?.[to]);
    return rate > 0 ? base * rate : null;
}

export function sumBase(entries, settings) {
    let total = 0;
    const missing = new Set();
    for (const { amount, currency } of entries) {
        const v = toBase(amount, currency, settings);
        if (v == null) missing.add(currency);
        else total += v;
    }
    return { total, missing: [...missing] };
}

export function totalsByCurrency(entries) {
    const map = new Map();
    for (const { amount, currency } of entries) map.set(currency, (map.get(currency) || 0) + amount);
    return [...map].map(([currency, total]) => ({ currency, total })).sort((a, b) => a.currency.localeCompare(b.currency));
}

export const openDebts = (debts, direction) => debts.filter((d) => d.direction === direction && d.remaining > 0.004);
export const ownedAssets = (assets) => assets.filter((a) => a.status === 'owned');

/** Patrimoine dans la devise de référence : fonds + créances + biens − dettes. */
export function netWorth({ accounts, debts, assets }, settings) {
    const funds = sumBase(accounts.map((a) => ({ amount: a.balance, currency: a.currency })), settings);
    const receivables = sumBase(openDebts(debts, 'owed_to_me').map((d) => ({ amount: d.remaining, currency: d.currency })), settings);
    const owed = sumBase(openDebts(debts, 'i_owe').map((d) => ({ amount: d.remaining, currency: d.currency })), settings);
    const goods = sumBase(ownedAssets(assets).filter((a) => a.value != null).map((a) => ({ amount: a.value, currency: a.currency })), settings);
    const missing = [...new Set([...funds.missing, ...receivables.missing, ...owed.missing, ...goods.missing])];

    return {
        funds: funds.total,
        receivables: receivables.total,
        assets: goods.total,
        debts: owed.total,
        net: funds.total + receivables.total + goods.total - owed.total,
        missing,
    };
}

/** Jours avant l'échéance (négatif = en retard). */
export const daysUntil = (due, today) => daysBetween(today, due);

/** Créances et dettes ouvertes en retard ou à échéance proche, les plus urgentes d'abord. */
export function dueSoon(debts, today, horizon = 14) {
    return debts
        .filter((d) => d.remaining > 0.004 && d.due_on)
        .map((d) => ({ ...d, days: daysUntil(d.due_on, today) }))
        .filter((d) => d.days <= horizon)
        .sort((a, b) => a.days - b.days);
}

/** Pour chaque type de bien : possédé, souhaité, ou pas encore. */
export function assetGaps(assets) {
    return ASSET_CATEGORIES.map((c) => {
        const owned = assets.filter((a) => a.category === c.key && a.status === 'owned');
        const wanted = assets.filter((a) => a.category === c.key && a.status === 'wanted');
        return { ...c, owned, wanted, state: owned.length ? 'owned' : wanted.length ? 'wanted' : 'missing' };
    });
}

export function dueLabel(days) {
    if (days < 0) return `en retard de ${-days} j`;
    if (days === 0) return 'échéance aujourd’hui';
    if (days === 1) return 'demain';
    return `dans ${days} j`;
}
