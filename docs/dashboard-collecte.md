# Dashboard — la collecte (G4)

Statut : G4a validé le 07/11/2026 · G4b validé le 11/11/2026 (décisions D8 et D9 comprises) · G4c : essai de bout en bout validé, mise en ligne à faire · lot G du `TODO.md`
Règles de référence : `docs/dashboard-vie-privee.md` (§§ 4 à 6). Ce document décrit le code qui les applique.

## 1. Trois temps

| Étape | Contenu | Effet sur le site |
|---|---|---|
| **G4a** | Quatre classes pures et leurs tests | **Aucun** : rien ne les appelle |
| **G4b** | Écriture en base (`VisitCollector`), point d'entrée (`CollectorHook`), deux lignes dans `index.php` | La collecte commence **si `dashboard.config.php` existe**. Sans ce fichier : aucun effet |
| **G4c, 1re partie** | `test_collector_live.php` et `check-g4.sh` : de vraies requêtes contre le site local, puis relecture de la base | **Aucun** (tests seulement) |
| G4c, 2e partie | `dashboard.config.php` de production, migrations importées chez IONOS, `check-g4.sh prod` | Mise en ligne contrôlée |

Une page ne doit jamais échouer à cause de la collecte : sans fichier de configuration, base absente, saturée ou en erreur, le site répond comme avant.

## 2. Les quatre briques (`classes/dashboard/`, G4a)

Aucune ne lit `$_SERVER`, un cookie ou la base : on leur passe des valeurs, on récupère des valeurs. Compatibles PHP 8.0.

| Classe | Rôle |
|---|---|
| `VisitPrivacy` | Les fonctions de référence de G1, déplacées ici sans changement de comportement : adresse IP normalisée (jamais stockée), sel du jour dérivé du secret serveur, clé de visite (HMAC tronqué), jour au fuseau Europe/Paris, continuité d'une visite (moins de 30 min, jamais à travers minuit), origine réduite au domaine, paramètres `utm_*` sobres |
| `VisitText` | Nettoie tout texte venu du visiteur avant la base, qui est en mode strict : UTF-8 invalide remplacé, caractères de contrôle et de sens d'écriture retirés, longueur bornée **en caractères**. `path()` retire la requête et le fragment : le jeton d'un lien de visite guidée n'arrive jamais en base |
| `VisitClassifier` | Profil `test` (User-Agent contenant `JTissier-CheckBot`), `bot` ou `human`. Pour un humain : appareil, navigateur et système en familles grossières, jamais le User-Agent. `optedOut()` lit `DNT` et `Sec-GPC` (décision D1) |
| `BurstLimiter` | Plafond **global** d'écritures par fenêtre de temps (fichier verrouillé dans le dossier temporaire). Au-delà, la collecte ignore la demande sans ouvrir de connexion et compte les demandes ignorées. Si le dossier temporaire est inutilisable, il ne freine rien |

## 3. Classement des visites

Premier critère satisfait :

1. **test** : le User-Agent contient `JTissier-CheckBot`. Ce robot reste compté dans ses propres compteurs, mais n'écrit aucune ligne d'erreur 404 et ne consomme pas le frein des 404 (décision D9, § 4.2).
2. **bot** : User-Agent absent ou `-` · robot nommé (moteurs de recherche, IA, aperçus de liens, SEO, surveillance, navigateurs sans écran) · outil ou bibliothèque HTTP (curl, Wget, python-requests, Java…) · mot générique (`bot`, `crawler`, `spider`, `scan`, `fetch`…) · User-Agent qui ne commence ni par `Mozilla/` ni par `Opera/`.
3. **human** : tout le reste.

Les profils `guided` et `developer` dépendent des cookies de G5 et G6 : ils s'ajouteront à ce classement.

Limites connues, communes à toute mesure d'audience sans script ni cookie : un robot qui se fait passer pour un navigateur est compté comme humain ; un iPad récent se présente comme un Mac et est compté comme ordinateur ; un navigateur intégré à une application (LinkedIn, Instagram) apparaît comme « App WebView ».

**Ajouter un robot** : une ligne dans `VisitClassifier::KNOWN_BOTS` (fragment du User-Agent => nom affiché, les plus précis en premier), puis un cas dans `tests/dashboard/test_visit_classifier.php`.

## 4. L'écriture en base (G4b)

### 4.1 Le branchement

`index.php` appelle la collecte **une seule fois**, juste après la résolution de la route (après la ligne `$lang = $route['lang'];`) :

```php
// Collecte d'audience sans cookie (lot G) : sans dashboard.config.php, aucun effet
if (is_file(__DIR__ . '/classes/dashboard/CollectorHook.php')) {
    require_once __DIR__ . '/classes/dashboard/CollectorHook.php';
    CollectorHook::register();
}
```

