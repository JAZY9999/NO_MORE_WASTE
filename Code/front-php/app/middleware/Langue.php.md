# `app/middleware/Langue.php` — le site multilingue

> ⏱️ **Lecture : ~10 min** · 751 mots, 34 lignes de code

> **Phase 8** — 🟥 **exigence directe du sujet** : *« comme l'association s'est installée à l'étranger, à la demande des municipalités, le site devra être multilingue »*.

## Le principe : des clés, pas des textes

La règle est simple et ne souffre aucune exception : **aucun texte affiché n'est écrit en dur dans une page**.

À la place, on écrit une **clé**, et cette classe va chercher le texte correspondant dans la langue active :

```php
<h1><?= Langue::t('connexion.titre') ?></h1>
```

| Langue | Résultat |
|---|---|
| `fr` | Connexion |
| `en` | Sign in |
| `it` | Accedi |
| `pt` | Entrar |

Une seule page, quatre langues. Sans ce mécanisme, il faudrait quatre copies de chaque page — et à la moindre correction, penser à la reporter quatre fois.

Les traductions vivent dans quatre fichiers JSON : [`app/locales/fr.json`](../locales/fr.json), `en.json`, `it.json`, `pt.json`. Chacun est un simple objet `clé → texte`.

> ⚠️ **Ces fichiers sont régénérés depuis la base** par le back-office : les modifier à la main est inutile, la prochaine publication écrasera tout. Voir [`app/locales/LISEZ-MOI.md`](../locales/LISEZ-MOI.md).

> **i18n** est l'abréviation habituelle d'*internationalization* : `i`, 18 lettres, `n`. Tu la croiseras partout.

Le choix des langues suit le sujet : l'association s'est implantée à Naples (italien), Porto (portugais) et Dublin (anglais).

## Comment la langue est choisie

Quatre sources, dans un ordre de priorité qui n'est pas arbitraire :

```
1. ?lang=en dans l'URL   -> l'utilisateur vient de cliquer sur un drapeau
2. la session            -> il avait déjà choisi
3. Accept-Language       -> deviné d'après son navigateur
4. le français           -> valeur par défaut
```

**La logique : du plus explicite au plus deviné.** Un choix volontaire doit toujours l'emporter sur une déduction automatique. Si un Italien clique sur « FR », le site passe en français et **y reste** — même si son navigateur est configuré en italien. L'inverse serait insupportable à l'usage.

L'étape 2 est ce qui rend le choix persistant : sans elle, il faudrait remettre `?lang=it` dans chaque adresse.

### La détection par le navigateur

Chaque navigateur envoie un en-tête décrivant les langues de son utilisateur :

```
Accept-Language: fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7
```

Les `q=` sont des niveaux de préférence (de 0 à 1). On reste simple : on prend les **deux premières lettres** de chaque entrée (`fr-FR` → `fr`) et on garde la première langue qu'on sait afficher.

Ça suffit largement ici, et surtout ça reste explicable. Gérer les `q=` correctement demanderait de trier par pondération pour un bénéfice quasi nul.

## Le filet de sécurité à deux niveaux

```php
public static function t(string $cle): string
{
    return self::$traductions[$cle]        // 1. la langue active
        ?? self::$traductionsDefaut[$cle]  // 2. sinon, le français
        ?? $cle;                           // 3. sinon, la clé elle-même
}
```

`??` est l'opérateur de fusion null : « si ça existe, prends-le ; sinon, passe à la suite ».

Ce double filet évite le pire scénario : une clé oubliée dans `it.php` produirait sinon un **trou blanc** dans la page italienne, invisible tant que personne ne consulte cette version.

Ici : on retombe d'abord sur le français (le site reste utilisable), et si la clé n'existe nulle part, on affiche **la clé elle-même** — `connexion.titre` s'affiche à l'écran. C'est moche, et c'est exactement le but : **une erreur visible est une erreur qui sera corrigée**.

⚠️ **Règle de travail** : toute clé ajoutée dans `fr.php` doit l'être dans les trois autres fichiers. `fr.php` est le fichier de référence.

## Le sélecteur de langue

Dans [`layout.php`](../views/layout_back.php) :

```php
<a href="?lang=<?= $code ?>"><?= strtoupper($code) ?></a>
```

Astuce simple : `?lang=it` **sans chemin** conserve la page courante. On change de langue **sans quitter la page** où on se trouve — si tu es sur la liste des commerçants, tu y restes.

## Pourquoi seule l'interface est traduite

Les libellés (menus, boutons, titres de colonnes) sont traduits. **Le contenu métier ne l'est pas** : le nom d'un commerçant, la description d'un service, une adresse restent tels qu'ils ont été saisis.

C'est un choix assumé, et c'est aussi ce que font la plupart des sites réels. Traduire le contenu métier imposerait une table de traductions en base, une saisie en quatre langues dans le back-office pour chaque service créé, et un travail de traduction humain permanent. Hors de proportion avec ce que demande le sujet.

**À savoir formuler à l'oral** : « le site est multilingue au niveau de l'interface ; le contenu saisi par l'association reste dans sa langue d'origine ».

## Comment le vérifier soi-même

```bash
for lang in fr en it pt; do
  curl -s "http://localhost:8080/connexion?lang=$lang" | grep -o '<h1>[^<]*</h1>'
done
```

```
<h1>Connexion</h1>
<h1>Sign in</h1>
<h1>Accedi</h1>
<h1>Entrar</h1>
```

Détection automatique par le navigateur :

```bash
curl -s -H "Accept-Language: it-IT,it;q=0.9" http://localhost:8080/connexion | grep -o '<h1>[^<]*</h1>'
# <h1>Accedi</h1>
```

Persistance en session (on choisit l'italien sur l'accueil, puis on va ailleurs sans préciser la langue) :

```bash
curl -s -c /tmp/c.txt "http://localhost:8080/?lang=it" > /dev/null
curl -s -b /tmp/c.txt http://localhost:8080/connexion | grep -o '<h1>[^<]*</h1>'
# <h1>Accedi</h1>   -> le choix a bien été retenu
```

Vérifié le 2026-08-01 : les quatre langues, la détection navigateur et la persistance fonctionnent.

## Fichiers liés

- [../locales/LISEZ-MOI.md](../locales/LISEZ-MOI.md) — les fichiers de traduction et pourquoi on ne les édite pas à la main
- [../services/Traductions.php.md](../services/Traductions.php.md) — la synchronisation base ↔ fichiers
- [../views/layout.php.md](../views/layout_back.php.md) — où se trouve le sélecteur
- [Auth.php.md](Auth.php.md) — l'autre middleware
