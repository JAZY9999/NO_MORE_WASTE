# `blocs/menu_back.php` — la barre latérale du back-office

> Inclus par `layout_back.php`. La **description** du menu, elle, vit dans `app/config/menu_back.php`.

## Le principe : décrire d'un côté, dessiner de l'autre

Ce fichier ne contient **aucune rubrique**. Il parcourt le tableau que lui donne `config/menu_back.php` :

```php
foreach ($menu['sections'] as $section) {
    foreach ($section['entrees'] as $entree) { … }
}
```

Conséquence : ajouter une entrée au menu ne demande pas de toucher à ce fichier. On ajoute une ligne dans la configuration, et elle apparaît — traduite, avec son icône, au bon endroit.

## Le mode sombre sans une ligne de CSS

```html
<aside data-bs-theme="dark">
```

Cet attribut fait basculer **toutes** les classes Bootstrap à l'intérieur en version sombre : `bg-body` devient sombre, `text-body` devient clair, et ainsi de suite.

Le projet n'écrit pas de CSS à la main. Le contraste entre la barre sombre et le contenu clair suffit à séparer la navigation du reste — on n'a même pas besoin d'une bordure.

## Les initiales

```php
$initiales = mb_strtoupper(mb_substr($email, 0, 2));
```

Faute de photo de profil, deux lettres tirées de l'e-mail.

`mb_substr` et non `substr` : en UTF-8, un caractère accentué occupe **deux octets**. `substr($email, 0, 2)` couperait « ét… » en plein milieu du `é` et afficherait un losange noir. Les fonctions `mb_*` comptent les caractères, pas les octets.

## Certaines entrées se masquent selon le rôle

```php
if (isset($entree['role']) && Auth::role() !== $entree['role']) {
    continue;
}
```

Ajouté en vague 4 pour l'entrée "Utilisateurs", réservée à `admin_back`. Sans ce filtre, un `staff_back` voyait l'entrée, cliquait, et se faisait renvoyer au tableau de bord — **un lien qui rebondit donne l'impression d'un site cassé**.

⚠️ Ce `continue` n'est qu'un affichage. La vraie protection est dans le contrôleur (`UtilisateursController` vérifie le rôle exact à chaque appel) : masquer un lien n'empêche personne de taper l'adresse à la main. C'est le même principe que le bouton "Valider" désactivé sur la fiche d'un bénévole.

## Le surlignage de l'entrée courante

```php
$estActive = ($entree['cle'] === $menuActif);
```

`$menuActif` est calculé par `Vue::afficher()` en trois temps : `$options['menu']` s'il est fourni, sinon la table `parents` de la configuration, sinon le dernier segment du chemin de vue.

Cette règle en cascade fait que **la plupart des écrans n'ont rien à configurer**. Et sur `/back/benevoles/1`, c'est bien « Bénévoles » qui reste surligné — sans elle, aucune entrée ne le serait, et l'utilisateur perdrait ses repères dès qu'il ouvre une fiche.

## Fichiers liés

- [../../config/menu_back.php.md](../../config/menu_back.php.md) — les rubriques et la table `parents`
- [../layout_back.php.md](../layout_back.php.md) — le gabarit qui l'inclut
- [../../Vue.php.md](../../Vue.php.md) — le calcul de `$menuActif`
