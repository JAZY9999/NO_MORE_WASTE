# models/traduction.go — une langue, un libellé

> ⏱️ **Lecture : ~5 min** · 260 mots

> **À lire avec** [../app/traductions.go.md](../app/traductions.go.md), qui manipule ces deux structures.

## C'est quoi ce fichier ?

Deux structs, sans logique : `Langue` (une langue proposée par le site) et `Traduction` (un libellé, dans une langue).

## Langue

```go
type Langue struct {
    Code    string `json:"code"`
    Libelle string `json:"libelle"`
}
```

### Le code EST la clé primaire — pas un id numérique

C'est le choix à savoir défendre. La colonne `code` (`"fr"`, `"en"`…) sert directement de clé primaire en base, plutôt qu'un `id` auto-incrémenté classique.

Pourquoi : ce code est réutilisé **partout ailleurs**, tel quel — le nom du fichier `app/locales/fr.json`, le paramètre `?lang=fr` dans l'URL, l'attribut `<html lang="fr">`. Si la clé primaire était un entier, chacun de ces usages devrait faire une jointure ou une conversion pour retrouver `"fr"` à partir d'un numéro — pour une valeur qui, de toute façon, ne change jamais une fois choisie.

## Traduction

```go
type Traduction struct {
    Id         int    `json:"id"`
    Cle        string `json:"cle"`
    Valeur     string `json:"valeur"`
    CodeLangue string `json:"code_langue"`
}
```

Un libellé de l'interface, dans une langue :

```json
{"cle": "connexion.titre", "valeur": "Connexion",  "code_langue": "fr"}
{"cle": "connexion.titre", "valeur": "Sign in",    "code_langue": "en"}
```

**La clé ne s'affiche jamais** : c'est un identifiant technique, stable, qui ne change pas quand on corrige une traduction. C'est la `valeur` qui apparaît à l'écran.

### La paire `(cle, code_langue)` est unique

C'est cette contrainte qui rend possible `ON CONFLICT (cle, code_langue) DO UPDATE` dans `EnregistrerTraduction` (voir `db/traductionsRepository.go.md`) : une même clé existe une fois par langue, jamais deux fois dans la même langue.

## Fichiers liés

- [../app/traductions.go.md](../app/traductions.go.md) — les neuf routes qui manipulent ces structs
- [../db/traductionsRepository.go.md](../db/traductionsRepository.go.md) — le SQL, dont `ON CONFLICT`
- [../../front-php/app/Middleware/Langue.php.md](../../front-php/app/Middleware/Langue.php.md) — la lecture côté front
