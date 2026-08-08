# `tester-tous-les-endpoints.py` — vérifier les 80 requêtes en une commande

> ⏱️ **Lecture : ~10 min** · 789 mots, 46 lignes de code

> **Phase** : 10 (consolidation), enrichi à chaque vague du portage front.
> **Tag** : 🟦 **AJOUT PERSO** — le sujet ne demande pas de tests automatisés. C'est un bonus, à présenter comme tel.

## Pourquoi ce script existe

Jusqu'ici, tester l'API voulait dire lancer des commandes `curl` à la main, une par une. Avec 78 routes, c'est long, on en oublie, et on ne le refait pas après chaque modification.

Pire : la [collection Postman](../api-go/NO-MORE-WASTE.postman_collection.json) sert de documentation. Une documentation qui ment est plus dangereuse que pas de documentation du tout. Il fallait donc pouvoir **prouver** que chaque exemple documenté fonctionne vraiment.

Ce script fait exactement ça : il lit la collection Postman et **rejoue les 80 requêtes** contre l'API qui tourne.

## Comment l'utiliser

L'API doit tourner (`docker compose up -d`). C'est tout : **le script se prépare lui-même** — il vide les données métier puis crée son compte administrateur.

Tu peux donc le relancer autant de fois que tu veux, il repart propre à chaque fois.

```bash
python tests/tester-tous-les-endpoints.py
```

Sortie :

```
======================================================================
05 - Stocks : produits & code-barre
======================================================================
  [OK    ] 201  Enregistrer un produit (scan code-barre)
  [OK    ] 200  RECHERCHE RAPIDE PAR CODE-BARRE (exigence sujet)
  ...
======================================================================
TOTAL : 80 requetes | 80 OK | 0 en echec
======================================================================
```

> ⚠️ **Le script VIDE les données métier à chaque lancement.** Il est fait pour une **base de développement**, pas pour des données qu'on veut garder. Les tables de référence (`langues`, `competences`) sont conservées.
>
> Pour le lancer sans rien effacer :
> ```bash
> python tests/tester-tous-les-endpoints.py --garder-donnees
> ```

### Si l'API ne tourne pas sur le port 8080

Trouvé en déployant sur un serveur où le port `8080` était déjà pris par un autre outil (code-server) : `NGINX_PORT` avait été changé à `8000` dans `.env`, mais le script continuait d'appeler `localhost:8080` — codé en dur — et tombait donc sur l'**autre programme**, pas sur l'API. Symptôme observé : des erreurs `401` et `JSONDecodeError` incompréhensibles, puisque ce n'était même pas l'API qui répondait.

```bash
NMW_BASE_URL=http://localhost:8000 python tests/tester-tous-les-endpoints.py
```

`NMW_BASE_URL` est facultative : sans elle, le script se comporte exactement comme avant (`http://localhost:8080`).
> Dans ce cas, attends-toi à des échecs 409 « existe déjà » si des données du run précédent sont encore là — c'est normal, pas une régression.

## Comment ça marche, morceau par morceau

### 1. Lire la collection

```python
DOSSIER_ICI = os.path.dirname(os.path.abspath(__file__))
COLLECTION = os.path.join(DOSSIER_ICI, "..", "api-go", "NO-MORE-WASTE.postman_collection.json")
```

Le chemin est calculé **par rapport au fichier lui-même**, pas par rapport au dossier depuis lequel on lance la commande. Le script marche donc quel que soit l'endroit d'où on l'appelle, et sur n'importe quelle machine — il n'y a aucun chemin `C:\Users\...` codé en dur.

### 2. Envoyer une requête

```python
def appeler(methode, url, corps, entetes):
    req = urllib.request.Request(url, data=donnees, method=methode)
    ...
    try:
        with urllib.request.urlopen(req) as rep:
            return rep.status, rep.read()
    except urllib.error.HTTPError as e:
        return e.code, e.read()
```

Le `try/except` est indispensable : en Python, `urlopen` considère un code 4xx ou 5xx comme une **exception**, pas comme une réponse normale. Sans le `except`, le script planterait au premier 404 — alors qu'ici, recevoir un 404 est une information qu'on veut mesurer, pas un crash.

`urllib` fait partie de la bibliothèque standard de Python : **rien à installer**.

### 3. Récupérer le token au vol