`register()` enregistre une fonction de **fin de script**. La page est donc entièrement envoyée au visiteur avant la moindre écriture (`fastcgi_finish_request()` quand l'hébergement le permet) ; à ce moment on lit le **vrai** code HTTP : 200, 301 (redirection canonique), 404, ou 500, y compris après une erreur PHP fatale que PHP n'a pas encore signalée. Les classes de la collecte ne sont chargées qu'à cet instant, dans une zone protégée : un fichier manquant est journalisé, il ne casse pas la page. Rien ne se passe en ligne de commande (tests, scripts).

**Interrupteur général** : renommer ou supprimer `dashboard.config.php` arrête la collecte immédiatement, sans toucher au code.

### 4.2 Ce qui est écrit

| Requête | `daily_stats` (compteur) | `errors` | Visite et parcours (`visits`, `hits`) |
|---|---|---|---|
| Page 200 | +1 | | ouvre ou continue la visite, ajoute la page au parcours |
| Redirection 301 | +1 | | rien |
| Page introuvable 404 | +1 (page « 404 ») | une ligne `not_found` par jour et par chemin, avec le nombre d'occurrences (**sauf pour le robot de test**, D9) | ajoutée au parcours **si une visite est en cours**, n'en ouvre jamais |
| Fichier statique introuvable (css, js, image, police, pdf…) | rien | une ligne `not_found` : lien cassé (**rien du tout pour le robot de test**, D9) | rien : ce n'est pas une page vue |
| Erreur 500 (y compris erreur PHP fatale) | +1 | une ligne `server_error` par jour et par chemin | ajoutée au parcours si une visite est en cours, n'en ouvre jamais |
| `DNT: 1` ou `Sec-GPC: 1` (décision D1) | +1 | rien | rien |
| Adresse IP absente ou invalide | +1 | comme ci-dessus | rien |
| Autre méthode que GET (formulaire de contact en POST, HEAD des surveillances), autre code (302, 403, 405…) | rien | rien | rien |

**Décision D8 (validée le 11/11/2026)** : seule une **page de code 200 ouvre une visite**. Un scanner qui se fait passer pour un navigateur et enchaîne 300 adresses inexistantes ne compte donc que pour des 404 (dans `errors` et `daily_stats`), jamais pour 300 visiteurs ; une 301 est toujours suivie de la page 200 de l'adresse canonique, qui ouvre la visite.

**Décision D9 (validée le 11/11/2026)** : le robot de test (`JTissier-CheckBot`, profil « test ») ne laisse **aucune ligne d'erreur 404** et ne consomme pas le frein de 30 pages 404 par minute. Ses 404 restent comptées dans `daily_stats` (profil « test », exclu des statistiques), de sorte que les tests de bout en bout peuvent toujours les relire ; ses images ou fichiers introuvables ne laissent rien du tout. Raison : une série de contrôles (`check-*.sh`) provoque volontairement des 404 ; sans cette règle, elle remplirait la table `errors` de fausses alertes et userait le frein au détriment des vrais visiteurs. **Ce qui reste écrit pour le robot de test** : les erreurs 500, parce qu'une panne du site qu'un contrôle déclenche est une information réelle. **Ce qui ne change pas** : un robot d'indexation (Googlebot…), un scanner ou un humain laisse ses erreurs 404 comme avant ; le test `test_collector_live.php` le vérifie avec un User-Agent de Googlebot.

Rien d'autre n'est écrit : jamais d'adresse IP (elle sert à calculer la clé en mémoire), jamais le User-Agent d'un humain, jamais la requête d'une adresse (`?token=…`) ni le chemin d'un Referer.

### 4.3 Visites

- Clé de visite = HMAC(sel du jour, IP normalisée + User-Agent), 32 caractères hexadécimaux. Le sel du jour dérive du secret du fichier de configuration : la base seule ne permet pas de retrouver une adresse.
- Une page rejoint la visite **la plus récente** du même visiteur le même jour si sa dernière page date de **moins de 30 minutes** ; sinon, nouvelle visite. Une visite ne franchit jamais minuit (Europe/Paris).
- Identifiant de création (`hits.creation_id`) : noté seulement pour une page `creations` de code 200 dont le paramètre `creation` est un identifiant sobre (lettres, chiffres, `_`, `-`, 64 caractères au plus).

### 4.4 Tâches du jour

La **première requête du jour** (une seule, même si dix arrivent ensemble) exécute :

1. **Rotation** : les clés de visite des jours passés sont remises à `NULL` (`visits` et `login_attempts`). Les visites gardent leurs pages et leurs statistiques, seul le pseudonyme disparaît.
2. **Purge** : visites et parcours de plus de **13 mois** (les parcours partent avec leur visite), erreurs de plus de **90 jours**, tentatives de connexion de plus de **30 jours**. Les compteurs de `daily_stats` sont conservés.

Si une tâche échoue, elle est annulée en entier, **le jour n'est pas marqué comme fait** et la requête suivante réessaie. Une tâche en échec n'empêche jamais d'enregistrer la page. Un cron (G10) pourra les lancer à heure fixe ; la logique est la même.

### 4.5 Protections

- **Base indisponible, saturée ou en erreur** : la page est déjà partie ; l'erreur est journalisée avec ses **codes seulement** (jamais le message de la base, une adresse ou un chemin). Une panne durable (table absente, droits, base lente) suspend la collecte **60 secondes** (disjoncteur de `DashboardDatabase`), pour ne pas répéter l'échec à chaque page.
- **Interblocage** (deux requêtes qui créent la même ligne au même instant) : réessayé une fois ; il ne suspend pas la collecte.
- **Atomicité** : une requête est écrite dans une seule transaction (compteur, erreur, visite, page). Un échec au milieu n'écrit rien : pas de visite orpheline, pas de compteur à moitié écrit.
- **Rafales** : au-delà de **30 pages 404 détaillées par minute** (le robot de test n'y compte pas), ou de **600 écritures par minute** toutes pages confondues, les demandes sont ignorées **sans ouvrir de connexion**. Elles sont résumées dans `errors`, à la prochaine écriture réussie, par deux lignes : `[rafale de 404 : demandes non détaillées]` (type `not_found`) et `[plafond d'écritures atteint : demandes non détaillées]` (type `security`), dont `occurrences` est le nombre de demandes ignorées. Ces sujets commencent par `[` : ils ne peuvent pas être confondus avec un chemin.
- **Textes hostiles** : octets invalides, caractères de contrôle, sens d'écriture inversé, chemins de 5 000 caractères : nettoyés et bornés avant la base, qui est en mode strict.

### 4.6 Limites connues

- Deux **premières pages simultanées** d'un même visiteur (deux onglets ouverts au même instant) peuvent ouvrir deux visites au lieu d'une. Verrouiller la visite coûterait une attente sur chaque page ; l'écart est négligeable.
- Dans `errors`, la comparaison de MariaDB / MySQL ignore la casse et les accents : `/Page` et `/page`, `/cafe` et `/café` forment une seule ligne. C'est voulu (un scanner qui varie la casse ne produit qu'une ligne) ; SQLite, utilisé pour les tests, les distingue : le test sur la vraie base le vérifie.
- L'écriture après l'envoi de la page dépend de l'hébergement (`fastcgi_finish_request`). Sans elle, la collecte s'exécute quand même en fin de script, avant la fermeture de la connexion : quelques millisecondes de plus par page. À mesurer chez IONOS en G4c.

## 5. Tests

Depuis la racine du projet :

```bash
php tests/dashboard/test_visit_text.php
php tests/dashboard/test_visit_classifier.php
php tests/dashboard/test_burst_limiter.php
php tests/dashboard/test_visit_privacy.php
php tests/dashboard/test_visit_collector.php
php tests/dashboard/test_collector_mysql.php
php tests/dashboard/test_collector_live.php http://localhost/JT-Developpeur
```

Et, pour enchaîner le tout contre le site local : `check-g4.sh local http://localhost/JT-Developpeur` (script du dépôt `website-checker`).

| Test | Contrôles attendus | Rôle |
|---|---|---|
| `test_visit_text.php` | 54 | G4a : nettoyage des textes |
| `test_visit_classifier.php` | 312 | G4a : classement, DNT et GPC |
| `test_burst_limiter.php` | 39 (36 sous Windows, avec 2 sections ignorées : droits 0600 et lien symbolique) | G4a : plafonds |
| `test_visit_privacy.php` | 67 | G1 : règles de vie privée (portent sur `VisitPrivacy`) |
| `test_visit_collector.php` | 237 avec l'extension `pdo_sqlite` ; **43 et 1 section ignorée sans elle** | G4b : normalisation de la requête et statut final (sans base), puis tous les scénarios d'écriture sur SQLite : visites, DNT, 404 et 500, rotation de minuit, purge, interblocages, série de 400 requêtes comparée à un modèle écrit à part, freins, configuration, disjoncteur, robot de test (D9). Le schéma SQLite est **généré depuis les vraies migrations** |
| `test_collector_mysql.php` | aucun chiffre à l'avance : seul « 0 échec » compte | G4b : **les mêmes scénarios sur ta vraie base MariaDB / MySQL** (base jetable `jt_dashboard_test_xxxxxxxx`, supprimée à la fin, refuse tout autre hôte que localhost), plus ce qui n'existe que sur ce moteur : mode strict (erreur 1406), comparaison sans casse, émoji sur 4 octets, réglages de session identiques à ceux du site, et **6 processus PHP qui écrivent en même temps** (`collector-worker.php`, lancé par le test et jamais à la main) |
| `test_collector_live.php` | 30 | G4c : **de vraies requêtes HTTP** vers ton site local (200, 301, 404, image introuvable, POST, HEAD, DNT, visiteur neuf, robot d'indexation), puis relecture de la base du dashboard : exactement ce qui devait être écrit, et rien d'autre. Refuse tout site non local. Les lignes « test » et « bot » qu'il laisse dans ta base locale sont exclues des statistiques |
| `check-g4.sh` | 30 en local (étape `tout`) | G4c : trois étapes. `http` : les pages répondent comme avant (200, 301, 404, HEAD, POST, DNT), sans cookie ni lenteur, et les fichiers de la collecte sont fermés au public (valable en local **et** en production). `db` (local seulement) : lance `test_collector_live.php`. `src` : le crochet est bien branché dans `index.php` et `test_visit_collector.php` passe. Aucun e-mail n'est envoyé |

Les fichiers `collector-scenarios.php` (les scénarios, partagés par les deux tests) et `collector-test-support.php` (traduction des migrations en SQLite, fausse connexion qui sait tomber en panne) ne sont pas des tests : ils sont chargés par eux.

Un contrôle ne vaut que s'il peut échouer : le schéma de test refuse les textes trop longs, les valeurs d'ENUM inconnues, les entiers négatifs, les caractères hors ASCII, les dates mal formées, les doublons et les clés étrangères, comme la base réelle. Les pannes sont simulées (interblocage, erreur d'insertion, ligne créée par une autre requête entre la lecture et l'écriture) parce que seule une vraie concurrence les provoquerait.

Les tests de G2 (`test_dashboard_infra.php`, `test_db_schema.php`) ne couvrent pas ces classes ; ils servent de non-régression et doivent rester à 0 échec.

**Qualité des tests eux-mêmes** : 17 erreurs volontaires glissées une à une dans les classes de G4a (toutes détectées) et **67 dans le code de G4b et de D9** (HEAD ou code 302 enregistrés, fichier statique ou `.php` mal classé, DNT qui ouvre une visite, 301 qui prolonge une visite, 404 qui ouvre une visite, inactivité de 30 minutes ignorée, clé de visite sans l'adresse, User-Agent d'un humain écrit, requête conservée dans le chemin, clés de la veille jamais effacées ou clés du jour effacées aussi, minuit de Paris pris pour minuit UTC, purge à 12 mois ou limite incluse, interblocage jamais réessayé ou réessayé trois fois, transaction jamais annulée, erreur d'insertion avalée, frein des 404 ou plafond global contourné, rafale non résumée, disjoncteur enclenché à tort ou jamais, erreur fatale non vue, journal contenant le message de la base, 11 pour D9 : robot de test qui écrit une erreur 404 sur une page ou sur un fichier statique, qui consomme le frein, dont la 404 n'est plus comptée ou dont la 500 n'est plus écrite, robot d'indexation traité comme le robot de test, demande « DNT » sur un fichier statique qui ouvre quand même une connexion, etc.) : les 67 sont détectées.

**Essai de bout en bout** avec un vrai serveur web PHP et un `index.php` de démonstration branché comme au paragraphe 4.1, sur une base volontairement injoignable : sans `dashboard.config.php`, aucun effet (ni fichier, ni journal) ; avec lui, la page est servie normalement, la panne est journalisée **une seule fois** (codes seulement, sans mot de passe ni secret) et la collecte est suspendue 60 secondes ; les codes 404, 301 et 500 (erreur fatale) sont pris en compte, les codes 302 et les méthodes POST et HEAD ignorés. L'écriture effective dans la base est validée par `test_collector_mysql.php` sur ta machine.

## 6. Avant la mise en ligne (G4c)

1. Appliquer le branchement de `index.php` (paragraphe 4.1) sur **ta version courante** du fichier.
2. Lancer les tests ci-dessus, dont `test_collector_mysql.php` sur la base de Laragon, puis `check-g4.sh local` (il lance `test_collector_live.php`).
3. Côté IONOS : créer `dashboard.config.php` à la racine du site (secret d'au moins 32 octets, accès à la base ; modèle dans `docs/dashboard.config.example.php`) et importer `001_core.sql` puis `002_access.sql` dans phpMyAdmin.
4. `check-g4.sh prod` (sous le User-Agent du robot de test, pour ne pas fausser les statistiques) **avant** puis **après** la création de `dashboard.config.php` : les réponses ne doivent pas changer. Puis première journée de collecte contrôlée dans phpMyAdmin.
