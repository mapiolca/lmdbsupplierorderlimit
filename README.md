# lmdbsupplierorderlimit

Module externe Dolibarr ajoutant un contrôle de plafonds d’approbation HT aux commandes fournisseurs natives.

Ce dépôt correspond directement au contenu du répertoire d’installation Dolibarr `htdocs/custom/lmdbsupplierorderlimit/`.

## Fonctionnement

Le module ne remplace pas le workflow fournisseur Dolibarr. Il ajoute une condition financière :

```text
Droit Dolibarr natif d’approbation fournisseur
+
Plafond actif utilisateur ou groupe
```

Une commande fournisseur peut être approuvée si son `total_ht` est inférieur ou égal au plafond applicable.

## Compatibilité

- Dolibarr : v20+
- PHP : 8.0+
- Base : MySQL/MariaDB via l’abstraction Dolibarr

## Administration

Les réglages sont accessibles depuis la page native des modules, entrée unique `setup.php@lmdbsupplierorderlimit`.

Onglets internes :

- Réglages
- Plafonds
- Logs
- Compatibilité
- À propos

## Sécurité

- Aucun fichier core modifié.
- Droits fournisseurs natifs conservés.
- Contrôle serveur par hook `ordersuppliercard`.
- Garde-fou par trigger natif `ORDER_SUPPLIER_APPROVE`.
- Requêtes filtrées par `entity`.
- Actions admin protégées par token CSRF.

## Installation

Copier ou monter la racine de ce dépôt dans :

```text
htdocs/custom/lmdbsupplierorderlimit/
```

Puis activer le module depuis l’administration Dolibarr.
