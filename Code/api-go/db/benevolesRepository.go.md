# db/benevolesRepository.go — bénévoles, documents, compétences

> ⏱️ **Lecture : ~5 min** · 409 mots, 18 lignes de code

## C'est quoi ce fichier ?

Le plus gros repository du projet jusqu'ici : CRUD des bénévoles, gestion des documents (conditions à valider), et gestion des compétences (association bénévole ↔ compétence).

## Les fonctions classiques

`CreateBenevole`, `GetBenevoleById`, `ListBenevoles(statut *string)` : rien de nouveau, mêmes techniques que `db/collectesRepository.go.md` (filtre optionnel par statut).

## UpdateStatutBenevole : même astuce que pour les collectes

```go
func UpdateStatutBenevole(id int, statut string) error {
    if statut == "valide" {
        // remplit aussi date_validation = CURRENT_DATE
    } else {
        // ne touche pas a date_validation
    }
}
```

Exactement le même principe que `db.UpdateStatutCollecte` (voir `db/collectesRepository.go.md`) : quand le statut passe à `"valide"`, on enregistre automatiquement la date de validation côté SQL (`CURRENT_DATE`), sans dépendre du client pour fournir cette information.

## Les documents (les "conditions à valider")

`CreateBenevoleDocument`, `ListDocumentsBenevole`, `ValiderDocument` : classiques. La fonction intéressante est la suivante.

## TousLesDocumentsSontValides — la fonction clé de tout le module

```go
func TousLesDocumentsSontValides(benevoleId int) (bool, error) {
    var nombreTotal, nombreValides int
    err := Conn.QueryRow(
        "SELECT COUNT(*), COUNT(*) FILTER (WHERE valide = true) FROM benevole_documents WHERE benevole_id = $1",
        benevoleId,
    ).Scan(&nombreTotal, &nombreValides)
    ...
    return nombreTotal > 0 && nombreTotal == nombreValides, nil
}
```

### `COUNT(*) FILTER (WHERE valide = true)`
Une fonctionnalité SQL qui permet de compter SEULEMENT les lignes qui correspondent à une condition supplémentaire, en une seule requête. Sans `FILTER`, il aurait fallu faire DEUX requêtes séparées (une pour compter le total, une pour compter les validés) — ici, une seule requête retourne les deux nombres à la fois (`Scan(&nombreTotal, &nombreValides)`, deux colonnes calculées).

### La condition finale : `nombreTotal > 0 && nombreTotal == nombreValides`
Deux vérifications combinées avec `&&` (ET logique) :
- `nombreTotal > 0` : le bénévole doit avoir AU MOINS un document enregistré (sinon, un bénévole sans aucune condition à remplir pourrait être validé instantanément, ce qui n'a pas de sens — "valider des conditions" suppose qu'il y en a au moins une).
- `nombreTotal == nombreValides` : TOUS les documents enregistrés doivent être validés, pas juste certains.

Cette fonction est appelée par `ValiderBenevole` (voir `app/benevoles.go.md`) AVANT d'autoriser le passage au statut `"valide"` — c'est le cœur de la logique métier demandée par le sujet.

## Les compétences

`ListCompetences`, `GetCompetenceById` : lecture simple de la table `competences` (le référentiel fixe).

`ListCompetencesBenevole` : utilise un `JOIN` (même technique que `db/rappelsRepository.go.md`) entre `competences` et `benevole_competences`, pour retrouver les libellés des compétences (`"chauffeur"`, etc.) d'un bénévole précis, plutôt que juste leurs identifiants numériques.

`AjouterCompetenceBenevole`/`RetirerCompetenceBenevole` : simples `INSERT`/`DELETE` dans la table de liaison `benevole_competences` (une table qui ne fait que relier deux autres tables entre elles, sans autre colonne que les deux clés étrangères — voir `schema.sql`).

`BenevoleADejaCompetence` : vérification anti-doublon avant d'ajouter une compétence, même principe que `RappelDejaEnvoye` (voir `db/rappelsRepository.go.md`) mais pour un cas différent (éviter d'associer deux fois la même compétence, plutôt que d'éviter d'envoyer deux fois le même email).
