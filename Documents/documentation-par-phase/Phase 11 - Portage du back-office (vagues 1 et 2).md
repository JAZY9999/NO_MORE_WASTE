# Phase 11 — Portage du back-office (vagues 1 et 2)

> ⏱️ **Lecture : ~15 min** · 1 400 mots

> **Légende** : 🟥 = écrit noir sur blanc dans le sujet · 🟧 = pas nommé littéralement mais indispensable pour un point du sujet · 🟦 = bonus, absent du sujet (assumé, pas du hors-sujet).
>
> Phase de **construction du front**. L'API était terminée et testée (78 routes, 77/77 et 17/17) ; c'est le site qui manquait. Le jury verra le site, pas les maquettes.

## Le besoin (pourquoi cette phase existe)

Fin de la phase 10, le déséquilibre était net : **31 écrans dessinés dans les maquettes, 5 vues PHP codées**. Toutes les fonctionnalités du sujet existaient côté API et étaient prouvées par des tests — mais rien de tout cela n'était utilisable par un humain.

Cette phase répond à :

- 🟥 **« un site web pour l'association »** avec back-office de gestion ;
- 🟥 **site multilingue** — chaque écran porté doit exister dans les quatre langues ;
- 🟥 **le récapitulatif PDF de chaque livraison** — l'API le fabriquait, aucun écran ne permettait de l'obtenir ;
- 🟥 **le planning des bénévoles exportable** (Excel → CSV) ;
- 🟧 un habillage cohérent entre les 22 écrans du back-office.

## Vague 1 — Le socle

C'est la vague qui conditionne toutes les autres. Les maquettes du back-office ont une **barre latérale**, l'ancien gabarit une **barre horizontale** : une refonte structurelle, pas un changement de couleurs.

### La décision principale : deux gabarits, choisis automatiquement

```php
$gabarit = str_starts_with($chemin, 'back/') ? 'layout_back' : 'layout_front';
```

**Pourquoi pas un fichier unique avec un `if`** : le `if` aurait englobé deux structures HTML entières — 350 lignes dont on ne lit jamais la moitié, avec des `endif` à 200 lignes de leur `if`. Précisément le fichier qu'on n'arrive plus à expliquer à l'oral.

**Pourquoi pas un paramètre explicite** (`Vue::afficher(..., 'back')`) : son mode de panne est *silencieux*. Avec une valeur par défaut `front`, un contrôleur de back-office qui oublie de la préciser rend sa page dans le mauvais habillage — sans erreur PHP, sans 500, juste un écran faux. Le genre de bug qu'on ne découvre qu'en démonstration.

