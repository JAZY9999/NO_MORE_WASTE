# `utils/erreurs.go` — répondre proprement quand quelque chose casse

> ⏱️ **Lecture : ~16 min** · 1 400 mots

> **À lire avant** : rien de spécial. Ce fichier est court et c'est un bon point d'entrée.
> **Phase** : 10 (consolidation de l'API), enrichi pendant le portage du front (phase 11).
>
> 🔄 **Ce fichier a grandi trois fois depuis sa création.** Chaque fois selon le même scénario : un écran du back-office révèle un code d'erreur PostgreSQL mal traduit, on l'ajoute **à un seul endroit**, et les 80 routes en bénéficient. C'est le meilleur argument en faveur de la centralisation.

## Le problème que ce fichier résout

Pendant tout le développement (Phases 1 à 7), chaque handler faisait ceci quand une requête SQL échouait :

```go
produit, err := db.GetProduitById(id)
if err != nil {
    http.Error(w, "Erreur de recuperation du produit", http.StatusInternalServerError)
    return
}
```

Ça a l'air correct… mais regarde bien la variable `err`. Elle contient **la vraie explication** de ce qui s'est passé (« la colonne date_prevue ne peut pas être vide », « cet emplacement n'existe pas »…). Et cette variable n'est **utilisée nulle part**. Elle est jetée à la poubelle.

Résultat concret : l'API répond « Erreur de récupération du produit », et **personne ne saura jamais pourquoi**. Ni l'utilisateur, ni toi. Si ça plante en démo devant le jury, tu n'as aucune piste.

C'est ce qu'on appelle une **erreur 500 silencieuse** : le serveur crie qu'il a mal, mais il ne dit pas où.

Il y avait **101 endroits** comme celui-ci dans le projet.

## La solution : une fonction qui parle aux deux publics

Une réponse d'erreur a deux destinataires qui n'ont pas les mêmes besoins :

| Destinataire | Ce qu'il veut | Ce qu'il ne doit PAS voir |
|---|---|---|
| L'utilisateur (ou le front) | Un message court et clair | Les noms de tables, les messages SQL |
| Le développeur (toi) | La cause exacte, technique | — |

D'où la fonction `ErreurServeur`, qui sert les deux d'un coup :

```go
func ErreurServeur(w http.ResponseWriter, r *http.Request, message string, err error) {
    log.Printf("[ERREUR 500] %s %s -> %s | cause : %v", r.Method, r.URL.Path, message, err)
    http.Error(w, message, http.StatusInternalServerError)
}
```

- `log.Printf` écrit dans les **logs du serveur** (visibles avec `docker compose logs api-go`) : c'est privé, on peut y mettre les détails techniques.
- `http.Error` envoie au **client** le message générique.

Les handlers appellent donc maintenant :

```go
if err != nil {
    utils.ErreurServeur(w, r, "Erreur de recuperation du produit", err)
    return
}
```

Une ligne au lieu de deux, et la cause n'est plus perdue.

### Pourquoi on ne renvoie jamais `err` au client

Tentant :

```go
http.Error(w, err.Error(), 500)   // NE JAMAIS FAIRE
```

Le client recevrait par exemple `pq: insert or update on table "produits" violates foreign key constraint "produits_emplacement_id_fkey"`. Ça donne gratuitement à un attaquant : le nom de tes tables, le nom de tes colonnes, le nom de tes contraintes, et le fait que tu utilises PostgreSQL. C'est une aide au piratage.

## La deuxième idée : toutes les erreurs 500 n'en sont pas

Un code `500` veut dire quelque chose de précis : **« le serveur a un bug »**.

Mais prends ce cas : le client envoie `{"emplacement_id": 99999}` alors que l'emplacement 99999 n'existe pas. PostgreSQL refuse l'insertion. Est-ce un bug du serveur ? **Non.** Le serveur va très bien : c'est la donnée envoyée qui est fausse.

Répondre 500 ici, c'est mentir, et surtout c'est inutile pour celui qui appelle : il ne sait pas quoi corriger.

La bonne réponse est **400 (Bad Request)** : « ta requête est mal formée, corrige-la ».

### Comment on détecte ce cas

PostgreSQL numérote ses erreurs. Chaque erreur a un code à 5 caractères. **Trois** sont traduits ici :

| Code | Nom | Ce que ça veut dire | Réponse |
|---|---|---|---|
| `23503` | foreign_key_violation | Tu référence un élément qui n'existe pas | **400** |
| `23505` | unique_violation | Tu crées un doublon (ex: même code-barre) | **409** |
| `23514` | check_violation | La valeur n'est pas dans la liste autorisée | **400** |

On regarde donc si l'erreur est une erreur PostgreSQL, puis on aiguille sur son code :

```go
var erreurPostgres *pq.Error
if errors.As(err, &erreurPostgres) {
    switch erreurPostgres.Code {
    case codeViolationCleEtrangere: // 400
    case codeViolationUnicite:      // 409
    case codeViolationContrainte:   // 400
    }
}
```

### Pourquoi 409 et pas 400 pour un doublon

`400` dit « ta requête est mal formée ». Or une création en doublon est **parfaitement bien formée** : elle serait acceptée si l'autre valeur n'existait pas.

`409 Conflict` dit exactement cela : la demande est valide en soi, mais elle entre en conflit avec ce qui existe déjà. Le client sait alors qu'il ne doit pas corriger sa requête, mais choisir une autre valeur.

Cas typiques rencontrés : deux fois la même clé de traduction pour la même langue, un code-barre déjà enregistré, ou un compte utilisateur déjà rattaché à une autre boutique.

### Le cas `23514`, trouvé en portant l'écran des services

La colonne `services.type` a une contrainte `CHECK` : sept valeurs, pas une de plus. Mon formulaire proposait un champ texte libre.

Taper « cuisine » au lieu de « cours_cuisine » produisait une **erreur 500** — de quoi chercher un bug dans le serveur alors que c'était une faute de frappe. Le formulaire est devenu un menu déroulant, **et** le code `23514` a rejoint les deux autres ici.

### `errors.As`, expliqué simplement

`errors.As` pose la question : **« cette erreur est-elle, ou contient-elle quelque part, une erreur de type `*pq.Error` ? »**

Le « ou contient quelque part » est important. Nos repositories ne renvoient pas l'erreur brute : ils y ajoutent leur nom pour qu'on sache d'où ça vient.

```go
return 0, fmt.Errorf("CreateProduit : %w", err)
```

L'erreur finale est donc une sorte de poupée russe : `"CreateProduit : ..."` qui **contient** l'erreur PostgreSQL d'origine. Une comparaison directe (`err == quelquechose`) échouerait. `errors.As` sait ouvrir les poupées.

### ⚠️ Le piège `%w` contre `%v` — à retenir absolument

C'est le point le plus subtil du fichier, et il a nécessité de corriger **97 lignes** dans `db/`.

```go
fmt.Errorf("CreateProduit : %v", err)   // ❌ écrase l'erreur, garde juste son texte
fmt.Errorf("CreateProduit : %w", err)   // ✅ emballe l'erreur, on peut la retrouver
```

- `%v` (ou `%s`) transforme l'erreur en **simple texte**. Le texte est identique à l'affichage… mais l'erreur d'origine est **détruite**. `errors.As` ne trouvera plus rien.
- `%w` (`w` comme **wrap**, emballer) garde l'erreur d'origine **à l'intérieur**. C'est ce qui rend `errors.As` possible.

Avant la Phase 10, tout le projet écrivait `fmt.Errorf("... : %v", err.Error())`. Visuellement les logs étaient les mêmes, donc le problème était invisible — jusqu'au moment où on a voulu inspecter l'erreur. D'où la conversion des 97 lignes.

**La règle à retenir** : quand tu enveloppes une erreur, utilise toujours `%w`.

## Les deux autres fonctions : quand la faute vient d'ailleurs

Trois fonctions, trois natures de problème :

| Fonction | Code | Ce que ça veut dire |
|---|---|---|
| `ErreurServeur` | 500 (ou 400/409) | mon code a un bug — sauf si c'est le client qui a mal envoyé |
| `ErreurBaseIndisponible` | **503** | je vais bien, mais la base ne répond plus |
| `ErreurEmail` | **502** | je vais bien, mais le service d'envoi refuse |

### `ErreurBaseIndisponible`

Utilisée uniquement par le *health check* dans `app.go`.

Avant, ce handler faisait `panic(err)` si la base ne répondait pas. Un `panic` dans un serveur HTTP est le pire cas : le client reçoit une **connexion coupée**, sans message ni code d'erreur. Et c'était sur la route dont le seul but est de dire si tout va bien…

Maintenant on répond **503 (Service Unavailable)**. La nuance avec 500 :

- **500** = « mon code a un bug » → il faut corriger le programme.
- **503** = « je vais bien, mais un service dont je dépends est tombé » → ça peut revenir tout seul, réessaie.

Un outil de supervision traite ces deux cas différemment, d'où l'intérêt de ne pas tout mettre en 500.

### `ErreurEmail`, ajoutée en portant l'écran des adhésions

Cliquer sur « Relancer » répondait **500 « Erreur d'envoi de l'email »**.

Doublement faux. Le serveur va très bien : c'est Brevo qui refuse, parce que les identifiants SMTP ne sont pas renseignés dans le `.env`. Et le message ne dit pas quoi faire — le personnel ne sait pas s'il doit réessayer, prévenir un développeur, ou vérifier une configuration.

```
502 Bad Gateway
« Le service d'envoi d'emails n'a pas repondu.
  Verifiez les identifiants SMTP du fichier .env. »
```

**502** = un service **extérieur** n'a pas répondu comme attendu. Même raisonnement que le 503 de la base, appliqué à un autre voisin.

C'est le genre de détail qui compte le jour de la démonstration : le message dit lui-même où est le problème.

## Comment le vérifier soi-même

Provoque une erreur de clé étrangère (emplacement inexistant) :

```bash
curl -X POST http://localhost:8080/api/produits/ \
  -H "Authorization: $TOKEN" -H "Content-Type: application/json" \
  -d '{"code_barre":"TEST","libelle":"Test","emplacement_id":99999}'
```

Réponse (**400**, pas 500) :

```
Erreur de creation du produit : un des elements references n'existe pas (verifiez les identifiants envoyes)
```

Et côté serveur :

```bash
docker compose logs api-go | grep ERREUR
```

```
[ERREUR 400] POST /produits/ -> Erreur de creation du produit | reference inexistante |
cause : CreateProduit : pq: insert or update on table "produits" violates foreign key constraint "produits_emplacement_id_fkey"
```

Le client a un message actionnable, le développeur a la cause exacte. C'est tout l'objectif du fichier.

## Questions qu'on peut te poser en live coding

**« Pourquoi ne pas avoir fait un middleware au lieu d'appeler la fonction partout ? »**
Un middleware s'exécute *autour* du handler ; ici on doit réagir *à l'intérieur*, au moment précis où une requête SQL échoue, en sachant quelle opération a échoué. Et le cours ESGI construit les gardes de la même façon (appel explicite au début du handler, cf. `utils.RequireRole`) — on reste cohérent.

**« Pourquoi modifier `ErreurServeur` plutôt que les 101 handlers ? »**
Parce que les 101 handlers passent déjà par cette fonction. Ajouter la détection d'un code PostgreSQL à un seul endroit corrige les 101 d'un coup.

C'est un argument qui s'est vérifié **trois fois** : `23505` puis `23514` ont été découverts bien après, en portant des écrans du back-office. Chaque fois, une dizaine de lignes ici ont suffi.

**« Comment ces codes ont-ils été découverts ? »**
Toujours pareil : en utilisant un écran, pas en lisant du code. Le doublon (`23505`) est apparu en créant deux fois la même clé de traduction. La contrainte `CHECK` (`23514`) en saisissant un type de service inventé.

Les suites de tests étaient au vert dans les deux cas — elles vérifient ce qu'on a pensé à vérifier, un écran demande *tout ce dont il a besoin pour être utilisable*.

**« Et les codes non traités, `23502` par exemple ? »**
`23502` (champ obligatoire manquant) tombe dans le cas général et répond 500. On pourrait l'ajouter, mais il ne s'est jamais présenté : les handlers vérifient les champs obligatoires **avant** d'appeler la base, avec un message métier plus précis.

On n'ajoute du générique que là où rien n'existe — et seulement quand le cas s'est réellement produit.

## Les trois autres cas, vérifiables aussi

```bash
# 23505 : doublon -> 409
curl -X POST http://localhost:8080/api/commercants/ -H "Authorization: $TOKEN" \
  -d '{"raison_sociale":"Doublon","utilisateur_id":2}'   # compte déjà rattaché
# -> 409 « cette valeur existe deja »

# 23514 : valeur hors liste -> 400
curl -X POST http://localhost:8080/api/services/ -H "Authorization: $TOKEN" \
  -d '{"nom":"Test","type":"invente"}'
# -> 400 « une des valeurs envoyees n'est pas autorisee pour ce champ »

# service d'envoi injoignable -> 502
curl -X POST http://localhost:8080/api/adhesions/1/relancer -H "Authorization: $TOKEN"
# -> 502 « Verifiez les identifiants SMTP du fichier .env. »
```

## Fichiers liés

- [app.go.md](../app.go.md) — le health check qui utilise `ErreurBaseIndisponible`
- [guard.go.md](guard.go.md) — l'autre fonction utilitaire appelée dans les handlers (`RequireRole`)
- [../db/produitsRepository.go.md](../db/produitsRepository.go.md) — un exemple d'enveloppement avec `%w`
- [mailer.go.md](mailer.go.md) — l'envoi d'emails, dont `ErreurEmail` traduit les échecs
- [../../front-php/app/controllers/back/ServicesController.php.md](../../front-php/app/controllers/back/ServicesController.php.md) — l'écran qui a révélé le code `23514`
- [../../front-php/app/controllers/back/AdhesionsController.php.md](../../front-php/app/controllers/back/AdhesionsController.php.md) — celui qui a révélé le 500 sur l'envoi d'email
