# Le module campagnes — contrôleur et vues

> ⏱️ **Lecture : ~9 min** · 850 mots

> Couvre `app/controllers/back/CampagnesController.php`, `app/views/back/campagnes.php` et `campagne_detail.php`.

## Ce que le sujet demande

Communiquer avec les partenaires, et notamment **relancer les anciens adhérents**. Une campagne, c'est un email plus des **critères** qui décident qui le reçoit.

## La prévisualisation est la pièce maîtresse

Un envoi d'emails est **irréversible**. On ne peut pas rappeler un message parti chez cinquante commerçants.

L'écran est donc construit autour d'une seule idée : **voir la liste exacte des destinataires avant de déclencher**. C'est la seule protection possible contre un critère mal compris.

Concrètement, cela dicte trois choix.

### 1. Créer n'envoie rien

```php
Flight::route('POST /back/campagnes', [$campagnes, 'creer']);       // rédige
Flight::route('POST /back/campagnes/@id', [$campagnes, 'declencher']); // envoie
```

Deux routes, deux actions. On rédige, on ouvre la fiche, on lit la liste, et **seulement ensuite** on envoie.

Après une création, on est redirigé vers la fiche — pas vers la liste :

```php
Auth::rediriger('/back/campagnes/' . (int) ($reponse['corps']['id'] ?? 0));
```

La première chose à faire après avoir créé une campagne est de vérifier qui elle vise. L'écran y mène tout seul.

### 2. Le bouton d'envoi est tout en bas

Après la liste complète des destinataires. Il faut avoir fait défiler la liste pour l'atteindre.

Ce n'est pas une contrainte gratuite : c'est ce qui rend le geste délibéré.

### 3. Une confirmation que seul le formulaire produit

```php
if (($_POST['confirmation'] ?? '') !== 'oui') {
    $_SESSION['message_erreur'] = Langue::t('campagnes.confirmation_requise');
    Auth::rediriger('/back/campagnes/' . $id);
    return;
}
```

La case à cocher n'est pas qu'un ralentisseur visuel : **un POST forgé sans elle ne déclenche rien**. La vérification est côté serveur, pas seulement dans le HTML.

## Les critères facultatifs, et le piège de la chaîne vide

```php
foreach (['critere_ville', 'critere_pays'] as $champ) {
    $valeur = trim($_POST[$champ] ?? '');
    if ($valeur !== '') { $campagne[$champ] = $valeur; }
}
```

Tous les critères sont facultatifs — sans aucun, la campagne s'adresse à tous les commerçants (`NULL` = pas de filtre côté SQL).

Mais un formulaire envoie **toujours** ses champs. Transmettre `critere_ville: ""` ne voudrait pas dire « toutes les villes » : la requête chercherait les commerçants dont la ville **est** la chaîne vide, et n'en trouverait aucun.

**Un critère vide et un critère absent sont deux choses différentes.** C'est exactement le même piège que les champs facultatifs du scan de collecte, mais ici la conséquence est plus sournoise : la campagne serait créée, sans erreur, et n'aurait simplement aucun destinataire.

## Les villes proposées sont celles qui existent

```php
foreach ($this->extraire($this->api->get('/commercants/', Auth::jeton())) as $c) {
    $ville = $c['ville'] ?? '';
    if ($ville !== '' && !in_array($ville, $villes, true)) { $villes[] = $ville; }
}
```

Un champ texte libre permettrait de taper « Marseile ». Le menu ne propose que des villes où un partenaire est réellement installé — une campagne sans destinataire devient impossible à créer par erreur de frappe.

Même motif que le filtre par ville de la liste des commerçants.

## Le chiffre affiché est celui des envois réels

```php
$nombre = (int) ($reponse['corps']['nombre_envoyes'] ?? 0);
$_SESSION['message_succes'] = sprintf(Langue::t('campagnes.envoyee'), $nombre);
```

L'API renvoie `{"nombre_envoyes": N}` : le nombre d'emails **réellement partis**, pas le nombre de destinataires visés. Les deux diffèrent dès qu'une adresse manque ou qu'un envoi échoue — l'API fait `continue` dans les deux cas.

Afficher « c'est fait » serait vague et, aujourd'hui, faux : le SMTP n'étant pas configuré, on obtient **0 envoi sur 12 destinataires**. Le chiffre le dit.

C'est la même honnêteté que le message d'envoi des plannings.

## Les destinataires sans adresse sont comptés à part

```php
foreach ($destinataires as $d) {
    if (!empty($d['email'])) { $avecEmail++; }
}
```

L'API ignore silencieusement les destinataires sans email. La fiche affiche donc **deux chiffres** : combien recevront vraiment, et combien seront ignorés.

Sans cette distinction, on croirait avoir touché toute sa cible. C'est aussi ce qui donne une raison de compléter les fiches commerçants — le lien est fait explicitement dans le formulaire de création d'un partenaire.

## Le résumé des critères en une phrase

Dans la liste, la colonne « Cible » ne montre pas des valeurs techniques :

```php
if (!empty($c['critere_statut_adhesion'])) {
    $criteres[] = Langue::t('adhesions.statut_' . $c['critere_statut_adhesion']);
}
```

`expiree` devient « Expirée », et l'absence de critère devient « Tous les commerçants » plutôt qu'une cellule vide. Une campagne se relit alors sans ouvrir sa fiche.

## Comment le vérifier soi-même

```bash
# créer une campagne ciblée
curl -X POST http://localhost:8080/back/campagnes -b cookies.txt \
  --data-urlencode "nom=Relance anciens" \
  --data-urlencode "sujet_email=Vous nous manquez" \
  --data-urlencode "corps_email=Bonjour {{raison_sociale}}, ..." \
  --data-urlencode "critere_statut_adhesion=expiree"
# -> redirection vers la fiche, avec la liste des destinataires

# déclencher sans cocher la confirmation
curl -X POST http://localhost:8080/back/campagnes/1 -b cookies.txt
# -> « Cochez la case de confirmation avant d'envoyer. » ; rien n'est envoyé

# déclencher avec confirmation
curl -X POST http://localhost:8080/back/campagnes/1 -b cookies.txt \
  --data-urlencode "confirmation=oui"
# -> « Campagne déclenchée : N email(s) réellement envoyé(s). »

# critères sans résultat
# -> « Aucun commerçant ne correspond à ces critères. » et AUCUN bouton d'envoi
```

Vérifié le 2026-08-08, dans les quatre langues.

## Fichiers liés

- [../../views/back/campagnes.php.md](../../views/back/campagnes.php.md) et [../../views/back/campagne_detail.php.md](../../views/back/campagne_detail.php.md)
- [AdhesionsController.php.md](AdhesionsController.php.md) — l'autre écran qui envoie des emails
- [CommercantsController.php.md](CommercantsController.php.md) — d'où viennent les adresses
- [../../../../api-go/app/campagnes.go.md](../../../../api-go/app/campagnes.go.md) — la résolution des destinataires
