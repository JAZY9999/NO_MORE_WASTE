# `app/config/menu_back.php` — la barre latérale décrite une seule fois

> ⏱️ **Lecture : ~13 min** · 900 mots

> **À lire avec** [../views/layout_back.php.md](../views/layout_back.php.md), qui l'affiche, et [../Vue.php.md](../Vue.php.md), qui s'en sert pour surligner la bonne entrée.

## Pourquoi un fichier de configuration

Le menu apparaît sur les **22 écrans** du back-office. S'il était écrit en HTML dans le gabarit, ajouter une rubrique voudrait dire retrouver la bonne section au milieu des balises. Ici, c'est une ligne dans un tableau.

C'est le même motif que `config.php` : un fichier qui fait `return [...]`, et `require` récupère le tableau. Rien de nouveau à apprendre.

## Les libellés sont des clés, jamais du texte

```php
['cle' => 'commercants', 'libelle' => 'menu.commercants', 'icone' => 'bi-shop', 'url' => '/back/commercants'],
```

`menu.commercants` est une **clé de traduction**, pas un libellé. C'est ce qui fait que la barre latérale change de langue avec le reste du site.

⚠️ **Écrire `Commerçants` en clair ici casserait le multilingue sur les 22 écrans d'un coup** — alors que le sujet exige un site multilingue. Et le défaut ne se verrait qu'en changeant de langue, c'est-à-dire peut-être jamais avant la soutenance.

C'est donc le premier test à refaire après toute modification de ce fichier :

```bash
curl -s -b cookies.txt "http://localhost:8080/back?lang=it" | grep -A1 "letter-spacing:.12em"
```

Vérifié : *Panoramica, Rete, Logistica, Attività, Amministrazione*.

## L'ordre suit le parcours métier

```
Pilotage        Tableau de bord
Réseau          Commerçants · Adhésions · Bénévoles · Bénéficiaires
Logistique      Collectes · Stocks · Emplacements · Tournées
Activités       Services · Catalogue · Campagnes
Administration  Utilisateurs · Traductions
```

> 🔄 La rubrique "Activités" s'appelait `menu.creneaux`. Renommée `menu.services` en vague 2 : c'est le mot du sujet ("services accessibles aux adhérents"), et l'écran gère les deux — services et créneaux — mais c'est le service qu'on vient chercher. **Piège rencontré au passage** : renommer une clé de traduction ne suffit pas, l'import "Fichiers vers base" ajoute et met à jour mais **ne supprime pas** — l'ancienne clé serait restée en base et aurait pollué le prochain export si elle n'avait pas été supprimée à la main.

On entre dans le **réseau** (ceux qui donnent et ceux qui aident), on **collecte**, on **stocke**, on **distribue**. Puis viennent les activités, et enfin l'administration — ce qui ne relève pas du travail quotidien.

C'est plus facile à retenir que l'ordre alphabétique ou l'ordre des tables de la base, et ça raconte ce que fait l'association.

## La table `parents` — pourquoi elle existe

```php
'parents' => [
    'back/benevole_detail'  => 'benevoles',
    'back/commercant_detail' => 'commercants',
    ...
],
```

Une fiche de détail s'ouvre **depuis une liste** : elle ne mérite pas sa ligne dans le menu. Mais sans cette table, ouvrir une fiche ferait **perdre toute position** — plus rien de surligné, on ne sait plus où l'on est.

### Elle ne liste que les exceptions

Pour tous les autres écrans, le **dernier segment du chemin de vue est déjà la bonne clé** : `back/commercants` → `commercants`. C'est la troisième règle de résolution dans `Vue::afficher`, et c'est elle qui évite d'avoir à configurer 22 lignes ici.

Concrètement : **15 des 22 écrans ne demandent aucune configuration**. Seules les 7 fiches de détail figurent dans `parents`.

### Pourquoi indexer par chemin de vue et non par URL

Deux raisons. D'abord, le chemin de vue est la seule information dont `Vue::afficher` dispose déjà — pas besoin de lui passer quoi que ce soit. Ensuite, il reste **stable si une adresse change** : renommer `/back/benevoles` en `/back/equipe` ne casserait pas le surlignage.

### Le cas de `back/profil`

```php
'back/profil' => '',   // aucune entrée active
```

Une chaîne vide, volontairement. « Mon compte » ne fait partie d'aucune rubrique métier : on s'y rend en cliquant sur son nom, en bas de la barre. Aucune entrée ne doit donc être surlignée — ce qui est différent de « on n'a rien configuré ».

## La couleur des pastilles appartient à l'entrée

```php
['cle' => 'adhesions', ..., 'couleur' => 'warning'],
```

Ce n'est pas au contrôleur de décider. « Les adhésions à renouveler » sont **toujours** un avertissement, quel que soit l'écran affiché ; « les produits périmés » sont **toujours** un problème. La couleur est une propriété de la rubrique, la valeur seule vient du contrôleur.

⚠️ **Aucune pastille n'est alimentée aujourd'hui.** C'est une décision assumée : les remplir imposerait quatre appels API supplémentaires sur *chaque* page du back-office, et une panne de l'API casserait alors les 22 écrans au lieu d'un seul. La forme est posée, la donnée arrivera écran par écran.

## La clé `role` — une entrée visible par un seul rôle

```php
['cle' => 'utilisateurs', ..., 'role' => 'admin_back'],
```

Ajoutée en vague 4, sur l'entrée "Utilisateurs". Trouvé en testant : un `staff_back` voyait cette entrée dans son menu, cliquait, et se faisait renvoyer au tableau de bord — `UtilisateursController` est réservé à `admin_back` seul. **Un lien qui rebondit donne l'impression d'un site cassé.**

`blocs/menu_back.php` saute désormais les entrées dont le `role` ne correspond pas à celui du compte connecté :

```php
if (isset($entree['role']) && Auth::role() !== $entree['role']) {
    continue;
}
```

⚠️ **C'est du confort, pas de la sécurité.** N'importe qui peut taper `/back/utilisateurs` à la main ; c'est le contrôleur qui protège réellement, en vérifiant le rôle exact avant toute action. Même raisonnement que le bouton désactivé de la fiche d'un bénévole — on a besoin des deux.

La clé est **facultative** : une entrée sans `role` reste visible à tous les rôles qui accèdent au back-office.

## Ajouter une rubrique

Trois choses, dans cet ordre :

1. une entrée dans la bonne section de ce fichier ;
2. les clés `menu.xxx` dans les **quatre** fichiers de `app/locales/`, puis « Fichiers vers base » dans `/back/traductions` (sinon elles disparaîtront au prochain export) ;
3. la route et le contrôleur.

Si la vue s'appelle comme la clé de menu, il n'y a **rien** à ajouter dans `parents`.

## Fichiers liés

- [../views/layout_back.php.md](../views/layout_back.php.md) — le gabarit qui affiche ce menu
- [../Vue.php.md](../Vue.php.md) — les trois règles de résolution de l'entrée active
- [config.php.md](config.php.md) — l'autre fichier de configuration, même motif
- [../locales/LISEZ-MOI.md](../locales/LISEZ-MOI.md) — pourquoi les fichiers de langue ne s'éditent pas à la main
- [../views/blocs/menu_back.php.md](../views/blocs/menu_back.php.md) — le masquage par rôle
- [../controllers/back/UtilisateursController.php.md](../Controllers/Back/UtilisateursController.php.md) — pourquoi cette entrée est réservée
