# Réception sans nouvelle approbation

## Diagnostic et périmètre — 2026-10-13

La capture signale `LimitHistoryIncomplete` lors de la réception d’une commande déjà envoyée. Le trigger du module traitait `ORDER_SUPPLIER_RECEIVE` comme une mutation financière : sans consommation active, il refusait l’opération ; avec un instantané différent et une attribution ambiguë, il pouvait également la refuser. Un retour négatif empêche le commit du parcours natif de réception et entraîne son rollback.

La cause est reproduite localement avec un registre absent. L’état réel du registre de CF00163, les logs, le SHA déployé et l’issue de sa transaction ne sont pas accessibles : aucun diagnostic de données ni succès sur cette instance n’est affirmé.

Le correctif retire du traitement comptable les événements natifs d’envoi et de réception : `ORDER_SUPPLIER_SUBMIT`, `ORDER_SUPPLIER_RECEIVE`, `ORDER_SUPPLIER_STATUS_ORDERED`, `ORDER_SUPPLIER_STATUS_RECEIVED_PARTIALLY` et `ORDER_SUPPLIER_STATUS_RECEIVED_COMPLETELY`. Ces opérations ne changent ni le montant HT, ni le projet, ni l’approbateur final. Le module retourne son résultat neutre sans lire ni écrire son registre ; les permissions, la validation du statut et la transaction restent à la charge du parcours natif.

Les approbations, modifications financières, lignes, annulations, refus, suppressions et retours en brouillon conservent leurs contrôles. Une attribution historique manquante n’est pas inventée et continue à bloquer les cumuls périodiques lors d’une approbation. La réconciliation reste une opération distincte. Aucun changement SQL, migration, réglage, droit, document, Agenda, notification, API ou cron n’est ajouté. Il ne s’agit pas d’une release ; la version et le changelog publié restent inchangés.

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
| 25 | Non examiné pour ce correctif | Non vérifié ; reste dans la cible obligatoire |

Cette lecture ne valide aucune instance Dolibarr 20 à 25, ni une future version finale. Le socle PHP 8.0 reste déclaré, sans essai PHP 8.0 pour ce correctif.

## Vérifications

- `php test/run.php` : échec avant le correctif sur l’événement d’envoi avec registre absent, puis 21 scénarios simulés réussis sous PHP 8.4.22. Le nouveau scénario couvre les cinq événements avec registre absent, inactif, ambigu et résolu, une entité non réconciliée et un utilisateur sans permission d’approbation. Il vérifie l’absence de toute requête comptable et le maintien des refus d’approbation périodique et de modification financière.
- Les scénarios existants couvrent notamment le refus du passage direct au statut approuvé, les deux niveaux d’approbation, les plafonds, la libération après annulation et les modifications dépassant le budget.
- `php test/native_smoke.php ../dolibarr/htdocs` : succès avec les helpers natifs du checkout ci-dessus ; écritures de configuration simulées.
- Lint des 24 fichiers PHP et `git diff --check` : succès.
- PHPStan : non exécuté, aucun exécutable ou configuration propre au module disponible. Aucune suppression ni baseline ajoutée.
- Aucun déploiement, test navigateur, appel API réel, test MySQL/MariaDB concurrent ou action sur les données de l’instance.

## Recette après déploiement sur une instance de test

1. Réceptionner partiellement puis complètement une commande déjà envoyée dont le registre est absent ou ambigu, avec les droits natifs de réception et sans droit d’approbation. Vérifier le statut final et le commit, les réceptions et mouvements de stock attendus, sans nouvelle consommation.
2. Vérifier également l’envoi natif et les changements de statut via les points d’entrée autorisés, puis le refus natif avec droits de réception insuffisants.
3. Confirmer que les nouvelles approbations avec cumul périodique restent refusées si la reprise est incomplète, et que les modifications financières, lignes et passages directs au statut approuvé restent contrôlés.
4. Confirmer le SHA effectivement servi et refaire la recette sur Dolibarr 20/PHP 8.0, chaque version majeure 21 à 25 disponible et le couple exact Dolibarr/PHP/Multicompany de l’instance cible. Préserver les données ambiguës pour leur réconciliation séparée.
