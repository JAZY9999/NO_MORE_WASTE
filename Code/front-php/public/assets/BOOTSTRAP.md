# `public/assets/` — Bootstrap, et pourquoi il est stocké ici

> ⏱️ **Lecture : ~10 min** · 867 mots, 33 lignes de code

> Remplace l'ancien `style.css` écrit à la main, supprimé le 2026-08-02.
> Toute la mise en forme du site passe désormais par **Bootstrap 5.3** et **Bootstrap Icons 1.11**.

## Ce que contient le dossier

```
public/assets/
├── bootstrap/
│   ├── bootstrap.min.css          (233 Ko)  toute la mise en forme
│   └── bootstrap.bundle.min.js    ( 81 Ko)  menus déroulants, alertes, menu mobile
└── icons/
    ├── bootstrap-icons.css        ( 86 Ko)  les noms des icônes
    └── fonts/
        ├── bootstrap-icons.woff2  (130 Ko)  les dessins des icônes
        └── bootstrap-icons.woff   (176 Ko)  version de repli
```

Environ 700 Ko en tout, chargés dans [`layout.php`](../../app/views/layout_back.php.md) :

```html
<link rel="stylesheet" href="/assets/bootstrap/bootstrap.min.css">
<link rel="stylesheet" href="/assets/icons/bootstrap-icons.css">
...
<script src="/assets/bootstrap/bootstrap.bundle.min.js"></script>
```

## Pourquoi en local et pas depuis un CDN

L'usage courant est de charger Bootstrap depuis internet :

```html
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/..." rel="stylesheet">
```

C'est plus court, mais ça crée une **dépendance au réseau**. Sans connexion, le site s'affiche entièrement **sans style** : du texte noir sur fond blanc, les menus dépliés, les tableaux bruts.

Le risque est réel dans deux situations qui comptent : travailler dans un train ou un avion, et faire la démonstration le jour de la soutenance sur le wifi de l'école. Un site qui apparaît nu devant un jury est un incident évitable en une décision.

En local, le site est **complètement autonome** : il ne dépend que de ses propres conteneurs.

C'est aussi cohérent avec l'exigence de packaging du sujet — *« prévoir un script pour installer/copier les répertoires, bibliothèques, fichiers utiles »*. Les bibliothèques font partie du livrable ; elles ne sont pas censées être téléchargées ailleurs à l'exécution.

## Le piège des polices d'icônes

Les icônes de Bootstrap ne sont pas des images : c'est une **police de caractères**, où chaque « lettre » est un pictogramme. Le fichier CSS va donc chercher les fichiers de police :

```css
url("fonts/bootstrap-icons.woff2?dd67030699838ea613ee6dbda90effa6")
```

Ce chemin est **relatif au fichier CSS**. Il faut donc impérativement respecter l'arborescence : `bootstrap-icons.css` et son dossier `fonts/` doivent rester côte à côte.

Si on déplace le CSS sans le dossier `fonts/`, la page s'affiche normalement mais **toutes les icônes deviennent des carrés vides** — un symptôme déroutant, car rien n'indique que le problème vient d'un fichier manquant.

Vérification rapide :

```bash
curl -s -o /dev/null -w "%{http_code} %{content_type}\n" \
  http://localhost:8080/assets/icons/fonts/bootstrap-icons.woff2
# 200 font/woff2
```

## Les classes Bootstrap utilisées dans le projet

Les repérer dans les vues devient facile une fois qu'on connaît la logique de nommage.

### Mise en page

| Classe | Effet |
|---|---|
| `container` | centre le contenu avec des marges |
| `row` / `col` | grille en colonnes |
| `row-cols-1 row-cols-md-2` | 1 colonne sur téléphone, 2 à partir d'un écran moyen |
| `d-flex`, `gap-3` | aligner des éléments côte à côte |
| `ms-auto` | pousse l'élément vers la droite (marge gauche automatique) |

### Composants

| Classe | Composant |
|---|---|
| `navbar navbar-expand-lg` | barre de navigation qui se replie sur petit écran |
| `card`, `card-body` | les cartes du tableau de bord |
| `table table-striped table-hover` | tableau avec lignes alternées |
| `table-responsive` | rend le tableau défilable au lieu de déformer la page |
| `alert alert-danger` | les messages d'erreur |
| `form-control`, `form-select` | champs de formulaire |
| `badge` | les petites pastilles (compteur, rôle) |

### Le système d'espacement

`mb-3`, `py-4`, `gap-2`, `p-5`… se lisent toujours de la même façon :

- 1re lettre : `m` = marge extérieure, `p` = marge intérieure
- 2e lettre : `t` haut, `b` bas, `s` gauche, `e` droite, `x` horizontal, `y` vertical
- chiffre : de 0 à 5 (0 = rien, 3 = 1rem, 5 = 3rem)

`mb-3` = *margin bottom, niveau 3*. Une fois cette grille comprise, on lit n'importe quelle page Bootstrap sans documentation.

## Les deux thèmes de couleur

La barre de navigation change selon l'espace, avec les couleurs natives de Bootstrap :

```php
$couleurBarre = $estStaff ? 'bg-dark' : 'bg-success';
```

- **Vert** (`bg-success`) : front-office, la partie publique
- **Sombre** (`bg-dark`) : back-office, la partie interne

Ce n'est pas décoratif : pendant une démonstration, on voit **immédiatement** dans quel espace on se trouve, ce qui matérialise à l'écran la séparation exigée par le sujet.

Aucune couleur personnalisée n'a été ajoutée : tout vient de Bootstrap, conformément à la consigne.

## Pourquoi le CSS maison a été supprimé

Le projet a d'abord eu un `style.css` de 250 lignes écrit à la main, dans la logique du reste (Go sans framework, cURL sans Guzzle). La consigne a ensuite été explicite : **Bootstrap uniquement, icônes comprises**.

Ce qui change dans l'argumentation à l'oral. Avant : « j'ai tout écrit moi-même, je peux expliquer chaque ligne ». Maintenant : « j'utilise le standard du marché, ce qui donne un rendu cohérent, responsive et accessible sans réinventer ce qui existe ».

Les deux se défendent. Le second est plus proche de la pratique réelle en entreprise, et évite de perdre du temps sur de la mise en forme au lieu du métier.

À savoir formuler : **le front utilise Bootstrap ; l'API, elle, reste en Go sans framework** parce que c'est ce que le cours impose et que c'est là que se trouve la vraie logique du projet.

## Mettre à jour Bootstrap

```bash
cd Code/front-php/public/assets
curl -sSL -o bootstrap/bootstrap.min.css \
  https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css
curl -sSL -o bootstrap/bootstrap.bundle.min.js \
  https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js
```

Puis reconstruire l'image, car les fichiers sont copiés dedans :

```bash
docker compose up -d --build front-php
```

⚠️ Un simple `restart` ne suffit **pas** : le `Dockerfile` fait un `COPY`, donc les fichiers sont figés au moment de la construction. C'est le même piège que pour la configuration nginx.

## Fichiers liés

- [../../app/views/layout_back.php.md](../../app/views/layout_back.php.md) — où Bootstrap est chargé, la barre de navigation
- [../../app/views/back/commercants.php.md](../../app/views/back/commercants.php.md) — le tableau Bootstrap et son filtre
- [../index.php.md](../index.php.md) — le point d'entrée du site
