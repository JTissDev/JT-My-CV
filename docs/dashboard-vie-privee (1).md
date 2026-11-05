# Dashboard — cahier des charges vie privée (G1)

Statut : **validé le 31/10/2026** (D1 à D6) ; D7 proposée, à confirmer · rédigé le 28/10/2026 · lot G du `TODO.md`
Ce document fixe les règles avant tout code (G2 base, G4 collecte). Il sert aussi de source à la politique de confidentialité (G3).
Ce n'est pas un avis juridique : la conformité finale reste à vérifier (voir B9 et G3).

## 1. Objet et principes

Mesurer l'audience du site **pour moi seul** (éditeur), sous forme de **statistiques anonymes**, sans suivre un visiteur d'un site à l'autre et sans croiser ces données avec autre chose. C'est le cadre de la mesure d'audience exemptée de consentement décrit par la [CNIL](https://www.cnil.fr/fr/cookies-solutions-pour-les-outils-de-mesure-daudience).

Cinq règles, dans l'ordre d'importance :

1. **Aucune adresse IP n'est jamais écrite** : ni brute, ni tronquée, ni en base, ni dans un journal applicatif, ni dans un export.
2. **Aucun cookie pour le visiteur ordinaire.** Les deux seuls cookies (développeur, visite guidée) sont posés sur des appareils que je désigne moi-même (§ 7).
3. **On n'enregistre que ce qui sert à un compteur ou à un graphique** (minimisation) : si une donnée ne sert à aucun écran du dashboard, elle n'est pas collectée.
4. **Tout ce qui permettrait de rattacher une visite à une personne disparaît à minuit** (§ 5).
5. **La collecte ne casse jamais le site** : base indisponible ou saturée = la page s'affiche quand même, sans message d'erreur.

## 2. Données enregistrées

