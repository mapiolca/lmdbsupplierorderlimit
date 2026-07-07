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

Une commande fournisseur peut être approuvée au premier niveau si son `total_ht` est inférieur ou égal au plafond applicable. Lorsque l’utilisateur possède aussi le droit natif de second niveau `approve2`, le module laisse l’approbation de premier niveau suivre le chemin natif Dolibarr et applique le plafond uniquement à l’approbation de second niveau.

Par défaut, l’absence de plafond utilisateur ou groupe signifie que l’utilisateur n’a pas de plafond financier imposé par le module. Pour refuser explicitement un utilisateur, créer un plafond actif à `0` ou configurer le comportement global sans plafond sur `Refuser`.

## Compatibilité

- Dolibarr : v20+
- PHP : 8.0+
- Base : MySQL/MariaDB via l’abstraction Dolibarr
- Langues : français, anglais, allemand, espagnol, italien

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
