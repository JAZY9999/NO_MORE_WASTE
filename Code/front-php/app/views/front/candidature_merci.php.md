# `candidature_merci.php` — après l'envoi

> Vue rendue par `CandidatureController::merci()`, après une redirection.

## Pourquoi une page entière plutôt qu'un message

**Elle explique la suite.** Les trois étapes reprennent exactement le parcours du back-office : candidat → justificatifs vérifiés → validé. Un candidat qui ignore ce qui l'attend relance l'association au bout de trois jours, ou se décourage.

**Elle survit à un rafraîchissement.** Un message flash disparaît au premier F5, et la page redevient un formulaire vide — comme si rien n'avait été envoyé.

C'est aussi le motif POST-puis-redirection : sans lui, recharger la page renverrait la candidature une seconde fois.

## Les étapes sont une boucle, pas trois blocs recopiés

```php
$etapes = ['etape_1', 'etape_2', 'etape_3'];
foreach ($etapes as $rang => $cle):
```

Le numéro affiché est `$rang + 1`. Ajouter une étape ne demande qu'une clé de traduction de plus — la numérotation suit toute seule, et les trois pastilles restent forcément cohérentes entre elles.

➡️ **Explication complète : [../../controllers/front/CandidatureController.php.md](../../controllers/front/CandidatureController.php.md)**
