# Réseau

Application personnelle, utilisable depuis un téléphone (PWA), en trois parties :

- **Contacts** : carnet moderne avec domaines (« qui contacter pour… »), rôle, anecdote, services dus ou rendus, carte OpenStreetMap, distance par rapport à ta position, import Google Contacts (`.vcf`).
- **Itinéraire dans l'application** : depuis la fiche d'un contact ou la carte, le trajet s'affiche sur la carte en plein écran (voiture, à pied ou vélo) avec durée, distance, étapes en français et suivi en direct (la consigne avance avec ta position, recalcul si tu t'écartes). Si le service de routage est injoignable, tu gardes la direction à vol d'oiseau et un lien de secours vers une autre application.
- **Coach** : chaque jour, des suggestions pour cultiver ton réseau (relancer, présenter deux contacts, combler un domaine vide, rendre un service, écrire une anecdote, apprendre d'un expert, surprendre quelqu'un) et des jauges de progression.
- **Formulaires en plusieurs étapes** partout (connexion, contact, habitude, objectif, mesure, repas), avec validation étape par étape, animations (désactivées si le système demande moins de mouvement) et comportement d'application mobile : pas de zoom, barre du haut aux couleurs de la page, pas d'action involontaire au double tap.
- **Illustrations** originales intégrées (SVG, sans image externe) sur l'écran de connexion et dans les écrans vides, adaptées au mode sombre. **Carte en plein écran** (bouton en haut à droite de la carte, Échap pour quitter) avec filtres et position.
- **Finances** : tous tes fonds quel que soit le compte, la source ou le lieu (banque, mobile money, espèces, épargne, tontine…) avec historique des mouvements et virements entre comptes (même entre devises) ; ce que les autres te doivent (créances) et ce que tu dois (dettes) avec échéances, remboursements partiels et lien avec un compte et un contact ; tes biens (immobilier, terrain, véhicule, commerce, or…) et la liste des **types de biens que tu ne possèdes pas encore**, avec des « envies » à planifier ; patrimoine net converti dans la devise de référence avec tes propres taux de change. Le Coach te rappelle les créances à relancer et les dettes à honorer.
- **Santé** : programmes prêts à l'emploi (reprise en douceur, perte de poids progressive, reprise de poids saine, sommeil, soins du corps), planning hebdomadaire d'habitudes à cocher, planning des repas, suivi du poids / tour de taille / sommeil / énergie / humeur avec courbes, séries et taux de réalisation, **rappels par notification**.
- **Coffre (dossiers confidentiels)** : des dossiers sur des personnes ou des sujets, rattachables à un contact (bouton « Dossier confidentiel » sur la fiche), avec résumé, **photos, vidéos, enregistrements audio** (micro intégré), documents. Photo et vidéo peuvent être prises directement depuis le téléphone. Voir la section *Coffre* plus bas.
- **Mots de passe** (dans le Coffre) : comptes en ligne, comptes bancaires, cartes (PIN), codes PIN, **schémas de déverrouillage** (grille 3 × 3), Wi-Fi, notes sécurisées. Générateur de mot de passe et de code, indicateur de robustesse, champs libres (codes de secours, CVV…), favoris, recherche. Les secrets restent masqués jusqu'à ce que tu les affiches (remasqués après 20 s) et le presse-papiers est vidé 30 s après une copie.
- **Partage ponctuel** : un lien à usage unique et avec expiration (1 h, 24 h ou 7 j) pour montrer un document du Coffre à quelqu'un sans lui donner accès au reste.
- **Export chiffré du Coffre** : une sauvegarde de tes dossiers et mots de passe en un clic, indépendante du FTP, protégée par un mot de passe que tu choisis. Se rouvre hors ligne avec `dechiffrer.html`, une page qui fonctionne dans n'importe quel navigateur.
- **Objectif de réseau** et **recherche unique** (contacts, Coffre, finances) dans le Coach et l'en-tête de l'application ; **journal des nouveautés** (`/nouveautes`).

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

### Mettre à jour une installation existante

```sh
git pull origin claude/kind-galileo-c0rsyi
composer install
npm install
php artisan migrate        # applique les nouvelles tables (ex. clés d'accès)
npm run build
php artisan serve
```

### Face ID / empreinte (clés d'accès)

Après une première connexion avec ton mot de passe : *Réglages → Face ID / empreinte → Ajouter cet appareil*. Ensuite l'écran de connexion de cet appareil propose un bouton « Face ID / empreinte », sans mot de passe. C'est le **téléphone** qui vérifie ton visage ou ton empreinte (standard WebAuthn) : l'application ne reçoit et ne stocke aucune donnée biométrique, seulement une clé publique, et une photo ne suffit pas à tromper le système.

- Il faut **HTTPS et un nom de domaine** (pas une adresse IP). En local sur l'ordinateur, utilise `http://localhost:8000` (et non `127.0.0.1`). Pour un téléphone, il faut un tunnel HTTPS ou le déploiement.
- Derrière un tunnel ou un domaine particulier, renseigne dans `.env` : `APP_URL=https://ton-domaine`, `WEBAUTHN_RP_ID=ton-domaine` et, si le serveur est derrière un proxy, `TRUSTED_PROXIES=*`.
- Une clé d'accès est liée au domaine : si le domaine change (nouveau tunnel), il faut réenregistrer l'appareil.
- Le bouton n'apparaît que sur les appareils déjà enregistrés. Il n'existe aucun moyen de contourner le mot de passe : sans clé enregistrée sur l'appareil, rien ne permet d'entrer.
- Les tentatives sont limitées (10 échecs par 15 minutes), chaque réponse est vérifiée (signature, origine, défi à usage unique, compteur anti-clonage, vérification de la personne obligatoire).

