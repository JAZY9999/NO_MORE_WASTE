# L'espace bénévole — contrôleur et vues

> ⏱️ **Lecture : ~8 min** · 700 mots

> Couvre `app/controllers/front/EspaceBenevoleController.php`, `app/views/front/espace_benevole.php` et `espace_benevole_sans_fiche.php`.

## Ce que le sujet demande

> *« chacun peut s'inscrire pour devenir bénévole **à condition de valider un certain nombre de conditions** »*

Cette phrase a deux moitiés. Le back-office traite la seconde (le personnel valide). **Cet écran traite ce qui manque entre les deux : dire au candidat où il en est.**

Un bénévole bloqué au statut « candidat » sans savoir pourquoi ne relance pas — il abandonne. C'est la raison d'être de l'écran, plus encore que le planning.

## L'ordre de la page répond aux questions dans l'ordre où elles se posent

1. **Où en est ma candidature** — le statut, et ce qui le bloque
2. **Quand suis-je attendu** — le planning
3. **Que sais-je faire** — les compétences

Un candidat n'a que faire de son planning : il n'en a pas. Un bénévole validé, lui, vient d'abord pour ses dates. L'ordre sert donc les deux, chacun trouvant sa réponse en haut de ce qui le concerne.

## Un appel qu'on évite

```php
if (($benevole['statut'] ?? '') === 'valide') {
    $planning = $this->extraire($this->api->get('/mon-espace/planning', Auth::jeton()));
}
```

Un candidat n'est affecté à rien : son planning est **toujours** vide. Faire l'appel quand même coûterait une requête pour un résultat connu d'avance.

La vue affiche alors une phrase différente selon le cas — « votre planning apparaîtra ici une fois votre candidature validée » plutôt que « aucune mission ». Les deux disent que la liste est vide ; seule la première dit **pourquoi**.

## La progression, calculée dans le contrôleur

```php
$valides = 0;
foreach ($documents as $d) {
    if (!empty($d['valide'])) { $valides++; }
}
```

`2 / 3 justificatifs vérifiés` : c'est ce chiffre qui explique le statut. La vue n'a qu'à l'afficher.

C'est la même information que la fiche du back-office, vue de l'autre côté : là-bas elle sert à décider si le bouton « Valider » s'active, ici à expliquer l'attente. **Une seule règle métier, deux lectures.**

## Ce que l'écran ne permet pas — et pourquoi c'est assumé

Un bénévole **ne peut pas déposer un justificatif depuis cet écran**. La route existe (`POST /benevoles/{id}/documents`) mais elle est réservée au personnel, et elle attend un `chemin_fichier` — pas un envoi de fichier.

Un vrai téléversement demanderait de gérer le stockage, les types de fichiers autorisés, la taille maximale, et l'accès aux fichiers déposés. C'est un chantier à part entière, hors du périmètre du sujet.

L'écran assume donc : il **montre** l'état du dossier et dit à qui s'adresser. C'est honnête, et c'est déjà ce qui manquait au candidat.

## Le 404 : compte sans fiche

Même cas que pour le commerçant, mais un scénario différent : quelqu'un a créé un compte sans jamais déposer de candidature — ou l'a déposée en anonyme, avant de créer son compte.

L'écran dédié l'oriente vers le formulaire de candidature, plutôt que de le laisser devant une page vide.

C'est d'ailleurs pour réduire ce cas que la candidature rattache automatiquement la fiche au compte quand le visiteur est déjà connecté.

## Comment le vérifier soi-même

```bash
# se connecter en bénévole, puis
curl -s -b cookies.txt http://localhost:8080/mon-espace/benevole
# -> statut « Candidat », « 0 / 0 justificatifs vérifiés »,
#    « Votre planning apparaîtra ici une fois votre candidature validée. »

# valider le bénévole depuis le back-office, puis recharger
# -> le planning remplace le message d'attente

# un adhérent ne doit pas entrer ici
curl -s -o /dev/null -w "%{http_code} %{redirect_url}\n" -b cookies-adherent.txt \
  http://localhost:8080/mon-espace/benevole
# -> 302 vers /

# déconnecté
# -> 302 vers /connexion
```

Vérifié le 2026-08-07, dans les quatre langues.

## Fichiers liés

- [../../views/front/espace_benevole.php.md](../../views/front/espace_benevole.php.md) — la vue
- [CandidatureController.php.md](CandidatureController.php.md) — comment la fiche est créée et rattachée
- [../back/BenevolesController.php.md](../back/BenevolesController.php.md) — la même règle, côté personnel
- [../../../../api-go/app/monEspace.go.md](../../../../api-go/app/monEspace.go.md) — les routes `/mon-espace`
