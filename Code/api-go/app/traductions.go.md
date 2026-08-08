# app/traductions.go — langues et traductions, gérées depuis le back-office

> ⏱️ **Lecture : ~14 min** · 1 100 mots

> **À lire avec** [../db/traductionsRepository.go.md](../db/traductionsRepository.go.md) et [../../front-php/app/views/back/traductions.php.md](../../front-php/app/views/back/traductions.php.md), l'écran qui pilote ce fichier.
>
> 🔄 **Ce fichier n'avait jamais eu de documentation propre.** Comblé pendant la passe finale du portage front (phase 11), après que son écran a révélé un piège important (voir plus bas).

## C'est quoi ce fichier ?

Neuf handlers, en deux groupes : les **langues** (`ListerLangues`, `CreerLangue`, `SupprimerLangue`) et les **traductions** proprement dites (`ListerTraductions`, `CreerTraduction`, `ModifierTraduction`, `SupprimerTraduction`, `ImporterTraductions`), plus une fonction de validation partagée.

C'est le système qui remplace une bibliothèque i18n classique. Le sujet demande un site multilingue, pas l'usage d'un outil particulier — un système fait maison, administrable depuis le back-office, se montre et s'explique de bout en bout en soutenance.

## La base est la source de vérité, les fichiers sont un cache

C'est l'architecture à comprendre avant tout le reste :

```
table `traductions`  --export-->  app/locales/{fr,en,it,pt}.json  --lus par-->  le site
        ↑                                    │
        └──────────── import ────────────────┘
```

Le site **lit** les fichiers JSON à chaque page — interroger la base pour deux cents libellés à chaque affichage serait du gâchis. Mais c'est la **base** qu'on modifie depuis l'écran `/back/traductions`. D'où deux routes de synchronisation, une dans chaque sens (l'export vit dans `db/traductionsRepository.go`, l'import est ici).

## Deux routes publiques, et pourquoi c'est sûr

```go
// ListerLangues, ListerTraductions : aucun utils.RequireRole
```

Le sélecteur de langue et les libellés de l'interface doivent s'afficher pour un visiteur **non connecté** — sinon la page d'accueil elle-même ne pourrait pas être traduite. Ce n'est pas une faille : ces routes ne contiennent **aucune donnée sensible**, seulement des libellés d'interface (« Accueil », « Se connecter »…).

Toutes les routes qui **modifient** quelque chose (créer, modifier, supprimer, importer) restent réservées à `admin_back`/`staff_back`.

## `fr` est protégée contre la suppression

```go
if code == "fr" {
    http.Error(w, "La langue de reference (fr) ne peut pas etre supprimee", http.StatusBadRequest)
    return
}
```

Le français est la langue de référence : c'est sur elle qu'on retombe quand une clé manque ailleurs (voir `middleware/Langue.php` côté front). La supprimer romprait ce filet de sécurité et laisserait apparaître des clés brutes (`nav.accueil`) sur tout le site, dans toutes les langues à la fois.

Supprimer une langue **cascade** sur ses traductions — déclaré dans `schema.sql`, pas ici : la base fait le ménage toute seule.

## `ImporterTraductions` — la moitié la plus utilisée du fichier

```go
var corps struct {
    CodeLangue  string            `json:"code_langue"`
    Traductions map[string]string `json:"traductions"`
}
```

C'est la route appelée par le bouton « Fichiers vers base » de l'écran. Elle reçoit un objet entier (`{"nav.accueil": "Accueil", ...}`) et l'enregistre clé par clé :

```go
for cle, valeur := range corps.Traductions {
    db.EnregistrerTraduction(cle, valeur, corps.CodeLangue)
}
```

### ⚠️ Le piège découvert deux fois cette phase : l'import n'efface JAMAIS

`EnregistrerTraduction` fait un `INSERT ... ON CONFLICT DO UPDATE` — elle **crée ou met à jour**, mais ne supprime rien :

```sql
INSERT INTO traductions (cle, valeur, code_langue)
VALUES ($1, $2, $3)
ON CONFLICT (cle, code_langue) DO UPDATE SET valeur = EXCLUDED.valeur
```

Conséquence directe : **renommer une clé de traduction dans les fichiers JSON ne supprime jamais l'ancienne clé en base.** Elle reste orpheline, et le prochain export ("Base vers fichiers") la réintroduirait dans les JSON.

C'est arrivé concrètement deux fois pendant le portage du back-office :

1. `menu.creneaux` → `menu.services` (renommage du libellé de menu, vague 2) ;
2. `beneficiaires.type_association` → `beneficiaires.type_association_caritative` (unification des conventions de clé, vague 4).

Dans les deux cas, l'ancienne clé a dû être supprimée **explicitement** via `DELETE /traductions/{id}`, en plus du renommage dans les fichiers. **C'est désormais un réflexe à avoir à chaque renommage de clé**, pas seulement une note en bas de page.

### Pourquoi c'est idempotent malgré tout

```go
// Envoyer deux fois le meme fichier ne cree donc aucun doublon
```

`ON CONFLICT (cle, code_langue)` désigne exactement la paire qui doit rester unique. Réimporter le même fichier plusieurs fois ne produit jamais de doublon — seulement des mises à jour inutiles mais sans danger. C'est ce qui rend la route sûre à appeler après chaque modification manuelle des fichiers.

## `validerTraduction` — la validation commune

```go
func validerTraduction(w http.ResponseWriter, t *models.Traduction) bool
```

Appelée par `CreerTraduction` **et** `ModifierTraduction` : même règles (aucun champ vide, clé de 100 caractères maximum), un seul endroit à corriger si elles changent. Retourne `false` après avoir déjà envoyé la réponse d'erreur — le style utilisé partout ailleurs dans le projet pour les fonctions d'aide appelées en plein milieu d'un handler.

## Comment le vérifier soi-même

```bash
# renommer une clé : l'ancienne reste-t-elle en base ?
# 1. remplacer une clé dans les 4 fichiers JSON par un nouveau nom
# 2. sur /back/traductions, cliquer "Fichiers vers base"
curl -s "http://localhost:8080/api/traductions/?langue=fr" -H "Authorization: $TOKEN" \
  | python -c "import sys,json; d=json.load(sys.stdin); print([t for t in d if t['cle']=='ancien.nom'])"
# -> la ligne existe TOUJOURS : l'import n'a pas supprimé l'ancienne clé

# fr protégée
curl -X DELETE http://localhost:8080/api/langues/fr -H "Authorization: $TOKEN"
# -> 400 « La langue de reference (fr) ne peut pas etre supprimee »

# import idempotent
curl -X POST http://localhost:8080/api/traductions/import -H "Authorization: $TOKEN" \
  -d '{"code_langue":"fr","traductions":{"nav.accueil":"Accueil"}}'
# -> exécuter deux fois de suite : aucun doublon créé
```

## Fichiers liés

- [../db/traductionsRepository.go.md](../db/traductionsRepository.go.md) — `EnregistrerTraduction`, l'export vers les fichiers
- [../../front-php/app/views/back/traductions.php.md](../../front-php/app/views/back/traductions.php.md) — l'écran qui appelle ces routes, et le rappel du même piège côté front
- [../../front-php/app/Middleware/Langue.php.md](../../front-php/app/Middleware/Langue.php.md) — la lecture des fichiers JSON par le site
