# `app/routes/back_routes.php` — les adresses du back-office

> ⏱️ **Lecture : ~9 min** · 750 mots

> **Phase 9, complétée en phases 11 (vagues 2-4)** — 🟥 **exigence du sujet** : *« il y a ici à la fois un back-office (utilisé par NO MORE WASTE) et un front office (utilisé par les clients de NO MORE WASTE) »*.
>
> 🔄 Ce fichier ne comptait que 2 routes à sa création (Phase 9). Il en compte aujourd'hui **22**, réparties sur 10 modules. Les sections ci-dessous couvrent les motifs récurrents ; la table en bas de page liste chaque module.

## Ce qu'est une route

Une route associe une **adresse** à un **bout de code** :

```php
Flight::route('GET /back/commercants', [$commercants, 'liste']);
```

« Quand quelqu'un demande `/back/commercants` en GET, appelle la méthode `liste()` de l'objet `$commercants` ».

Aucun fichier `back/commercants.php` n'existe sur le disque. L'adresse est **inventée** par ce fichier — c'est ce que permet la réécriture d'URL de nginx.

## Pourquoi deux fichiers de routes

Le sujet demande deux espaces distincts. On aurait pu tout mettre dans un seul fichier ; la séparation en **`front_routes.php`** et **`back_routes.php`** rend cette exigence visible dans l'organisation même du projet.

Bénéfice concret : pour répondre à « quelles pages sont réservées au personnel ? », il suffit d'ouvrir **ce fichier**. Pas besoin de lire tout le code.

## La convention du préfixe `/back`

Toutes les adresses d'ici commencent par `/back`. C'est une règle simple qui rend la sécurité **vérifiable d'un coup d'œil** :

> Si une adresse commence par `/back`, elle doit être protégée par `Auth::exigerStaff()`.

Un écart se repère immédiatement lors d'une relecture.

## Où se trouve réellement la protection

⚠️ **Pas dans ce fichier.** Ce fichier ne fait que déclarer des adresses.

La protection est dans **chaque contrôleur**, en première ligne :

```php
public function liste(): void
{
    if (!Auth::exigerStaff($this->config)) { return; }
    ...
}
```

Beaucoup de frameworks permettent d'attacher une protection à un groupe de routes. On ne l'a pas fait, pour la même raison que côté Go avec `utils.RequireRole` : **la protection se voit en lisant le contrôleur**.

Avec un mécanisme automatique, la question « cette page est-elle protégée ? » oblige à aller vérifier ailleurs — et un oubli passe inaperçu. Ici, l'absence de la ligne saute aux yeux.

**La contrepartie**, à connaître : c'est à toi d'y penser à chaque nouveau contrôleur de back-office. C'est le premier réflexe à avoir.

## Les deux routes pour `/back`

```php
Flight::route('GET /back',  [$tableauDeBord, 'index']);
Flight::route('GET /back/', [$tableauDeBord, 'index']);
```

Avec et sans barre oblique finale. Flight considère `/back` et `/back/` comme deux adresses différentes : sans la seconde ligne, un visiteur qui tape `/back/` tomberait sur une 404 alors que tout va bien.

C'est le genre de détail qui donne une mauvaise impression en démonstration.

## Comment les contrôleurs reçoivent leurs dépendances

```php
$commercants = new CommercantsController($api, $config);
```

Les variables `$api` et `$config` viennent de `public/index.php`. Elles sont visibles ici parce que `require` exécute le fichier **dans le contexte de l'appelant** : c'est comme si son contenu était écrit à cet endroit.

On donne au contrôleur ce dont il a besoin **au moment de le créer**, plutôt que de le laisser aller chercher lui-même une variable globale. Ça s'appelle l'**injection de dépendances**, et l'intérêt pratique est que le contrôleur ne dépend d'aucun contexte caché : on voit dans son constructeur tout ce qu'il utilise.

## Trois motifs qui reviennent d'un module à l'autre

Une fois qu'on les reconnaît une fois, on les voit partout dans ce fichier.

### 1. Une seule page, une seule route POST, un champ caché `action`

```php
Flight::route('POST /back/services', [$services, 'traiter']);
```

Services, bénévoles, collectes partagent ce motif : l'écran a cinq boutons (valider, refuser, ajouter une compétence…), mais **un seul** point d'entrée. Le contrôleur lit `$_POST['action']` et aiguille avec un `switch`. Évite une explosion de routes pour un seul écran, et c'est le même motif que l'écran des traductions, plus ancien.

