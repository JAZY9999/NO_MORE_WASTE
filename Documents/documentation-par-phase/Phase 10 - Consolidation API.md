# Phase 10 — Consolidation de l'API Go

> ⏱️ **Lecture : ~15 min** · 1302 mots, 30 lignes de code

> **Légende** : 🟥 = écrit noir sur blanc dans le sujet · 🟧 = pas nommé littéralement mais indispensable pour un point du sujet · 🟦 = bonus, absent du sujet (assumé, pas du hors-sujet).
>
> Phase de **relecture**, pas de nouvelles fonctionnalités : aucune route n'a été ajoutée. On repasse sur les 63 routes déjà écrites pour corriger ce qui a été fait vite pendant les phases métier.

## Le besoin (pourquoi cette phase existe)

Les phases 1 à 7 ont été écrites module par module, chacune testée isolément. Ça produit inévitablement des incohérences entre modules : une convention prise en Phase 1 et oubliée en Phase 5, un cas d'erreur traité correctement à un endroit et bâclé ailleurs.

Cette phase répond à deux points :
- 🟥 **« API en Go »** — respecté par construction depuis le début.
- 🟧 **Gestion d'erreurs HTTP propre, sans 500 silencieux** — c'est le vrai travail de cette phase.
- 🟦 **Documentation des endpoints** — la collection Postman.

## Ce qui a été corrigé (5 points)

### 1. 🟧 Les 101 erreurs 500 qui ne disaient rien

**Le problème.** Chaque handler faisait ceci :

```go
if err != nil {
    http.Error(w, "Erreur de recuperation du produit", http.StatusInternalServerError)
    return
}
```

La variable `err` — celle qui contient la vraie explication — n'était utilisée nulle part. Elle était jetée. Si l'API plantait en démonstration, **aucun moyen de savoir pourquoi**.

**La correction.** Une fonction `utils.ErreurServeur(w, r, message, err)` qui écrit la cause exacte dans les logs du serveur *et* renvoie le message générique au client. Appliquée aux 101 endroits.

```
[ERREUR 500] POST /produits/ -> Erreur de creation du produit
| cause : CreateProduit : pq: violates foreign key constraint "produits_emplacement_id_fkey"
```

On ne renvoie jamais l'erreur brute au client : elle divulguerait les noms des tables et des colonnes.

### 2. 🟧 Les erreurs 500 qui auraient dû être des 400

**Le problème.** Un client envoie `{"emplacement_id": 99999}` alors que cet emplacement n'existe pas. PostgreSQL refuse, l'API répondait **500**.

Or 500 veut dire « le serveur a un bug ». Ici le serveur va très bien : c'est la **donnée envoyée** qui est fausse. Le bon code est **400**, avec un message qui dit quoi corriger.

**La correction.** `ErreurServeur` regarde si l'erreur est une erreur PostgreSQL de code `23503` (violation de clé étrangère) et répond alors 400 :

```
Erreur de creation du produit : un des elements references n'existe pas
(verifiez les identifiants envoyes)
```

Comme les 101 handlers passent tous par cette fonction, **une seule modification les corrige tous**.

**Effet de bord nécessaire** : ça n'a pu fonctionner qu'après avoir converti **97 lignes** de `db/` du format `fmt.Errorf("... : %v", err.Error())` vers `fmt.Errorf("... : %w", err)`. Le verbe `%v` détruit l'erreur d'origine (il n'en garde que le texte) ; `%w` l'emballe et permet de la retrouver. C'est le point technique le plus subtil de la phase.

### 3. 🟧 Le `panic` du health check

`GET /` faisait `panic(err)` si la base ne répondait pas. Un `panic` dans un serveur HTTP donne au client une **connexion coupée**, sans message ni code — sur la route dont le seul rôle est justement de dire si tout va bien.

Remplacé par une réponse **503 (Service Unavailable)**, qui a un sens précis : « je vais bien, mais un service dont je dépends est tombé ». Différent de 500 (« mon code a un bug »), et un outil de supervision traite les deux différemment.

### 4. 🟧 nginx écrasait les messages de l'API

**Le problème, trouvé en testant.** Un appel d'API en erreur renvoyait… une page HTML complète. Le message calculé par le code Go était jeté et remplacé par la page d'erreur du site.

La cause : `proxy_intercept_errors on;` sur `location /api/`. C'est le bon réglage pour un **site** (un humain veut une jolie page) et le mauvais pour une **API** (un programme veut un message exploitable).

**La correction.** `off` pour `/api/`, `on` conservé pour le site. L'exigence du sujet (« pages d'erreur personnalisées ») reste remplie — elle porte sur le site, qui est ce que le sujet désigne.

> ⚠️ **Piège rencontré** : la conf nginx est copiée dans l'image (`COPY` dans le Dockerfile), pas montée en volume. `docker compose restart nginx` ne change donc **rien** ; il faut `docker compose up -d --build nginx`.

### 5. 🟧 La seule incohérence de nommage du projet

Toute l'API utilise des noms de champs français (`raison_sociale`, `date_debut`, `code_barre`, `montant_cotisation`…) — **sauf** la connexion, qui attendait `password`.

Renommé en `mot_de_passe` (et la struct `Credentials` en `Identifiants`).

