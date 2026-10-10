# Réseau — carnet de contacts personnel

Application web (PWA) sans serveur : ouvre `reseau/index.html` depuis GitHub Pages (ou n'importe quel hébergement statique), puis « Ajouter à l'écran d'accueil » sur le téléphone.

- **Contacts** : recherche, filtre par domaine, tri par distance / ajout récent / à relancer, appel, SMS, WhatsApp, email, itinéraire.
- **Carte** : tes contacts sur OpenStreetMap, avec ta position et un rayon (1 à 100 km) pour voir qui est près de toi.
- **Domaines** : pour chaque domaine, la liste de ceux que tu peux appeler.
- **Fiche** : rôle, comment vous vous êtes connus, anecdote à retenir, services dus/rendus, dernier contact.
- **Import / export** : sauvegarde `.json`, import `.vcf` (export Google Contacts).

## Données

Les contacts sont stockés dans le navigateur de l'appareil (`localStorage`), jamais dans ce dépôt. Pour passer d'un appareil à un autre : menu ⋯ → *Exporter une sauvegarde*, puis *Importer* sur l'autre appareil. Une synchronisation automatique (compte + base distante) reste à ajouter.

## Dépendances

Leaflet 1.9.4 (BSD-2) est inclus dans `vendor/leaflet/`. Les tuiles viennent d'OpenStreetMap et la recherche d'adresse de Nominatim.
