# Plafonds multiples — état de validation du développement

Travail local du 22 septembre 2026, à partir de `5e95493`, sans publication ni déploiement. Le descripteur conserve la version publiée `1.0.0` ; ces changements ne constituent pas une nouvelle release. L’historique publié de `ChangeLog.md` est conservé.

## Implémentation

- Natures `order`, `day`, `month`, `year`, `project_budget`, priorité individuelle obligatoire par nature, priorité locale par bénéficiaire, meilleur plafond partagé/de groupe sans addition.
- Registre indépendant des journaux, conservation de l’approbateur final et de la date native d’approbation, instantané HT actualisé lors d’une modification autorisée. Suppression logique de la consommation après sortie des états approuvés, réintroduction après nouvelle approbation.
- Lecture actuelle sous verrou dans la transaction native. Verrous de portée par entité puis projets triés. Le trigger ne démarre ni ne termine la transaction native ; un appel hors transaction est refusé. Deadlock/échec SQL : refus et rollback par le core, puis nouvelle tentative complète.
- Pour le budget projet, lecture agrégée des commandes natives en états `2,3,4,5`, tous approbateurs et entités concernés, sans restitution de cet agrégat dans la décision publique. Cela inclut les commandes approuvées quand le module était arrêté. Le budget est lu dans `projet.budget_amount` ; `NULL` est distingué de zéro.
- Migration DDL rejouable, réconciliation à chaque activation et action de réconciliation manuelle protégée par CSRF. La DDL MySQL a des commits implicites : elle est séparée de la transaction de reprise ; une migration interrompue doit être relancée.
- Droits natifs directs, filtrage des groupes existants/globaux et de leurs appartenances par entité, règles partagées consultables et modifiables seulement depuis l’entité propriétaire. Journaux filtrés selon les droits fournisseurs et le périmètre commercial, avant pagination.
- Les trois hooks Multicompany exposent la même définition que l’initialisation et la désactivation. Les constantes métier ne sont pas déclarées comme supprimables dans `$this->const` ; les réglages de partage sont fusionnés et conservés.

## Contrats examinés dans les sources