### Ouvrir l'application depuis un téléphone (même Wi-Fi)

`php artisan serve` n'écoute par défaut que l'ordinateur lui-même (`127.0.0.1`) : le téléphone ne peut pas s'y connecter. Lance plutôt :

```sh
composer lan          # équivaut à : php artisan serve --host=0.0.0.0 --port=8000
```

puis ouvre `http://ADRESSE-IP-DE-TON-ORDINATEUR:8000` sur le téléphone (adresse : `ipconfig` sous Windows, `ipconfig getifaddr en0` sous Mac, `hostname -I` sous Linux). Le téléphone et l'ordinateur doivent être sur le même Wi-Fi, et le pare-feu doit autoriser PHP sur les réseaux privés (Windows te le demande au premier lancement).

Si la page reste blanche : arrête `npm run dev` (ou supprime `public/hot`) puis relance `npm run build`. Le serveur de développement Vite écoute sur `localhost`, que le téléphone ne voit pas. Pour aller plus vite, décommente `PHP_CLI_SERVER_WORKERS=4` dans `.env`.

Limite : en `http://` sur une adresse IP, la localisation, Face ID / empreinte, l'installation sur l'écran d'accueil et les notifications sont bloquées par le navigateur. Il faut HTTPS (tunnel ou déploiement) pour les essayer.

### Voir l'application avec des données de démonstration

```sh
php artisan reseau:demo
```

Crée (ou recrée) le compte `demo@reseau.test` / `demo-password-123` avec 32 contacts fictifs, 70 jours d'habitudes cochées, des repas planifiés, des pesées et un objectif. Les dates sont relatives à aujourd'hui, donc l'écran paraît toujours vivant. La commande ne touche qu'à ce compte, et refuse de tourner en production sans `--force`.

Il n'y a **pas d'inscription publique** : les comptes se créent uniquement avec `reseau:user`. Pour changer un mot de passe, relance la même commande avec la même adresse.

Tests : `composer test` (PHPUnit, 87 tests) et `npm test` (Vitest, 73 tests : coach, santé, finances, dates, géolocalisation, clés d'accès).

## Mise en ligne

> Hébergement mutualisé **LWS** (FTP + CRON, sans SSH) : déploiement automatique depuis GitHub, voir `docs/deploiement/lws/LISEZ-MOI.md`.

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

## Coffre (dossiers confidentiels)

- **Verrou** : l'onglet *Coffre* demande le mot de passe du compte (5 essais par 15 minutes). Une session volée ne suffit donc pas à l'ouvrir. Il se referme après 15 minutes d'inactivité (`VAULT_UNLOCK_MINUTES`), au bouton *Verrouiller*, ou quand l'application reste plus d'une minute en arrière-plan.
- **Chiffrement** : chaque fichier a sa propre clé aléatoire (elle-même chiffrée avec `APP_KEY`) ; le contenu est chiffré par morceaux de 1 Mo (XChaCha20-Poly1305) avant d'être écrit dans `storage/app/private/vault`. Résumés et notes sont chiffrés dans la base. Un morceau modifié, déplacé ou copié d'un autre fichier est détecté et refusé.
- **Sauvegarde** : sauvegarde la base **et** `storage/app/private/vault`, et garde `APP_KEY` à part. **Sans `APP_KEY`, les fichiers du coffre sont définitivement illisibles.** Ne la change jamais après avoir ajouté des fichiers.
- **Envoi** : les fichiers partent par morceaux de 1 Mo, avec reprise automatique en cas de coupure réseau : les limites `upload_max_filesize` / `post_max_size` de PHP n'ont pas à être modifiées. Limites par défaut : 500 Mo par fichier (`VAULT_MAX_FILE_MB`), 4 Go au total (`VAULT_QUOTA_MB`).
- **Types acceptés** : reconnus d'après le contenu (images, vidéos, audio, PDF, texte, Office, zip), jamais d'après l'extension. HTML, SVG et scripts sont refusés. Les fichiers sont servis avec `nosniff`, une CSP `sandbox` et sans mise en cache ; seuls images, vidéos et audio s'affichent, le reste se télécharge.
- **Mots de passe** : chaque fiche (titre, identifiant, secret, notes, champs) est un bloc JSON chiffré avec `APP_KEY` ; la liste n'envoie jamais les secrets, qui ne sont demandés qu'à l'ouverture d'une fiche, et seulement si le coffre est déverrouillé. Ce n'est pas un chiffrement « zéro connaissance » : quelqu'un qui aurait la base **et** `APP_KEY` pourrait les lire.
- **Nettoyage** : `reseau:vault-clean` (planifié chaque nuit) supprime les envois abandonnés depuis plus de 24 h et les fichiers chiffrés orphelins.
- **Enregistrement audio et caméra** demandent une connexion HTTPS (ou `localhost`), comme la géolocalisation.
- ⚠️ Conserver des informations sur d'autres personnes est encadré par la loi de ton pays (protection des données, vie privée, secret des correspondances). Garde ce que tu as le droit de détenir, pour un usage légitime.

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
