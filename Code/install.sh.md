# `install.sh` — installer NO MORE WASTE en une commande

> ⏱️ **Lecture : ~12 min** · 1 050 mots

> 🟥 **Exigence du sujet** (item 12.1) : une application « packagée pour pouvoir être aisément déployée ».

## Le problème qu'il résout

Sans ce script, déployer le projet sur une machine neuve demandait quatre choses, dans le bon ordre, sans se tromper :

1. copier `.env.example` vers `.env` et inventer un secret JWT à la main ;
2. lancer les conteneurs ;
3. deviner combien de temps attendre avant que l'API soit prête ;
4. créer un premier compte administrateur — ce qui est **impossible depuis l'application elle-même**.

Le point 4 mérite qu'on s'y arrête, parce que c'est le cœur du script.

## Le problème de l'œuf et de la poule

`POST /utilisateurs/` (créer un compte **avec choix du rôle**) est réservé à `admin_back` (voir `front-php/app/controllers/back/UtilisateursController.php.md`). C'est voulu : pouvoir créer des comptes, c'est pouvoir se fabriquer un accès, donc cette capacité ne se délègue pas.

Mais sur un serveur tout juste installé, **aucun compte n'existe encore**. Personne ne peut donc créer le premier administrateur depuis l'application — il faudrait déjà en être un.

Ce n'est pas un bug à corriger : c'est structurel, et ça n'a **pas de solution purement applicative**. La solution retenue, déjà utilisée par `tests/tester-tous-les-endpoints.py` pour préparer son compte de test, tient en deux étapes :

1. créer un compte normal via la route **publique** `POST /auth/register/` (qui crée toujours un `adherent`) ;
2. le promouvoir en `admin_back` par une requête SQL directe.

`install.sh` fait exactement ça, une seule fois, à l'installation — pas à chaque lancement comme le font les scripts de test.

## Rejouable, comme les scripts de test

```bash
compte_admin_existant=$(docker compose exec -T postgres psql ... -c \
    "SELECT count(*) FROM utilisateurs WHERE role='admin_back';")

if [ "$compte_admin_existant" -gt 0 ]; then
    echo "Un compte administrateur existe deja : rien a faire ici."
```

Relancer `install.sh` sur une installation déjà faite ne crée pas un second administrateur, et ne touche pas au `.env` existant. C'est le même principe que `tester-tous-les-endpoints.py`, qui doit pouvoir tourner plusieurs fois de suite sans jamais échouer sur un doublon.

## Le `.env` n'est créé qu'une seule fois

```bash
if [ -f .env ]; then
    echo "[.env] deja present, conserve tel quel."
else
    cp .env.example .env
    ...
fi
```

Le script ne touche **jamais** à un `.env` qui existe déjà. Quelqu'un a peut-être déjà renseigné ses vraies clés Brevo — l'écraser sous ses pieds serait une mauvaise surprise, et la pire chose qu'un script d'installation puisse faire est de détruire une configuration qui fonctionne.

### Un secret JWT généré, pas recopié

```bash
secret_genere=$(head -c 48 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | head -c 40)
sed -i "s|JWT_SECRET=change_me_a_generer|JWT_SECRET=${secret_genere}|" .env
```

`.env.example` contient un jeton `change_me_a_generer`, jamais une vraie valeur. Si toutes les installations partageaient le même secret JWT (celui du `.env` de développement, par exemple), n'importe qui connaissant ce secret pourrait fabriquer un jeton valide pour n'importe quel serveur exécutant ce code — un peu comme si toutes les portes du monde avaient la même clé.

`tr -dc 'A-Za-z0-9'` restreint le secret aux caractères alphanumériques. Ce n'est pas cosmétique : un `/` ou un `|` dans le secret casserait la commande `sed` juste après, qui utilise `|` comme séparateur.

