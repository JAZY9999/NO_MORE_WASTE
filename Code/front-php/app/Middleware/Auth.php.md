# `app/middleware/Auth.php` — qui est connecté, et qui a le droit d'entrer

> ⏱️ **Lecture : ~15 min** · 1113 mots, 49 lignes de code

> **À lire après** [ApiClient.php.md](../Services/ApiClient.php.md).
> **Phases** : 1.3 (connexion) et 9 (séparation back-office / front-office).

## Ce que ce fichier ne fait PAS

C'est le point le plus important à comprendre : **ce fichier ne vérifie aucun mot de passe**.

Il ne connaît pas les mots de passe, ne parle pas à la base de données, ne sait pas les déchiffrer. Tout ça vit dans l'API Go, qui compare le mot de passe saisi au haché bcrypt stocké en base.

Le front fait seulement ceci :

```
1. il envoie l'email et le mot de passe à l'API
2. l'API répond « c'est bon, voici un jeton »
3. le front range ce jeton dans la session
4. à chaque page, il ressort le jeton et le joint à ses appels d'API
```

Le front est donc un **porteur de jeton**, pas un gardien de mots de passe.

## Qu'est-ce que la session PHP

Le web est « sans mémoire » : chaque requête est indépendante, le serveur ne se souvient de rien d'une page à l'autre. Sans mécanisme supplémentaire, il faudrait se reconnecter à chaque clic.

La session résout ça :

1. À la première visite, PHP crée un **espace de stockage sur le serveur** et lui donne un identifiant unique.
2. Il envoie cet identifiant au navigateur dans un **cookie** (`PHPSESSID`).
3. À chaque requête suivante, le navigateur renvoie le cookie, et PHP retrouve l'espace correspondant.

Ce qu'on y range ici : le jeton JWT, l'email et le rôle de l'utilisateur, la langue choisie.

**Le mot de passe n'y est jamais stocké.** Il transite une fois, au moment de la connexion, et n'est plus jamais conservé nulle part.

## `session_regenerate_id(true)` — la ligne de sécurité

```php
public static function connecter(string $jeton, array $utilisateur): void
{
    session_regenerate_id(true);
    ...
}
```

Cette ligne change l'identifiant de session **au moment précis de la connexion**. Elle protège contre une attaque appelée **fixation de session** :

1. Un attaquant visite le site et obtient un identifiant de session, disons `ABC123`.
2. Il piège la victime pour qu'elle utilise ce même identifiant (lien piégé, cookie imposé).
3. La victime se connecte normalement avec son vrai mot de passe.
4. Sans cette ligne, la session `ABC123` — que l'attaquant connaît — devient une session **authentifiée**. Il n'a plus qu'à s'en servir.

Avec `session_regenerate_id(true)`, l'identifiant change à la connexion : `ABC123` devient inutile, et le `true` détruit l'ancienne session au lieu de la laisser traîner.

C'est une ligne facile à oublier, et une question typique de jury sur la sécurité des sessions.

## Les deux gardes

### `exigerConnexion()` — « es-tu connecté ? »

Renvoie vers `/connexion` si non. Utilisé pour toute page nécessitant un compte.

### `exigerStaff($config)` — « fais-tu partie du personnel ? »

C'est la garde du **back-office**, et donc la traduction concrète de l'exigence du sujet : séparer l'espace de NO MORE WASTE de celui de ses clients.

Elle enchaîne deux vérifications, dans cet ordre :

```php
if (!self::exigerConnexion()) { return false; }   // 1. connecté ?
if (!self::estStaff($config)) { ... }             // 2. bon rôle ?
```

L'ordre compte, et correspond à deux codes HTTP différents :

| Situation | Code | Sens |
|---|---|---|
| Pas connecté | **401** | « je ne sais pas qui tu es » → va te connecter |
| Connecté, mauvais rôle | **403** | « je sais qui tu es, mais tu n'as pas le droit » |

Confondre les deux est une erreur classique. Un adhérent connecté qui tente d'accéder au back-office n'a pas besoin de se reconnecter — se reconnecter ne changerait rien à son rôle.

## Les quatre gardes, un seul mécanisme

| Garde | Rôles acceptés | Message de refus |
|---|---|---|
| `exigerConnexion()` | tout compte connecté | `connexion.obligatoire` |
| `exigerStaff($config)` | `admin_back`, `staff_back` | `back.acces_refuse` |
| `exigerAdherent($config)` | `adherent` | `espace.reserve_adherent` |
| `exigerBenevole($config)` | `benevole` | `espace.reserve_benevole` |

Les trois dernières ne diffèrent que par **la liste des rôles acceptés** et **le message**. Elles passent donc toutes par une méthode privée commune :

```php
private static function exigerRoles(array $rolesAutorises, string $cleMessage): bool
```

Écrire trois fois la même mécanique serait trois occasions d'oublier le `http_response_code(403)`, ou de renvoyer un 401 là où il faut un 403.

`estStaff()` teste l'appartenance à une **liste** (deux rôles), `adherent` et `benevole` sont des rôles **uniques** : le cas unique devient simplement une liste à un élément. Un seul mécanisme, pas deux.

⚠️ `exigerStaff()` **a gardé sa signature exacte** lors de ce regroupement : les appels existants dans les contrôleurs n'ont pas changé d'une ligne.