Avec la détection par dossier, **on ne peut pas oublier** : le chemin de vue est obligatoire. Et la convention `back/` contre `front/` existait déjà trois fois dans le projet (dossiers de vues, fichiers de routes, préfixes d'URL) — correspondance vérifiée sur les 31 écrans, sans exception.

### Le travail organisé pour ne rien casser

Les étapes 1 à 5 sont **purement additives** : elles créent des fichiers que personne n'appelle encore. Pendant tout ce temps, le site continue de tourner sur l'ancien gabarit. **Une seule étape bascule tout, et elle ne touche qu'un fichier** (`Vue.php`). En cas de casse, on sait exactement où regarder.

### La faille trouvée en chemin 🟧

```php
$fichier = __DIR__ . '/views/' . $chemin . '.php';
extract($donnees);     // écrase les variables existantes
require $fichier;      // donc potentiellement n'importe quel fichier
```

Une vue à qui l'on passerait `['fichier' => ...]` — ce qui n'a rien d'absurde pour un écran de documents de bénévoles — ferait charger un fichier arbitraire du serveur. Corrigé d'un mot : `extract($donnees, EXTR_SKIP)`.

Pas exploitable ce jour-là (aucune vue ne recevait cette clé), mais ça l'aurait été au premier écran de documents.

### Le menu décrit une fois, dessiné une fois

`app/config/menu_back.php` contient les cinq sections et leurs entrées. Le bloc `menu_back.php` ne fait que le parcourir.

**Les libellés sont des clés de traduction, jamais du texte.** Un seul libellé écrit en clair casserait le multilingue sur les 22 écrans d'un coup — et on ne le verrait qu'en changeant de langue, c'est-à-dire peut-être jamais avant la soutenance.

La table `parents` résout l'entrée à surligner pour les écrans de détail. La règle en cascade (`$options['menu']` → `parents` → dernier segment du chemin) fait que **15 des 22 écrans ne demandent aucune configuration**.

## Vague 2 — Les six modules cités par le sujet

| Module | Écrans | Point du sujet |
|---|---|---|
| Bénévoles | liste + fiche | 🟥 compétences, validation conditionnée |
| Collectes | liste + détail avec scan | 🟥 « gérer le système des collectes » |
| Stocks + emplacements | 2 écrans | 🟥 « retrouvable TRÈS RAPIDEMENT » |
| Tournées | liste + détail + PDF | 🟥 récapitulatif PDF de chaque livraison |
| Services et créneaux | 1 écran + CSV | 🟥 services aux adhérents, affectation |

Chaque écran suit le même patron : garde → appel API → normalisation du `null` que Go renvoie pour une slice vide (`?? []`) → `Vue::afficher`.

### Porter un écran, c'est tester l'API pour de vrai

C'est le résultat le plus important de cette phase. **Les deux suites automatiques passaient avant (77/77, 17/17) et passent toujours après — et pourtant quatre défauts réels ont été trouvés.**

Un test vérifie ce qu'on a pensé à vérifier. Un écran, lui, demande à l'API *tout ce dont il a besoin pour être utilisable*.

#### 1. 🟥 Le PDF exigé par le sujet était inatteignable

`GET /tournees/{id}/etapes` disait qu'un arrêt était livré, mais ne donnait aucun moyen de retrouver **sa** livraison — donc aucun moyen de construire le lien vers le récapitulatif.

Correction : un champ `livraison_id`, alimenté par un `LEFT JOIN`. **`LEFT` et non `JOIN` simple** — avec un JOIN ordinaire, les arrêts pas encore clôturés disparaîtraient, et l'écran ne montrerait plus que le travail déjà fait.

#### 2. 🟧 Les heures s'affichaient « 0000- »

`heure_prevue` est une colonne `TIME`. Lue dans une chaîne Go, `database/sql` la reçoit comme une date complète : `"0000-01-01T10:30:00Z"`.

Corrigé à la source par `to_char(heure_prevue, 'HH24:MI')`. Découper la chaîne côté PHP aurait marché, mais obligerait **chaque** consommateur de l'API à savoir qu'il faut ignorer onze caractères. Une API qui renvoie une heure doit renvoyer une heure.

Même défaut, même correctif, sur les créneaux de service.

#### 3. 🟧 Le type de service était un champ libre

La colonne `type` a une contrainte `CHECK` : sept valeurs. Un champ texte transformait une faute de frappe en erreur 500. Corrigé en menu déroulant, plus une revalidation côté serveur.

#### 4. 🟧 Une violation de `CHECK` répondait 500 au lieu de 400

C'est une faute du client, pas du serveur. Le code PostgreSQL `23514` a rejoint `23503` et `23505` dans `utils.ErreurServeur`.

Troisième fois que ce fichier central sert. Le motif se répète : un code d'erreur découvert en testant un écran, ajouté à un seul endroit, et les 78 routes en bénéficient.

### 🟥 Les fichiers téléchargeables passent par le front

Le lien évident serait `<a href="/api/livraisons/1/pdf">`. Vérifié plutôt que supposé : **401 Jeton invalide**.

Le jeton JWT vit dans la **session PHP**. Le navigateur qui suit ce lien n'envoie aucune preuve d'identité à l'API. Le front sert donc de relais — et au passage, la garde de rôle s'applique aussi au téléchargement : un récapitulatif de livraison n'est pas un document public.

| | PDF | CSV |
|---|---|---|
| Disposition | `inline` | `attachment` |
| Pourquoi | on le relit avant de l'imprimer pour signature | rien à montrer dans un navigateur, on veut Excel |

Les deux lisent `$reponse['brut']` et non `['corps']` : `json_decode` rend `null` sur un PDF.

### Ce qu'on ne duplique pas

L'affectation d'un bénévole exige deux conditions (statut validé, compétence requise). Le front n'en réimplémente aucune : il ne charge que les bénévoles validés, et laisse l'API refuser sur la compétence en affichant « requiert : cuisinier » pour rendre le refus compréhensible.

Dupliquer la règle la ferait diverger le jour où elle change côté API.

## Comment tester soi-même

```bash
# le cycle complet d'une livraison
curl -X POST http://localhost:8080/back/tournees/1 -b cookies.txt \
  -d "action=cloturer&etape_id=2&produit_id[]=3&quantite[]=2"
# -> produit 3 passe à « distribue », PDF disponible, doublon refusé en 409

# le PDF, servi par le front
curl -s -o recap.pdf -b cookies.txt http://localhost:8080/back/livraisons/2/pdf
head -c 8 recap.pdf        # %PDF-1.4

# le planning CSV
curl -s -b cookies.txt "http://localhost:8080/back/plannings?date=2026-08-11"

# type de service forgé
curl -X POST http://localhost:8080/api/services/ -H "Authorization: $TOKEN" \
  -d '{"nom":"Test","type":"invente"}'
# -> HTTP 400 (et non 500)

# les gardes, déconnecté
for u in /back /back/services /back/plannings /back/livraisons/1/pdf; do
  curl -s -o /dev/null -w "$u %{http_code}\n" "http://localhost:8080$u"
done
# -> 302 vers /connexion sur les quatre
```

**Le test à ne jamais sauter** : les quatre langues sur chaque écran porté. Un libellé oublié reste en français quand on change de langue — c'est le seul symptôme.

## Résultats vérifiés

- Les deux suites API au vert après modification de trois fichiers de l'API : **77/77**, **17/17**
- 9 écrans de back-office × 4 langues = **36 pages**, sans une seule clé de traduction non résolue
- **263 clés** de traduction par langue, base et fichiers alignés
- **Chaque fichier `.php` du front a son `.md`** ; 130 liens entre documents vérifiés, aucun mort

## Reste à faire

- Vague 3 : espace commerçant, espace bénévole, services publics, candidature
- Vague 4 : adhésions, bénéficiaires, campagnes, utilisateurs, profils de rappel
- 🟥 Déploiement sur serveur réel avec HTTPS (11.2)
- 🟥 Script d'installation (12.1) — règle au passage le problème du tout premier compte administrateur

## Fichiers liés

- [../../Code/front-php/app/Vue.php.md](../../Code/front-php/app/Vue.php.md) — le choix du gabarit et `$options`
- [../../Code/front-php/app/config/menu_back.php.md](../../Code/front-php/app/config/menu_back.php.md) — le menu et la table `parents`
- [../../Code/front-php/app/controllers/back/BenevolesController.php.md](../../Code/front-php/app/controllers/back/BenevolesController.php.md) — le patron, expliqué en détail
- [../../Code/front-php/app/controllers/back/TourneesController.php.md](../../Code/front-php/app/controllers/back/TourneesController.php.md) — le relais de fichier et les deux correctifs API
- [../../Code/front-php/app/controllers/back/ServicesController.php.md](../../Code/front-php/app/controllers/back/ServicesController.php.md) — les trois défauts trouvés en testant
- [Phase 10 - Consolidation API.md](Phase%2010%20-%20Consolidation%20API.md) — `utils.ErreurServeur`, enrichi ici du code `23514`
