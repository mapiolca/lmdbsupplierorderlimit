# Réception sans nouvelle approbation

## Diagnostic et périmètre — 2026-10-13

La capture signale `LimitHistoryIncomplete` lors de la réception d’une commande déjà envoyée. Le trigger du module traitait `ORDER_SUPPLIER_RECEIVE` comme une mutation financière : sans consommation active, il refusait l’opération ; avec un instantané différent et une attribution ambiguë, il pouvait également la refuser. Un retour négatif empêche le commit du parcours natif de réception et entraîne son rollback.

La cause est reproduite localement avec un registre absent. L’état réel du registre de CF00163, les logs, le SHA déployé et l’issue de sa transaction ne sont pas accessibles : aucun diagnostic de données ni succès sur cette instance n’est affirmé.

Le correctif distingue la réception légitime, les transitions directes et l’annulation. `ORDER_SUPPLIER_RECEIVE` avec statut final partiellement ou complètement reçu retourne un résultat neutre après chargement de la commande et contrôle de son entité, sans lecture ni écriture du registre et sans contrôle financier du réceptionnaire. Avec statut final `7`, ce même événement libère la consommation sous verrou d’entité dans la transaction native ; un échec SQL reste bloquant. `ORDER_SUPPLIER_SUBMIT` reste sans traitement comptable.

Les trois événements `ORDER_SUPPLIER_STATUS_ORDERED`, `ORDER_SUPPLIER_STATUS_RECEIVED_PARTIALLY` et `ORDER_SUPPLIER_STATUS_RECEIVED_COMPLETELY` sont contrôlés : `setStatus()` transmet encore le statut précédent dans l’objet lors du trigger. Le module le capture avant rechargement et exige un état déjà approuvé `2–5`, avec aliases cohérents lorsque `status` et `statut` sont tous deux renseignés. Un brouillon, une validation provisoire, une annulation, un refus ou un état absent/mal formé est refusé, sans tentative d’approbation au nom du réceptionnaire. Le statut final rechargé doit correspondre à l’événement. Une transition autorisée conserve intégralement le registre, même absent ou ambigu.

Les approbations, modifications financières, lignes, annulations, refus, suppressions et retours en brouillon conservent leurs contrôles. Une attribution historique manquante n’est pas inventée et continue à bloquer les cumuls périodiques lors d’une approbation. La réconciliation reste une opération distincte. Ce correctif n’ajoute aucun changement SQL, migration, réglage, droit, document, Agenda, notification, API ou cron. Il est intégré à la version **1.0.2** du 13 octobre 2026 et résumé dans `ChangeLog.md` ; son déploiement reste à confirmer.

## Contrats natifs lus

Lecture locale le 2026-10-13 de `htdocs/fourn/class/fournisseur.commande.class.php` : `commande()` met à jour les données d’envoi et appelle `ORDER_SUPPLIER_SUBMIT` ; `Livraison()` change le statut puis appelle `ORDER_SUPPLIER_RECEIVE` dans sa transaction ; `setStatus()` ne change que le statut et appelle les événements correspondants. Aucun de ces chemins n’effectue une nouvelle approbation ni un recalcul du montant/projet.

| Version | Commit examiné | Nature de preuve |
|---|---|---|
| 20.0.0 | `697bf01970740a3339cd99cf055b4428fc5e051c` | Lecture de sources uniquement |
| 21.0.0 | `fd970b582a4d8c5779a2958a4e9f4fce225cf085` | Lecture de sources uniquement |
| 22.0.0 | `49b9a6d19f3deb6d410c0e9b3310e95be4ea7710` | Lecture de sources uniquement |
| 23.0.0 | `57a1f05d490a7a80944a8232e9c613e6556d2704` | Lecture de sources uniquement |
| 24.0.0 | `769c7db907099643558e77d7002c109cfda919e5` | Lecture de sources uniquement |
| Checkout 24.0.2 | `b9604c05e163fdea99508249fc6aef98007eec81` | Lecture de sources et smoke test des helpers sous PHP 8.4.22 |
| 25.0.0-alpha | `0d20b226f5e13b848bb58528398967f52862688c` | Méthodes `setStatus()`/`Livraison()` exécutées avec SQL simulé pour la correction des P1 ; aucune instance validée |