## Les rôles sont déclarés dans la config, pas ici

```php
// app/config/config.php
'roles_back_office' => ['admin_back', 'staff_back'],
'role_adherent'     => 'adherent',
'role_benevole'     => 'benevole',
```

Écrire `'adherent'` en dur dans `Auth.php` créerait un **deuxième endroit** à maintenir. Le jour où un rôle change de nom côté API, on en oublierait un — et la panne ne se verrait qu'à l'exécution, sous la forme d'un utilisateur bloqué partout sans explication.

## `urlEspace()` — éviter la logique de rôle dans les gabarits

```php
public static function urlEspace(array $config): ?string
// /back, /mon-espace/commercant, /mon-espace/benevole, ou null
```

Le lien « Mon espace » de l'en-tête public mène à un endroit différent selon le rôle. Sans cette méthode, le gabarit contiendrait un `if/elseif` sur les rôles — de la logique métier dans un fichier dont le seul travail est de mettre en forme.

Retourne `null` pour un visiteur non connecté : le gabarit affiche alors « Connexion ».

### 🔄 Un lien mort, resté invisible jusqu'à la vague 3

Cette méthode renvoyait `/mon-espace` pour un adhérent — une adresse qui n'a **jamais existé**. Le lien « Mon espace » de l'en-tête répondait donc **404**.

Le défaut datait de la vague 1, écrit avant que l'écran de l'espace commerçant existe. Il n'avait jamais été remarqué parce que **personne ne s'était connecté en adhérent** pendant tout ce temps — les tests de la vague 1 vérifiaient les gardes du back-office, pas le parcours d'un client.

Corrigé en `/mon-espace/commercant`, l'adresse réelle de l'écran. C'est le genre de défaut qu'on ne découvre qu'en se mettant réellement dans la peau de chaque rôle — d'où la vérification systématique des trois destinations (`/mon-espace/commercant`, `/mon-espace/benevole`, `/back`) à chaque changement touchant l'authentification.

### Pourquoi appeler la garde dans chaque contrôleur

```php
public function liste(): void
{
    if (!Auth::exigerStaff($this->config)) { return; }
    ...
}
```

On aurait pu brancher un mécanisme automatique sur toutes les adresses commençant par `/back`. On ne l'a pas fait, **volontairement**, pour la même raison que `utils.RequireRole` côté Go : la protection se **voit** en lisant le contrôleur.

Avec un mécanisme invisible, la question « cette page est-elle protégée ? » oblige à aller vérifier ailleurs, et un oubli ne se remarque pas. Ici, l'absence de la ligne saute aux yeux.

⚠️ **La contrepartie** : c'est à toi d'y penser à chaque nouveau contrôleur du back-office. C'est le premier réflexe à avoir en ajoutant un écran.

## Le `exit` de `rediriger()` — un vrai piège

```php
public static function rediriger(string $chemin): void
{
    header('Location: ' . $chemin);
    exit;
}
```

**Sans le `exit`, la redirection ne protège rien.**

`header('Location: ...')` ne fait qu'*ajouter une consigne* à la réponse. PHP **continue d'exécuter** le reste du script : il générerait la page réservée en entier et l'enverrait avec la consigne de redirection.

La plupart des navigateurs suivent la redirection sans afficher le contenu — mais il a quand même été **calculé et envoyé**. N'importe quel outil qui regarde la réponse brute (les outils de développement, `curl`) la lit sans difficulté :

```bash
curl http://localhost:8080/back        # afficherait la page malgré la redirection
```

C'est une fuite de données réelle. Le `exit` arrête tout immédiatement.

## Comment le vérifier soi-même

```bash
# 1. Sans connexion, /back renvoie vers /connexion
curl -s -o /dev/null -w "%{http_code} -> %{redirect_url}\n" http://localhost:8080/back
# 302 -> http://localhost:8080/connexion

# 2. Connexion en tant qu'adhérent (pas staff)
curl -s -c /tmp/c.txt -X POST http://localhost:8080/connexion \
  -d "email=adherent@test.fr&mot_de_passe=motdepasse123"

# 3. Il est bien bloqué à l'entrée du back-office
curl -s -o /dev/null -w "%{http_code} -> %{redirect_url}\n" -b /tmp/c.txt http://localhost:8080/back
# 302 -> http://localhost:8080/   + message « Vous n'avez pas les droits… »
```

Vérifié le 2026-08-01 : bloqué correctement, et le lien « Back-office » n'apparaît même pas dans son menu.

> Masquer le lien est du confort, **pas de la sécurité** : c'est la garde côté serveur qui protège. Un lien caché reste accessible en tapant l'adresse à la main. Il faut toujours les deux, et ne jamais compter sur le premier seul.

## Fichiers liés

- [Langue.php.md](Langue.php.md) — l'autre middleware, pour le multilingue
- [../controllers/front/AuthController.php.md](../Controllers/Front/AuthController.php.md) — qui appelle `connecter()`
- [../routes/back_routes.php.md](../routes/back_routes.php.md) — les routes protégées par `exigerStaff`
- [../../../api-go/utils/guard.go.md](../../../api-go/utils/guard.go.md) — l'équivalent côté API (la vraie barrière)