### 2. GET et POST n'ont jamais le même effet

```php
Flight::route('GET /back/plannings', [$services, 'planning']);          // télécharge un CSV
Flight::route('POST /back/plannings', [$services, 'envoyerPlannings']); // envoie des emails
```

Même adresse, deux verbes, deux natures d'action radicalement différentes. **Un GET ne doit jamais avoir d'effet** : sinon un simple rafraîchissement de page rejouerait l'action. C'est vrai pour les plannings, les adhésions (`declencherJob`/`relancer`) et les campagnes (`declencher`) — partout où l'action envoie réellement des emails.

### 3. `/nouveau` avant `/@id`

```php
Flight::route('GET /back/commercants/nouveau', [$commercants, 'formulaireCreation']);
Flight::route('GET /back/commercants/@id', [$commercants, 'detail']);
```

**L'ordre compte.** Dans l'autre sens, FlightPHP prendrait `nouveau` pour un identifiant — `(int) "nouveau"` vaut `0`, l'API répondrait 404, et la page de création afficherait "Commerçant introuvable". Une panne **silencieuse** : aucune erreur PHP, juste un écran qui ne s'ouvre jamais.

## Le PDF et le CSV : pourquoi ils passent par le front

```php
Flight::route('GET /back/livraisons/@id/pdf', [$tournees, 'pdf']);
```

Un lien direct vers `/api/livraisons/1/pdf` répondrait **401** : le jeton JWT vit dans la session PHP, pas dans un cookie que l'API saurait lire. Le navigateur qui suit ce lien n'envoie donc aucune preuve d'identité.

Le front sert de relais : il demande le fichier à l'API avec le jeton de la session, puis le renvoie tel quel au navigateur. Bénéfice secondaire, la garde de rôle protège aussi le téléchargement.

## L'état actuel — 22 routes, 10 modules

| Module | Ce qu'il couvre | Point notable |
|---|---|---|
| Tableau de bord | accueil du back-office | — |
| Bénévoles | liste, fiche, validation | le module le plus détaillé par le sujet |
| Collectes | liste, détail, scan des produits | — |
| Stocks / Emplacements | recherche code-barre, rangement | — |
| Tournées | liste, détail, clôture, **PDF** | 🟥 récapitulatif exigé par le sujet |
| Services | catalogue, créneaux, **planning CSV** | — |
| Adhésions | liste, fiche, **rappel automatique** | 🟥 point le plus cité du sujet |
| Bénéficiaires | liste + création | destinataires des tournées |
| Commerçants | liste, fiche, création, modification | `PUT /commercants/{id}` ajouté en vague 4 |
| Campagnes | liste, fiche, envoi confirmé | irréversible : bouton tout en bas de la fiche |
| Utilisateurs | liste + création avec choix du rôle | réservé à `admin_back`, pas `staff_back` |
| Traductions | gestion du multilingue | déjà présent en phase 9 |

Chaque module a son propre document détaillé (voir les liens ci-dessous) : celui-ci ne couvre que le **routage**, pas la logique métier de chaque écran.

## Comment le vérifier soi-même

```bash
# Sans connexion -> renvoyé vers la page de connexion
curl -s -o /dev/null -w "%{http_code} -> %{redirect_url}\n" http://localhost:8080/back

# Connecté en tant que staff -> le tableau de bord s'affiche
curl -s -c /tmp/c.txt -X POST http://localhost:8080/connexion \
  -d "email=staff2@nomorewaste.fr&mot_de_passe=motdepasse123"
curl -s -b /tmp/c.txt http://localhost:8080/back | grep -o "<h1>[^<]*</h1>"
```

## Fichiers liés

- [front_routes.php.md](front_routes.php.md) — l'autre espace, et l'espace client
- [../middleware/Auth.php.md](../middleware/Auth.php.md) — `exigerStaff`, la vraie protection
- [../controllers/back/CommercantsController.php.md](../controllers/back/CommercantsController.php.md) — l'exemple de `/nouveau` avant `/@id`
- [../controllers/back/TourneesController.php.md](../controllers/back/TourneesController.php.md) — le relais du PDF
- [../controllers/back/AdhesionsController.php.md](../controllers/back/AdhesionsController.php.md) — le module le plus cité du sujet
- [../../public/index.php.md](../../public/index.php.md) — qui charge ce fichier
