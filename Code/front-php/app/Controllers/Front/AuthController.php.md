# `app/controllers/front/AuthController.php` — la connexion

> ⏱️ **Lecture : ~10 min** · 559 mots, 44 lignes de code

> **Item 1.3** de la todo. À lire après [Auth.php.md](../../middleware/Auth.php.md).

## Ce que fait un contrôleur

Un contrôleur est le chef d'orchestre d'une page. Il ne contient ni SQL, ni HTML :

1. il lit ce que l'utilisateur a envoyé,
2. il demande les données à l'API,
3. il choisit la vue à afficher, ou redirige.

## `formulaire()` — afficher la page

```php
if (Auth::estConnecte()) {
    Auth::rediriger('/');
    return;
}
```

Si l'utilisateur est déjà connecté, inutile de lui montrer un formulaire de connexion : on le renvoie à l'accueil. Détail d'ergonomie, mais c'est ce que fait tout site correct.

## `traiter()` — le cœur du fichier

### Étape 1 : demander à l'API de vérifier

```php
$reponse = $this->api->post('/auth/login/', [
    'email' => $email,
    'mot_de_passe' => $motDePasse,
]);
```

**Le front ne vérifie rien lui-même.** Il transmet à l'API, qui compare le mot de passe au haché bcrypt stocké en base et fabrique le jeton.

⚠️ Le champ s'appelle **`mot_de_passe`**, pas `password`. L'API a été uniformisée en français lors de la Phase 10. Envoyer `password` ferait arriver un mot de passe vide côté Go, et le message d'erreur serait trompeur (« le mot de passe doit contenir entre 5 et 50 caractères ») car il ne dit pas que le nom du champ est faux.

### Étape 2 : en cas d'échec, ne jamais renvoyer le mot de passe

```php
Vue::afficher('front/connexion', [
    'emailSaisi' => $email,   // on garde l'email
], ...);                       // mais JAMAIS le mot de passe
```

On réaffiche l'email pour éviter de tout retaper. Le mot de passe, lui, n'est jamais réinjecté dans le HTML : il se retrouverait dans le code source de la page, dans le cache du navigateur, et potentiellement dans l'historique.

Le message affiché reste volontairement vague : **« Email ou mot de passe incorrect »**. On ne dit pas lequel des deux est faux — sinon on offrirait un moyen de découvrir quelles adresses email possèdent un compte (c'est ce qu'on appelle l'**énumération de comptes**).

### Étape 3 : récupérer le rôle

```php
$profil = $this->api->get('/auth/me/', $jeton);
```

Un deuxième appel, parce que la connexion ne renvoie que le jeton. Or on a besoin du **rôle** pour savoir si cette personne a droit au back-office.

**Pourquoi ne pas simplement décoder le JWT côté PHP ?** Le rôle est écrit dedans, ce serait plus rapide. Mais un JWT n'est **pas chiffré** : c'est du texte encodé en base64, que n'importe qui peut lire *et fabriquer*. Ce qui le rend fiable, c'est sa **signature**, vérifiable uniquement avec la clé secrète — qui vit dans l'API.

Décoder sans vérifier la signature reviendrait à croire un badge sans regarder s'il est authentique : il suffirait de fabriquer un jeton disant `"role": "admin_back"` pour entrer dans le back-office. En demandant à l'API, on obtient un rôle **vérifié**.

> Nuance à savoir formuler : même si quelqu'un trompait le front de cette manière, il ne pourrait rien faire de plus — l'API revérifie le rôle à chaque requête, avec la signature. Le front ne fait qu'afficher ou masquer ; **la vraie barrière est côté API**.

### Étape 4 : orienter selon le rôle

```php
Auth::rediriger(Auth::estStaff($this->config) ? '/back' : '/');
```

Le personnel arrive directement sur le back-office, les adhérents sur l'accueil. Chacun sur l'espace qui le concerne — c'est la séparation demandée par le sujet, rendue concrète dès la connexion.

## Le parcours complet

```
Formulaire (POST /connexion)
   |
   v
AuthController::traiter()
   |
   |-- POST /auth/login/  --> API : bcrypt + fabrication du JWT
   |                              <-- {"token": "..."}
   |
   |-- GET /auth/me/      --> API : vérifie la signature du jeton
   |                              <-- {"email": ..., "role": "admin_back"}
   |
   |-- Auth::connecter()  : session_regenerate_id + rangement en session
   |
   v
Redirection vers /back (staff) ou / (adhérent)
```

## Comment le vérifier soi-même

```bash
# Connexion réussie -> redirection vers /back
curl -s -o /dev/null -w "%{http_code} -> %{redirect_url}\n" \
  -c /tmp/c.txt -X POST http://localhost:8080/connexion \
  -d "email=staff2@nomorewaste.fr&mot_de_passe=motdepasse123"
# 302 -> http://localhost:8080/back

# Mauvais mot de passe -> on reste sur la page avec le message d'erreur
curl -s -X POST http://localhost:8080/connexion \
  -d "email=staff2@nomorewaste.fr&mot_de_passe=faux" | grep -o 'message-erreur">[^<]*<'
```

## Fichiers liés

- [../../middleware/Auth.php.md](../../middleware/Auth.php.md) — la session et les gardes
- [../../services/ApiClient.php.md](../../services/ApiClient.php.md) — comment l'appel part vers l'API
- [../../routes/front_routes.php.md](../../routes/front_routes.php.md) — GET et POST sur la même adresse
- [../../../../api-go/app/auth.go.md](../../../../api-go/app/auth.go.md) — ce qui se passe côté API