| Donnée | Forme enregistrée | Pourquoi | Durée |
|---|---|---|---|
| Page vue | clé interne de la page (`home`, `career`…), langue, code HTTP (200, 301, 404, 500), identifiant de création si valide | compteurs, pages les plus vues | 13 mois |
| Instant | horodatage UTC | durée, parcours, courbes | 13 mois |
| Numéro de visite | entier interne (`visit_id`) | regrouper les pages d'une visite | 13 mois |
| Clé de visite | 32 caractères hexa (§ 5) | suivre le parcours **dans la journée** | jusqu'à minuit, puis effacée |
| Profil | `human`, `bot`, `test`, `developer`, `guided` | filtrer les compteurs | 13 mois |
| Appareil | famille de navigateur, système, type (mobile, tablette, ordinateur) — jamais le User-Agent brut d'un humain | statistiques d'usage | 13 mois |
| Robot ou test | nom reconnu, sinon User-Agent tronqué à 255 caractères (ce n'est pas une donnée personnelle) | distinguer les robots | 13 mois |
| Origine | **domaine seul** du Referer (jamais chemin ni requête) + `utm_source`, `utm_medium`, `utm_campaign` si valeur sobre | provenance des visiteurs | 13 mois |
| Chemin d'une 404 | chemin demandé **sans la requête**, caractères de contrôle retirés, 255 caractères max | repérer les liens cassés et les scanners | 90 jours |
| Visite guidée | libellé que j'ai saisi (ex. « Entretien X ») | retrouver le parcours d'une démo | durée du lien + 30 jours |
| Compteur quotidien (`daily_stats`) | nombre de pages vues par jour, profil, page, langue et code HTTP : **toutes** les pages, y compris celles des visiteurs `DNT` et celles dont le détail est purgé | source des compteurs, sans aucun identifiant | illimitée (anonyme) |

## 3. Données jamais enregistrées

Adresse IP · User-Agent brut d'un humain · Referer complet · requête (`?…`) d'une URL · contenu du formulaire de contact (nom, e-mail, message) · langue du navigateur · pays ou ville (pas de géolocalisation) · empreinte du navigateur (canvas, polices, résolution) · identifiant commun à plusieurs sites · jeton `guide` d'un lien de visite guidée (retiré avant tout enregistrement).

**Hors de notre périmètre** : les journaux d'accès de l'hébergeur (IONOS) contiennent des adresses IP et ne sont pas ceux du dashboard. La politique de confidentialité (G3) devra le dire.

## 4. Profils de visite

Le profil est décidé **à l'écriture** ; rien n'est jeté, c'est l'affichage qui filtre. Premier critère satisfait :

1. `test` — le User-Agent contient `JTissier-CheckBot` (mes scripts de contrôle). Déclaratif : n'importe qui peut l'imiter, sans conséquence puisque la visite est conservée et affichée à part.
2. `guided` — cookie de visite guidée valide (§ 7). Passe avant `developer` : sur mon propre appareil, une démo doit compter comme guidée.
3. `developer` — cookie développeur valide.
4. `bot` — User-Agent absent, ou présent dans la liste des robots du dépôt (moteurs de recherche, aperçus de liens WhatsApp/Slack/mail, `curl`, navigateurs sans écran…), ou demande de `robots.txt`.
5. `human` — tout le reste.

Le compteur « visiteurs » affiché ne compte que `human`. Les quatre autres profils ont chacun leur compteur.

## 5. Identifiant de visite

But : relier les pages d'une même visite **sans jamais stocker l'IP**.

```
sel_du_jour = HMAC-SHA256(secret_serveur, "visit-salt|" + jour)        jour = AAAA-MM-JJ, fuseau Europe/Paris
clé_visite  = HMAC-SHA256(sel_du_jour, IP_normalisée + "|" + User-Agent), tronqué à 32 caractères hexa
```

- **IP normalisée** : IPv4 telle quelle ; IPv4 mappée en IPv6 ramenée à l'IPv4 ; IPv6 réduite à son préfixe /64 (les adresses temporaires changent les 64 derniers bits). IP invalide : pas de clé, la page est comptée sans parcours. L'IP normalisée n'existe qu'en mémoire, le temps du calcul.
- **Secret serveur** : 32 octets aléatoires, dans le fichier de config créé à la main sur le serveur (hors Git, exclu du déploiement). **Jamais en base de données** : une fuite de la base, d'une sauvegarde ou d'un export ne contient donc aucun sel et ne permet pas de retrouver une IP.
- **Pourquoi le sel compte** : avec le sel, l'IP se retrouve par énumération (démontré par le test : un /16, soit 65 536 adresses, en 0,05 s ; tout l'espace IPv4 en moins d'une heure). Sans le sel, c'est impossible.
- **Une visite** = pages de la même clé, le même jour, séparées de moins de 30 minutes. Elle ne franchit pas minuit. Durée = dernière page − première page (une visite d'une seule page dure 0 s ; option G8 : balise `sendBeacon`).
- **Effacement à minuit** : à la première requête d'un nouveau jour (une seule fois, repéré par `settings.last_rotation`) et par la purge planifiée (cron, si disponible, voir G0), toutes les clés des jours passés sont mises à `NULL`, dans `visits` **et** dans `login_attempts`. La visite ne garde que son numéro interne : plus rien ne la rattache à un appareil.
- **Avant minuit**, la clé est un **pseudonyme** (pas encore anonyme) ; après l'effacement, la donnée est anonyme.

Fonctions de référence, testées (67 contrôles) : `tests/dashboard/visit-privacy-reference.php`.

## 6. Origine et campagnes

Le Referer est réduit au **nom de domaine** (`https://www.google.com/search?q=…` donne `google.com`). Ignorés : navigation interne, schémas autres que http(s), valeurs douteuses. Seuls `utm_source`, `utm_medium` et `utm_campaign` sont conservés, s'ils font 64 caractères au plus et n'utilisent que lettres, chiffres, `. _ ~ -` et espace (donc ni `@`, ni `<`, ni retour à la ligne).

## 7. Cookies

| Cookie | Qui le reçoit | Rôle | Durée | Contenu |
|---|---|---|---|---|
| (aucun) | visiteur ordinaire | — | — | — |
| `jt_dev` | mon appareil, à ma connexion à l'admin | exclure mes propres visites | 13 mois au plus | jeton opaque signé, aucune donnée personnelle |
| `jt_guest` | mon appareil en mode « visite guidée », ou l'invité qui ouvre mon lien personnel | classer la visite en `guided` | session du navigateur et jamais au-delà de l'expiration du lien ou du mode (24 h par défaut, 7 jours au plus) | numéro opaque de la visite guidée |
| session admin | moi, à la connexion | authentification | session | identifiant de session |

Tous : `Secure`, `HttpOnly`, `SameSite=Lax` (`Strict` pour la session admin). Le cookie `jt_guest` n'est posé à un invité que s'il ouvre **mon lien personnel**, jamais automatiquement ; les aperçus de lien (robots) ne le reçoivent pas. À mentionner dans la politique de confidentialité (G3).

**État au 31/10/2026** : ni `jt_dev` (G5) ni `jt_guest` (G6) n'existent encore. La politique publiée par G3 dit donc « aucun cookie », et `check-g3.sh` le vérifie sur toutes les pages (en-tête `Set-Cookie` absent) et dans le code (`session_start()`, `setcookie()`, `$_SESSION`, `$_COOKIE` interdits tant que G5 n'est pas livré). Le routeur du 22/10/2026 appelait `session_start()` pour tous les visiteurs, ce qui dépose un cookie `PHPSESSID` : à retirer avant la mise en ligne du texte de G3 (la langue figure déjà dans l'adresse de chaque page).

## 8. Conservation et purge

| Donnée | Durée | Mécanisme |
|---|---|---|
| Clé de visite | jusqu'à minuit | mise à `NULL` (§ 5) |
| Visites et pages (détail) | 13 mois (la CNIL recommande 25 mois au plus) | agrégation quotidienne puis suppression |
| Chemins de 404, erreurs PHP | 90 jours | purge quotidienne |
| Tentatives de connexion | 30 jours | purge quotidienne |
| Liens de visite guidée | expiration + 30 jours | purge quotidienne ; jetons stockés **hachés** (SHA-256) |
| Compteur quotidien (`daily_stats`) | illimitée | anonyme |
| Sauvegardes de la base | 7 jours glissants (IONOS) | hors de mon contrôle ; elles peuvent contenir les clés de visite de la journée, mais comme le sel n'est jamais en base (D5) elles ne permettent pas de retrouver une IP |

La purge s'exécute à la première requête du jour (mécanisme sans dépendance) et, si IONOS le permet, par une tâche planifiée (G10).

## 9. Sécurité

- Compte MariaDB **dédié** au dashboard, avec les seuls droits `SELECT`, `INSERT`, `UPDATE`, `DELETE` ; les migrations (création des tables) se font à la main avec un compte d'administration.
- Requêtes **préparées** PDO uniquement ; connexion courte (pas de connexion persistante : l'hébergement limite à 18 connexions simultanées), délai de connexion d'environ 2 secondes, abandon silencieux et journal d'erreur **sans donnée personnelle**.
- Identifiants de base et secret serveur : fichier hors Git, créé à la main, exclu du déploiement (`deploy.yml`), vérifié inaccessible en HTTP par le test de fumée.
- Espace admin : HTTPS seulement, `noindex`, hors sitemap, mot de passe haché (argon2id si disponible, sinon bcrypt), CSRF, session durcie.
- Limitation des tentatives de connexion par **clé de visite du jour** (jamais l'IP) + délai croissant global sur le compte unique.
- Les visites en local (Laragon) vont dans une base locale : aucune donnée de développement en production.

## 10. Droits des personnes

Après effacement de la clé de visite (minuit), aucune donnée ne permet d'identifier une personne : il n'y a donc rien à retrouver, à rectifier ni à supprimer individuellement. La politique de confidentialité l'explique et donne une adresse de contact (§ 14). À valider juridiquement avec B9.

## 11. Décisions à valider

| # | Question | Décision |
|---|---|---|
| D1 | Respecter `DNT: 1` et `Sec-GPC: 1` ? | **Oui** : pas de clé de visite ni de parcours, seulement le compteur quotidien anonyme de la page |
| D2 | Durée de conservation du détail | **13 mois** (maximum CNIL : 25) |
| D3 | Durée de conservation des erreurs et chemins de 404 | **90 jours** |
| D4 | Durée par défaut d'une visite guidée | **24 h**, maximum 7 jours |
| D5 | Sel : dérivé d'un secret serveur (proposé) plutôt que tiré au hasard et stocké en base | **Dérivé** : une sauvegarde ou un export SQL ne contient aucun sel. Contrepartie : le secret reste dans le fichier de config tant qu'on ne le change pas. |
| D6 | Pays ou ville des visiteurs | **Non collectés** |
| D7 | Durée de conservation des messages du formulaire de contact (hors dashboard : ils arrivent par e-mail) | **12 mois après le dernier échange**, sauf relation professionnelle ou contractuelle en cours (proposée, à confirmer) |

D1 à D6 : validées par Julien le 31/10/2026.

## 12. Tests de conformité (à exécuter en G11, sous le User-Agent du robot de test)

1. Après un lot de visites de test (IPv4, IPv6, User-Agent variés), exporter toute la base et chercher des motifs d'IPv4 et d'IPv6 : **0 résultat**. Même recherche dans le journal d'erreurs.
2. Après un changement de jour simulé : `visit_key` est `NULL` pour tous les jours passés.
3. Une requête avec `?guide=…` : le jeton n'apparaît ni en base ni dans les journaux ; l'URL redirigée est propre.
4. Mauvais mot de passe de base, puis 18 connexions saturées : pages en 200, aucun message d'erreur affiché.
5. Referer avec chemin et requête : seul le domaine est en base.
6. User-Agent d'un humain : absent de la base (seule la famille grossière y figure).
7. Requête avec `DNT: 1` (si D1 est accepté) : aucune clé de visite.
8. `login_attempts` : aucune IP.
9. Les fonctions de référence : `php tests/dashboard/test_visit_privacy.php` doit afficher `0 échec(s)`.

## 13. Conséquences sur le TODO

- **G2** (livré) : pas de table de sels (le sel est dérivé du secret serveur) ; `daily_stats` compte toutes les pages vues et `hits` ne sert qu'aux parcours ; `errors` regroupe les erreurs (une ligne par jour, type et sujet) ; `login_attempts` identifiée par la clé de visite du jour, effacée à minuit comme celle de `visits` ; la table `reports` est créée par la migration de G10, quand ses réglages seront connus.
- **G4** : appel unique après la résolution de route ; profils du § 4 ; effacement des clés et purge à la première requête du jour ; `DNT`/`GPC` si D1.
- **G3** (livré, voir § 14) : la politique reprend §§ 2, 3, 7, 8 et 10.
- **Déploiement (A7)** : `docs/`, `tests/` et `database/` n'ont rien à faire en production : exclus du paquet et refusés en HTTP. Le fichier `dashboard.config.php` (identifiants, secret) n'est jamais dans Git : il est conservé sur le serveur par le déploiement.
- **Collecte (G4)** : les textes envoyés en base (User-Agent des robots, chemins de 404) doivent être nettoyés avant écriture (UTF-8 valide, caractères de contrôle retirés, longueur bornée), car le mode strict de la base refuse une valeur invalide.

## 14. Politique de confidentialité : texte et calendrier de mise à jour

- **Où** : `data/cv_fr.json` et `data/cv_en.json`, clé `pages.confidentialite` (13 sections, texte brut : la vue échappe tout HTML, pas de lien cliquable). Les balises `{name}`, `{site_url}`, `{dev_adress}`, `{dev_mail}`, `{host_name}` viennent de `data/registre.json`.
- **Contrôles** : `php tests/dashboard/test_privacy_policy.php` (structure, FR et EN parallèles, balises résolues comme `index.php`, chiffres de ce document, même date de mise à jour) et `./check-g3.sh` (aucun cookie, aucune ressource d'un autre site, texte à jour en ligne).
- **Règle** : le texte décrit uniquement ce qui est en ligne. Tout lot qui ajoute une collecte met le texte à jour, dans les deux langues, **avant** sa mise en ligne, ainsi que la date « Dernière mise à jour » et la liste `$notYetLive` du test.

| Lot | Ce que le texte doit ajouter avant la mise en ligne |
|---|---|
| G3 | Texte initial : mesure d'audience sans cookie, formulaire de contact, hébergeur, durées, droits |
| G4 | Rien si la collecte respecte §§ 2 et 3 ; sinon ajuster le texte ou le code |
| G5 | Cookie `jt_dev` (13 mois), cookie de session d'administration, tentatives de connexion (30 jours) ; retirer `jt_dev` de `$notYetLive` |
| G6 | Visites guidées : libellé saisi, jeton stocké haché, cookie `jt_guest`, durée (24 h par défaut, 7 jours au plus), suppression 30 jours après l'expiration ; retirer `jt_guest` de `$notYetLive` |
| G10 | Rapports par e-mail, si le contenu envoyé comporte des données de visite |

**Délai de suppression des clés de visite.** Le texte promet : « supprimée de la base lors de la première visite du jour suivant, ou par une tâche automatique quotidienne ». C'est exact avec le mécanisme de G4 seul, mais sur un site à faible trafic la clé d'une veille peut rester en base jusqu'à la prochaine visite. Si IONOS autorise une tâche planifiée (G0), la brancher dès G4 donne un effacement réel chaque nuit.
