# ChangeLog

## 1.0.2 - 2026-10-13

- Ajout des plafonds HT par commande, jour, mois, année et budget projet natif, avec périodes civiles ou glissantes et priorité des règles individuelles sur les règles de groupe.
- Suivi transactionnel des consommations attribuées à l’approbateur final, conservation des deux niveaux d’approbation natifs, contrôle des modifications financières et respect des droits et du périmètre Multicompany sans exemption administrateur.
- Migration idempotente des anciennes règles vers la nature « À la commande » et reprise des approbations existantes sans inventer leur attribution ; réconciliation manuelle et affichage des cas ambigus dans les réglages. Après mise à jour, réactiver le module, examiner les anomalies accessibles et réconcilier ; les historiques incomplets bloquent les contrôles périodiques.
- Correction de l’activation et des contrôles transactionnels lorsque DebugBar trace la connexion, avec conservation des réglages et des partages existants.
- Correction du blocage de l’envoi et des réceptions partielles ou complètes par un historique d’approbation incomplet, sans nouvelle consommation ni suppression des contrôles d’approbation. Socle PHP 8.0 et cible Dolibarr v20 à v25 conservés ; les preuves et limites de validation sont détaillées dans `doc/validation.md`, `doc/activation.md` et `doc/reception.md`.

## 1.0.1 - 2026-08-25

- Suppression de l'initialisation dynamique obsolète de la propriété de menus sous PHP 8.2.

## 1.0.0 - 2026-07-07

- Création du module externe `lmdbsupplierorderlimit`.
- Ajout des tables de plafonds et de logs.
- Ajout du contrôle centralisé des plafonds d’approbation des commandes fournisseurs.
- Ajout de l’intégration par hook `ordersuppliercard`.
- Ajout du garde-fou trigger `ORDER_SUPPLIER_APPROVE`.
- Ajout des pages d’administration, traductions `fr_FR` et `en_US`.
- Correction de la compatibilité CSRF avec les versions Dolibarr où `checkToken()` n’existe pas.
- Formatage des montants de refus avec le réglage Dolibarr d’arrondi des totaux.
- Accès complet pour super-administrateur, administrateur et administrateur Multicompany.
- Alignement du libellé du bouton d’approbation refusé sur les clés natives des commandes fournisseurs.
- Amélioration des traductions françaises et ajout des traductions allemandes, espagnoles et italiennes.
- Affichage natif des utilisateurs et groupes dans la liste des plafonds, avec photo utilisateur, et ajout de la suppression de ligne.
- Ajout du comportement sans plafond `Illimité`, utilisé par défaut lorsque aucun plafond utilisateur ou groupe n’est défini.
- Ajustement du contrôle des plafonds pour distinguer l’approbation premier niveau de l’approbation second niveau, avec forçage runtime du workflow natif de seconde approbation lorsque le plafond bloque le second niveau.
- Désactivation visuelle du bouton natif d’approbation second niveau lorsque le plafond applicable est insuffisant.
- Alignement de la liste des plafonds sur les filtres, tris et boutons de recherche/réinitialisation natifs Dolibarr.
- Ouverture des formulaires de création et modification de plafond dans des modales depuis le bouton `+` natif et l’icône d’édition.
- Alignement de la liste des journaux sur les filtres, tris, pagination et boutons de recherche/réinitialisation natifs Dolibarr, avec liens natifs vers les utilisateurs et commandes fournisseurs.
- Traduction des codes de journaux affichés pour les types d’événements, raisons et messages.
