# `app/locales/` — les fichiers de langue

> ⏱️ **Lecture : ~10 min** · 824 mots, 15 lignes de code

> ⚠️ **Ne modifie pas ces fichiers à la main.** Ils sont **régénérés** depuis la base de données par le back-office (écran *Traductions*). Toute modification manuelle sera écrasée à la prochaine synchronisation.
>
> Pour changer un libellé : `/back/traductions`.

## Pourquoi deux endroits pour la même chose

C'est **la** question à savoir répondre, parce qu'à première vue stocker les traductions deux fois (en base *et* dans des fichiers) ressemble à une erreur de conception. Ça n'en est pas une : les deux ne servent pas au même usage.

| | Rôle | Pourquoi |
|---|---|---|
| **Base de données** | **éditer** | Le back-office y ajoute, modifie, supprime. Plusieurs personnes peuvent y travailler. Les données survivent à un `docker compose down`. |
| **Fichiers JSON** | **lire** | Une page affiche 30 à 50 libellés. Les chercher un par un en base ferait autant d'allers-retours réseau **pour un seul écran**. |

C'est le principe d'un **cache** : la base fait autorité, le fichier est une copie rapide à lire, régénérée à la demande.

**Le chiffre qui justifie tout** : sans les fichiers, afficher le back-office demanderait une cinquantaine d'appels à l'API rien que pour les libellés du menu et des colonnes. Avec, c'est **un seul accès disque**.

Second bénéfice, souvent oublié : **si l'API tombe, le site reste lisible**. Les libellés viennent du disque, pas du réseau.

## Le cycle complet

```
1. Le staff modifie un libellé       ->  écran /back/traductions
2. Enregistré en base                ->  PUT /traductions/{id}  (API Go)
3. Clic « Base vers fichiers »       ->  ces fichiers .json sont réécrits
4. Le site affiche le nouveau texte  ->  Langue::t() lit le .json
```

⚠️ **L'étape 3 n'est pas automatique.** Tant qu'on ne clique pas, le site continue d'afficher l'ancien texte alors que la base contient déjà le nouveau. C'est déroutant la première fois — d'où l'avertissement affiché en permanence sur l'écran du back-office.

**Pourquoi ne pas régénérer automatiquement ?** Parce qu'on corrige rarement un seul libellé : on en modifie dix, puis on publie. Régénérer à chaque frappe réécrirait quatre fichiers pour rien. C'est le principe « enregistrer » puis « publier ».

## Le sens inverse : fichiers → base

Le bouton « Fichiers vers base » lit ces `.json` et les charge en base. Deux usages :

- **la mise en place** — c'est ce qui a servi à charger les 63 premiers libellés ;
- **restaurer** la base après une réinitialisation (`docker volume rm code_pgdata`), sans ressaisir les libellés un par un.

L'API enregistre chaque libellé en « créer ou mettre à jour » (`ON CONFLICT` en SQL). Relancer l'import deux fois **ne crée aucun doublon** : l'opération est répétable sans risque.

## Le format

```json
{
  "app.nom": "NO MORE WASTE",
  "connexion.titre": "Connexion",
  "nav.accueil": "Accueil"
}
```

Un objet plat, `clé => texte`. Les clés sont **triées alphabétiquement** à la génération : sans ça, l'ordre dépendrait de la base et le fichier changerait à chaque export même sans modification réelle — impossible de voir ce qui a bougé dans un outil de comparaison.

Les accents sont écrits tels quels (`JSON_UNESCAPED_UNICODE`) plutôt qu'en `é` : le fichier reste lisible à l'œil.

## Le filet de sécurité à deux niveaux

Dans `Langue::t()` :

```php
return self::$traductions[$cle]        // 1. la langue active
    ?? self::$traductionsDefaut[$cle]  // 2. sinon, le français
    ?? $cle;                           // 3. sinon, la clé elle-même
```

Une clé absente de `it.json` retombe sur le français : la page italienne reste utilisable. Absente partout, c'est **la clé technique** (`nav.accueil`) qui s'affiche — moche exprès : **une erreur visible est une erreur qui sera corrigée**, alors qu'un blanc passerait inaperçu.

Sur l'écran du back-office, les traductions manquantes apparaissent comme des champs vides bordés d'orange, remplissables directement.

## Le garde-fou de l'export

Si la base est vide, l'export est **refusé** au lieu d'écraser ces fichiers par du vide :

> « Aucune traduction en base : export annulé pour ne pas effacer les fichiers existants. »

Sans ce contrôle, une base fraîchement réinitialisée + un clic sur « Base vers fichiers » = **toutes les traductions perdues**, et un site affichant des clés techniques partout. C'est le scénario catastrophe que ce test évite.

## D'où vient cette architecture

Elle reprend celle du projet **UpcycleConnect** (table `traduction` + fichiers `locales/*.json` + double synchronisation depuis le back-office), adaptée ici à PostgreSQL.

Deux différences assumées, à savoir justifier :

1. **Une contrainte `UNIQUE (cle, code_langue)`** en base. UpcycleConnect ne l'avait pas : rien n'empêchait deux lignes `nav.accueil` en français, et l'affichage devenait imprévisible. C'est aussi elle qui rend l'`ON CONFLICT` possible.
2. **La clé étrangère est le code de langue** (`'fr'`) et non un identifiant numérique. On évite une jointure pour retrouver `fr` à partir d'un numéro, et le code est déjà ce qu'on manipule partout ailleurs (nom du fichier, `?lang=fr`, `<html lang="fr">`).

## Historique

Avant le 2026-08-03, les traductions étaient dans quatre fichiers PHP (`app/i18n/*.php`) écrits en dur. Ils ont été convertis en JSON puis supprimés : le dossier `app/i18n/` n'existe plus.

La raison du changement : le sujet demande un back-office, et un tableau PHP figé dans le code ne se modifie pas depuis une interface — il faut un déploiement pour corriger une faute de frappe.

## Fichiers liés

- [../middleware/Langue.php.md](../Middleware/Langue.php.md) — comment la langue est choisie et les libellés lus
- [../services/Traductions.php.md](../Services/Traductions.php.md) — le code des deux synchronisations
- [../controllers/back/TraductionsController.php.md](../Controllers/Back/TraductionsController.php.md) — l'écran de gestion
