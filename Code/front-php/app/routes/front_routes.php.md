# `app/routes/front_routes.php` — les adresses publiques

> ⏱️ **Lecture : ~9 min** · 700 mots

> **Phase 9, complétée en phase 11 (vague 3)** — le pendant de [back_routes.php](back_routes.php.md) : ici, tout ce qui est accessible aux visiteurs, adhérents et candidats bénévoles.
>
> 🔄 Ce fichier ne comptait que 4 routes à sa création. Il en compte aujourd'hui **14** : le catalogue public des services, la candidature bénévole, et les deux espaces client.

## Ce qu'on trouve ici

| Route | Rôle | Accès |
|---|---|---|
| `GET /` | accueil | public |
| `GET /connexion` | afficher le formulaire | public |
| `POST /connexion` | traiter la connexion | public |
| `GET /deconnexion` | fermer la session | connecté |
| `GET /services`, `GET /services/@id` | catalogue et détail | public |
| `POST /services/@id/inscription` | s'inscrire à un créneau | adhérents |
| `GET`/`POST /benevoles/candidature` | candidature bénévole | public |
| `GET /benevoles/candidature/merci` | remerciement | public |
| `GET /mon-espace/commercant` | espace commerçant | adhérents |
| `POST /mon-espace/collectes` | demander une collecte | adhérents |
| `GET /mon-espace/benevole` | espace bénévole | bénévoles |

Aucun écran de gestion interne : c'est ce qui distingue ce fichier du back-office. Mais ce n'est plus un fichier "vitrine seulement" — c'est ici que vit tout le **front office** exigé par le sujet.

## Deux routes pour la même adresse

```php
Flight::route('GET /connexion',  [$auth, 'formulaire']);
Flight::route('POST /connexion', [$auth, 'traiter']);
```

Même adresse, deux méthodes HTTP, deux comportements — c'est la façon habituelle de gérer un formulaire sur le web :

- **GET** = « montre-moi la page » (l'utilisateur arrive sur `/connexion`)
- **POST** = « voici ce que j'ai saisi » (il clique sur le bouton)

Le formulaire HTML déclare la méthode utilisée à l'envoi :

```html
<form method="post" action="/connexion">
```

### Pourquoi POST et pas GET pour envoyer un mot de passe

C'est une question de jury classique. Avec GET, les données partent **dans l'adresse** :

```
/connexion?email=staff@nmw.fr&mot_de_passe=motdepasse123
```

Le mot de passe se retrouverait alors dans l'historique du navigateur, dans les logs du serveur, dans l'en-tête `Referer` envoyé aux sites suivants, et visible à l'écran de quiconque regarde. Avec POST, les données voyagent dans le **corps** de la requête et n'apparaissent nulle part de tout ça.

> À noter : POST n'est pas *chiffré* pour autant. C'est HTTPS qui chiffre — d'où l'item 11.3 de la todo. POST évite la **divulgation**, HTTPS empêche l'**interception**. Les deux sont nécessaires.

## Consulter est public, agir ne l'est pas

C'est la distinction qui structure ce fichier, visible dans le contraste entre :

```php
Flight::route('GET /services', [$services, 'liste']);            // tout le monde
Flight::route('POST /services/@id/inscription', [$services, 'inscrire']); // adhérents
```

Les routes `GET /services*` consomment des routes d'API **publiques** — aucun jeton envoyé, ce qui dit au lecteur que la page est ouverte. C'est la vitrine de l'association : exiger un compte pour **regarder** le catalogue ferait fuir exactement les gens qu'on cherche à attirer.

Seule l'inscription à un créneau et la demande de collecte exigent `Auth::exigerAdherent()`.

## L'inscription n'envoie aucun identifiant

```php
$this->api->post('/creneaux/' . $creneauId . '/inscriptions', [], Auth::jeton());
```

Le corps est vide. L'API déduit du jeton **qui** s'inscrit — c'est la correction de sécurité de la vague 3 : avant elle, un adhérent pouvait envoyer l'identifiant d'une autre boutique et l'inscrire à sa place. Voir [ServicesPublicsController.php.md](../Controllers/Front/ServicesPublicsController.php.md) pour le détail complet, y compris le piège du tableau PHP vide qui a fallu corriger dans `ApiClient`.

## Les deux espaces client ne prennent jamais d'identifiant dans l'URL

```php
Flight::route('GET /mon-espace/commercant', [$espaceCommercant, 'index']);
Flight::route('GET /mon-espace/benevole', [$espaceBenevole, 'index']);
```

Pas de `/mon-espace/commercant/@id`. Les routes `/mon-espace` de l'API font elles-mêmes le chemin `jeton → compte → fiche`. C'est ce qui rend impossible de lire le dossier d'un autre en changeant un numéro — il n'y a aucun numéro à changer.

## La candidature a sa propre page de remerciement

```php
Flight::route('GET /benevoles/candidature/merci', [$candidature, 'merci']);
```

Pas un simple message flash : elle explique la suite du parcours (candidat → justificatifs vérifiés → validé), et elle **survit à un rafraîchissement** — un flash disparaîtrait au premier F5, laissant croire que rien n'a été envoyé.

## Fichiers liés

- [back_routes.php.md](back_routes.php.md) — l'espace réservé au personnel
- [../controllers/front/AuthController.php.md](../Controllers/Front/AuthController.php.md) — connexion et déconnexion
- [../controllers/front/ServicesPublicsController.php.md](../Controllers/Front/ServicesPublicsController.php.md) — la faille d'inscription et sa correction
- [../controllers/front/EspaceCommercantController.php.md](../Controllers/Front/EspaceCommercantController.php.md) et [EspaceBenevoleController.php.md](../Controllers/Front/EspaceBenevoleController.php.md)
- [../../public/index.php.md](../../public/index.php.md) — qui charge ce fichier
