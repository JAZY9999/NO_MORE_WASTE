# Le catalogue public des services — contrôleur et vues

> ⏱️ **Lecture : ~10 min** · 950 mots

> Couvre `app/controllers/front/ServicesPublicsController.php`, `app/views/front/services.php` et `service_detail.php`.

## Ce que le sujet demande

> des services *« accessibles aux adhérents »* : cours de cuisine anti-gaspillage, conseils, partage de véhicule, petites réparations

## Consulter est public, s'inscrire ne l'est pas

C'est la distinction structurante de l'écran.

| Action | Qui |
|---|---|
| Voir le catalogue | tout le monde, connecté ou non |
| Voir les créneaux d'un service | tout le monde |
| S'inscrire à un créneau | adhérents seulement |

```php
$services = $this->extraire($this->api->get($chemin));   // pas de jeton
```

Les routes correspondantes de l'API sont publiques : c'est la vitrine de l'association. Exiger un compte pour **regarder** ce qui est proposé ferait fuir exactement les gens qu'on cherche à attirer.

Ne pas passer de jeton n'est pas un oubli : ça dit au lecteur que la page est ouverte.

## Le bouton a trois états, selon qui regarde

```php
<?php if ($complet): ?>              … Complet
<?php elseif ($peutSInscrire): ?>    … le bouton
<?php elseif (!$estConnecte): ?>     … « Se connecter »
<?php else: ?>                       … « Réservé aux adhérents »
<?php endif; ?>
```

Le dernier cas compte autant que les autres : un **bénévole** connecté qui verrait « Se connecter » serait dérouté — il *est* connecté. Lui dire que l'inscription est réservée aux adhérents est la seule réponse qui ne l'envoie pas tourner en rond.

## L'inscription n'envoie aucun identifiant

```php
$this->api->post('/creneaux/' . $creneauId . '/inscriptions', [], Auth::jeton());
```

Le corps est **vide**. L'API déduit du jeton qui s'inscrit.

Ce n'est pas de la simple élégance. Avant que cette règle soit ajoutée côté API, un adhérent envoyant `{"commercant_id": 4}` **inscrivait la boutique de quelqu'un d'autre** — la requête répondait 201. C'est cet écran qui a fait apparaître le problème.

### Le piège du tableau vide en PHP

Envoyer `[]` a d'abord échoué. En PHP, un tableau vide est à la fois une liste et un dictionnaire, et `json_encode([])` produit `"[]"` — pas `"{}"`.

L'API attend toujours un **objet** JSON dans un corps de requête. Elle recevait donc une valeur du mauvais type et répondait « JSON invalide », pour une requête qui n'avait simplement rien à transmettre.

Corrigé une fois pour toutes dans `ApiClient` :

```php
json_encode($donnees === [] ? new \stdClass() : $donnees)
```

Un tableau **non vide** n'a jamais eu ce problème : dès qu'il a des clés, `json_encode` produit un objet.

## Deux filtrages que l'API ne fait pas

```php
if (($c['statut'] ?? '') === 'annule')                  { continue; }
if (substr($c['date_creneau'], 0, 10) < $aujourdhui)    { continue; }
```

L'API renvoie **tous** les créneaux d'un service, y compris ceux de mars dernier. C'est correct pour le back-office, qui consulte l'historique. Ça ne l'est pas pour la vitrine : afficher « Cours du 3 mars » en août ferait douter que le site soit à jour.

Le filtrage est donc côté front, parce que c'est **cet écran-là** qui a cette exigence, pas l'API.

## Le filtre n'apparaît que s'il sert

```php
<?php if (count($typesPresents) > 1): ?>
```

Et les catégories proposées sont celles **réellement présentes** :

```php
foreach ($tousLesServices as $s) {
    $typesPresents[$s['type']] = true;
}
```

Une pastille « Gardiennage » qui ne renvoie jamais rien donne l'impression d'un site cassé. Proposer de filtrer une liste homogène n'aide personne non plus.

Noter le second appel quand un filtre est actif : la liste filtrée ne contient qu'un type, impossible d'en déduire les autres. Même compromis que les onglets du back-office.

## Une liste, pas une grille de cartes

Les descriptions de services ont des longueurs très différentes. Une grille de cartes obligerait soit à tronquer les textes, soit à laisser des trous entre les cartes courtes et les longues.

Une liste supporte n'importe quelle longueur sans rien casser. C'est aussi ce qui se lit le mieux sur téléphone.

## Comment le vérifier soi-même

```bash
# sans aucune connexion
curl -s http://localhost:8080/services
# -> le catalogue s'affiche

curl -s http://localhost:8080/services/1
# -> les créneaux, avec « Se connecter » à la place du bouton

# en adhérent
curl -X POST http://localhost:8080/services/1/inscription -b cookies.txt -d "creneau_id=1"
# -> « Inscription enregistrée. À bientôt ! »

# vérifier QUI a été inscrit
curl -s http://localhost:8080/api/creneaux/1/inscriptions -H "Authorization: $TOKEN"
# -> commercant_id = celui du compte connecté, jamais un autre

# en bénévole : pas de bouton, et le POST forcé est refusé
curl -s -o /dev/null -w "%{http_code} %{redirect_url}\n" -b cookies-benevole.txt \
  -X POST http://localhost:8080/services/1/inscription -d "creneau_id=1"
# -> 302 vers /

# un service qui n'existe pas
curl -s -o /dev/null -w "%{redirect_url}\n" http://localhost:8080/services/9999
# -> retour à /services avec un message, pas une page vide
```

Vérifié le 2026-08-07, dans les quatre langues.

## Fichiers liés

- [../../views/front/services.php.md](../../views/front/services.php.md) et [../../views/front/service_detail.php.md](../../views/front/service_detail.php.md)
- [../Back/ServicesController.php.md](../Back/ServicesController.php.md) — le même domaine, côté gestion
- [../../services/ApiClient.php.md](../../Services/ApiClient.php.md) — le cas du tableau vide
- [../../../../api-go/app/services.go.md](../../../../api-go/app/services.go.md) — la règle « on ne s'inscrit que soi-même »
