import { norm } from './contacts';
import { fmtMoney } from './finance';
import { TYPES as SECRET_TYPES } from './secrets';

/**
 * Recherche unique, entièrement côté client, sur ce qui est déjà chargé en mémoire : pas d'appel réseau, donc
 * instantanée. Les dossiers et mots de passe du Coffre ne sont cherchés que si le coffre est déverrouillé (sinon
 * ces listes sont vides) ; seuls les titres sont cherchés, jamais le contenu chiffré lui-même.
 *
 * Renvoie un objet { contacts, dossiers, secrets, finances }, chaque groupe limité à `limit` résultats.
 */
export function search({ contacts = [], dossiers = [], secrets = [], accounts = [], debts = [], assets = [] }, query, limit = 6) {
    const q = norm(query).trim();
    if (!q) return { contacts: [], dossiers: [], secrets: [], finances: [] };

    const has = (...parts) => norm(parts.filter(Boolean).join(' ')).includes(q);

    const contactResults = contacts
        .filter((c) => has(c.name, ...(c.domains || []), c.place, c.role))
        .slice(0, limit)
        .map((c) => ({ kind: 'contact', id: c.id, title: c.name, subtitle: c.role || (c.domains || [])[0] || null }));

    const dossierResults = dossiers
        .filter((d) => has(d.title, d.subject))
        .slice(0, limit)
        .map((d) => ({ kind: 'dossier', id: d.id, title: d.title, subtitle: d.subject }));

    const secretResults = secrets
        .filter((s) => has(s.title, s.username, s.url))
        .slice(0, limit)
        .map((s) => ({ kind: 'secret', id: s.id, title: s.title, subtitle: SECRET_TYPES[s.type]?.label }));

    const financeResults = [
        ...accounts.filter((a) => has(a.name, a.institution, a.location)).map((a) => ({ kind: 'account', id: a.id, title: a.name, subtitle: fmtMoney(a.balance, a.currency) })),
        ...debts.filter((d) => has(d.counterparty)).map((d) => ({ kind: 'debt', id: d.id, title: d.counterparty, subtitle: fmtMoney(d.amount, d.currency), direction: d.direction })),
        ...assets.filter((a) => has(a.name)).map((a) => ({ kind: 'asset', id: a.id, title: a.name, subtitle: a.status === 'wanted' ? 'Envie' : null })),
    ].slice(0, limit);

    return { contacts: contactResults, dossiers: dossierResults, secrets: secretResults, finances: financeResults };
}

export const hasResults = (r) => !!(r.contacts.length || r.dossiers.length || r.secrets.length || r.finances.length);