Cette lecture ne valide aucune instance Dolibarr 20 à 25, ni une future version finale. Le socle PHP 8.0 reste déclaré, sans essai PHP 8.0 pour ce correctif.

## Vérifications

- `php test/run.php` : échec avant le correctif sur l’événement d’envoi avec registre absent, puis 21 scénarios simulés réussis sous PHP 8.4.22. Le nouveau scénario couvre les cinq événements avec registre absent, inactif, ambigu et résolu, une entité non réconciliée et un utilisateur sans permission d’approbation. Il vérifie l’absence de toute requête comptable et le maintien des refus d’approbation périodique et de modification financière.
- Les scénarios existants couvrent notamment le refus du passage direct au statut approuvé, les deux niveaux d’approbation, les plafonds, la libération après annulation et les modifications dépassant le budget.
- `php test/native_smoke.php ../dolibarr/htdocs` : succès avec les helpers natifs du checkout ci-dessus ; écritures de configuration simulées.
- Lint des 24 fichiers PHP et `git diff --check` : succès.
- PHPStan : non exécuté, aucun exécutable ou configuration propre au module disponible. Aucune suppression ni baseline ajoutée.
- Aucun déploiement, test navigateur, appel API réel, test MySQL/MariaDB concurrent ou action sur les données de l’instance.

## Recette après déploiement sur une instance de test

### Validation des deux remarques P1 de la PR #3

Les commentaires `discussion_r4258155675` (contournement par `setStatus()`) et `discussion_r4258155687` (annulation via `Livraison()`) sont fondés : l’exclusion inconditionnelle de ces événements dans le premier patch laissait ces deux parcours sans contrôle. Le correctif ci-dessus rétablit leur traitement sans conditionner une réception à un plafond d’approbation.

`php test/native_fulfillment.php <fournisseur.commande.class.php>` extrait avec le tokenizer PHP les corps non modifiés de `setStatus()` et `Livraison()` et les exécute dans une classe de test. Le chargement de commande, le transport SQL, les transactions et le gestionnaire d’événements sont simulés ; ce n’est pas une instance ni un test de mouvements de stock. Les scénarios couvrent les anciens statuts et données manquantes/mal formées, les statuts finaux `3–5`, les aliases incohérents, réception partielle/complète avec plafond zéro et aucun droit d’approbation, annulations `can`/`nev`, registre absent/inactif/ambigu, rejeu, erreur SQL, permission native manquante et accès interentités. La libération d’une commande partagée cible son entité propriétaire, sans nouvelle attribution ni montant inventé.

Exécution sous PHP 8.4.22 sur les commits v20–v24 de la table ci-dessus, le checkout 24.0.2 et la préversion 25.0.0-alpha `0d20b226f5e13b848bb58528398967f52862688c` : 73 scénarios réussis pour chaque source, incluant le droit natif avec le module Réceptions actif et inactif. Il s’agit de preuves ciblées sur des méthodes natives avec doublures, sans validation PHP 8.0, transaction InnoDB réelle ou compatibilité avec une future v25 finale. Les 22 scénarios métier, les 21 combinaisons d’activation, le smoke test natif, le lint des 25 fichiers PHP et le contrôle du diff passent aussi. PHPStan demeure indisponible.

1. Réceptionner partiellement puis complètement une commande déjà envoyée dont le registre est absent ou ambigu, avec les droits natifs de réception et sans droit d’approbation. Vérifier le statut final et le commit, les réceptions et mouvements de stock attendus, sans nouvelle consommation.
2. Vérifier également l’envoi natif et les changements de statut via les points d’entrée autorisés, puis le refus natif avec droits de réception insuffisants.
3. Confirmer que les nouvelles approbations avec cumul périodique restent refusées si la reprise est incomplète, et que les modifications financières, lignes et passages directs au statut approuvé restent contrôlés.
4. Confirmer le SHA effectivement servi et refaire la recette sur Dolibarr 20/PHP 8.0, chaque version majeure 21 à 25 disponible et le couple exact Dolibarr/PHP/Multicompany de l’instance cible. Préserver les données ambiguës pour leur réconciliation séparée.
