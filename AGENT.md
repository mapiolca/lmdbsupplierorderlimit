# AGENT.md - Module lmdbsupplierorderlimit

Ce dépôt correspond directement au module Dolibarr `lmdbsupplierorderlimit`.
Lorsqu’il est installé, sa racine doit être copiée ou montée dans `htdocs/custom/lmdbsupplierorderlimit/`.

Règles locales :

- ne jamais modifier le core Dolibarr ;
- dans ce dépôt, modifier uniquement les fichiers du module, c’est-à-dire les fichiers présents à la racine du dépôt et dans ses sous-répertoires métier (`admin/`, `class/`, `core/`, `lib/`, `langs/`, `sql/`) ;
- conserver la compatibilité Dolibarr v20+ et PHP 8.0+ ;
- utiliser les droits fournisseurs natifs pour l’approbation ;
- ajouter uniquement la condition financière `total_ht <= plafond applicable` ;
- filtrer toutes les données métier par `entity` ;
- protéger les actions sensibles par token CSRF ;
- conserver les réglages à la désactivation/réactivation.
