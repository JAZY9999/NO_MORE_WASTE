# `espace_benevole.php` — l'espace d'un bénévole

> Vue rendue par `EspaceBenevoleController::index()`.

Trois sections, qui répondent aux questions dans l'ordre où elles se posent : **où en est ma candidature**, **quand suis-je attendu**, **que sais-je faire**.

## La section qui justifie l'écran

Le sujet dit qu'on devient bénévole « à condition de valider un certain nombre de conditions ». Cet écran dit au candidat **ce qui manque** :

- une barre `2 / 3 justificatifs vérifiés` ;
- chaque justificatif marqué « Vérifié » ou « À vérifier ».

Sans ça, un candidat bloqué ne comprend pas pourquoi on ne lui confie aucune mission. Il ne relance pas : il abandonne.

C'est la même information que la fiche du back-office, vue de l'autre côté — là-bas elle décide si le bouton « Valider » s'active, ici elle explique l'attente.

## Trois messages différents pour une liste vide

| Situation | Message |
|---|---|
| pas encore validé | « Votre planning apparaîtra ici une fois votre candidature validée. » |
| validé, rien de prévu | « Aucune mission à venir pour le moment. » |
| aucune compétence | « L'association les ajoute après entretien. » |

Les trois disent que la liste est vide. Seul le premier dit **pourquoi**, et c'est celui qui compte.

## Les heures ne sont pas découpées

```php
$horaire = ($p['heure_debut'] ?? '') . ' – ' . ($p['heure_fin'] ?? '');
```

L'API renvoie déjà `"HH:MM"` — c'est le correctif `to_char` appliqué côté SQL en vague 2. Avant lui, il aurait fallu ignorer onze caractères ici et partout ailleurs.

## Le statut pilote la couleur

```php
$couleurs = ['candidat' => 'warning', 'valide' => 'success', …];
```

Un tableau plutôt qu'une cascade de `if`. Ajouter un statut, c'est ajouter une ligne — et la valeur par défaut (`secondary`) évite qu'un statut inconnu casse la page.

➡️ **Explication complète : [../../controllers/front/EspaceBenevoleController.php.md](../../controllers/front/EspaceBenevoleController.php.md)**
