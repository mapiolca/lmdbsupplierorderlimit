# Activation et transactions avec DebugBar — 2026-09-22

Correctif préparé à partir de `main` au commit `13740189e3d56dff6020583b1d57e2fecb6b1385`. Le module conserve sa version déclarée `1.0.1`, son identifiant `450025` et ses permissions. Ce travail ne constitue pas une release et ne modifie pas l'historique publié de `ChangeLog.md`.

## Diagnostic et correction

Le journal fourni indique Dolibarr **23.0.2**, une activation dans l'entité 2 et la séquence suivante : contrôle du schéma, sauvegarde du partage Multicompany, début de transaction, puis fin de la requête sans première requête de verrouillage du registre. Le journal ne contient pas l'exception interceptée. La version PHP de l'instance et l'activation de DebugBar n'ont pas été confirmées au moment de cette analyse.

Un défaut correspondant à cette séquence a été reproduit avec les classes natives : `TraceableDB::begin()` délègue à la connexion encapsulée, mais sa propriété héritée `transaction_opened` reste vide. Le contrôle du module rejetait alors une transaction réellement ouverte. Son traitement d'erreur consultait ce même compteur et pouvait omettre le rollback.

- `LmdbSupplierOrderLimitConsumption::lock()` lit désormais le compteur de la connexion sous-jacente lorsqu'elle est enveloppée par `TraceableDB`. Les requêtes et les verrous restent exécutés à travers la connexion tracée. Un appel sans transaction reste refusé avant toute requête.
- Le descripteur et l'action de réconciliation manuelle contrôlent les retours de `begin()` et `commit()`. Ils suivent la transaction qu'ils ont ouverte pour la fermer en cas d'échec. Un échec du commit ne conduit pas à annoncer une reprise réussie.
- Les erreurs enregistrent uniquement l'étape et la classe d'exception via `dol_syslog()`. Les messages utilisateur restent traduits par le catalogue existant. Aucun journal d'instance, SQL client ou contenu Multicompany fourni par l'utilisateur n'est incorporé au dépôt.

La DDL et l'initialisation des constantes restent distinctes de la transaction de reprise. Le rollback de cette dernière ne prétend pas annuler une migration DDL ou les constantes déjà initialisées.

## Contrats natifs et tests exécutés

Le test `test/native_activation.php` charge les vraies classes `DoliDB`, `DoliDBMysqli`, `TraceableDB` et `DolibarrModules`. Seuls le transport SQL, les écritures de constantes, le chargement des tables et l'enregistrement final du module sont simulés. Il n'ouvre aucune connexion à une base ni à une instance.

Avant correction, le scénario d'activation réussit sans DebugBar et échoue avec un `TraceableDB` natif. Après correction, les 15 combinaisons passent : connexion directe, un ou deux enveloppements DebugBar, puis succès ou échec du début de transaction, de l'écriture finale de réconciliation, du commit ou du contrôle de schéma. Sont aussi vérifiés : réactivation, conservation des réglages zéro/vides et d'un partage tiers, maintien des requêtes de verrou dans DebugBar, refus hors transaction, rollback des écritures simulées et conservation du niveau d'une transaction appelante imbriquée.

Chaque combinaison a été exécutée sous **PHP 8.4.22**, avec `error_reporting=-1`, à partir des sources des tags suivants extraites temporairement dans le répertoire de test puis supprimées :

| Dolibarr | Commit réellement exécuté | Résultat |
|---|---|---|
| 20.0.0 | `697bf01970740a3339cd99cf055b4428fc5e051c` | 15/15 |
| 21.0.0 | `fd970b582a4d8c5779a2958a4e9f4fce225cf085` | 15/15 |
| 22.0.0 | `49b9a6d19f3deb6d410c0e9b3310e95be4ea7710` | 15/15 |
| 23.0.2 | `ccef1102e6850b7545be7bad91cf0cc4c74ac6ea` | 15/15 |
| 24.0.0 | `769c7db907099643558e77d7002c109cfda919e5` | 15/15 |

Sources de référence : [TraceableDB en 23.0.2](https://github.com/Dolibarr/dolibarr/blob/ccef1102e6850b7545be7bad91cf0cc4c74ac6ea/htdocs/debugbar/class/TraceableDB.php), [DoliDB en 23.0.2](https://github.com/Dolibarr/dolibarr/blob/ccef1102e6850b7545be7bad91cf0cc4c74ac6ea/htdocs/core/db/DoliDB.class.php). Ce sont des vérifications ciblées des classes natives avec SQL simulé, pas des installations complètes validées.

Autres contrôles : les 20 scénarios métier de `test/run.php`, le test `test/native_smoke.php` sur le checkout `0d20b226f5e13b848bb58528398967f52862688c` et le lint des 24 fichiers PHP passent. PHPStan n'a pas pu être exécuté : aucun exécutable ni configuration du module disponible ; aucune suppression ni baseline ajoutée.

## Portée et recette restante

Le verrou commun est utilisé par l'activation, la réconciliation manuelle et les contrôles définitifs des approbations natives, notamment via fiche ou API. Le parcours HTTP de la page de réglages et l'activation complète ne sont pas exécutés par le nouveau test.

À vérifier sur une instance de test servant le correctif :

1. Activer, désactiver et réactiver avec DebugBar actif puis inactif ; contrôler droits, réglages, partage et reprise dans deux entités. Relever les états initiaux et les restaurer après les essais.
2. Exécuter la réconciliation manuelle avec un token valide puis tester son refus sans token. Vérifier une approbation autorisée et un dépassement refusé depuis la fiche et l'API, avec rollback InnoDB et sans consommation résiduelle.
3. Exécuter les essais concurrents et la recette PHP 8.0 déjà décrits dans `validation.md`, avec les versions réelles de Multicompany et PHP de l'instance.

Aucun déploiement ni essai navigateur distant effectué. La correspondance du défaut DebugBar avec l'incident de l'instance reste à confirmer sur place. SQL/migrations métier, droits, règles financières, entités et partage ne changent pas ; documents, Agenda, Notifications, catégories, numérotation et cron ne sont pas concernés par ce correctif.
