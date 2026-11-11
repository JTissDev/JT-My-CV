# 🗺️ Plan d’Action : Refonte de la Page Parcours
## Plan générique

### Phase 1 : Unification de l'Alternance (BTS MUC & Manager Super U)
L'objectif est de fusionner deux entrées distinctes de ton registre en un bloc visuel unique et cohérent.
#### 1.1 Validation des données de liaison : 
+ S'assurer que les objets concernés (bts_muc et manager_super_u) partagent bien le même identifiant de groupe dans registre.json (ex: linkedGroupId: "bts_manager_alternance"), ce qui est déjà amorcé dans ton architecture.   
#### 1.2 Adaptation de la logique du Contrôleur :
+ Modifier le contrôleur pour que, lorsqu'un groupe (linkedGroupId) est détecté, les deux entités (l'école et l'entreprise/poste) soient regroupées dans une seule et unique structure de données pour la vue.
#### 1.3 Refonte visuelle de la carte « Alternance » :
+ Prévoir une étiquette/badge spécifique et générique portant la mention « Alternance » (valable pour celle-ci et d'éventuelles futures).
+ Structurer l'affichage interne de la carte pour présenter clairement les deux facettes en parallèle : l'organisme de formation d'un côté et l'expérience en entreprise de l'autre, sous une seule période calendaire unifiée (2011 - 2013).   
---
### Phase 2 : Mise en valeur des fiches « Lien Informatique » (lienInfo)Rappel de la règle validée : la clé lienInfo (Lien Informatique / Scientifique) identifie les jalons clés de ton orientation vers la tech ou ses fondations.   
#### 2.1 Injection d'une classe CSS conditionnelle :
+ Dans le contrôleur, vérifier l'état du booléen lienInfo pour chaque objet du parcours.   
+ Si true, injecter dynamiquement une classe spécifique (ex: .timeline-item--tech-highlight) dans les attributs de rendu de la carte.
#### 2.2 Définition de l'identité visuelle (côté SCSS) :
+ Réfléchir au design system pour ces fiches (ex: bordure latérale plus marquée, liseré lumineux, icône subtile ou nuance de fond distincte) afin qu'un recruteur identifie immédiatement ton fil conducteur informatique et scientifique au fil de la frise.
---
### Phase 3 : Intégration des Fiches de Transition (Pivots de vie et de carrière)
+ Insérer des jalons narratifs entre les grandes étapes pour expliquer les virages (maths, commerce, auto-entreprise, reconversion).   
#### 3.1 Structuration de la lecture séquentielle :
+ Faire évoluer le contrôleur (ou le CareerManager) pour qu'il sache lire et injecter les éléments du tableau transitions (définis par leurs clés from et to dans registre.json) au bon endroit de la chronologie.   
#### 3.2 Design et gabarit des blocs de transition :
+ Créer un composant ou un style de carte spécifique (différent des expériences et des études) pour les transitions.
+ Piste ergonomique : Un bloc centré ou en encart narratif (façon "note de carnet" ou "virage clé") qui rompt légèrement avec la grille classique pour attirer l'œil sur l'explication du choix de vie.
#### 3.3 Rattachement textuel :Alimenter proprement le nouveau bloc transitions dans cv_fr.json pour y lier les titres et paragraphes correspondants.   
---
### Phase 4 : Préparation des fiches détaillées ouvrables
+ Poser les bases techniques et ergonomiques pour permettre d'ouvrir un contenu approfondi (descriptions longues) sur les fiches éligibles.
#### 4.1 Structuration des données textuelles (longdesc) :Valider l'utilisation d'un tableau de chaînes de caractères (string[]) pour les descriptions détaillées dans le fichier de langue, permettant un découpage propre en plusieurs paragraphes (exactement comme pour tes projets dans creations).   
#### 4.2 Choix de l'expérience utilisateur (UX) :
+ Définir le mode d'affichage des détails :
    - Option A : Une modale (fenêtre surgissante) au clic sur la carte.
    - Option B : Un accordéon (dépliant) directement dans la ligne de la timeline.
    - Option C : Une page dédiée par fiche (sur le modèle de ton portfolio).
+ (À arbitrer selon la fluidité que tu souhaites donner à ta page).
---
### 🛠️ Évolution des Classes et de l'Architecture (Recommandations)
Pour supporter proprement ces nouveautés sans alourdir ton code :
1. Gestionnaire de Données (CareerManager) : Il devra non seulement instancier les objets (Experience, Etude, ProjetPersonnel), mais aussi gérer l'association des groupes d'alternance et l'interstratification des blocs de transition dans le tableau final renvoyé à la vue.
2. Modélisation d'une Transition (Optionnel mais propre) : Tu pourrais envisager de créer une classe dédiée TransitionParcours (ou de l'intégrer proprement) pour manipuler les données de transition de la même manière que tes objets de parcours, garantissant une stricte orientation objet.
3. Nettoyage du code existant : Profiter de ce déplacement de fichiers dans classes/career/ pour clarifier la responsabilité du contrôleur, qui doit uniquement préparer et formater les données, en laissant les classes métiers encapsuler la logique.

