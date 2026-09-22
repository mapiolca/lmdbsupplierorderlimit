# lmdbsupplierorderlimit

Module externe Dolibarr contrôlant les plafonds d’approbation HT des commandes fournisseurs natives. Le dépôt est la racine du module, à installer dans une racine de modules externes configurée dans Dolibarr.

## Plafonds et priorité

| Nature | Montant contrôlé |
|---|---|
| À la commande | HT de cette commande uniquement. Un refus ne bloque aucune autre commande. |
| Au jour | Approbations définitives de l’utilisateur dans la journée ou les dernières 24 heures. |
| Au mois | Approbations définitives de l’utilisateur dans le mois ou les derniers 30 jours. |
| À l’année | Approbations définitives de l’utilisateur dans l’année ou les derniers 365 jours. |
| Au budget projet | Toutes les commandes fournisseurs approuvées du projet, comparées à son budget natif. |

Les natures se cumulent : chaque contrôle applicable doit réussir. L’égalité est autorisée.

Pour chaque nature, une règle individuelle active et valide remplace les règles de groupe, même si son plafond est inférieur. Sans règle individuelle applicable, le plus élevé des plafonds des groupes est retenu, sans addition. Pour chaque bénéficiaire, une règle de l’entité propriétaire de la commande prime sur les règles partagées ; entre règles partagées, la plus permissive est retenue. Un illimité neutralise uniquement sa nature. Une règle inactive ou expirée laisse l’héritage s’appliquer.

Les groupes doivent exister dans le périmètre autorisé ; l’appartenance doit être valable pour l’entité de la commande. Les groupes globaux et les utilisateurs du mode transverse sont pris en compte. Chaque membre consomme personnellement son plafond périodique, indépendamment de ses collègues et des autres entités.

L’absence totale de règle suit le réglage existant **Illimité / Refuser**. Une erreur technique refuse l’opération et ne devient jamais une absence de règle. La priorité individuelle est obligatoire ; l’ancienne constante `LMDBSUPPLIERORDERLIMIT_DIRECT_USER_PRIORITY` n’est plus utilisée.

## Consommation et workflow natif

L’affichage effectue une simulation sans consommation. Le trigger contrôle l’approbation définitive dans la transaction native et enregistre une seule consommation, attribuée à l’approbateur final. Une annulation, un refus, une suppression ou un retour en brouillon la libère. Une nouvelle approbation l’introduit à nouveau.

Le registre est indépendant des journaux facultatifs. Il contient l’entité propriétaire, les identifiants de commande, projet et approbateur, la date d’approbation et un instantané HT nécessaire à l’imputation. Les modifications autorisées du total ou du projet actualisent cet instantané en conservant la date et l’approbateur d’origine ; seules les consommations appartenant à la fenêtre courante entrent dans le contrôle périodique.

Les verrous InnoDB du module sérialisent les contrôles par entité et projet. Les lectures définitives sont actuelles et verrouillées jusqu’au commit ou rollback natif. Un échec SQL ou un conflit refuse l’opération ; il faut rejouer l’opération complète après résolution.

Les deux niveaux d’approbation sont conservés, sans consommation au premier niveau provisoire. Sur la fiche, les adaptations temporaires du workflow sont restaurées après l’opération. Une approbation directe par API dépassant un plafond définitif est refusée ; elle n’est pas convertie en approbation provisoire.

Une nouvelle approbation doit passer par `approve()` ou l’endpoint natif d’approbation. Un simple `setStatus()` vers le statut approuvé ne peut pas remplacer ce parcours ni contourner ses deux niveaux.

Les triggers de lignes natives précèdent le recalcul du total de la commande : une commande déjà approuvée doit revenir en brouillon avant modification de ses lignes. Les écritures SQL directes et les appels désactivant volontairement les triggers ne sont pas couverts.

## Périodes et budget projet

Les réglages de chaque entité proposent **civil / glissant** séparément pour le jour, le mois et l’année, avec civil par défaut. Les périodes civiles suivent le fuseau de l’instance ; les fenêtres glissantes durent 24 heures, 30 jours et 365 jours. Changer de mode ne remet pas le registre à zéro.

