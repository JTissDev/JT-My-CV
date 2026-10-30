<?php
/**
 * Fichier : docs/dashboard.config.example.php
 * Rôle : Modèle de configuration du dashboard. Ce fichier ne contient AUCUN secret.
 *
 * Mise en place :
 *   1. copier ce fichier à la RACINE du site sous le nom  dashboard.config.php  (à côté de index.php) ;
 *   2. remplacer les valeurs ci-dessous ;
 *   3. ne JAMAIS le mettre dans Git (il est listé dans .gitignore) ni l'envoyer dans une conversation.
 *
 * Un fichier par environnement, avec ses propres valeurs :
 *   - local (Laragon)  : base « jt_dashboard », utilisateur « root », mot de passe vide, secret propre au poste ;
 *   - production (IONOS) : valeurs de l'espace client IONOS, déposées à la main par SFTP à la racine du site.
 *     Le déploiement automatique ne l'écrase ni ne le supprime (liste KEEP_ON_SERVER de deploy.yml).
 *
 * Générer le secret (32 octets aléatoires, 64 caractères hexadécimaux), au choix :
 *   Git Bash : openssl rand -hex 32
 *   PHP      : php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
 * Le secret sert à calculer le sel du jour (voir docs/dashboard-vie-privee.md, § 5) : il ne doit jamais se trouver dans la base de
 * données. Le changer n'a qu'une conséquence : les visites en cours ce jour-là sont coupées en deux.
 */
return [
    'db_host'     => 'localhost',                  // production : nom d'hôte de la base affiché dans l'espace client IONOS
    'db_port'     => 3306,
    'db_name'     => 'jt_dashboard',               // production : nom de la base IONOS
    'db_user'     => 'root',                       // production : utilisateur de la base IONOS (jamais root)
    'db_password' => '',                           // production : mot de passe de l'utilisateur de la base
    'secret'      => 'REMPLACER-PAR-64-CARACTERES-HEXADECIMAUX',
];
