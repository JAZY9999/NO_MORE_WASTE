# `app/services/ApiClient.php` — le pont entre le front et l'API

> ⏱️ **Lecture : ~13 min** · 1 050 mots

> **À lire après** [index.php.md](../../public/index.php.md).
> C'est **le seul fichier du front qui parle à l'API Go**. Tout passe par lui.

## Pourquoi une classe dédiée

Chaque page du back-office a besoin de données venant de l'API. Sans classe dédiée, on écrirait le même bloc de 20 lignes dans chaque contrôleur : construire l'adresse, joindre le jeton, envoyer, décoder le JSON, gérer les pannes. Quarante fois le même code, c'est quarante occasions de se tromper — et le jour où le format change, quarante fichiers à modifier.

Ici, tout est écrit **une fois**. Un contrôleur se contente de :

```php
$reponse = $this->api->get('/commercants/', Auth::jeton());
```

## Le piège de l'adresse : `api-go`, pas `localhost`

```php
'api_base_url' => 'http://api-go:8080'
```

C'est le point qui trompe le plus souvent. Dans ton navigateur, l'API est à `http://localhost:8080/api`. Mais ce code **ne s'exécute pas dans ton navigateur** : il s'exécute à l'intérieur du conteneur `front-php`.

Et pour ce conteneur, `localhost` désigne… **lui-même**. Il chercherait l'API chez lui, ne la trouverait pas, et échouerait.

Docker donne à chaque conteneur un nom utilisable comme adresse réseau, identique au nom du service dans `docker-compose.yml`. L'API est donc joignable à `http://api-go:8080` depuis n'importe quel autre conteneur.

Deux chemins différents, à ne pas confondre :

```
Navigateur   -> http://localhost:8080/api/...  -> nginx -> api-go
front-php    -> http://api-go:8080/...                  -> api-go   (direct)
```

Le front ne passe pas par nginx : il est déjà à l'intérieur du réseau Docker.

## Comment marche `requete()`

C'est le cœur du fichier. Toutes les autres méthodes l'appellent.

### cURL en quatre temps

```php
$ch = curl_init($url);                                   // 1. préparer
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);          // 2. régler
$brut = curl_exec($ch);                                  // 3. envoyer
curl_close($ch);                                         // 4. fermer
```

cURL est l'outil HTTP **intégré à PHP** — rien à installer.

⚠️ **`CURLOPT_RETURNTRANSFER` est l'option à ne jamais oublier.** Sans elle, cURL **affiche** la réponse directement dans la page au lieu de nous la donner. On se retrouve avec du JSON brut au milieu du HTML sans comprendre d'où il sort. Avec elle, `curl_exec` **retourne** la réponse.

### Le jeton part sans `Bearer`

```php
$entetes[] = 'Authorization: ' . $jeton;
```

Dans la plupart des tutoriels on écrit `Authorization: Bearer eyJhbGc...`. **Ici, non.** Le cours ESGI enseigne la version simplifiée : le jeton part brut, et `utils/jwt.go` côté API lit l'en-tête tel quel.

Si tu ajoutes `Bearer `, toutes les requêtes authentifiées échouent en 401. C'est un choix assumé de cohérence avec le cours, pas un oubli.

### Le code 0 : quand il n'y a même pas eu de dialogue

```php
if ($brut === false) {
    return ['code' => 0, 'corps' => null, 'brut' => 'API injoignable : ' . $erreurReseau];
}
```

`curl_exec` renvoie `false` si l'API est **injoignable** — conteneur arrêté, mauvaise adresse, réseau coupé. Ce n'est pas une réponse HTTP : il n'y a eu aucun dialogue, donc **aucun code de statut**.

On invente donc le code `0`, impossible à confondre avec un vrai code HTTP. Sans ce cas, on afficherait « erreur 200 » ou un message vide, ce qui est incompréhensible à déboguer.

### Pourquoi retourner le code plutôt que lever une exception

```php
return ['code' => 404, 'corps' => ..., 'brut' => ...];
```

Un 404 ou un 401 ne sont **pas des accidents** ici : ce sont des réponses normales que le contrôleur doit savoir traiter (afficher « introuvable », rediriger vers la connexion). Les transformer en exceptions obligerait à écrire un `try/catch` partout pour du fonctionnement normal.

