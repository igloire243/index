<?php

/*
 * Référentiel des accomplissements de vie. Les libellés sont affichés par l'interface (resources/js/lib/life-goals.js
 * en garde une copie pour l'affichage) ; seules les clés comptent côté serveur.
 *
 * Le catalogue mélange des étapes universelles et des réalités congolaises (diplôme d'État, parcelle, dot,
 * mobile money…) : un point de départ à adapter, pas une liste à suivre à la lettre. Chaque utilisateur peut
 * retirer ce qui ne lui correspond pas et ajouter ses propres objectifs.
 */

return [
    'domains' => [
        'education', 'carriere', 'finances', 'famille', 'spiritualite',
        'sante', 'voyage', 'communaute', 'developpement_personnel', 'loisirs',
    ],

    'age_brackets' => ['avant_12', 'avant_18', 'avant_25', 'avant_30', 'avant_40', 'avant_50', 'avant_60', 'un_jour'],

    'statuses' => ['none', 'planned', 'done'],

    'catalog' => [
        // Avant 12 ans
        ['key' => 'lire_ecrire', 'domain' => 'education', 'age_bracket' => 'avant_12', 'title' => 'Apprendre à lire et écrire couramment'],
        ['key' => 'nager', 'domain' => 'loisirs', 'age_bracket' => 'avant_12', 'title' => 'Apprendre à nager'],
        ['key' => 'velo', 'domain' => 'loisirs', 'age_bracket' => 'avant_12', 'title' => 'Apprendre à faire du vélo'],
        ['key' => 'primaire', 'domain' => 'education', 'age_bracket' => 'avant_12', 'title' => 'Terminer l’école primaire'],
        ['key' => 'epargne_enfant', 'domain' => 'finances', 'age_bracket' => 'avant_12', 'title' => 'Avoir un premier compte d’épargne, même ouvert par un parent'],
        ['key' => 'ville_differente', 'domain' => 'voyage', 'age_bracket' => 'avant_12', 'title' => 'Visiter une ville différente de la sienne'],

        // Avant 18 ans
        ['key' => 'diplome_etat', 'domain' => 'education', 'age_bracket' => 'avant_18', 'title' => 'Obtenir son diplôme d’État'],
        ['key' => 'orientation', 'domain' => 'carriere', 'age_bracket' => 'avant_18', 'title' => 'Choisir une orientation d’études ou de métier'],
        ['key' => 'langue_base', 'domain' => 'education', 'age_bracket' => 'avant_18', 'title' => 'Apprendre les bases d’une langue étrangère'],
        ['key' => 'petit_boulot', 'domain' => 'finances', 'age_bracket' => 'avant_18', 'title' => 'Avoir un petit boulot ou une première activité qui rapporte'],
        ['key' => 'cuisiner_seul', 'domain' => 'developpement_personnel', 'age_bracket' => 'avant_18', 'title' => 'Savoir cuisiner un repas complet seul(e)'],
        ['key' => 'benevolat', 'domain' => 'communaute', 'age_bracket' => 'avant_18', 'title' => 'Avoir rendu un service bénévole à sa communauté'],
        ['key' => 'dix_livres', 'domain' => 'developpement_personnel', 'age_bracket' => 'avant_18', 'title' => 'Lire 10 livres en dehors de l’école'],

        // Avant 25 ans
        ['key' => 'diplome_superieur', 'domain' => 'education', 'age_bracket' => 'avant_25', 'title' => 'Obtenir un diplôme universitaire ou une qualification professionnelle'],
        ['key' => 'premier_emploi', 'domain' => 'carriere', 'age_bracket' => 'avant_25', 'title' => 'Décrocher son premier emploi stable ou lancer sa première activité'],
        ['key' => 'compte_bancaire', 'domain' => 'finances', 'age_bracket' => 'avant_25', 'title' => 'Ouvrir un compte bancaire ou mobile money à son nom'],
        ['key' => 'permis_conduire', 'domain' => 'developpement_personnel', 'age_bracket' => 'avant_25', 'title' => 'Obtenir son permis de conduire'],
        ['key' => 'premier_voyage', 'domain' => 'voyage', 'age_bracket' => 'avant_25', 'title' => 'Voyager hors de son pays pour la première fois'],
        ['key' => 'premier_vote', 'domain' => 'communaute', 'age_bracket' => 'avant_25', 'title' => 'Voter pour la première fois'],
        ['key' => 'vivre_seul', 'domain' => 'developpement_personnel', 'age_bracket' => 'avant_25', 'title' => 'Vivre seul(e) ou loin de sa famille pendant un moment'],
        ['key' => 'budget_personnel', 'domain' => 'finances', 'age_bracket' => 'avant_25', 'title' => 'Apprendre à gérer un budget personnel'],
        ['key' => 'papiers_identite', 'domain' => 'communaute', 'age_bracket' => 'avant_25', 'title' => 'Avoir sa propre carte d’identité et son passeport'],

        // Avant 30 ans
        ['key' => 'parcelle', 'domain' => 'finances', 'age_bracket' => 'avant_30', 'title' => 'Acheter sa première parcelle ou son premier terrain'],
        ['key' => 'construire_maison', 'domain' => 'finances', 'age_bracket' => 'avant_30', 'title' => 'Commencer à construire sa propre maison'],
        ['key' => 'mariage', 'domain' => 'famille', 'age_bracket' => 'avant_30', 'title' => 'Se marier (coutumier et/ou civil)'],
        ['key' => 'premier_enfant', 'domain' => 'famille', 'age_bracket' => 'avant_30', 'title' => 'Avoir son premier enfant'],
        ['key' => 'creer_entreprise', 'domain' => 'carriere', 'age_bracket' => 'avant_30', 'title' => 'Créer sa propre entreprise ou son propre commerce'],
        ['key' => 'autre_continent', 'domain' => 'voyage', 'age_bracket' => 'avant_30', 'title' => 'Voyager sur un autre continent'],
        ['key' => 'independance_financiere', 'domain' => 'finances', 'age_bracket' => 'avant_30', 'title' => 'Devenir financièrement indépendant de ses parents'],
        ['key' => 'soutien_famille', 'domain' => 'famille', 'age_bracket' => 'avant_30', 'title' => 'Pouvoir soutenir financièrement sa famille élargie'],
        ['key' => 'poste_responsabilite', 'domain' => 'carriere', 'age_bracket' => 'avant_30', 'title' => 'Occuper un poste à responsabilité'],

        // Avant 40 ans
        ['key' => 'voiture', 'domain' => 'finances', 'age_bracket' => 'avant_40', 'title' => 'Posséder sa propre voiture'],
        ['key' => 'dettes_remboursees', 'domain' => 'finances', 'age_bracket' => 'avant_40', 'title' => 'Avoir remboursé ses dettes importantes'],
        ['key' => 'mentor', 'domain' => 'communaute', 'age_bracket' => 'avant_40', 'title' => 'Devenir mentor ou formateur pour quelqu’un de plus jeune'],
        ['key' => 'cinq_pays', 'domain' => 'voyage', 'age_bracket' => 'avant_40', 'title' => 'Avoir visité au moins 5 pays différents'],
        ['key' => 'formation_avancee', 'domain' => 'education', 'age_bracket' => 'avant_40', 'title' => 'Obtenir une formation avancée (maîtrise, spécialisation…)'],
        ['key' => 'revenu_passif', 'domain' => 'finances', 'age_bracket' => 'avant_40', 'title' => 'Avoir un bien locatif ou une activité qui génère un revenu passif'],
        ['key' => 'laisser_trace', 'domain' => 'developpement_personnel', 'age_bracket' => 'avant_40', 'title' => 'Écrire ou créer quelque chose qui laisse une trace'],
        ['key' => 'scolariser_enfants', 'domain' => 'famille', 'age_bracket' => 'avant_40', 'title' => 'Assurer l’éducation de ses enfants'],

        // Avant 50 ans
        ['key' => 'stabilite_financiere', 'domain' => 'finances', 'age_bracket' => 'avant_50', 'title' => 'Atteindre une stabilité financière durable'],
        ['key' => 'aider_demarrer', 'domain' => 'communaute', 'age_bracket' => 'avant_50', 'title' => 'Avoir aidé un proche à démarrer un projet ou une activité'],
        ['key' => 'transmettre_tradition', 'domain' => 'famille', 'age_bracket' => 'avant_50', 'title' => 'Transmettre un savoir-faire ou une tradition familiale'],
        ['key' => 'preparer_retraite', 'domain' => 'finances', 'age_bracket' => 'avant_50', 'title' => 'Préparer sa retraite ou son plan pour les années suivantes'],
        ['key' => 'oeuvre_communaute', 'domain' => 'communaute', 'age_bracket' => 'avant_50', 'title' => 'Avoir contribué à une œuvre pour sa communauté (école, église, puits…)'],
        ['key' => 'pelerinage', 'domain' => 'spiritualite', 'age_bracket' => 'avant_50', 'title' => 'Accomplir un pèlerinage ou un acte spirituel important pour soi'],

        // Avant 60 ans
        ['key' => 'enfants_independants', 'domain' => 'famille', 'age_bracket' => 'avant_60', 'title' => 'Voir ses enfants devenir indépendants'],
        ['key' => 'transmettre_patrimoine', 'domain' => 'finances', 'age_bracket' => 'avant_60', 'title' => 'Avoir transmis un patrimoine ou un bien à ses enfants'],
        ['key' => 'former_generation', 'domain' => 'communaute', 'age_bracket' => 'avant_60', 'title' => 'Avoir formé ou inspiré une nouvelle génération'],
        ['key' => 'bilan_vie', 'domain' => 'developpement_personnel', 'age_bracket' => 'avant_60', 'title' => 'Faire un bilan de vie et célébrer ses réalisations'],
        ['key' => 'temps_libre', 'domain' => 'loisirs', 'age_bracket' => 'avant_60', 'title' => 'Profiter de voyages ou de temps libre sans contrainte professionnelle'],

        // Un jour, sans âge précis
        ['key' => 'instrument', 'domain' => 'loisirs', 'age_bracket' => 'un_jour', 'title' => 'Apprendre à jouer d’un instrument de musique'],
        ['key' => 'marathon', 'domain' => 'sante', 'age_bracket' => 'un_jour', 'title' => 'Courir un marathon ou une course longue distance'],
        ['key' => 'cinquante_livres', 'domain' => 'developpement_personnel', 'age_bracket' => 'un_jour', 'title' => 'Lire 50 livres'],
        ['key' => 'troisieme_langue', 'domain' => 'education', 'age_bracket' => 'un_jour', 'title' => 'Parler couramment une troisième langue'],
        ['key' => 'publier_livre', 'domain' => 'developpement_personnel', 'age_bracket' => 'un_jour', 'title' => 'Publier un livre'],
        ['key' => 'don_important', 'domain' => 'communaute', 'age_bracket' => 'un_jour', 'title' => 'Faire un don important à une cause qui compte pour soi'],
        ['key' => 'reconciliation', 'domain' => 'famille', 'age_bracket' => 'un_jour', 'title' => 'Se réconcilier avec quelqu’un d’important'],
        ['key' => 'vivre_etranger', 'domain' => 'voyage', 'age_bracket' => 'un_jour', 'title' => 'Vivre dans un autre pays pendant au moins un an'],
        ['key' => 'creer_emploi', 'domain' => 'carriere', 'age_bracket' => 'un_jour', 'title' => 'Créer un emploi pour quelqu’un d’autre'],
        ['key' => 'parents_fiers', 'domain' => 'famille', 'age_bracket' => 'un_jour', 'title' => 'Accomplir quelque chose dont ses parents sont fiers'],
        ['key' => 'evenement_familial', 'domain' => 'famille', 'age_bracket' => 'un_jour', 'title' => 'Organiser un grand événement familial (dot, mariage, fête…)'],
        ['key' => 'checkup_sante', 'domain' => 'sante', 'age_bracket' => 'un_jour', 'title' => 'Faire un bilan de santé complet'],
    ],
];
