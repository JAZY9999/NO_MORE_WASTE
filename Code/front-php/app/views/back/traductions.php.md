# `traductions.php` — la gestion du multilingue depuis le back-office

> Vue rendue par `TraductionsController`. C'est l'écran qui rend le site traduisible **sans toucher au code**.

## Deux moitiés d'écran

1. **Les langues** : en créer une, en supprimer une.
2. **Les clés** : le tableau des libellés, une colonne par langue, modifiables sur place.

## Les deux boutons de synchronisation

C'est le point qu'il faut savoir expliquer, parce qu'il n'est pas devinable.

| Bouton | Sens | Quand s'en servir |
|---|---|---|
| **Base vers fichiers** | table `traductions` → `app/locales/*.json` | après avoir modifié des libellés ici |
| **Fichiers vers base** | `app/locales/*.json` → table `traductions` | après avoir ajouté des clés dans les fichiers |

La **base est la source de vérité**, les fichiers JSON sont un **cache de lecture**. Le site lit les JSON — interroger la base pour deux cents libellés à chaque page serait du gâchis — mais c'est la base qu'on modifie.

### Le piège à connaître

Ajouter des clés **dans les JSON seuls** ne suffit pas : le prochain « Base vers fichiers » les effacerait, puisque la base ne les connaît pas.

La séquence correcte est donc : écrire dans les quatre JSON → venir sur cet écran → **« Fichiers vers base »**.

C'est aussi ce qu'il faut faire après avoir lancé `tests/tester-tous-les-endpoints.py`, qui vide la table des traductions en fin de course pour rester rejouable. Le script le rappelle lui-même à l'écran.

## Une seule route POST pour sept actions

Créer, modifier, supprimer une clé ; créer, supprimer une langue ; les deux synchronisations. Sept boutons, **un seul** point d'entrée, distingué par un champ caché `action`.

C'est le même motif que la fiche d'un bénévole, et il évite une explosion de routes pour un unique écran.

## Pourquoi pas une bibliothèque i18n

Le sujet demande un site multilingue, pas l'usage d'un outil particulier. Un système fait maison — table, cache JSON, écran d'administration — se montre et s'explique de bout en bout pendant la soutenance, et il est **administrable par quelqu'un qui ne code pas**. Une bibliothèque à fichiers PHP obligerait à modifier le code pour corriger une faute d'orthographe.

## Fichiers liés

- [../../controllers/back/TraductionsController.php.md](../../controllers/back/TraductionsController.php.md)
- [../../middleware/Langue.php.md](../../middleware/Langue.php.md) — la lecture des JSON