On garde toujours **les trois** informations :

| Clé | Contenu | Utilité |
|---|---|---|
| `code` | le statut HTTP (200, 404, 0…) | décider quoi faire |
| `corps` | le JSON décodé en tableau PHP | afficher les données |
| `brut` | le texte tel quel | les messages d'erreur |

Pourquoi garder `brut` alors qu'on a `corps` ? Parce que l'API renvoie du **JSON** quand tout va bien, mais du **texte simple** pour ses erreurs (`Commercant introuvable`). Dans ce cas `json_decode` renvoie `null`, et sans `brut` on perdrait le message.

## 🔄 Le piège du tableau vide, trouvé en vague 3

```php
$corpsJson = json_encode($donnees === [] ? new \stdClass() : $donnees);
```

Découvert en portant l'inscription à un créneau de service, une requête qui n'a **rien** à envoyer : tout est déduit du jeton côté API. Poster un tableau vide semblait donc naturel — refusé par l'API, avec "JSON invalide".

### Pourquoi PHP trahit ici

En PHP, un tableau vide `[]` est à la fois une liste et un dictionnaire : rien ne permet de savoir laquelle des deux formes on voulait. `json_encode([])` tranche pour la liste et produit `"[]"`.

L'API, elle, attend **toujours** un objet JSON dans un corps de requête — jamais une liste. Elle recevait donc une valeur du mauvais type et répondait "JSON invalide" pour une requête qui n'avait simplement rien à transmettre.

### Pourquoi seul le cas vide pose problème

Dès qu'un tableau PHP a des **clés** (`['nom' => 'Dupont']`), `json_encode` sait qu'il s'agit d'un dictionnaire et produit `{"nom":"Dupont"}` sans ambiguïté. Le piège ne concerne que le tableau **vide**, précisément parce qu'il ne porte aucune information sur la forme voulue.

`new \stdClass()` — un objet PHP vide — s'encode toujours en `{}`, jamais en `[]`. Le cast ne s'applique donc que dans ce seul cas, sans toucher au comportement normal.

## Les deux fonctions d'aide

```php
ApiClient::estSucces($reponse)      // vrai si 200, 201, 204...
ApiClient::messageErreur($reponse)  // un message affichable
```

`estSucces` teste l'intervalle `200 <= code < 300` plutôt que d'énumérer les codes : toute la famille des 2xx signifie « succès », et l'API en utilise trois (200 lecture, 201 création, 204 modification sans contenu).

`messageErreur` gère les trois formes possibles : le code 0 (service indisponible), un tableau JSON de messages (l'inscription renvoie `["Email invalide"]`), ou du texte simple.

Ces méthodes sont `static` : elles ne dépendent d'aucune donnée de l'objet, juste de ce qu'on leur passe. Pas besoin d'une instance pour les appeler.

## Pourquoi cURL et pas Guzzle

Guzzle (la librairie HTTP la plus utilisée en PHP) était installée au départ, puis **retirée**.

La raison est la même que pour le Go en bibliothèque standard : ce fichier fait 130 lignes que tu peux expliquer **entièrement**, du premier `curl_init` au dernier `return`. Avec Guzzle, la réponse à « comment ta requête part-elle ? » serait « la librairie s'en occupe » — ce qui ne se défend pas bien à l'oral.

Bénéfice secondaire : une dépendance de moins dans `vendor/`, donc une image Docker plus légère.

## Comment le vérifier soi-même

Si une page du back-office affiche « Le service est momentanément indisponible », c'est le code 0 : l'API ne répond pas.

```bash
docker compose ps                    # api-go est-il démarré ?
docker compose logs api-go --tail 20 # que dit-il ?
```

## Fichiers liés

- [../config/config.php.md](../config/config.php.md) — d'où vient `api_base_url`
- [../middleware/Auth.php.md](../middleware/Auth.php.md) — d'où vient le jeton joint aux requêtes
- [../controllers/back/CommercantsController.php.md](../controllers/back/CommercantsController.php.md) — un exemple d'utilisation réelle
- [../../../api-go/utils/jwt.go.md](../../../api-go/utils/jwt.go.md) — comment l'API lit ce jeton
