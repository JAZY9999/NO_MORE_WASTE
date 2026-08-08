# `app/services/Traductions.php` — les deux synchronisations

> ⏱️ **Lecture : ~10 min** · 700 mots, 32 lignes de code

> À lire avec [`../locales/LISEZ-MOI.md`](../locales/LISEZ-MOI.md), qui explique **pourquoi** ce fichier existe.

## Ce que fait ce fichier

Deux méthodes, deux sens :

| Méthode | Sens | Quand |
|---|---|---|
| `exporter()` | base → fichiers JSON | après avoir modifié des libellés |
| `importer()` | fichiers JSON → base | mise en place, ou restauration après reset |

Le contrôleur du back-office les appelle sur clic d'un bouton.

## `exporter()` — le sens courant

### Le garde-fou, la partie la plus importante

```php
if (empty($traductions)) {
    return $this->echec(
        "Aucune traduction en base : export annule pour ne pas effacer les fichiers existants."
    );
}
```

**Le scénario que ça évite :** tu réinitialises la base (`docker volume rm code_pgdata`), tu ouvres le back-office par réflexe, tu cliques sur « Base vers fichiers ». Sans ce test, les quatre fichiers `.json` seraient réécrits **vides**. Résultat : le site affiche `nav.accueil`, `connexion.titre` partout, et les 63 libellés dans 4 langues sont **définitivement perdus** — ils n'existaient plus qu'à cet endroit.

Cinq lignes qui protègent contre une perte irréversible. C'est repris d'UpcycleConnect, où le même contrôle existe.

### Le regroupement en un seul passage

```php
$parLangue = [];
foreach ($traductions as $t) {
    $parLangue[$t['code_langue']][$t['cle']] = $t['valeur'];
}
```

L'API renvoie une liste à plat (`{cle, valeur, code_langue}`). On la réorganise **une fois** en `[langue][clé] => valeur`.

L'approche naïve serait de boucler sur toutes les traductions **pour chaque langue** : avec 4 langues et 252 traductions, ça ferait 1008 tours de boucle au lieu de 252. Sans conséquence visible ici, mais c'est le réflexe à avoir.

### `ksort()` — un détail qui compte

```php
ksort($donnees);
```

Trie les clés par ordre alphabétique **avant** d'écrire le fichier.

Sans ça, l'ordre dépendrait de celui renvoyé par la base. Le fichier changerait donc **à chaque export**, même sans aucune modification réelle — et comparer deux versions deviendrait impossible : tout apparaîtrait modifié. Avec le tri, seules les vraies différences ressortent.

### Les options de `json_encode`

```php
json_encode($donnees, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
```

| Option | Sans elle | Avec elle |
|---|---|---|
| `PRETTY_PRINT` | tout sur une ligne | un libellé par ligne, lisible |
| `UNESCAPED_UNICODE` | `Bénévoles` | `Bénévoles` |
| `UNESCAPED_SLASHES` | `\/back\/traductions` | `/back/traductions` |

Le `|` est un **OU binaire** : il combine plusieurs options en un seul nombre. C'est la façon habituelle de passer des drapeaux en PHP.

Sans `UNESCAPED_UNICODE`, les fichiers resteraient valides mais illisibles — un vrai frein quand on veut vérifier une traduction rapidement.

## `importer()` — le sens inverse

### On n'importe que les langues connues

```php
if (!in_array($code, $codesConnus, true)) {
    $ignorees[] = $code;
    continue;
}
```

Un fichier `brouillon.json` ou `fr.json.bak` oublié dans le dossier ne doit pas créer une langue fantôme qui apparaîtrait ensuite dans le sélecteur du site. On ignore, et on le **signale** dans le compte rendu plutôt que de le passer sous silence.

Le `true` en troisième argument d'`in_array` impose une comparaison **stricte** (type compris). Sans lui, PHP fait des conversions surprenantes — un classique des bugs difficiles à trouver.

### L'import est répétable

Chaque fichier part vers `POST /traductions/import`, qui enregistre en « créer ou mettre à jour » (`ON CONFLICT` côté SQL). Relancer l'import **ne crée aucun doublon**.

C'est ce qu'on appelle une opération **idempotente** : la refaire donne le même résultat. Utile, parce qu'on ne sait jamais vraiment si un import a été fait ou non — on peut relancer sans risque.

## Le format de retour

Les deux méthodes renvoient la même structure :

```php
['succes' => bool, 'message' => string, 'fichiers' => int, 'cles' => int]
```

Le contrôleur n'a alors qu'à regarder `succes` pour choisir entre un message vert et un message rouge. C'est plus simple qu'une exception ici : un échec de synchronisation n'est pas un accident du programme, c'est un cas de fonctionnement normal (base vide, droits d'écriture manquants) que l'utilisateur doit pouvoir lire et corriger.

## Le piège des droits d'écriture

```php
if (@file_put_contents(...) === false) {
    return $this->echec("Ecriture impossible dans " . $dossier . " ...");
}
```

Le dossier `app/locales/` doit être **inscriptible par PHP**. Dans le conteneur, PHP tourne sous l'utilisateur `www-data` : si le dossier appartient à `root` sans droits d'écriture pour les autres, l'export échoue.

Le `@` supprime l'avertissement PHP pour qu'on affiche **notre** message, plus clair. C'est l'un des rares cas où `@` est justifié : on gère l'erreur juste après.

Si tu vois ce message :

```bash
docker compose exec front-php chmod -R 775 /var/www/app/app/locales
```

## Comment le vérifier soi-même

```bash
# 1. Modifier un libellé dans /back/traductions, puis cliquer « Base vers fichiers »
# 2. Vérifier que le fichier a bougé :
docker compose exec front-php cat /var/www/app/app/locales/fr.json | head -20
```

## Fichiers liés

- [../locales/LISEZ-MOI.md](../locales/LISEZ-MOI.md) — pourquoi base **et** fichiers
- [../controllers/back/TraductionsController.php.md](../Controllers/Back/TraductionsController.php.md) — l'écran qui appelle ces méthodes
- [ApiClient.php.md](ApiClient.php.md) — comment les appels partent vers l'API
- [../middleware/Langue.php.md](../Middleware/Langue.php.md) — qui lit les fichiers produits ici
