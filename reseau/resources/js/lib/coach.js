import { daysSince, distKm, hasCoords, inDomain, norm } from './contacts';
import { isoDate } from './dates';

export const RELANCE_DAYS = 90;

export const COMMON_DOMAINS = [
    'juridique', 'santé', 'finance', 'immobilier', 'transport', 'sécurité', 'administration', 'médias',
    'éducation', 'informatique', 'artisanat', 'restauration', 'voyage', 'politique', 'commerce', 'énergie',
];

export const TRAITS = { reseau: 'Réseau', anecdote: 'Anecdotes', savoir: 'Savoir', imprevu: 'Imprévisible' };

const QUESTIONS = [
    'Quelle erreur les débutants font-ils le plus souvent dans ton métier ?',
    'Qui sont les trois personnes qui comptent vraiment dans ton milieu ?',
    'Qu’est-ce que personne ne sait de l’extérieur sur ton métier ?',
    'Quel problème revient sans cesse chez tes clients, et qui sait le résoudre ?',
    'Si tu devais me faire gagner un an dans ce domaine, que me conseillerais-tu ?',
];

/** Générateur pseudo-aléatoire stable pour une graine donnée (ici : la date du jour). */
export function seededRandom(seed) {
    let a = 0;
    for (const ch of seed) a = (a * 31 + ch.charCodeAt(0)) >>> 0;
    return () => {
        a = (a + 0x6d2b79f5) | 0;
        let t = Math.imul(a ^ (a >>> 15), 1 | a);
        t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}

/**
 * Calcule les suggestions du jour pour cultiver son réseau.
 * Fonction pure : le résultat dépend uniquement des contacts, de la position, de la date et des suggestions masquées.
 *
 * Chaque suggestion : { id, trait, score, title, text, contactId?, touch?, action: { label, type, id?, domains? } }
 */
export function buildSuggestions(contacts, { pos = null, today = isoDate(), hidden = {} } = {}) {
    const rnd = seededRandom(today);
    const pick = (arr) => arr[Math.floor(rnd() * arr.length)];
    const out = [];
    const add = (s) => out.push({ ...s, score: s.score + rnd() * 8 });
    const detail = (c) => ({ label: 'Ouvrir la fiche', type: 'detail', id: c.id });
    const edit = (c) => ({ label: 'Compléter', type: 'edit', id: c.id });
    const create = (label) => ({ label: 'Ajouter un contact', type: 'new', domains: [label] });
    const dist = (c) => (pos && hasCoords(c) ? distKm(pos, c) : null);

    if (contacts.length === 0) {
        return [
            {
                id: 'start', trait: 'reseau', score: 100,
                title: 'Commence ton carnet',
                text: 'Ajoute les 10 premières personnes qui te viennent à l’esprit, dans tous les domaines : famille, amis, collègues, voisins, artisans, anciens camarades.',
                action: { label: 'Ajouter un contact', type: 'new' },
            },
        ];
    }

    for (const c of contacts) {
        const since = daysSince(c.last_contact);
        const d = dist(c);
        const age = Math.floor((Date.now() - new Date(c.created_at).getTime()) / 86400000);

        if (c.favor === 'owe') {
            add({ id: 'owe:' + c.id, trait: 'reseau', score: 90, contactId: c.id, action: detail(c),
                title: `Rends le service à ${c.name}`,
                text: 'Tu dois un service à ce contact. La confiance se construit là-dessus : ne laisse pas traîner.' });
        }
        if (since != null && since >= RELANCE_DAYS) {
            add({ id: 'relance:' + c.id, trait: 'reseau', score: 80 + Math.min(since / 10, 15), contactId: c.id, touch: true, action: detail(c),
                title: `Relance ${c.name}`,
                text: `Dernier contact il y a ${since} jours. Un message court (une nouvelle, une question, un coup de main proposé) suffit à garder le lien.` });
        } else if (since == null && age >= 14) {
            add({ id: 'jamais:' + c.id, trait: 'reseau', score: 50, contactId: c.id, touch: true, action: detail(c),
                title: `Prends des nouvelles de ${c.name}`,
                text: 'Aucun échange noté pour l’instant. Écris un message, puis note la date dans la fiche.' });
        }
        if (d != null && d <= 5 && (since == null || since >= 30)) {
            add({ id: 'proche:' + c.id, trait: 'reseau', score: 65, contactId: c.id, touch: true, action: detail(c),
                title: `${c.name} est proche de toi`,
                text: 'Propose un café ou un passage rapide : un lien se nourrit surtout en face à face.' });
        }
        if (c.favor === 'owed') {
            add({ id: 'owed:' + c.id, trait: 'reseau', score: 30, contactId: c.id, action: detail(c),
                title: `${c.name} te doit un service`,
                text: 'Garde en tête ce que tu pourrais lui demander le jour où tu en auras besoin, et note-le dans la fiche.' });
        }
        if (!c.domains.length) {
            add({ id: 'nodom:' + c.id, trait: 'reseau', score: 55, contactId: c.id, action: edit(c),
                title: `Ajoute un domaine à ${c.name}`,
                text: 'Sans domaine, ce contact n’apparaîtra pas quand tu chercheras « qui contacter pour… ».' });
        }
        if (!hasCoords(c)) {
            add({ id: 'noloc:' + c.id, trait: 'reseau', score: 30, contactId: c.id, action: edit(c),
                title: `Localise ${c.name}`,
                text: 'Ajoute un lieu : tu verras ce contact sur la carte et tu sauras quand tu es dans les parages.' });
        }
        if (!c.anecdote) {
            add({ id: 'anec:' + c.id, trait: 'anecdote', score: 48, contactId: c.id, action: edit(c),
                title: `Écris l’anecdote de ${c.name}`,
                text: 'Une histoire vécue avec ce contact, en trois phrases (situation, tension, chute), sera ta meilleure carte pour illustrer un point ou le présenter.' });
        }
    }

    const withAnecdote = contacts.filter((c) => c.anecdote).length;
    if (withAnecdote < 10) {
        const first = contacts.find((c) => !c.anecdote);
        add({ id: 'anec-goal', trait: 'anecdote', score: 52, action: first ? edit(first) : null,
            title: `Anecdotes : ${withAnecdote} sur 10`,
            text: 'Vise dix histoires que tu racontes sans effort. Choisis-en une et raconte-la à voix haute aujourd’hui, à quelqu’un ou seul.' });
    }

    const covered = new Set(contacts.flatMap((c) => c.domains.map(norm)));
    const gaps = COMMON_DOMAINS.filter((dm) => !covered.has(norm(dm)));
    for (let i = 0; i < 2 && gaps.length; i++) {
        const label = gaps.splice(Math.floor(rnd() * gaps.length), 1)[0];
        add({ id: 'gap:' + norm(label), trait: 'reseau', score: 60, action: create(label),
            title: `Aucun contact en « ${label} »`,
            text: 'Demande autour de toi qui pourrait te présenter quelqu’un dans ce domaine. Un seul contact bien placé ouvre beaucoup de portes.' });
    }

    const counts = new Map();
    for (const c of contacts) for (const d of c.domains) counts.set(norm(d), { label: d, n: (counts.get(norm(d))?.n || 0) + 1 });
    for (const [key, { label, n }] of counts) {
        if (n === 1) {
            add({ id: 'thin:' + key, trait: 'reseau', score: 45, action: create(label),
                title: `Un seul contact en « ${label} »`,
                text: 'Si cette personne est injoignable, tu n’as plus personne. Trouve un deuxième contact dans ce domaine.' });
        }
    }

    // Deux contacts au même endroit mais dans des domaines différents : à présenter l'un à l'autre.
    const pool = contacts.slice(0, 300);
    const pairs = [];
    for (let i = 0; i < pool.length; i++) {
        for (let j = i + 1; j < pool.length; j++) {
            const a = pool[i];
            const b = pool[j];
            if (!a.domains.length || !b.domains.length) continue;
            if (a.domains.some((x) => inDomain(b, norm(x)))) continue;
            const sameCity = (a.place && norm(a.place) === norm(b.place)) || (hasCoords(a) && hasCoords(b) && distKm(a, b) < 15);
            if (sameCity) pairs.push([a, b]);
        }
    }
    if (pairs.length) {
        const [a, b] = pick(pairs);
        add({ id: `intro:${a.id}:${b.id}`, trait: 'reseau', score: 58, contactId: a.id, action: detail(a),
            title: `Présente ${a.name} à ${b.name}`,
            text: `Ils sont au même endroit mais dans des domaines différents (${a.domains[0]} et ${b.domains[0]}). Faire se rencontrer deux contacts, c’est devenir le point de passage.` });
    }

    const withDomain = contacts.filter((c) => c.domains.length);
    if (withDomain.length) {
        const c = pick(withDomain);
        add({ id: 'learn:' + c.id, trait: 'savoir', score: 50, contactId: c.id, touch: true, action: detail(c),
            title: `Apprends quelque chose de ${c.name}`,
            text: `Domaine : ${c.domains[0]}. Pose-lui cette question : « ${pick(QUESTIONS)} » Puis note la réponse dans sa fiche.` });
    }

    const stale = contacts.filter((c) => {
        const s = daysSince(c.last_contact);
        return s == null || s >= 30;
    });
    if (stale.length) {
        const c = pick(stale);
        add({ id: 'imprevu:' + c.id, trait: 'imprevu', score: 42, contactId: c.id, touch: true, action: detail(c),
            title: `Surprends ${c.name}`,
            text: 'Écris sans raison précise : une idée, un article, une photo, une blague. Sois imprévisible dans ta façon de faire, jamais dans ta parole.' });
    }

    // Au plus 2 suggestions par type, hors suggestions masquées.
    const perKind = {};
    const now = Date.now();
    return out
        .filter((s) => !(hidden[s.id] > now))
        .sort((x, y) => y.score - x.score)
        .filter((s) => {
            const kind = s.id.split(':')[0];
            perKind[kind] = (perKind[kind] || 0) + 1;
            return perKind[kind] <= 2;
        });
}

/** Jauges de progression du carnet. */
export function contactMeters(contacts) {
    const n = contacts.length;
    const covered = COMMON_DOMAINS.filter((dm) => contacts.some((c) => inDomain(c, norm(dm)))).length;
    const kept = contacts.filter((c) => {
        const s = daysSince(c.last_contact);
        return s != null && s < RELANCE_DAYS;
    }).length;
    const anecdotes = contacts.filter((c) => c.anecdote).length;
    const located = contacts.filter(hasCoords).length;

    return [
        { label: 'Réseau', value: `${n} / 50 contacts`, ratio: n / 50 },
        { label: 'Domaines couverts', value: `${covered} / ${COMMON_DOMAINS.length}`, ratio: covered / COMMON_DOMAINS.length },
        { label: 'Liens entretenus (moins de 90 j)', value: `${kept} / ${n}`, ratio: n ? kept / n : 0 },
        { label: 'Anecdotes', value: `${anecdotes} / 10`, ratio: anecdotes / 10 },
        { label: 'Contacts localisés', value: `${located} / ${n}`, ratio: n ? located / n : 0 },
    ];
}