## Plan d'action détaillé
| Bloc | Fichier | Tache | statut |
| --- | --- | --- | --- |
| DataFile | registre.json | [] **alternance** S'assurer que les 2 entrées partagent le meme `LinkedGroupId`  <br> [] **Transition** Ajouter propiété transition sur les etapes clé <br> [] **Transition** ajouter index pour indiquer etapes suivantes<br> [] **Lien Informatique** verification de la presence du boolean| ToDo |
| DataFile | cv_lang.json | [] **Bloc `transition`** Créer dictionnaire dédié indéxé par id de transition contenant titre et tableau de description <br> [] **Description Longues** S'appuyer sur la présence effective des textes dans les sections de description pour déterminer dynamiquement l'existence d'une fiche détaillée (approche DRY, sans redondance de drapeaux dans le registre).| ToDo |
| Classes Metiers PHP | Evenements_Parcours.php | [] Ajouter les propriété, getters, setters et methodes de verif pour gérer la liaison de group et la transition sortante | ToDo |
| Classes Metiers PHP | `Experience.php`<br>`Etude.php` | [] les constructeurs respectifs pour accepter et transmettre les nouveaux attributs de liaison (`linkedGroupId`).<br> [] Veiller à ce que les accesseurs nécessaires remontent correctement les informations vers le gestionnaire.  | ToDo |
| Classes Metiers PHP | `CareerManager.php` | [] Faire évoluer la méthode de chargement pour injecter les nouveaux attributs lors de l'instanciation des objets. <br> [] Ajuster l'algorithme de tri et de structuration pour permettre l'interstratification des blocs de transition entre les expériences/études. | ToDo |
| Controller | `careerController.php` | [] Modifier le traitement de la boucle pour détecter la présence d'un linkedGroupId. Si un groupe est identifié, regrouper l'école et l'entreprise dans une seule et même structure de données pour la vue, sous une période calendaire unifiée. | ToDo |
| Controller | `careerController.php` | [] Intégrer la logique permettant de récupérer le texte de transition associé (depuis le dictionnaire de langue) et de l'insérer au bon endroit dans le flux séquentiel des éléments formatés. | ToDo |
| Controller | `careerController.php` | [] Centraliser dans le contrôleur l'attribution des classes CSS conditionnelles (notamment pour la mise en valeur des fiches à lienInfo true et les types de cartes). | ToDo |
| Vue | `career.php` | [] Adapter la boucle d'affichage pour gérer les lignes groupées (affichage côte à côte ou empilé des deux facettes de l'alternance sous une seule et unique étiquette générique « Alternance »). | ToDo |
| Vue | `career.php` | [] Créer un gabarit ou un composant visuel spécifique pour insérer les fiches de transition de manière fluide entre les étapes de la frise. | ToDo |
| Vue | `career.php` | [] Appliquer proprement les classes CSS de mise en valeur pour les fiches portant le marqueur de lien informatique (lienInfo). | ToDo |
| SCSS | `parcours.scss` | [] Designer le composant de la carte groupée pour qu'il intègre clairement les deux facettes (formation et entreprise) de façon harmonieuse sans alourdir la frise. | ToDo |
| SCSS | `parcours.scss` | [] Créer un style d'encart narratif ou de « note de carnet » (centré ou en rupture visuelle avec la grille classique) pour attirer l'œil sur les choix de vie et pivots professionnels. | ToDo |
| SCSS | `parcours.scss` | [] Définir un liseré, une bordure latérale ou une nuance spécifique pour les fiches ayant lienInfo à true, permettant de repérer immédiatement le fil conducteur technique. | ToDo |
| SCSS | `parcours.scss` | [] Revoir les espacements, les positionnements absolus du marqueur de timeline (__marker) et les comportements responsive pour éliminer tout risque de chevauchement sur petits et grands écrans. | ToDo |