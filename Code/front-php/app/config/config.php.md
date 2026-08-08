# `app/config/config.php` — les réglages du front

> ⏱️ **Lecture : ~8 min** · 500 mots

> Équivalent de `config/config.go` côté API : un seul endroit pour tout ce qui peut changer selon la machine.

## Le principe

On ne code jamais une adresse ou une liste de rôles en dur au milieu du code. Sinon, le jour du déploiement sur un vrai serveur (Phase 11), il faudrait retrouver ces valeurs éparpillées dans dix fichiers.

Le fichier fait `return [...]`, et `require` récupère le tableau :

```php
$config = require __DIR__ . '/../app/config/config.php';
```

## `api_base_url` — le réglage le plus important

```php
'api_base_url' => getenv('API_BASE_URL') ?: 'http://api-go:8080',
```

`getenv` lit une variable d'environnement (celles du `.env`, transmises par Docker). Le `?:` fournit une valeur de repli si elle n'existe pas.

⚠️ **`api-go`, pas `localhost`.** C'est le piège expliqué en détail dans [ApiClient.php.md](../services/ApiClient.php.md) : ce code s'exécute dans le conteneur PHP, pour qui `localhost` désigne lui-même. `api-go` est le nom du service dans `docker-compose.yml`, utilisable comme adresse réseau entre conteneurs.

## `langues_disponibles`

```php
'langues_disponibles' => ['fr' => 'Francais', 'en' => 'English', 'it' => 'Italiano', 'pt' => 'Portugues'],
```

Cette liste sert à deux choses : **valider** ce qu'on reçoit (`?lang=xx` est ignoré si `xx` n'est pas dedans, sinon on chargerait n'importe quel fichier) et **construire** le sélecteur de langue du menu.

> 🔄 **Le système de traduction a changé depuis l'écriture initiale de ce fichier.** Les libellés ne vivent plus dans un fichier PHP par langue (`app/i18n/xx.php`) : ils vivent dans la table `traductions`, avec `app/locales/xx.json` comme **cache de lecture** régénéré depuis la base. Ajouter une langue reste une ligne ici, mais la synchronisation se fait ensuite depuis `/back/traductions`. Voir [../views/back/traductions.php.md](../views/back/traductions.php.md) pour le circuit complet et son piège (l'import ne supprime pas les clés orphelines).

## Les quatre rôles, déclarés au même endroit

```php
'roles_back_office' => ['admin_back', 'staff_back'],
'role_admin_back'   => 'admin_back',
'role_adherent'     => 'adherent',
'role_benevole'     => 'benevole',
```

Qui a le droit d'entrer dans chaque espace. Utilisé par `Auth::estStaff()`, `Auth::estAdherent()`, `Auth::estBenevole()`, et `Auth::urlEspace()`.

⚠️ **Cette liste doit rester identique aux rôles de l'API** (colonne `utilisateurs.role`). Si les deux divergent — par exemple si l'API introduisait un rôle `superviseur` non ajouté ici — la personne se connecterait sans jamais accéder à son espace, sans message d'erreur explicite. C'est le genre de panne difficile à diagnostiquer.

### Pourquoi `role_admin_back` est séparé de `roles_back_office`

```php
'roles_back_office' => ['admin_back', 'staff_back'],   // qui entre dans /back
'role_admin_back'   => 'admin_back',                    // qui gère les COMPTES
```

`roles_back_office` répond à "qui a accès au back-office" — les deux rôles y entrent. Mais certaines actions à l'intérieur du back-office ne se délèguent pas : **créer un compte, c'est pouvoir se fabriquer un accès**. `UtilisateursController` vérifie donc le rôle exact, en plus de la garde générale — `Auth::exigerStaff()` laisserait passer les deux rôles indifféremment.

Ajouté en portant l'écran des utilisateurs (vague 4), plutôt que d'écrire `'admin_back'` en dur dans le contrôleur : le jour où le nom du rôle changerait côté API, une seule ligne serait à corriger.

## Fichiers liés

- [../services/ApiClient.php.md](../services/ApiClient.php.md) — utilise `api_base_url`
- [../middleware/Langue.php.md](../middleware/Langue.php.md) — utilise `langues_disponibles`
- [../middleware/Auth.php.md](../middleware/Auth.php.md) — utilise les quatre clés de rôle
- [../controllers/back/UtilisateursController.php.md](../controllers/back/UtilisateursController.php.md) — pourquoi `role_admin_back` existe
