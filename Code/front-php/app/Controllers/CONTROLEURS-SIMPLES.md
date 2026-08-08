# Les contrôleurs et vues simples

> ⏱️ **Lecture : ~5 min** · 406 mots, 23 lignes de code

> Ce document couvre quatre fichiers trop courts pour mériter chacun leur `.md` :
> `front/AccueilController.php`, `back/TableauDeBordController.php`,
> `views/front/accueil.php`, `views/back/tableau_de_bord.php`.
>
> Les contrôleurs qui contiennent une vraie logique ont leur propre document :
> [AuthController](front/AuthController.php.md) et [CommercantsController](back/CommercantsController.php.md).

## Le contrôleur le plus simple possible

```php
class AccueilController
{
    public function index(): void
    {
        Vue::afficher('front/accueil', ['config' => $this->config], Langue::t('nav.accueil'));
    }
}
```

Une page statique n'a aucune donnée à chercher : le contrôleur se contente de désigner la vue. C'est normal et souhaitable — ça montre que **le contrôleur ne fait que coordonner**.

Pourquoi passer par un contrôleur pour si peu ? Pour que toutes les pages suivent le même chemin. Le jour où l'accueil devra afficher des chiffres (nombre de repas distribués…), le contrôleur existe déjà : on ajoute l'appel à l'API, sans réorganiser les routes.

## `TableauDeBordController` — la garde change tout

```php
public function index(): void
{
    if (!Auth::exigerStaff($this->config)) { return; }
    Vue::afficher('back/tableau_de_bord', ...);
}
```

Une seule ligne de plus que l'accueil, mais c'est la ligne qui sépare le back-office du front-office. Sans elle, n'importe qui verrait le tableau de bord interne.

C'est le **réflexe à avoir** à chaque nouveau contrôleur de back-office : première ligne, toujours.

## Les cartes grisées du tableau de bord

```php
<a class="carte" href="/back/commercants">
    <h2><?= Langue::t('back.commercants') ?></h2>
</a>
<a class="carte carte-inactive">
    <h2><?= Langue::t('back.benevoles') ?></h2>
</a>
```

Six modules sont affichés ; un seul est développé. Les cinq autres portent la classe `carte-inactive`, qui les grise et les rend non cliquables :

```css
.carte-inactive { opacity: 0.45; pointer-events: none; }
```

`pointer-events: none` désactive le clic entièrement.

**Pourquoi les afficher quand même ?** Parce que la structure prévue reste visible — pour toi comme pour un jury, on voit où va le projet. Et surtout, personne ne tombe sur une page en erreur pendant une démonstration : le grisé annonce clairement « pas encore fait ».

C'est plus honnête qu'un menu qui promet six écrans dont cinq renvoient une 404.

## Le point commun à tous ces fichiers

Aucun ne contient de SQL, de HTML dans le contrôleur, ni de logique dans la vue. La répartition est toujours la même :

| Fichier | Rôle |
|---|---|
| Route | quelle adresse mène où |
| Contrôleur | vérifier les droits, récupérer les données, choisir la vue |
| Vue | mettre en forme, échapper les données |
| Layout | l'habillage commun |

C'est ce découpage qu'il faut savoir réexpliquer : à chaque question « où est-ce que ça se passe ? », la réponse doit être immédiate.

## Fichiers liés

- [front/AuthController.php.md](front/AuthController.php.md) — le contrôleur le plus riche du front
- [back/CommercantsController.php.md](back/CommercantsController.php.md) — le modèle des écrans de back-office
- [../Vue.php.md](../Vue.php.md) — `Vue::afficher`
- [../middleware/Auth.php.md](../middleware/Auth.php.md) — `exigerStaff`
