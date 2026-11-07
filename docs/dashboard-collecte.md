# Dashboard — la collecte (G4)

Statut : G4a livré le 07/11/2026, à valider · G4b et G4c à faire · lot G du `TODO.md`
Règles de référence : `docs/dashboard-vie-privee.md` (§§ 4 à 6). Ce document décrit le code qui les applique.

## 1. Trois temps

| Étape | Contenu | Effet sur le site |
|---|---|---|
| **G4a** | Quatre classes pures et leurs tests | **Aucun** : rien ne les appelle encore |
| G4b | Écriture en base et appel unique dans `index.php` | La collecte commence (si `dashboard.config.php` existe) |
| G4c | `check-g4.sh`, `dashboard.config.php` de production, migrations importées | Mise en ligne contrôlée |

Une page ne doit jamais échouer à cause de la collecte : sans fichier de configuration, base absente, saturée ou en erreur, le site répond comme avant (G4b).

## 2. Les quatre briques (`classes/dashboard/`)

Aucune ne lit `$_SERVER`, un cookie ou la base : on leur passe des valeurs, on récupère des valeurs. Compatibles PHP 8.0.

| Classe | Rôle |
|---|---|
| `VisitPrivacy` | Les fonctions de référence de G1, déplacées ici sans changement de comportement : adresse IP normalisée (jamais stockée), sel du jour dérivé du secret serveur, clé de visite (HMAC tronqué), jour au fuseau Europe/Paris, continuité d'une visite (moins de 30 min, jamais à travers minuit), origine réduite au domaine, paramètres `utm_*` sobres |
| `VisitText` | Nettoie tout texte venu du visiteur avant la base, qui est en mode strict : UTF-8 invalide remplacé, caractères de contrôle et de sens d'écriture retirés, longueur bornée **en caractères**. `path()` retire la requête et le fragment : le jeton d'un lien de visite guidée n'arrive jamais en base |
| `VisitClassifier` | Profil `test` (User-Agent contenant `JTissier-CheckBot`), `bot` ou `human`. Pour un humain : appareil, navigateur et système en familles grossières, jamais le User-Agent. `optedOut()` lit `DNT` et `Sec-GPC` (décision D1) |
| `BurstLimiter` | Plafond **global** d'écritures par fenêtre de temps (fichier verrouillé dans le dossier temporaire). Au-delà, la collecte ignore la demande sans ouvrir de connexion et compte les demandes ignorées, pour que le dashboard les affiche. Si le dossier temporaire est inutilisable, il ne freine rien |

`tests/dashboard/visit-privacy-reference.php` ne contient plus de logique : il appelle `VisitPrivacy`. Les 67 contrôles de `test_visit_privacy.php` portent donc sur le code réellement exécuté en production (le dossier `tests/` n'est pas déployé).

## 3. Classement des visites

Premier critère satisfait :

1. **test** : le User-Agent contient `JTissier-CheckBot`.
2. **bot** : User-Agent absent ou `-` · robot nommé (moteurs de recherche, IA, aperçus de liens, SEO, surveillance, navigateurs sans écran) · outil ou bibliothèque HTTP (curl, Wget, python-requests, Java…) · mot générique (`bot`, `crawler`, `spider`, `scan`, `fetch`…) · User-Agent qui ne commence ni par `Mozilla/` ni par `Opera/`.
3. **human** : tout le reste.

Les profils `guided` et `developer` dépendent des cookies de G5 et G6 : ils s'ajouteront à ce classement.

Limites connues, communes à toute mesure d'audience sans script ni cookie : un robot qui se fait passer pour un navigateur est compté comme humain ; un iPad récent se présente comme un Mac et est compté comme ordinateur ; un navigateur intégré à une application (LinkedIn, Instagram) apparaît comme « App WebView ».

**Ajouter un robot** : une ligne dans `VisitClassifier::KNOWN_BOTS` (fragment du User-Agent => nom affiché, les plus précis en premier), puis un cas dans `tests/dashboard/test_visit_classifier.php`.

## 4. Tests

Depuis la racine du projet :

```bash
php tests/dashboard/test_visit_text.php
php tests/dashboard/test_visit_classifier.php
php tests/dashboard/test_burst_limiter.php
php tests/dashboard/test_visit_privacy.php
```

Résultats attendus : 54, 312, 39 et 67 contrôles, 0 échec. Les tests de G2 (`test_dashboard_infra.php`, `test_db_schema.php`) ne couvrent pas ces classes ; ils servent ici de non-régression et doivent rester à 0 échec.

Contrôle de la qualité des tests eux-mêmes : 17 erreurs volontaires ont été glissées une à une dans ces classes (plafond atteint accepté, fuseau UTC au lieu de Paris, DNT à 0 pris pour un refus, TelegramBot pris pour Twitterbot, robot de test classé robot, etc.). Les 17 sont détectées.

## 5. Prévu pour G4b et G4c

- Un seul appel dans `index.php`, après le routage : page, langue, code HTTP (200, 301, 404, 500). Fichiers statiques ignorés.
- Écriture après l'envoi de la page quand l'hébergement le permet (à vérifier chez IONOS).
- `BurstLimiter` devant les 404 ; le disjoncteur de `DashboardDatabase` reste la dernière protection.
- Visiteur avec `DNT: 1` ou `Sec-GPC: 1` : seulement le compteur quotidien anonyme de la page (décision D1).
- À faire avant G4c, côté IONOS : créer `dashboard.config.php` (secret d'au moins 32 octets, accès à la base) et importer `001_core.sql` et `002_access.sql` dans phpMyAdmin.
