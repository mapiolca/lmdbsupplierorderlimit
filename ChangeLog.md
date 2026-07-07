# ChangeLog

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
