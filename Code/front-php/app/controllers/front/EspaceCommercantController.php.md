# L'espace commerçant — contrôleur et vues

> ⏱️ **Lecture : ~10 min** · 900 mots

> Couvre `app/controllers/front/EspaceCommercantController.php`, `app/views/front/espace_commercant.php` et `espace_commercant_sans_fiche.php`.

## Ce que le sujet demande

> *« il y a ici à la fois un back-office (utilisé par NO MORE WASTE) et un front office (utilisé par les clients de NO MORE WASTE) »*

Sans cet écran, un adhérent connecté ne verrait **rien de plus qu'un visiteur de passage**. Le front-office n'existerait que de nom.

## La règle qui gouverne tout le fichier

**Aucune méthode n'envoie d'identifiant de commerçant à l'API.**

```php
$this->api->get('/mon-espace/commercant', Auth::jeton());
```

Pas de `/commercants/5`. Les routes `/mon-espace` font elles-mêmes le chemin `jeton → compte → fiche`. C'est ce qui rend impossible de lire le dossier d'un autre en changeant un numéro dans l'URL — il n'y a aucun numéro à changer.

Cette règle n'est pas théorique : en portant cet écran, j'ai trouvé que la route d'inscription à un créneau, elle, **acceptait** un identifiant du client. Un adhérent pouvait inscrire la boutique de quelqu'un d'autre. Corrigé côté API.

## Le 404 qui n'est pas une erreur

```php
if ($reponse['code'] === 404) {
    Vue::afficher('front/espace_commercant_sans_fiche', …);
    return;
}
```

L'API répond 404 quand le compte est légitime mais qu'aucune boutique n'y est rattachée. **Et 404 est le bon code** : ce n'est pas le compte qui est introuvable, c'est la fiche.

Laisser passer ce 404 afficherait « page introuvable » — un message faux et inquiétant. L'utilisateur croirait le site cassé alors que c'est son dossier qui est incomplet. L'écran dédié lui dit ce qui manque et quoi faire.

C'est le même raisonnement que le code-barre inconnu sur l'écran des stocks : **un message d'erreur doit être réservé à ce qui est réellement anormal.**

## L'adhésion la plus récente, pas la première trouvée

```php
foreach ($adhesions as $a) {
    if ($courante === null || $a['date_fin'] > $courante['date_fin']) {
        $courante = $a;
    }
}
```

Un commerçant fidèle en accumule plusieurs au fil des années. Prendre la première du tableau afficherait celle de 2023 — et annoncerait une adhésion expirée à quelqu'un parfaitement en règle.

Les jours restants sont calculés **ici**, pas dans la vue :

```php
$courante['jours_restants'] = (int) floor(($fin - strtotime(date('Y-m-d'))) / 86400);
```

`86400` = le nombre de secondes dans une journée. `floor` plutôt qu'un arrondi : à 12 heures de l'échéance, il reste 0 jour, pas 1.

## Le seuil des 30 jours n'est pas décoratif

Dans la vue :

```php
$bientot = $jours >= 0 && $jours <= 30;
```

**30 jours, c'est exactement le moment où l'association envoie son premier rappel par email.** L'écran passe à l'orange au moment précis où le mail part. Un autre seuil ferait dire deux choses différentes au site et à l'email — et l'adhérent ne saurait pas lequel croire.

## Trois chiffres justes plutôt que quatre dont un inventé

La maquette montrait « 312 articles donnés ». Pour l'obtenir il faudrait un appel par collecte, et l'espace client **n'a pas accès à la route des produits** — elle est réservée au personnel.

```php
return [
    'collectes' => count($collectes),
    'realisees' => $realisees,
    'annees' => count($adhesions),
];
```

On ne compte que ce que l'API renvoie réellement. Un chiffre approximatif sur un écran client se remarque tout de suite, et discrédite les trois autres.

**Le nom du bénévole affecté est absent pour la même raison** : `GET /benevoles/` est réservé au personnel. C'est d'ailleurs sain — un commerçant n'a pas besoin de savoir qui passera avant que la personne arrive.

## Demander une collecte : on n'envoie que la date

```php
$this->api->post('/mon-espace/collectes', ['date_prevue' => $date], Auth::jeton());
```

Ni statut, ni bénévole, ni identifiant de boutique. Le commerçant signale qu'il a des invendus ; il ne décide pas de l'organisation interne de l'association.

L'API l'impose de son côté (elle ignore tout autre champ), mais autant ne pas envoyer ce qui sera jeté : le code dit alors exactement ce qu'il fait.

### La date passée, vérifiée deux fois

```php
if ($date < date('Y-m-d')) { … }
```

Et dans la vue, `<input type="date" min="...">`.

L'attribut `min` empêche de choisir la date dans le calendrier. Il ne protège que ceux qui passent par le formulaire — d'où la vérification côté serveur. **Les deux servent, mais seule la seconde est une sécurité.**

Pourquoi vérifier ici plutôt que laisser l'API refuser : l'erreur est évidente pour l'utilisateur (il s'est trompé de mois), et une phrase claire vaut mieux qu'un refus sec venu du serveur.

## Comment le vérifier soi-même

```bash
# se connecter en adhérent, puis
curl -s -b cookies.txt http://localhost:8080/mon-espace/commercant
# -> « Bonjour, <raison sociale> », l'adhésion, les compteurs, les collectes

# date passée
curl -X POST http://localhost:8080/mon-espace/collectes -b cookies.txt -d "date_prevue=2020-01-01"
# -> « La date souhaitée doit être aujourd'hui ou plus tard. »

# date valide
curl -X POST http://localhost:8080/mon-espace/collectes -b cookies.txt -d "date_prevue=2026-09-15"
# -> « Demande enregistrée », et la ligne apparaît au statut « Demandée »

# un bénévole ne doit pas entrer ici
curl -s -o /dev/null -w "%{http_code} %{redirect_url}\n" -b cookies-benevole.txt \
  http://localhost:8080/mon-espace/commercant
# -> 302 vers /

# déconnecté
# -> 302 vers /connexion
```

Vérifié le 2026-08-07, dans les quatre langues.

## Une limite connue

Il n'existe **aucune route `PUT /commercants/{id}`**. Le rattachement d'une boutique à un compte se fait donc uniquement à la création (`POST /commercants/`, réservé au personnel). Une boutique créée sans compte ne peut plus être reliée ensuite autrement qu'en base.

C'est ce que l'écran « fiche commerçant » de la vague 4 devra combler.

## Fichiers liés

- [../../views/front/espace_commercant.php.md](../../views/front/espace_commercant.php.md) — la vue
- [EspaceBenevoleController.php.md](EspaceBenevoleController.php.md) — l'autre espace client
- [../../middleware/Auth.php.md](../../middleware/Auth.php.md) — `exigerAdherent()` et `urlEspace()`
- [../../../../api-go/app/monEspace.go.md](../../../../api-go/app/monEspace.go.md) — les routes `/mon-espace` côté API