**Pourquoi maintenant et pas plus tard** : changer un nom de champ JSON casse le contrat de l'API. Tant qu'aucun client ne la consomme, ça ne coûte rien ; une fois le front PHP écrit, il aurait fallu modifier les deux ensemble. C'était le dernier moment gratuit.

## 🟦 Le livrable : la collection Postman (66 requêtes)

Fichier : `Code/api-go/NO-MORE-WASTE.postman_collection.json`

Les **63 routes** de l'API, rangées en 14 dossiers, avec pour chacune un corps d'exemple réaliste et le rôle requis. Deux variables (`base_url`, `token`) et un script qui capture le JWT automatiquement à la connexion.

Point important : les dossiers suivent **l'ordre des dépendances réelles**, pas l'ordre des phases. On peut donc la rejouer du haut vers le bas sur une base vide et tout s'enchaîne (les bénévoles avant les collectes, les compétences avant les services…).

## 🟦 Le script de vérification

Fichier : `Code/tests/tester-tous-les-endpoints.py`

```bash
python tests/tester-tous-les-endpoints.py
```

```
TOTAL : 66 requetes | 66 OK | 0 en echec
```

Il lit la collection Postman et rejoue les 66 requêtes contre l'API réelle. Son intérêt : une documentation qui ment est pire que pas de documentation. Ici elle est **vérifiable**.

**Ce script a trouvé de vrais défauts** — c'est l'argument à donner si on t'interroge dessus. Au premier lancement : 43 OK / 64. Il a mis au jour les deux erreurs 500 du point 2, un enchaînement métier impossible dans la doc (valider un bénévole avant ses documents), et trois exemples faux.

## Ce que l'audit a confirmé comme déjà correct

Tout n'était pas à refaire — l'audit a aussi validé :

- **Aucune fuite d'information** : zéro occurrence de `http.Error(w, err.Error(), ...)`. Les messages SQL n'ont jamais été renvoyés au client.
- **404 correctement gérés** : les 18 repositories traitent `sql.ErrNoRows` en renvoyant `nil`, et les handlers répondent 404. Une ressource inexistante n'a jamais provoqué de 500.
- **Doublons correctement gérés** : un code-barre déjà utilisé répond 409 avec un message métier précis.
- **Codes d'authentification cohérents** : 401 sans token comme avec un token invalide, 403 en cas de rôle insuffisant.

## À savoir réexpliquer sans IA

1. **La différence entre 400, 401, 403, 404, 409, 500 et 503** — et pourquoi une donnée invalide envoyée par le client est du 400, jamais du 500.
2. **`%w` contre `%v`** dans `fmt.Errorf` : l'un emballe l'erreur, l'autre la détruit. Même affichage, comportement différent quand on veut l'inspecter.
3. **Pourquoi on ne renvoie jamais `err.Error()` au client** : divulgation des noms de tables, colonnes et contraintes.
4. **Pourquoi `proxy_intercept_errors` est `off` pour l'API et `on` pour le site** : les deux n'ont pas le même client.

## Comment le vérifier soi-même

```bash
# 1. Un ID inexistant -> 400 (et non 500), avec un message actionnable
curl -X POST http://localhost:8080/api/produits/ \
  -H "Authorization: $TOKEN" -H "Content-Type: application/json" \
  -d '{"code_barre":"TEST","libelle":"Test","emplacement_id":99999}'

# 2. La cause exacte est dans les logs du serveur
docker compose logs api-go | grep ERREUR

# 3. L'API garde ses messages, le site garde ses pages HTML
curl http://localhost:8080/api/commercants/999 -H "Authorization: $TOKEN"
curl http://localhost:8080/page-qui-nexiste-pas

# 4. Les 66 requêtes documentées fonctionnent
python tests/tester-tous-les-endpoints.py
```

## Ce qu'il reste à faire dans cette phase

- **Swagger** était marqué comme bonus optionnel dans la todo. Non fait : la collection Postman remplit déjà le rôle de documentation des endpoints, et Swagger demanderait soit une librairie externe (interdite par le cours), soit l'écriture à la main d'un fichier OpenAPI de plusieurs centaines de lignes qui doublonnerait la collection. **À assumer comme un choix, pas comme un oubli.**
- Un point hérité de la **Phase 1.1** reste ouvert : il n'existe pas d'endpoint pour créer un compte staff/admin. On passe par une inscription normale puis une requête SQL. À traiter avec le back-office (Phase 9).

## Pour aller plus loin (fichiers `.md` détaillés)

- [api-go/utils/erreurs.go.md](../../Code/api-go/utils/erreurs.go.md) — **à lire en priorité** : les 500 silencieux, `errors.As`, le piège `%w`/`%v`
- [api-go/NO-MORE-WASTE.postman_collection.json.md](../../Code/api-go/NO-MORE-WASTE.postman_collection.json.md) — comment lire et utiliser la collection
- [tests/tester-tous-les-endpoints.py.md](../../Code/tests/tester-tous-les-endpoints.py.md) — le script de vérification et ce qu'il a trouvé
- [nginx/conf.d/nmw.conf.md](../../Code/nginx/conf.d/nmw.conf.md) — les deux pièges d'interception d'erreurs
