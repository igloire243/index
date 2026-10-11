# Réseau

Application personnelle, utilisable depuis un téléphone (PWA), en trois parties :

- **Contacts** : carnet moderne avec domaines (« qui contacter pour… »), rôle, anecdote, services dus ou rendus, carte OpenStreetMap, distance par rapport à ta position, import Google Contacts (`.vcf`).
- **Coach** : chaque jour, des suggestions pour cultiver ton réseau (relancer, présenter deux contacts, combler un domaine vide, rendre un service, écrire une anecdote, apprendre d'un expert, surprendre quelqu'un) et des jauges de progression.
- **Formulaires en plusieurs étapes** partout (connexion, contact, habitude, objectif, mesure, repas), avec validation étape par étape, animations (désactivées si le système demande moins de mouvement) et comportement d'application mobile : pas de zoom, barre du haut aux couleurs de la page, pas d'action involontaire au double tap.
- **Santé** : programmes prêts à l'emploi (reprise en douceur, perte de poids progressive, reprise de poids saine, sommeil, soins du corps), planning hebdomadaire d'habitudes à cocher, planning des repas, suivi du poids / tour de taille / sommeil / énergie / humeur avec courbes, séries et taux de réalisation, **rappels par notification**.

> Le module santé est un outil de suivi, pas un avis médical. Il ne prescrit ni régime ni calories.

Pile : Laravel 13 (PHP 8.3), SQLite, Vue 3, Tailwind CSS 4, Leaflet. Aucune donnée n'est envoyée à un tiers, sauf les tuiles de carte (OpenStreetMap) et la recherche d'adresse (Nominatim) faites par le navigateur.

## Installation (développement)

Prérequis : PHP 8.3 avec `sqlite3`, Composer, Node 20+.

```sh
cd reseau
composer setup                     # dépendances, .env, clé, base SQLite, build
php artisan reseau:user toi@exemple.com   # crée ton compte (mot de passe demandé, 10 caractères min.)
php artisan serve                  # http://127.0.0.1:8000
```

### Voir l'application avec des données de démonstration

```sh
php artisan reseau:demo
```

Crée (ou recrée) le compte `demo@reseau.test` / `demo-password-123` avec 32 contacts fictifs, 70 jours d'habitudes cochées, des repas planifiés, des pesées et un objectif. Les dates sont relatives à aujourd'hui, donc l'écran paraît toujours vivant. La commande ne touche qu'à ce compte, et refuse de tourner en production sans `--force`.

Il n'y a **pas d'inscription publique** : les comptes se créent uniquement avec `reseau:user`. Pour changer un mot de passe, relance la même commande avec la même adresse.

Tests : `composer test` (PHPUnit, 43 tests) et `npm test` (Vitest, 49 tests : coach, santé, dates, géolocalisation).

## Mise en ligne

L'application a besoin d'un **serveur PHP avec un disque qui persiste** (VPS, Laravel Forge, Fly.io ou Railway avec un volume…). GitHub Pages ne convient pas. Sur un hébergement dont le disque est effacé à chaque déploiement, tes données SQLite seraient perdues.

```sh
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate --force
php artisan reseau:user toi@exemple.com
```

Dans `.env` :

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://reseau.ton-domaine.com
SESSION_SECURE_COOKIE=true
TRUSTED_PROXIES=*              # si l'application est derrière un proxy HTTPS
```

Puis `php artisan config:cache && php artisan route:cache`, et pointe la racine web vers `reseau/public`. Le HTTPS est obligatoire (géolocalisation, notifications, installation sur l'écran d'accueil).

**Sauvegarde** : copie régulièrement `database/database.sqlite` (par exemple `sqlite3 database/database.sqlite ".backup sauvegarde.sqlite"`). Les contacts peuvent aussi s'exporter en `.json` depuis Réglages.

### Rappels (notifications push)

1. `php artisan reseau:vapid` génère une paire de clés ; copie les trois lignes dans `.env` (`VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT`).
2. Ajoute la tâche planifiée Laravel au cron du serveur (chaque minute) :
   ```
   * * * * * cd /chemin/vers/reseau && php artisan schedule:run >> /dev/null 2>&1
   ```
3. Sur chaque appareil : Réglages → *Activer les rappels*. Sur iPhone (iOS 16.4 minimum), ajoute d'abord l'application à l'écran d'accueil.

Un rappel est envoyé à l'heure prévue de l'habitude (dans ton fuseau horaire, mis à jour automatiquement depuis ton navigateur) si elle n'est pas encore cochée, une fois par jour, et abandonné après 3 heures. Plusieurs rappels simultanés sont regroupés en une seule notification.

## Sécurité

- Connexion par session (cookie `HttpOnly`, `SameSite=Lax`, jeton CSRF), 5 tentatives de connexion par 15 minutes et par adresse.
- Chaque ressource est liée à son propriétaire : un utilisateur ne peut ni lire, ni modifier, ni supprimer les données d'un autre (testé).
- En-têtes de sécurité dont une politique CSP stricte (scripts uniquement depuis ce site).
- Le service worker ne met jamais en cache les réponses de `/api/` (données personnelles).

## Structure

```
app/Http/Controllers/Api/   API JSON (contacts, habitudes, repas, mesures, objectif, programmes, push)
app/Console/Commands/       reseau:user, reseau:vapid, reseau:send-reminders
config/programs.php         programmes santé prêts à l'emploi
resources/js/               application Vue (views/, components/, stores/, lib/)
public/sw.js                service worker (hors connexion + notifications)
```

Hors connexion, l'application s'ouvre mais les données demandent le réseau : une action sans connexion affiche une erreur au lieu d'être enregistrée.