Le budget vient directement de `projet.budget_amount`. Aucun montant concurrent n’est stocké dans une règle projet. Sans projet ou avec budget vide, seul ce contrôle est ignoré. Zéro reste contraignant. Un projet renseigné mais inaccessible entraîne un refus.

Le budget d’un projet partagé est unique. Le contrôle agrège les commandes natives approuvées du projet, tous approbateurs et entités confondus, y compris lorsque le module était arrêté. Les détails et cumuls d’objets inaccessibles ne sont pas restitués dans les messages ni les journaux. Les entités comparées doivent avoir la même devise de base ; les devises différentes ou indéterminées sont refusées.

## Permissions et Multicompany

Les permissions fonctionnelles restent celles de Dolibarr, contrôlées directement avec `hasRight()`, sans exemption administrateur. Les droits sur les commandes, tiers et projets sont vérifiés séparément du partage des règles.

La nature, les bénéficiaires et l’entité sont contrôlés côté serveur. Les règles partagées sont visibles avec une colonne et un filtre **Environnement**, et modifiables seulement depuis leur entité propriétaire. Les règles et modes sont évalués depuis l’entité propriétaire de la commande, sans changer l’entité de la session.

Le descripteur déclare les trois contextes Multicompany avec `entity => '0'`. La classe de hooks expose une définition unique, également fusionnée dans `MULTICOMPANY_EXTERNAL_MODULES_SHARING` pour l’entité courante à l’activation/désactivation. Les choix existants sont conservés.

La copie **Multicompany 22.0.1** examinée consomme cette déclaration persistée, mais ne contient pas les appels aux trois hooks externes. La découverte par hooks et l’affichage dans les réglages Multicompany restent à valider sur la version cible.

## Installation, mise à jour et reprise

1. Installer le répertoire `lmdbsupplierorderlimit/` puis activer le module depuis l’administration native.
2. Attribuer les droits du module aux utilisateurs ou groupes chargés de consulter et gérer les plafonds.
3. Choisir les modes de période et créer les règles dans les onglets internes accessibles depuis l’unique entrée `setup.php@lmdbsupplierorderlimit`.
4. Après une mise à jour, réactiver le module pour exécuter la migration et la réconciliation.

La migration transforme les règles existantes en **À la commande**, conserve leurs identifiants, bénéficiaires, montants, dates et états, et adapte les contraintes d’unicité pour inclure la nature. Elle est rejouable.

À chaque activation, la réconciliation reprend les commandes natives déjà approuvées et les changements intervenus pendant l’arrêt. Elle ne déduit l’approbateur final et la date que lorsque les données natives le permettent. Les cas ambigus bloquent les cumuls périodiques concernés ; les règles à la commande restent utilisables. Les réglages proposent une réconciliation manuelle et affichent jusqu’à 50 commandes ambiguës accessibles. Corriger leur situation par les opérations natives autorisées, puis réconcilier ; aucune date ou attribution historique n’est inventée.

Une désactivation conserve règles, registre, constantes et choix Multicompany. La réconciliation écrit uniquement dans les tables du module et ne rejoue pas de trigger métier natif.

## Compatibilité et validation

Socle **déclaré** : Dolibarr 20+, PHP 8.0+, MySQL/MariaDB InnoDB. Langues livrées : français, anglais, allemand, espagnol, italien.

Cette évolution est un développement local non publié. La version du descripteur et l’historique publié restent inchangés. Les sources examinées, contrôles exécutés, limites et scénarios de recette sont consignés dans [doc/validation.md](doc/validation.md).

```powershell
php test/run.php
php test/native_smoke.php ../dolibarr/htdocs
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

Les simulations locales ne valident pas une instance, une concurrence InnoDB réelle ou la compatibilité effective de toute la plage annoncée. La recette Dolibarr 20/PHP 8.0, celle de l’environnement cible, PHPStan et les contrôles navigateur/API/CSRF restent nécessaires avant release. Les essais métier doivent utiliser une instance de test : un rollback SQL n’annule pas les effets externes d’autres triggers.