| Source | Contrat examiné | Limite |
|---|---|---|
| Dolibarr 20.0.0, commit `697bf01970740a3339cd99cf055b4428fc5e051c` | `CommandeFournisseur::approve()`, approbateurs/dates, `ORDER_SUPPLIER_APPROVE`, statuts ; `User::hasRight()`, `CommonObject::validateField()`, `Project::budget_amount` | Lecture de sources, pas une instance PHP 8.0 |
| Tags Dolibarr 21 à 24 et checkout `0d20b226f5e13b848bb58528398967f52862688c` (25.0.0-alpha) | Points d’extension d’approbation, mutation du projet et des lignes ; transactions et suppression ; helpers de formulaires | Pas de validation complète de chaque version |
| Multicompany 22.0.1, commit local `0da9f094ec12755107290742faa4b8779a262e73` | Consommation de `MULTICOMPANY_EXTERNAL_MODULES_SHARING`, `getEntity()`, `DaoMulticompany::getEntityConfig()`, groupes et mode transverse | Cette copie ne contient pas les appels aux trois hooks externes ; leur découverte reste à tester sur la version cible |
| InnoDB | Lectures verrouillées actuelles, conservation des verrous jusqu’au commit/rollback | [Documentation MySQL](https://dev.mysql.com/doc/refman/8.0/en/innodb-locking-reads.html) ; aucune concurrence réelle exécutée localement |

Les triggers natifs sont exécutés après certaines écritures et peuvent être suivis d’autres triggers même en cas de refus. Un rollback SQL n’annule pas un email ou un appel externe. Les tests d’intégration doivent se faire sur une instance dédiée avec effets externes maîtrisés.

## Contrôles exécutables localement

```powershell
php test/run.php
php test/native_smoke.php ../dolibarr/htdocs
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

- PHP local : **8.4.22**.
- Lint PHP : 23 fichiers contrôlés sans erreur ; `git diff --check` sans anomalie ; cinq catalogues de langues avec les mêmes clés, sans doublon.
- `test/run.php` : 20 scénarios simulés réussis sur les règles, le moteur, le cycle natif, la réconciliation, les bénéficiaires et les requêtes. Les doubles SQL ne sont pas une base InnoDB et ne prouvent ni l’exécution des requêtes ni leur comportement concurrent. La validation native des champs est remplacée dans ces doubles ; ses contrats ont été lus dans les sources.
- `test/native_smoke.php` : appel réel de `price2num()` du checkout indiqué, avec plusieurs réglages `MAIN_MAX_DECIMALS_TOT` et entrées invalides ; conservation des constantes zéro/vides et fusion idempotente du partage avec lecteurs natifs et écritures simulées. Contrôles réussis ; aucun bootstrap d’instance ni accès à des données métier.
- PHPStan : pas d’exécutable ni de configuration propre au module trouvé dans cet environnement. Aucune baseline ni suppression ajoutée. Analyse à exécuter sur le module avec les classes du core cible avant release.
- Navigateur, MySQL/MariaDB, CSRF réel, API native, installation/réactivation sur instance : non exécutés. Aucun déploiement permettant une vérification navigateur n’a été effectué.

## Recette sur instance de test

Exécuter sur **Dolibarr 20/PHP 8.0**, puis sur le couple exact de l’instance cible et sa version Multicompany. Attribuer les droits nécessaires à des utilisateurs ordinaires ; inclure un administrateur dépourvu de droit fonctionnel.

1. **Migration et restauration** : créer des règles anciennes avec montant zéro, illimité, dates et états variés ; activer deux fois et comparer identifiants/valeurs. Vérifier les constantes `0`, vides, modes périodiques et options Multicompany après désactivation/réactivation. Réconcilier des commandes créées, annulées ou modifiées pendant l’arrêt. Examiner les cas ambigus : ils bloquent les périodes, sans empêcher les règles à la commande.
2. **Groupes et partage** : utilisateur avec plafond inférieur à son groupe, plusieurs groupes, règle individuelle expirée/inactive, groupe global, groupe transverse, appartenance dans une autre entité, groupe inexistant. Vérifier local avant partagé pour chaque bénéficiaire, puis individuel avant groupe. Partager les règles entre A/B et vérifier depuis B une commande propriétaire A : règles, modes et consommation d’A. Vérifier la colonne/filtre Environnement et le refus de modification d’une règle distante par URL et POST.
3. **Montants et périodes** : plafond zéro, égalité, dépassement ; une commande refusée puis une autorisée. Deux collègues héritent du même groupe, mais consomment séparément. Deux entités ne partagent pas le cumul personnel. Combiner les cinq natures. Tester minuit, fin de mois/année, 29 février, heure d’été/hiver, passage civil/glissant et nouvelle précision native sans remise à zéro du registre.
4. **Cycle** : approbation simple, premier puis second niveau, second puis premier niveau, rejeu, annulation/refus/suppression/brouillon, réapprobation, rattachement à un autre projet et modification du total. Une ligne approuvée doit revenir en brouillon avant modification, car son trigger précède le recalcul natif du total. Les appels qui désactivent volontairement les triggers (`notrigger`) et les écritures SQL externes ne sont pas des points d’entrée couverts.
5. **Budget projet** : projet absent, budget `NULL`, zéro, égalité et dépassement ; projet inexistant/inaccessible ; projet partagé avec commandes dans deux entités, une seule consommation par commande et aucun détail inter-entités dans les messages/journaux. Les entités doivent utiliser une devise de base commune : le code refuse les devises différentes ou indéterminées, également pour une règle monétaire partagée.
6. **Concurrence réelle** : avec plafond journalier 100 et deux commandes 60, démarrer deux transactions indépendantes et approuver simultanément pour le même utilisateur. Une seule doit être validée ; après commit/rejeu, le registre doit contenir une consommation 60. Répéter pour deux utilisateurs sur un projet commun, deux entités partageant le projet, annulation simultanée et déplacement entre deux projets. Un deadlock peut refuser une opération : vérifier rollback complet et absence de consommation résiduelle avant de rejouer. Aucune stratégie de retry partiel des triggers n’est fournie.
7. **Canaux et interface** : fiche, URL directe, API native et méthodes avec triggers. Formulaires avec/sans token ; colonnes masquables, filtre/tri/pagination et changement de limite dans son formulaire parent. Vérifier la langue FR/EN et les trois langues complémentaires. Restaurer les réglages temporaires après succès/échec et vérifier qu’une seconde commande ne les hérite pas.

Le module reste soumis au moteur natif d’approbation. Lorsqu’une approbation directe via API ne satisfait pas un plafond final, le trigger refuse la transaction ; l’API ne transforme pas silencieusement cette demande en approbation provisoire.

Le passage direct en statut approuvé par `setStatus()` est refusé s’il ne correspond pas à une consommation déjà active : cette méthode ne gère ni les niveaux d’approbation ni leurs auteurs/dates. Employer la méthode native `approve()` ou l’endpoint natif d’approbation. Les événements sans incidence sur les plafonds (email, document, classement facturé) n’ouvrent aucun traitement de consommation.

## Périmètres sans changement

Aucun modèle documentaire, numérotation, événement Agenda custom, notification custom, catégorie, extrafield, endpoint API, import/export ni tâche cron n’est créé. Les documents et effets natifs des commandes restent sous la responsabilité du core. L’identifiant de module existant **450025** et sa plage de droits sont conservés ; aucun nouvel identifiant n’est attribué.
