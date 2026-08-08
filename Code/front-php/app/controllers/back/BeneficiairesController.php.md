# Le module bénéficiaires — contrôleur et vue

> ⏱️ **Lecture : ~6 min** · 550 mots

> Couvre `app/controllers/back/BeneficiairesController.php` et `app/views/back/beneficiaires.php`.

## Ce que le sujet demande

> gérer les tournées de distribution vers des *« associations caritatives, particuliers en détresse… »*

Ces destinataires sont nommés explicitement. Jusqu'ici ils n'étaient **créables que par l'API**.

C'était un blocage en chaîne : on ne peut pas planifier une tournée sans arrêt, et on ne peut pas créer un arrêt sans bénéficiaire. L'écran des tournées dépendait donc d'une donnée que le back-office ne savait pas produire.

## Un écran volontairement simple

Liste et formulaire de création **sur la même page**, côte à côte.

Un bénéficiaire tient en cinq champs : nom, type, adresse, contact, téléphone. Un écran de détail séparé obligerait à naviguer pour lire trois lignes — et un formulaire sur une page à part ferait perdre de vue la liste qu'on est en train de compléter.

C'est le même choix que pour les emplacements de stock, pour la même raison.

## Le type est un menu, jamais un champ libre

```php
private const TYPES = ['association_caritative', 'particulier_detresse'];
```

La base impose ces deux valeurs par une contrainte `CHECK`. C'est la leçon de l'écran des services, où un champ texte transformait une faute de frappe en erreur serveur.

Et comme là-bas, le menu est doublé d'une **revalidation côté serveur** : le menu ne protège que ceux qui l'utilisent.

## Un piège de traduction, trouvé en testant

La vague 2 avait défini des clés **abrégées** pour l'écran des tournées :

```php
$typeBeneficiaire = ($b['type'] ?? '') === 'association_caritative'
    ? Langue::t('beneficiaires.type_association')      // ← nom court
    : Langue::t('beneficiaires.type_particulier');
```

Ce nouvel écran, lui, construit la clé dynamiquement :

```php
Langue::t('beneficiaires.type_' . $type)               // ← valeur exacte de la base
```

Résultat : **la clé brute s'affichait à l'écran**, dans les quatre langues.

Deux conventions coexistaient pour la même information. J'ai gardé celle qui suit la base — `beneficiaires.type_association_caritative` — et modifié la vue des tournées pour l'utiliser aussi. Une seule convention, construite mécaniquement à partir de la donnée.

### La conséquence à ne pas oublier

Renommer une clé ne suffit pas : l'import « Fichiers vers base » **ajoute et met à jour, mais ne supprime pas**. Les anciennes clés restent en base, et le prochain export les réintroduirait dans les fichiers.

Il faut donc les supprimer explicitement. C'est la deuxième fois dans le projet (après `menu.creneaux` → `menu.services`) — c'est désormais un réflexe à avoir à chaque renommage.

## Le filtre est fait côté PHP

```php
$beneficiaires = array_values(array_filter($tous, function ($b) use ($type) {
    return ($b['type'] ?? '') === $type;
}));
```

L'API n'expose pas de paramètre `?type=`. Le filtrage se fait donc ici, sur une liste qui reste petite — même compromis que le filtre par ville des commerçants.

`array_values` n'est pas décoratif : `array_filter` **conserve les clés d'origine**. Sans lui, un tableau filtré aurait des indices troués (0, 3, 7), et `json_encode` le rendrait comme un objet plutôt qu'une liste. Ça ne casse rien dans cette vue, mais c'est le genre de détail qui surprend plus tard.

## Comment le vérifier soi-même

```bash
# créer
curl -X POST http://localhost:8080/back/beneficiaires -b cookies.txt \
  --data-urlencode "nom=Secours Populaire Nantes" \
  --data-urlencode "type=association_caritative" --data-urlencode "ville=Nantes"
# -> « Bénéficiaire créé. »

# type forgé, en contournant le menu
curl -X POST http://localhost:8080/back/beneficiaires -b cookies.txt \
  --data-urlencode "nom=Pirate" --data-urlencode "type=nimporte_quoi"
# -> « Ce type de bénéficiaire n'existe pas. » ; l'API n'est pas appelée

# sans nom
# -> « Le nom et le type sont obligatoires. »

# le filtre
curl -s -b cookies.txt "http://localhost:8080/back/beneficiaires?type=particulier_detresse"
```

Vérifié le 2026-08-07, dans les quatre langues — sur cet écran **et** sur le détail d'une tournée, qui partage désormais les mêmes clés.

## Fichiers liés

- [../../views/back/beneficiaires.php.md](../../views/back/beneficiaires.php.md) — la vue
- [TourneesController.php.md](TourneesController.php.md) — où les bénéficiaires servent réellement
- [ServicesController.php.md](ServicesController.php.md) — la même leçon sur les listes fermées
- [../../views/back/traductions.php.md](../../views/back/traductions.php.md) — le circuit base ↔ fichiers