```python
if url.endswith("/auth/login/") and code == 200:
    token["valeur"] = json.loads(contenu)["token"]
```

C'est l'équivalent du petit script JavaScript attaché à la requête de connexion dans Postman. Une fois capturé, le token remplace `{{token}}` dans toutes les requêtes suivantes :

```python
valeur = h["value"].replace("{{token}}", token["valeur"])
```

### 4. Juger le résultat

```python
ATTENDU_OK = (200, 201, 204)
```

Trois codes valent « réussite » :

| Code | Nom | Quand |
|---|---|---|
| 200 | OK | lecture réussie |
| 201 | Created | création réussie |
| 204 | No Content | modification réussie, rien à renvoyer |

Tout le reste est signalé, avec le début du message d'erreur pour comprendre tout de suite.

### 5. L'échec qu'on accepte

```python
ECHECS_ATTENDUS = {
    "Relance manuelle (echoue en 502 tant que le SMTP n'est pas configure)",
}
```

Une requête envoie un vrai email. Tant que le fichier `.env` contient les identifiants SMTP par défaut, elle échoue — ce n'est pas un bug du code, c'est une configuration non renseignée. Le code est `502` (et non `500`) : ce n'est pas le serveur qui a un bug, c'est le service d'envoi externe qui refuse — voir [`utils/erreurs.go.md`](../api-go/utils/erreurs.go.md), fonction `ErreurEmail`.

Plutôt que de la retirer (elle fait partie de l'API et doit être documentée), on la marque comme échec attendu : elle s'affiche `[IGNORE]`. Quand tu rempliras les vraies clés Brevo, tu pourras retirer cette ligne et vérifier qu'elle passe.

### 6. Le code de sortie

```python
sys.exit(1 if echecs else 0)
```

Par convention, un programme renvoie `0` quand tout va bien et autre chose en cas de problème. Ça permettrait de brancher ce script sur un outil automatique plus tard (Phase 11/12) : l'outil saurait qu'il y a un souci sans avoir à lire le texte.

## Ce que ce script a réellement trouvé

Ce n'est pas un test de façade : au premier lancement, il a mis en évidence **deux vrais défauts de l'API**, corrigés depuis.

1. **Deux erreurs `500` qui auraient dû être des `400`** — envoyer un `emplacement_id` inexistant faisait répondre « bug serveur » alors que la donnée envoyée était simplement fausse. Corrigé dans [`utils/erreurs.go`](../api-go/utils/erreurs.go.md).
2. **Un enchaînement métier impossible dans la documentation** — la collection validait un bénévole avant de valider ses documents, ce que l'API refuse à juste titre.

Il a aussi corrigé trois exemples faux de la collection (`montant_cotisation` en chaîne, les vrais noms de champs des campagnes, le contrat réel de `POST /collectes/{id}/produits`).

C'est l'argument à donner si on te demande à quoi sert ce script : **il a trouvé des bugs**.

## Recréer le compte administrateur après un reset

🔄 **Mis à jour depuis la Phase 1.1** (comblée le 2026-08-03) : `POST /utilisateurs/` existe désormais et crée un compte avec le rôle de son choix — mais elle est réservée à `admin_back`, donc inutilisable tant qu'aucun admin n'existe encore. Ce script (comme `install.sh`, voir [`install.sh.md`](../install.sh.md)) continue donc de passer par l'inscription publique puis une promotion SQL directe :

```bash
curl -X POST http://localhost:8080/api/auth/register/ \
  -H "Content-Type: application/json" \
  -d '{"email":"staff2@nomorewaste.fr","mot_de_passe":"motdepasse123"}'

docker compose exec postgres psql -U nmw_user -d nmw \
  -c "UPDATE utilisateurs SET role='admin_back' WHERE email='staff2@nomorewaste.fr';"
```

C'est exactement le fonction `preparer_compte_admin()` de ce script, jouée automatiquement à chaque lancement — ces deux commandes ne sont utiles que si tu veux le refaire à la main, en dehors du script.

## Fichiers liés

- [../api-go/NO-MORE-WASTE.postman_collection.json.md](../api-go/NO-MORE-WASTE.postman_collection.json.md) — la collection que ce script rejoue
- [../api-go/utils/erreurs.go.md](../api-go/utils/erreurs.go.md) — la correction issue de ce test
