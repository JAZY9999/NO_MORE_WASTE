# La candidature bénévole — contrôleur et vues

> ⏱️ **Lecture : ~8 min** · 750 mots

> Couvre `app/controllers/front/CandidatureController.php`, `app/views/front/candidature.php` et `candidature_merci.php`.

## Ce que le sujet demande

> *« **chacun** peut s'inscrire pour devenir bénévole **à condition de valider un certain nombre de conditions** »*

Les deux moitiés de la phrase se lisent dans le code.

**« Chacun »** : la page est publique, aucune connexion demandée. C'est la porte d'entrée de l'association — exiger un compte avant même de savoir si la personne sera retenue ferait perdre des candidats.

**« à condition de valider »** : la candidature est enregistrée au statut `candidat`, jamais `valide`. C'est le back-office qui décide ensuite.

## Deux champs obligatoires, pas huit

```php
if ($nom === '' || $prenom === '') { … }
```

Nom et prénom. C'est tout.

Chaque champ obligatoire supplémentaire fait abandonner des candidats en cours de saisie. L'association peut demander le reste au premier contact ; elle ne peut pas rattraper quelqu'un qui a fermé l'onglet.

## Le rattachement au compte vient du jeton, jamais du corps

C'est le point de sécurité de l'écran.

```php
$this->api->post('/benevoles/candidature/', $candidature, Auth::jeton());
```

Si le visiteur est connecté, son jeton part avec la requête et l'API rattache la fiche à son compte — son espace bénévole fonctionnera dès la validation.

**Le corps ne contient jamais d'identifiant de compte.** La route est publique : accepter un `utilisateur_id` envoyé par le client permettrait à n'importe qui d'accrocher une fiche bénévole au compte d'autrui.

L'API l'impose de son côté — elle efface la valeur reçue avant tout traitement — et c'est vérifié par la suite de tests :

```
[OK] le compte visé dans le corps est ignoré
```

## Une case à cocher se lit avec `isset`

```php
$candidature['permis_conduire'] = isset($_POST['permis_conduire']);
```

Une case non cochée **n'est pas envoyée du tout** par le navigateur. Elle ne vaut pas `"0"`, ni `""` : elle est absente.

Lire `$_POST['permis_conduire'] ?? false` marcherait aussi, mais `isset` dit exactement ce qui se passe : la question est *est-elle là*, pas *que vaut-elle*.

## Les champs facultatifs ne partent pas vides

```php
foreach (['email', 'telephone', 'adresse'] as $champ) {
    $valeur = trim($_POST[$champ] ?? '');
    if ($valeur !== '') { $candidature[$champ] = $valeur; }
}
```

Un formulaire HTML envoie **toujours** ses champs, même vides. Enregistrer `email = ""` vaut moins que ne rien enregistrer : une chaîne vide se comporte comme une adresse dans les requêtes, un `NULL` non.

Même motif que le scan des collectes.

## La saisie est conservée après une erreur

```php
$_SESSION['candidature_saisie'] = $_POST;
```

…relue puis effacée à l'affichage suivant.

Sans ça, une erreur sur un seul champ obligerait à retaper les six autres. C'est le genre de détail qui fait abandonner — et il ne coûte que trois lignes.

L'effacement immédiat (`unset` après lecture) est indispensable : sinon le formulaire se préremplirait indéfiniment, y compris pour une nouvelle candidature.

## Une page de remerciement à part entière

```php
Auth::rediriger('/benevoles/candidature/merci');
```

Pas un simple message flash. Deux raisons :

1. **Elle explique la suite** — les trois étapes reprennent exactement le parcours du back-office : candidat → justificatifs vérifiés → validé. Un candidat qui ignore ce qui l'attend relance l'association au bout de trois jours, ou se décourage.
2. **Elle survit à un rafraîchissement.** Un message flash disparaît au premier F5, et la page redevient un formulaire vide — comme si rien n'avait été envoyé.

C'est aussi le motif POST-puis-redirection : sans lui, recharger la page renverrait la candidature une seconde fois.

## Comment le vérifier soi-même

```bash
# candidature incomplète
curl -X POST http://localhost:8080/benevoles/candidature -b cookies.txt \
  --data-urlencode "nom=Dupont"
# -> « Le nom et le prénom sont obligatoires. »
#    et le champ nom est réaffiché avec « Dupont »

# candidature complète, anonyme
curl -X POST http://localhost:8080/benevoles/candidature \
  --data-urlencode "nom=Martin" --data-urlencode "prenom=Lea" \
  --data-urlencode "permis_conduire=on"
# -> 302 vers /benevoles/candidature/merci
# -> en base : statut=candidat, permis=true, utilisateur_id=NULL

# la même, en étant connecté
# -> utilisateur_id = celui du compte connecté

# tentative de s'accrocher au compte d'un autre
curl -X POST http://localhost:8080/api/benevoles/candidature/ \
  -d '{"nom":"Pirate","prenom":"X","utilisateur_id":8}'
# -> 201, mais utilisateur_id reste NULL : le corps est ignoré

# la page de remerciement se recharge
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8080/benevoles/candidature/merci
# -> 200
```

Vérifié le 2026-08-07, dans les quatre langues.

## Fichiers liés

- [../../views/front/candidature.php.md](../../views/front/candidature.php.md) et [../../views/front/candidature_merci.php.md](../../views/front/candidature_merci.php.md)
- [EspaceBenevoleController.php.md](EspaceBenevoleController.php.md) — la suite du parcours, côté candidat
- [../back/BenevolesController.php.md](../back/BenevolesController.php.md) — la validation, côté personnel
- [../../../../api-go/app/benevoles.go.md](../../../../api-go/app/benevoles.go.md) — la route publique et sa règle de rattachement
