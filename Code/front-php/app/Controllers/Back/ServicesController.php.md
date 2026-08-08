# Le module services et créneaux — contrôleur et vue

> ⏱️ **Lecture : ~11 min** · 980 mots

> Couvre `app/controllers/back/ServicesController.php` et `app/views/back/services.php`.
>
> **Dernier module de la vague 2.** C'est aussi celui qui a révélé le plus de défauts en le testant — trois, tous corrigés, tous documentés plus bas.

## Ce que le sujet demande

> des services *« accessibles aux adhérents »* : cours de cuisine anti-gaspillage, conseils, partage de véhicule, petites réparations…
>
> l'*« affectation à un service donné »* des bénévoles

## L'écran est organisé autour du CRÉNEAU, pas du service

C'est le choix de départ, et il explique toute la mise en page.

Un service seul — « Cours de cuisine » — ne se passe nulle part, n'a besoin de personne et n'a pas de date. C'est une **catégorie**. Le créneau, lui, a un jour, une heure, un lieu, un bénévole affecté et des inscrits : c'est **la chose qu'on administre**.

Le tableau liste donc les créneaux à plat, tous services confondus, triés par date. Le nom du service n'est qu'une colonne.

## Les créneaux, service par service

L'API n'expose pas de route « tous les créneaux » : elle les donne service par service. Le contrôleur boucle donc :

```php
foreach ($services as $service) {
    $liste = $this->extraire($this->api->get('/services/' . $service['id'] . '/creneaux', …));
    …
}
```

**C'est assumé à cette échelle.** Une association a une poignée de services, pas des centaines. Si la liste grandissait, la bonne réponse serait une route `GET /creneaux/` côté API — pas de multiplier les appels ici.

Le nombre d'inscrits demande un appel de plus **par créneau**. Même raisonnement, même limite.

C'est le contraire du choix fait pour les collectes, où l'on construit un index en une requête. La différence : là-bas, une seule requête donnait toute l'information ; ici, il n'existe aucune route qui le permette.

## Le tri se fait dans le contrôleur

```php
usort($creneaux, function ($a, $b) {
    return strcmp($a['date_creneau'] . $a['heure_debut'],
                  $b['date_creneau'] . $b['heure_debut']);
});
```

Sans lui, les créneaux sortiraient groupés par service — l'ordre de création. Un planning se lit **dans l'ordre du temps**.

La concaténation date + heure fonctionne parce que les deux sont en format ISO (`2026-08-10` puis `14:00`) : dans ce format, l'ordre alphabétique **est** l'ordre chronologique. Ce ne serait pas vrai avec `10/08/2026`.

## L'affectation : deux conditions, appliquées par l'API

Le sujet demande qu'un bénévole soit affecté « à un service donné ». L'API impose deux conditions :

1. être au statut **`valide`** (tous ses documents vérifiés) ;
2. posséder la **compétence requise** par le service, s'il en exige une.

Le front n'en duplique **aucune** :

- pour le statut, le menu ne charge que `/benevoles/?statut=valide` — les autres ne sont pas proposés ;
- pour la compétence, on laisse l'API refuser. Son message dit exactement ce qui manque.

### Pourquoi ne pas vérifier la compétence côté front aussi

Il faudrait charger les compétences de chaque bénévole — un appel de plus par bénévole — et surtout **maintenir la même règle à deux endroits**. Le jour où elle change côté API, le front continuerait d'appliquer l'ancienne, et proposerait des choix refusés (ou en cacherait de valides).

À la place, l'écran affiche `requiert : cuisinier` sous le bouton. Le refus, s'il arrive, est compréhensible.

## Le CSV et l'envoi : deux verbes, deux natures

```php
Flight::route('GET  /back/plannings', [$services, 'planning']);
Flight::route('POST /back/plannings', [$services, 'envoyerPlannings']);
```

- **GET** télécharge un fichier. C'est une lecture : la rejouer ne coûte rien.
- **POST** déclenche des envois d'e-mails. Ce n'est pas une lecture.

Si l'envoi était un GET, un simple rafraîchissement de page **renverrait tous les e-mails**. C'est la raison d'être de la distinction entre les deux verbes HTTP.

### Le CSV passe par le front, comme le PDF des tournées

Un lien vers `/api/plannings/` répondrait 401 : le navigateur n'emporte pas le jeton, qui vit dans la session PHP. Le front sert donc de relais.

