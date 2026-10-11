/**
 * Journal des nouveautés, tenu à la main à chaque fonctionnalité livrée. Le plus récent en premier.
 * `id` sert à repérer ce que l'utilisateur a déjà vu (stocké dans son navigateur).
 */
export const CHANGELOG = [
    {
        id: '2026-10-11-partage-export',
        date: '2026-10-11',
        title: 'Partage ponctuel et export chiffré du Coffre',
        items: [
            'Un lien à usage unique, avec expiration, pour montrer un document du Coffre à quelqu’un sans lui donner accès au reste.',
            'Une sauvegarde chiffrée du Coffre (dossiers et mots de passe) à télécharger en un clic, protégée par un mot de passe que tu choisis.',
        ],
    },
    {
        id: '2026-10-11-recherche-objectif',
        date: '2026-10-11',
        title: 'Recherche unique et objectif de réseau',
        items: [
            'Une recherche en haut de l’application qui trouve un contact, un dossier ou une fiche financière en un seul champ.',
            'Un objectif mensuel de relances dans le Coach, avec une jauge de progression.',
            'Cette page : le journal des nouveautés.',
        ],
    },
    {
        id: '2026-10-11-mots-de-passe',
        date: '2026-10-11',
        title: 'Mots de passe, codes et schémas dans le Coffre',
        items: [
            'Comptes en ligne, comptes bancaires, cartes, codes PIN, schémas de déverrouillage, Wi-Fi, notes sécurisées.',
            'Générateur de mot de passe et de code, avec indicateur de robustesse.',
            'Secrets masqués par défaut, presse-papiers vidé automatiquement après une copie.',
        ],
    },
    {
        id: '2026-10-11-itineraire',
        date: '2026-10-11',
        title: 'Itinéraire dans l’application',
        items: ['Le trajet vers un contact s’affiche désormais sur la carte, en plein écran, sans ouvrir une autre application.'],
    },
    {
        id: '2026-10-10-coffre-dossiers',
        date: '2026-10-10',
        title: 'Dossiers confidentiels (le Coffre)',
        items: [
            'Des dossiers sur une personne ou un sujet, avec photos, vidéos, enregistrements audio et documents.',
            'Chiffrement de chaque fichier, verrou par mot de passe séparé.',
        ],
    },
    {
        id: '2026-10-09-finances',
        date: '2026-10-09',
        title: 'Finances',
        items: [
            'Tous tes fonds, quel que soit le compte ou la source, avec mouvements et virements.',
            'Créances, dettes, et le suivi des biens que tu possèdes — ou pas encore.',
        ],
    },
    {
        id: '2026-10-08-sante',
        date: '2026-10-08',
        title: 'Programmes de santé',
        items: ['Planning d’habitudes, suivi du poids et du sommeil, programmes prêts à l’emploi.'],
    },
    {
        id: '2026-10-07-lancement',
        date: '2026-10-07',
        title: 'Lancement de Réseau',
        items: ['Carnet de contacts moderne, carte, domaines, et un Coach qui suggère des actions chaque jour.'],
    },
];

const SEEN_KEY = 'reseau.nouveautes-vues';

export function lastSeenId() {
    try {
        return localStorage.getItem(SEEN_KEY);
    } catch {
        return null;
    }
}

export function markSeen() {
    try {
        if (CHANGELOG[0]) localStorage.setItem(SEEN_KEY, CHANGELOG[0].id);
    } catch {
        /* ignoré */
    }
}

/** Vrai s'il y a des entrées plus récentes que la dernière vue (pour un petit badge). */
export function hasUnseen() {
    const seen = lastSeenId();
    if (!seen) return CHANGELOG.length > 0;

    return CHANGELOG.findIndex((e) => e.id === seen) !== 0;
}