Aucune dépendance externe (pas d'`openssl`) : `/dev/urandom`, `base64`, `tr` et `head` sont présents sur toute machine Linux, exactement l'esprit « bibliothèque standard uniquement » déjà suivi côté Go.

## Attendre que l'API soit vraiment prête

```bash
until curl -sf "http://localhost:${NGINX_PORT}/api/" >/dev/null 2>&1; do
    tentatives=$((tentatives + 1))
    if [ "$tentatives" -ge 30 ]; then
        echo "L'API ne repond toujours pas apres 60 secondes."
        exit 1
    fi
    sleep 2
done
```

`docker compose up -d` rend la main dès que les conteneurs sont **lancés**, pas dès qu'ils sont **prêts**. PostgreSQL met quelques secondes à accepter des connexions ; sans cette attente, la création du compte admin échouerait au hasard selon la vitesse de la machine — exactement le genre de bug qui ne se reproduit jamais deux fois pareil.

`curl -sf` (le `-f`, pour *fail*) fait échouer curl si le code HTTP n'est pas un succès. C'est important : tant que la base de données n'est pas joignable, la route de santé de l'API répond **503** (voir `api-go/utils/erreurs.go.md`, `ErreurBaseIndisponible`), et `-f` transforme ce 503 en échec de `curl`, ce qui fait boucler `until` — exactement le comportement voulu.

## Pourquoi passer par nginx et pas directement par l'API

```bash
curl -sf "http://localhost:${NGINX_PORT}/api/"
```

Ce script s'exécute sur la machine **hôte**, pas dans un conteneur. Depuis l'hôte, `api-go:8080` n'existe pas — ce nom n'est résolu qu'à l'intérieur du réseau Docker (voir `front-php/app/services/ApiClient.php.md` pour la même distinction, côté PHP). Le seul point d'entrée accessible depuis l'extérieur est le port publié par nginx, `${NGINX_PORT}`, exactement comme le ferait un navigateur.

## Les variables viennent du `.env` lui-même, pas de valeurs figées

```bash
set -a
source .env
set +a
...
docker compose exec -T postgres psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" ...
```

`tests/tester-tous-les-endpoints.py` utilise `nmw_user`/`nmw` écrits en dur. Ici, ces valeurs sont **lues depuis le `.env` réellement utilisé** : si quelqu'un personnalise `POSTGRES_USER` avant l'installation, le script continue de fonctionner sans qu'il faille le modifier.

`set -a` / `set +a` encadre le `source .env` : `-a` exporte automatiquement toute variable définie ensuite (donc chaque ligne du `.env`), `+a` arrête ce comportement juste après. Sans lui, les variables seraient lues par le script mais pas transmises aux commandes qu'il lance.

## Le mot de passe n'est jamais stocké par ce script

```bash
read -rsp "Mot de passe (8 caracteres minimum) : " admin_mdp
...
curl ... -d "{\"email\":\"${admin_email}\",\"mot_de_passe\":\"${admin_mdp}\"}"
```

`read -s` masque la saisie à l'écran. Le mot de passe part une seule fois, en HTTPS s'il y en a (ou en HTTP local sinon), directement vers l'API, qui le hache immédiatement avec bcrypt (voir `api-go/app/auth.go.md`). Le script ne l'écrit dans aucun fichier, ne le journalise pas, et la variable disparaît avec le script à la fin de son exécution.

## Comment le vérifier soi-même

```bash
# syntaxe
bash -n install.sh

# execution complete sur une installation existante
./install.sh
# -> doit afficher "Un compte administrateur existe deja : rien a faire ici."

# la generation du secret, en isolation (sans toucher au vrai .env)
cp .env.example /tmp/test.env
head -c 48 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | head -c 40
# -> 40 caracteres alphanumeriques, differents a chaque execution
```

Vérifié le 2026-08-08 : les trois branches (création du `.env`, attente de l'API, création **et** détection d'un compte admin) testées séparément, puis le script entier exécuté de bout en bout sans erreur. Les deux suites de non-régression (`tester-tous-les-endpoints.py`, `tester-espace-client.py`) restent au vert après le redémarrage complet des conteneurs déclenché par le script.

## Fichiers liés

- `.env.example` — le modèle copié à la première installation (pas de fichier `.md` : un `.env` n'en a jamais eu non plus, ses commentaires internes suffisent)
- [front-php/app/controllers/back/UtilisateursController.php.md](front-php/app/controllers/back/UtilisateursController.php.md) — pourquoi créer un compte avec rôle est réservé à `admin_back`
- [api-go/app/auth.go.md](api-go/app/auth.go.md) — `Register`, la route publique utilisée pour l'étape 1
- [api-go/utils/erreurs.go.md](api-go/utils/erreurs.go.md) — le 503 qui fait boucler l'attente
- [tests/tester-tous-les-endpoints.py.md](tests/tester-tous-les-endpoints.py.md) — le même motif (créer puis promouvoir), utilisé à chaque lancement des tests
