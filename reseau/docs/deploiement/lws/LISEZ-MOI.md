# Déployer Réseau sur LWS (FTP + CRON)

Adapté du mode d'emploi « déploiement automatique LWS ». Principe : GitHub compile le site et l'envoie par FTP ; le
CRON du serveur (chaque minute) recopie le code, migre la base et vide les caches. Il n'y a ni SSH ni adresse web de
déploiement.

```
GitHub « Run workflow » ──FTP──► htdocs/home/reseau-app      dépôt de passage (fermé au web par .htaccess)
                         └─FTP──► htdocs/reseau.mark-cd.com  partie publique (index.php, build/, icônes)

CRON, chaque minute : php /home/reseau-app/cron.php  →  deploiement:finaliser
   recopie le dépôt vers /home/reseau-app (hors du web), optimize:clear, migrate, optimize
```

> **Vert sur GitHub ne veut pas dire déployé.** Le site est à jour une à deux minutes après, quand le CRON a passé.

## Ce qui est dans le dépôt

| Fichier | Rôle |
|---|---|
| `.github/workflows/deployer.yml` (racine du dépôt) | compile et envoie par FTP, à la demande uniquement |
| `reseau/app/Console/Commands/FinaliserLeDeploiement.php` | recopie, caches, migrations (une fois par déploiement) |
| `reseau/routes/console.php` | planifie la commande chaque minute |
| `reseau/cron.php` | point d'entrée du CRON LWS (refuse le web) |
| `reseau/amorcage.php` | premier déploiement, à lancer une seule fois |
| `reseau/config/deploiement.php` | chemins du dépôt de passage et de l'application |
| `reseau/docs/deploiement/lws/reseau/index.php`, `htaccess.txt` | partie publique propre à LWS |
| `reseau/docs/deploiement/lws/env-serveur.exemple` | modèle du `.env` du serveur |

## À faire une seule fois

### 1. GitHub : environnement, secrets, variables
Dépôt → Settings :

- **Environments → New environment → `production`** (sans lui, le job reste en attente). Tu peux y exiger ton approbation.
- **Secrets and variables → Actions → Secrets** :

  | Nom | Valeur |
  |---|---|
  | `LWS_FTP_HOTE` | l'adresse du serveur FTP |
  | `LWS_FTP_UTILISATEUR` | l'identifiant FTP |
  | `LWS_FTP_MOT_DE_PASSE` | le mot de passe FTP |

- **… → Variables** :

  | Nom | Valeur |
  |---|---|
  | `LWS_DOSSIER_APP` | `home/reseau-app` |
  | `LWS_DOSSIER_PUBLIC` | `reseau.mark-cd.com` (le nom exact du dossier du sous-domaine dans `htdocs`) |
  | `LWS_FTP_PROTOCOLE` | `ftps` si LWS l'accepte (sinon rien : `ftp`, mot de passe en clair sur le réseau) |

  Ces chemins sont **relatifs à `htdocs`** : jamais `/htdocs/…`.

### 2. Le fichier `.env` du serveur
Sur ton poste : `cp reseau/docs/deploiement/lws/env-serveur.exemple .env`, puis remplis :
- `APP_KEY` : `php artisan key:generate --show`. **Ne la change jamais** (elle chiffre le Coffre) et garde-la ailleurs aussi.
- `VAPID_*` : `php artisan reseau:vapid` (rappels par notification).
- `AMORCAGE_EMAIL`, `AMORCAGE_NOM`, `AMORCAGE_MOT_DE_PASSE` : ton premier compte.

### 3. Premier déploiement (amorçage)
1. GitHub → Actions → **Déployer sur LWS** → *Run workflow* (premier envoi long : `vendor/` fait des milliers de fichiers).
2. Dépose ton `.env` par FTP dans `htdocs/home/reseau-app/` (à côté de `VERSION`).
3. Panneau LWS → **Tâches CRON** : crée une tâche, à lancer une fois, sur `php /home/htdocs/home/reseau-app/amorcage.php`.
   Il recopie le code vers `/home/reseau-app`, **supprime le `.env` du dépôt** (il est sous `htdocs`), crée la base SQLite, migre,
   crée ton utilisateur et retire les lignes `AMORCAGE_*`. Lis sa sortie si LWS l'affiche ; sinon vérifie que le site répond.
4. Crée la tâche **de chaque minute** : `php /home/reseau-app/cron.php`. Elle finalise tous les déploiements suivants, et
   fait aussi tourner les rappels de santé et le nettoyage du Coffre.
5. Ouvre `https://reseau.mark-cd.com` et connecte-toi.

Si LWS ne propose pas d'intervalle d'une minute, la finalisation prendra l'intervalle choisi, et les rappels seront moins précis.

## Déployer ensuite
1. Fusionne ce que tu veux publier dans la branche `main` (le workflow n'apparaît dans l'onglet Actions que depuis la branche par défaut).
2. Actions → **Déployer sur LWS** → *Run workflow* (case « migrations » cochée par défaut).
3. Attends que GitHub soit vert (1 à 4 min), puis 1 à 2 min de plus, et recharge avec Ctrl + F5.

Ce que le déploiement ne touche **jamais** : `.env`, `storage/` (Coffre chiffré, sessions), `database/*.sqlite`, `bootstrap/cache/`.

## Sauvegardes
Sauvegarde régulièrement (FTP) `home/reseau-app/database/database.sqlite` **et** `home/reseau-app/storage/app/private/vault/`.
Sans `APP_KEY`, les fichiers du Coffre sont perdus.

## Modifier le `.env` du serveur
L'application met sa configuration en cache. Après une modification du `.env`, elle n'est prise en compte qu'au prochain
déploiement (qui vide le cache), ou après `php artisan optimize:clear`.

## Dépannage

| Symptôme | Cause probable | Remède |
|---|---|---|
| Run vert, site inchangé | CRON absent, ou `DEPLOIEMENT_DEPOT` faux | vérifier la tâche CRON, le chemin vu du serveur, la présence de `VERSION` dans le dépôt |
| Fichiers dans `htdocs/htdocs` | `LWS_DOSSIER_*` écrits en absolu | les rendre relatifs à `htdocs` |
| 500 partout sauf `/` | `.htaccess` | remplacer son contenu par `FallbackResource /index.php` |
| 500 sur toutes les pages | `.env` absent / `APP_KEY` vide, droits d'écriture | `storage/`, `bootstrap/cache/`, `database/` en 775 |
| Erreur « could not find driver » | extension `pdo_sqlite` absente de la version PHP du CRON ou du site | choisir une version PHP ≥ 8.3 avec SQLite dans le panneau LWS |
| `sodium` / `fileinfo` manquant | extension désactivée | l'activer dans le panneau LWS (le Coffre en a besoin) |
| La migration ne se fait pas | `migrer=0`, ou une migration échoue en boucle | lire la sortie du CRON ; la version n'est marquée traitée qu'après succès |
| Géolocalisation, micro, Face ID refusés | site en HTTP | activer le SSL du sous-domaine |

## Limites
- Le mot de passe FTP voyage en clair tant que `ftps` n'est pas activé.
- Le CRON et l'amorçage sont des gestes du panneau LWS : ils ne peuvent pas être faits depuis Git.
- Non vérifié sur le vrai serveur : chemins FTP, version PHP du CRON, intervalle minimal du CRON.
