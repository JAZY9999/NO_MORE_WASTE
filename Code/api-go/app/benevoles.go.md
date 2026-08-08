# app/benevoles.go — candidature, validation, documents, compétences

> ⏱️ **Lecture : ~13 min** · 850 mots

## C'est quoi ce fichier ?

Le module le plus riche du projet en nombre de routes (10 handlers), qui couvre tout le cycle de vie d'un bénévole demandé par le sujet : "gérer le suivi des bénévoles, depuis leur candidature jusqu'à leur affectation à un service donné".

## Fonction 1 : PoserCandidature — la première route VRAIMENT publique du projet

```go
func PoserCandidature(w http.ResponseWriter, r *http.Request) {
    var b models.Benevole
    err := json.NewDecoder(r.Body).Decode(&b)
    ...
    b.Statut = "candidat"
    b.UtilisateurId = nil

    // Si connecte, on rattache la fiche a son compte -- deduit du JETON,
    // jamais du corps de la requete.
    if emailConnecte, _, err := utils.VerifyJWT(r.Header.Get("Authorization")); err == nil {
        utilisateur, _ := db.GetUtilisateurByEmail(emailConnecte)
        if utilisateur != nil {
            b.UtilisateurId = &utilisateur.Id
        }
    }

    id, err := db.CreateBenevole(b)
    ...
}
```

### Pas de `utils.RequireRole` ici — et c'est volontaire
Contrairement à TOUS les autres handlers du projet jusqu'ici (protégés par `admin_back`/`staff_back`), cette route n'a AUCUNE vérification de rôle. Le sujet dit explicitement : "chacun peut s'inscrire auprès de NO MORE WASTE et proposer ses services" — n'importe quelle personne, même pas connectée, doit pouvoir déposer une candidature de bénévolat. C'est le même esprit que `POST /auth/register` (voir `app/auth.go.md`) : une route publique par nature.

`b.Statut = "candidat"` est codé en dur, écrasant ce que le client aurait pu envoyer — une candidature commence TOUJOURS à ce statut, jamais directement "valide".

### 🔒 Le rattachement au compte, et pourquoi il ne vient JAMAIS du corps

Ajouté en portant l'espace bénévole du front. Un visiteur **connecté** qui candidate voit sa fiche rattachée à son compte — son espace bénévole fonctionnera dès la validation, sans requête SQL à la main.

Le point à retenir : **le compte vient du jeton, jamais d'un champ JSON envoyé par le client**.

```go
b.UtilisateurId = nil   // on efface ce que le client aurait pu envoyer
```

Cette route est **publique**. Si elle acceptait un `utilisateur_id` dans le corps, n'importe qui pourrait poster :

```json
{"nom": "Pirate", "prenom": "X", "utilisateur_id": 8}
```

… et accrocher une fiche bénévole au compte de quelqu'un d'autre. C'est vérifié par `tester-espace-client.py` : une candidature avec un `utilisateur_id` forgé aboutit bien, mais le champ reste `NULL` en base.

Sans jeton (visiteur anonyme, cas normal d'un inconnu qui découvre l'association), `b.UtilisateurId` reste `nil` : la candidature est anonyme, comme avant.

## Fonctions 2 et 3 : ListerBenevoles, ObtenirBenevole

Classiques, protégées par rôle (le staff doit pouvoir consulter les candidatures reçues).

## Fonction 4 : ValiderBenevole — LE handler le plus important du module

```go
if dto.Statut == "valide" {
    documentsOk, err := db.TousLesDocumentsSontValides(id)
    ...
    if !documentsOk {
        http.Error(w, "Impossible de valider : tous les documents du benevole doivent d'abord etre valides", http.StatusBadRequest)
        return
    }
}

err = db.UpdateStatutBenevole(id, dto.Statut)
```

C'est ici que se joue concrètement l'exigence du sujet "à condition de valider un certain nombre de conditions". Avant d'autoriser le passage au statut `"valide"`, on vérifie avec `db.TousLesDocumentsSontValides` (voir `db/benevolesRepository.go.md`) que TOUTES les pièces exigées ont déjà été validées par le staff — si ce n'est pas le cas, on refuse avec un message clair (`400 Bad Request`), plutôt que de laisser passer une validation "incomplète".

Notez que cette vérification ne s'applique QUE quand on essaie de passer à `"valide"` — les autres transitions (`"en_validation"`, `"refuse"`) ne sont pas bloquées par cette condition, ce qui a du sens : refuser un bénévole ne nécessite pas que ses documents soient validés.

## Fonctions 5, 6, 7 : gestion des documents (les "conditions")

`AjouterDocumentBenevole` : le staff enregistre qu'un document est attendu/reçu pour un bénévole (`valide` vaut `false` par défaut, voir `schema.sql`). `ListerDocumentsBenevole` : consultation. `ValiderDocumentBenevole` : le staff marque UN document précis comme validé (`PUT /benevoles/{id}/documents/{docId}/validation`) — c'est cette action, répétée pour chaque document, qui permettra ensuite à `ValiderBenevole` de réussir.

### Une route avec DEUX identifiants dans l'URL
```go
http.HandleFunc("PUT /benevoles/{id}/documents/{docId}/validation", app.ValiderDocumentBenevole)
```
Premier cas du projet avec deux segments variables dans la même URL. `r.PathValue("id")` récupère le premier (`{id}`, le bénévole), `r.PathValue("docId")` récupère le second (`{docId}`, le document) — chaque `{...}` dans le motif de route doit avoir un nom DIFFÉRENT pour que Go puisse les distinguer.

## Fonctions 8, 9, 10, 11 : gestion des compétences

`ListerCompetences` : le référentiel fixe (chauffeur, cuisinier...). `ListerCompetencesBenevole` : les compétences d'UN bénévole précis. `AjouterCompetenceBenevole`/`RetirerCompetenceBenevole` : associer/dissocier, avec les mêmes vérifications systématiques déjà vues ailleurs — le bénévole existe (404 sinon), la compétence existe (404 sinon), pas de doublon (409 sinon pour l'ajout).

### `DELETE`, la première utilisation de cette méthode HTTP dans le projet
```go
http.HandleFunc("DELETE /benevoles/{id}/competences/{competenceId}", app.RetirerCompetenceBenevole)
```
Vu dans le cours HTTP (`http.pdf`) : `DELETE` = "suppression de ressource". Ici, la "ressource" supprimée n'est pas le bénévole ni la compétence elles-mêmes, mais l'ASSOCIATION entre les deux (la ligne dans `benevole_competences`) — conforme à la convention REST : l'URL désigne précisément quelle association on vise (ce bénévole, cette compétence).

## Piège à connaître

`AjouterCompetenceBenevole` et `RetirerCompetenceBenevole` (les handlers, pas les fonctions du repository qui portent les mêmes noms) partagent volontairement les mêmes noms que dans `db/benevolesRepository.go.md` — ce n'est pas un problème en Go car ils sont dans des packages DIFFÉRENTS (`app` contre `db`), donc `app.AjouterCompetenceBenevole` et `db.AjouterCompetenceBenevole` sont deux fonctions bien distinctes, jamais confondues grâce au préfixe du package.
