# Le module commerçants — contrôleur et vues

> ⏱️ **Lecture : ~11 min** · 1 000 mots

> Couvre `app/controllers/back/CommercantsController.php`, `app/views/back/commercants.php`, `commercant_detail.php` et `commercant_nouveau.php`.
>
> C'est le module de la **vague 1** (item 2.4 : liste + filtre), complété en vague 4 par la fiche, la création et la modification.

## Ce que le sujet demande

> gérer les *« commerçants partenaires »* et leur **adhésion**, avec un rappel de renouvellement

La liste seule ne suffisait pas : elle était une **impasse**. On voyait les partenaires sans pouvoir en ouvrir un seul, et aucun ne pouvait être créé depuis l'application.

## Une route API qui manquait — et sa conséquence

Il n'existait **aucun `PUT /commercants/{id}`**. La conséquence n'était pas théorique :

Une boutique enregistrée **sans compte** ne pouvait plus jamais être rattachée à son propriétaire. Celui-ci se connectait, et son espace client répondait « aucune boutique rattachée à votre compte » — sans le moindre recours depuis l'application. Il fallait une requête SQL à la main.

C'est le trou signalé à la fin de la vague 3. Il est comblé.

### La mise à jour partielle, et pourquoi elle compte

```go
var dto struct {
    RaisonSociale *string `json:"raison_sociale"`
    Siret         *string `json:"siret"`
    …
}
```

Des **pointeurs**, pas des chaînes. La distinction est essentielle :

| Ce que le client envoie | Ce que ça veut dire |
|---|---|
| champ absent | *ne touche pas à cette valeur* |
| `"siret": ""` | *vide ce champ* |

Sans cette distinction, un `PUT` classique remplacerait l'objet entier. Le formulaire de rattachement de compte, qui n'affiche ni le SIRET ni l'adresse, **les effacerait silencieusement** à chaque enregistrement.

C'est le piège classique des routes `PUT`, et il ne se voit qu'après coup — quand une donnée a disparu sans que personne sache quand.

### Le `0` qui détache

```go
if *dto.UtilisateurId == 0 {
    modifie.UtilisateurId = nil
}
```

Un menu déroulant HTML ne peut pas envoyer `null`. `0` est donc la convention pour « aucun compte » — aucun identifiant ne vaut zéro, la valeur est sans ambiguïté.

## Trois écrans, trois usages

| Écran | Ce qu'on y fait |
|---|---|
| Liste | retrouver un partenaire, voir d'un coup d'œil qui a un compte |
| Fiche | tout le reste : adhésion, compte, coordonnées, historique |
| Création | enregistrer un nouveau partenaire, souvent au téléphone |

### L'ordre des routes est un piège

```php
Flight::route('GET /back/commercants/nouveau', …);   // AVANT
Flight::route('GET /back/commercants/@id', …);       // APRÈS
```

Dans l'autre sens, FlightPHP prendrait `nouveau` pour un identifiant. `(int) "nouveau"` vaut `0`, l'API répondrait 404, et la page de création afficherait « Commerçant introuvable ».

**Panne silencieuse** : aucune erreur PHP, aucun log. Juste un écran qui ne s'ouvre jamais.

## L'ordre de la fiche suit les questions

1. **Est-il à jour ?** — l'adhésion, en premier : c'est elle qui conditionne tout le reste.
2. **Peut-il se connecter ?** — le compte rattaché.
3. **Comment le joindre ?** — les coordonnées.
4. **Qu'a-t-on fait avec lui ?** — l'historique de collectes.

L'adhésion affichée est **la plus récente** :

```php
if ($courante === null || $a['date_fin'] > $courante['date_fin']) {
```

Un partenaire fidèle en accumule plusieurs. Prendre la première du tableau annoncerait une adhésion expirée à quelqu'un parfaitement en règle. Même règle que dans l'espace client — vue de l'autre côté.

## Seuls les comptes adhérents sont proposés

```php
if (($u['role'] ?? '') === 'adherent' || (int) $u['id'] === $compteActuel) {
```

Rattacher un compte de personnel ou de bénévole n'aurait aucun sens : **c'est le rôle qui décide de l'espace auquel on accède**. Un bénévole rattaché à une boutique n'ouvrirait toujours que son espace bénévole.

Le `|| $compteActuel` mérite un mot : sans lui, si le compte déjà rattaché changeait de rôle, il disparaîtrait du menu — et la fiche afficherait « aucun compte » alors qu'il y en a un. Le menu mentirait sur l'état réel.

## Un avertissement quand le compte manque

```php
<?php if ($compteActuel === 0): ?>
    … « Sans compte rattaché, ce commerçant ne peut pas ouvrir
        son espace client ni demander de collecte en ligne. »
```

L'information est aussi sur la liste, sous forme de pastille verte ou grise. On voit donc **d'un coup d'œil** quels partenaires sont coupés du front-office.

Sans cet indicateur, le problème resterait invisible jusqu'à ce qu'un commerçant appelle pour se plaindre.

## Deux vérifications faites côté front

```php
if ($fin <= $debut) { … }        // dates d'adhésion
if ($raisonSociale === '') { … } // création
```

Les deux erreurs sont **évidentes pour l'utilisateur** — il s'est trompé d'année, il a oublié un champ. Une phrase claire vaut mieux qu'un refus sec venu du serveur.

L'API les refuse aussi, de son côté. Les deux servent, mais seule la seconde est une sécurité.

## La saisie survit à une erreur

```php
$_SESSION['commercant_saisie'] = $_POST;
```

…relue puis effacée à l'affichage suivant. Sans ça, une raison sociale oubliée obligerait à retaper les huit autres champs.

Même mécanisme que le formulaire de candidature.

## Comment le vérifier soi-même

```bash
# la mise à jour partielle : le reste survit-il ?
curl -X PUT http://localhost:8080/api/commercants/1 -H "Authorization: $TOKEN" \
  -d '{"ville":"Lyon"}'
# -> 204, puis GET : siret, email, téléphone inchangés

# rattacher un compte déjà pris
curl -X PUT http://localhost:8080/api/commercants/2 -H "Authorization: $TOKEN" \
  -d '{"utilisateur_id":2}'
# -> 409 « cette valeur existe deja »

# détacher
curl -X PUT http://localhost:8080/api/commercants/1 -H "Authorization: $TOKEN" \
  -d '{"utilisateur_id":0}'
# -> 204, utilisateur_id revient à null

# vider la raison sociale
# -> 400 « raison_sociale ne peut pas etre vide »

# l'ordre des routes
curl -s -o /dev/null -w "%{http_code}\n" -b cookies.txt \
  http://localhost:8080/back/commercants/nouveau
# -> 200 (et non une redirection vers la liste)
```

Vérifié le 2026-08-07, dans les quatre langues.

## Fichiers liés

- [../../views/back/commercant_detail.php.md](../../views/back/commercant_detail.php.md) et [../../views/back/commercant_nouveau.php.md](../../views/back/commercant_nouveau.php.md)
- [AdhesionsController.php.md](AdhesionsController.php.md) — les rappels que l'email de la fiche rend possibles
- [../Front/EspaceCommercantController.php.md](../Front/EspaceCommercantController.php.md) — ce que le rattachement de compte débloque
- [../../../../api-go/app/commercants.go.md](../../../../api-go/app/commercants.go.md) — la route `PUT` et sa mise à jour partielle
