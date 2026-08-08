# Le module bénévoles — contrôleur et vues

> ⏱️ **Lecture : ~10 min** · 860 mots, 36 lignes de code

> Couvre `app/controllers/back/BenevolesController.php`, `app/views/back/benevoles.php` et `app/views/back/benevole_detail.php`.
>
> **C'est le module que le sujet détaille le plus.** La fiche d'un bénévole est l'écran le plus important du back-office.

## Ce que le sujet demande

Trois choses, citées explicitement :

1. gérer les bénévoles *« en prenant en compte les différentes capacités qu'ils ont (chauffeurs, cuisiniers, plombiers…) »* ;
2. *« chacun peut s'inscrire (…) **à condition de valider un certain nombre de conditions** »* ;
3. le parcours candidature → validation → affectation.

Le point 2 est la règle centrale, et c'est elle qui structure l'écran.

## La règle : validation conditionnée

**Un bénévole ne peut être validé que si TOUS ses documents justificatifs le sont.**

Cette règle est appliquée par l'API (`PUT /benevoles/{id}/validation` refuse sinon). L'écran ne la duplique pas — il la **rend visible avant le clic** :

```php
$peutValider = ($total > 0 && $valides === $total);
```

Trois conséquences à l'écran :

- un bandeau orange explique *pourquoi* la validation est impossible, avec une barre de progression `2/3` ;
- le bouton « Valider le bénévole » porte l'attribut `disabled` ;
- les documents non validés sont surlignés en orange dans la liste.

### Pourquoi désactiver le bouton plutôt que laisser cliquer

L'API refuserait de toute façon. Mais laisser cliquer pour afficher ensuite « impossible » fait perdre du temps et donne l'impression d'un bug. **Un bouton désactivé accompagné de son explication vaut mieux qu'un refus après coup.**

⚠️ Le `disabled` est du **confort**, pas de la sécurité : n'importe qui peut le retirer avec les outils du navigateur. C'est l'API qui protège réellement. On a besoin des deux — même raisonnement que pour le lien « Back-office » masqué dans le menu.

### Le cas `$total > 0`

Un bénévole **sans aucun document** ne doit pas être validable non plus. Sans ce test, `0 === 0` serait vrai et le bouton s'activerait pour quelqu'un qui n'a rien fourni.

## Le filtre par statut, côté API cette fois

```php
$chemin = '/benevoles/' . ($statut !== '' ? '?statut=' . urlencode($statut) : '');
```

Contrairement aux commerçants — où le filtre est fait en PHP faute de paramètre côté API — la route des bénévoles **expose `?statut=`**. On l'utilise : moins de données transférées, et le filtrage est fait par la base, qui sait le faire vite.

### On ne fait pas confiance au paramètre d'URL

```php
if (!in_array($statut, self::STATUTS, true)) {
    $statut = '';
}
```

Sans cette vérification, `?statut=pirate` serait transmis tel quel à l'API. Ce n'est pas une faille grave ici (l'API renverrait une liste vide), mais c'est le réflexe à avoir : **tout ce qui vient de l'URL est fourni par l'utilisateur**, donc suspect.

### Le second appel pour les compteurs

Les onglets affichent le nombre de bénévoles par statut. Or la liste filtrée ne contient que le statut courant : impossible d'en déduire les autres. D'où un second appel sans filtre.

C'est un compromis assumé : deux requêtes au lieu d'une, pour une information qui aide à naviguer. Si la liste devenait très grande, il faudrait une route de comptage côté API.

## Une seule route POST pour cinq boutons

```php
Flight::route('POST /back/benevoles/@id', [$benevoles, 'traiter']);
```

La fiche contient cinq actions : valider un document, valider le bénévole, le refuser, ajouter une compétence, en retirer une. Plutôt que cinq routes, un seul point d'entrée qui lit un champ caché `action` :

```html
<input type="hidden" name="action" value="valider_document">
```

Le `switch` du contrôleur aiguille. C'est le même motif que l'écran des traductions, et ça évite une explosion de routes pour un seul écran.

### La redirection après POST — indispensable

```php
Auth::rediriger($retour);
```

Sans elle, rafraîchir la page **rejouerait l'action** : on validerait le même document deux fois, on ajouterait deux fois la même compétence. Le navigateur propose même de « renvoyer les données du formulaire », ce qui déroute.

C'est le motif **POST puis redirection** (*Post/Redirect/Get*) : après une action qui modifie quelque chose, on redirige toujours vers une page en lecture.

## Ce que la vue reçoit déjà calculé

Le contrôleur ne se contente pas de passer les données brutes : il calcule `$documentsValides`, `$documentsTotal`, `$peutValider` et `$competencesRestantes`.

**La vue ne fait aucun calcul métier.** Elle affiche. C'est ce qui permet de lire `benevole_detail.php` sans se demander d'où sort une condition — et de changer la règle à un seul endroit.

`$competencesRestantes` mérite un mot : c'est le catalogue moins celles déjà attribuées.

```php
$competencesRestantes = array_values(array_filter(
    $catalogue,
    fn($c) => !in_array($c['id'], $dejaIds, true)
));
```

Sans ce filtrage, le menu d'ajout proposerait des compétences que le bénévole possède déjà — et l'API répondrait 409.

## Trois appels pour une fiche

`GET /benevoles/{id}`, puis ses documents, puis ses compétences (plus le catalogue). Quatre requêtes pour un écran.

C'est justifiable : les documents **conditionnent** la validation, les compétences **conditionnent** les affectations. Les trois se lisent ensemble, un écran qui n'en montrerait qu'une partie obligerait à naviguer pour comprendre.

## Comment le vérifier soi-même

Le cycle complet, celui qui prouve la règle du sujet :

```bash
# se connecter en staff, puis créer un bénévole avec 2 documents non validés
# -> la fiche affiche « Validation impossible », 0/2, bouton disabled

curl -X POST http://localhost:8080/back/benevoles/3 -b cookies.txt \
  -d "action=valider_document&document_id=1"
# -> 1/2, toujours bloqué

curl -X POST http://localhost:8080/back/benevoles/3 -b cookies.txt \
  -d "action=valider_document&document_id=2"
# -> 2/2, le bouton s'active

curl -X POST http://localhost:8080/back/benevoles/3 -b cookies.txt -d "action=valider"
# -> statut « valide » en base
```

Vérifié le 2026-08-03, étape par étape. Le multilingue aussi :

```bash
for l in fr en it pt; do curl -s -b cookies.txt "http://localhost:8080/back/benevoles?lang=$l"; done
# Bénévole / Volunteer / Volontario / Voluntario
```

Et le point qui valide le socle : sur `/back/benevoles/1`, c'est bien l'entrée **Bénévoles** qui reste surlignée dans le menu, grâce à la table `parents` de `menu_back.php`.

## Fichiers liés

- [../../config/menu_back.php.md](../../config/menu_back.php.md) — la table `parents` qui garde l'entrée surlignée
- [../../views/layout_back.php.md](../../views/layout_back.php.md) — le gabarit et `$options`
- [CommercantsController.php.md](CommercantsController.php.md) — le même patron, avec un filtre côté PHP
- [../../../../api-go/app/benevoles.go.md](../../../../api-go/app/benevoles.go.md) — la règle appliquée côté API