Une différence avec le PDF : `attachment` au lieu de `inline`. Un PDF se relit à l'écran avant d'être imprimé ; un CSV n'a rien à montrer dans un navigateur — on veut l'ouvrir dans Excel.

```php
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
    $date = date('Y-m-d');
}
```

La date part **dans l'URL appelée et dans le nom du fichier téléchargé**. On n'accepte donc qu'une date bien formée. Vérifié : `?date=../../etc/passwd` retombe sur la date du jour.

## Trois défauts trouvés en testant cet écran

### 1. Les heures s'affichaient « 0000- »

`heure_debut` est une colonne `TIME`. Lue dans une chaîne Go, `database/sql` la reçoit comme une date complète : `"0000-01-01T14:00:00Z"`.

Corrigé dans la requête SQL par `to_char(heure_debut, 'HH24:MI')` — même correctif que pour les étapes de tournée.

Le tri, lui, porte toujours sur la colonne `TIME` et non sur le texte : trier des heures comme du texte marcherait par chance en 24 h, mais pas avec un format sur 12 h.

Le CSV, lui, n'était **pas** cassé : `utils.formaterHeure` savait déjà lire les deux formes. Mais il devait le savoir — c'est exactement la rustine que `to_char` rend inutile.

### 2. Le type de service était un champ libre

La colonne `type` a une contrainte `CHECK` : sept valeurs, pas une de plus.

Mon formulaire proposait un `<input type="text">`. Taper « cuisine » au lieu de « cours_cuisine » produisait une **erreur 500** pour ce qui est, du point de vue de l'utilisateur, une faute de frappe.

Corrigé en menu déroulant, **plus** une revalidation côté serveur : le menu ne propose que des valeurs justes, mais rien n'empêche d'en forger une autre.

### 3. Une valeur hors liste répondait 500 au lieu de 400

Conséquence du point 2, et défaut réel de l'API : une violation de contrainte `CHECK` est une faute du **client**, pas du serveur.

Le code PostgreSQL `23514` a donc rejoint `23503` et `23505` dans `utils.ErreurServeur`. L'API répond maintenant `400` avec un message utilisable.

## Le message d'envoi dit ce qui se passe vraiment

L'API lance l'envoi et répond aussitôt : elle **n'attend pas** le résultat SMTP. Écrire « Plannings envoyés » affirmerait une réussite qu'on ne connaît pas encore — et c'est faux tant que les clés Brevo ne sont pas renseignées dans le `.env`.

Le message dit donc : *« Envoi des plannings lancé. Le détail de chaque envoi est dans les journaux du serveur. »*

## Comment le vérifier soi-même

```bash
# type forgé, en contournant le menu
curl -X POST http://localhost:8080/back/services -b cookies.txt \
  --data-urlencode "action=creer_service" \
  --data-urlencode "nom=Pirate" --data-urlencode "type=nimporte_quoi"
# -> « Ce type de service n'existe pas. » ; l'API n'est pas appelée

# la même chose directement sur l'API
curl -X POST http://localhost:8080/api/services/ -H "Authorization: $TOKEN" \
  -d '{"nom":"Test","type":"invente"}'
# -> HTTP 400 (et non 500) : « une des valeurs envoyées n'est pas autorisée »

# heures inversées
# -> « L'heure de fin doit être après l'heure de début. »

# le CSV
curl -s -b cookies.txt "http://localhost:8080/back/plannings?date=2026-08-11"
# -> Date;Heure debut;Heure fin;Service;Lieu
#    11/08/2026;09:00;10:00;Conseil anti-gaspi;Visio

# date forgée
curl -sD - -o /dev/null -b cookies.txt "http://localhost:8080/back/plannings?date=../../etc/passwd"
# -> filename="planning-2026-08-07.csv" (retour à la date du jour)

# déconnecté
# -> 302 vers /connexion
```

Vérifié le 2026-08-07, cas d'erreur compris, ainsi que les quatre langues — y compris les sept libellés du menu des types.

## Fichiers liés

- [../../views/back/services.php.md](../../views/back/services.php.md) — la vue
- [BenevolesController.php.md](BenevolesController.php.md) — la validation qui conditionne l'affectation
- [TourneesController.php.md](TourneesController.php.md) — le même relais de fichier, pour le PDF
- [../../../../api-go/utils/erreurs.go.md](../../../../api-go/utils/erreurs.go.md) — les codes PostgreSQL traduits en codes HTTP
