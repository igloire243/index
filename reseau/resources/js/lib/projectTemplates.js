/** Modèles de projet : un départ tout prêt, à adapter. Les étapes sont créées automatiquement après la fiche. */
export const PROJECT_TEMPLATES = [
    {
        key: 'commerce',
        title: 'Ouvrir un commerce',
        domain: 'commerce',
        description: 'Lancer une activité commerciale, du local à l’ouverture.',
        steps: ['Étudier le marché et la concurrence', 'Trouver un local', 'Réunir le capital de départ', 'Obtenir les autorisations nécessaires', 'Aménager et stocker', 'Ouvrir et faire connaître'],
    },
    {
        key: 'langue',
        title: 'Apprendre une langue',
        domain: 'éducation',
        description: '',
        steps: ['Choisir une méthode ou une appli', 'Pratiquer 15 minutes chaque jour', 'Trouver un partenaire de conversation', 'Regarder des contenus dans la langue', 'Passer un test de niveau'],
    },
    {
        key: 'formation',
        title: 'Suivre une formation',
        domain: 'éducation',
        description: '',
        steps: ['Choisir la formation', 'S’inscrire et payer', 'Bloquer du temps chaque semaine', 'Terminer les modules', 'Obtenir le certificat'],
    },
    {
        key: 'logement',
        title: 'Trouver un logement',
        domain: 'immobilier',
        description: '',
        steps: ['Définir le budget et le quartier', 'Visiter plusieurs logements', 'Négocier et signer', 'Organiser le déménagement', 'S’installer'],
    },
    {
        key: 'evenement',
        title: 'Organiser un événement',
        domain: '',
        description: '',
        steps: ['Fixer la date et le lieu', 'Établir le budget', 'Inviter les participants', 'Prévoir la logistique', 'Jour J'],
    },
    {
        key: 'creatif',
        title: 'Un projet créatif (livre, œuvre…)',
        domain: 'médias',
        description: '',
        steps: ['Définir le sujet et le plan', 'Écrire ou créer un premier jet', 'Relire et corriger', 'Partager ou publier'],
    },
];

export const findTemplate = (key) => PROJECT_TEMPLATES.find((t) => t.key === key) ?? null;
