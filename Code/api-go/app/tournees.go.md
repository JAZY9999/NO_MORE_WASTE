# app/tournees.go — tournées de distribution et récapitulatifs PDF

> ⏱️ **Lecture : ~10 min** · 493 mots, 33 lignes de code

## C'est quoi ce fichier ?

Douze handlers couvrant tout le circuit de distribution demandé par le sujet : gérer les bénéficiaires, planifier des tournées avec leurs étapes, clôturer chaque livraison et produire le récapitulatif PDF.

## Les bénéficiaires

`CreerBeneficiaire`, `ListerBeneficiaires` : CRUD classique. Le champ `type` distingue les deux catégories citées par le sujet (association caritative / particulier en détresse), et la liste est filtrable par ce type.

## Les tournées

`CreerTournee` reprend la règle de la Phase 7 : si un bénévole chauffeur est désigné, on vérifie qu'il est bien au statut `"valide"` (ses conditions sont remplies, voir Phase 6) avant de l'accepter. Un bénévole encore candidat ne peut pas conduire une tournée.

`AjouterEtapeTournee` (`POST /tournees/{id}/etapes`) : ajoute un arrêt à la tournée. Vérifie que la tournée existe ET que le bénéficiaire existe (404 dans les deux cas) — même principe de vérification préalable que partout ailleurs dans le projet.

## CloturerLivraison — LE handler central de la Phase 5

Route : `POST /tournee-etapes/{id}/livraison`

C'est ici que se joue l'exigence du sujet : *"Chaque livraison donnera lieu à l'émission d'un récapitulatif au format PDF"*. Le handler enchaîne **cinq opérations** :

```go
// 1. Verifier qu'aucune livraison n'existe deja pour cette etape
existante, err := db.GetLivraisonParEtape(etapeId)
if existante != nil {
    http.Error(w, "Cette etape a deja fait l'objet d'une livraison", http.StatusConflict)
    return
}

// 2. Verifier que TOUS les produits envoyes existent
for _, p := range dto.Produits {
    produit, err := db.GetProduitById(p.ProduitId)
    if produit == nil {
        http.Error(w, "Produit introuvable : "+strconv.Itoa(p.ProduitId), http.StatusNotFound)
        return
    }
}

// 3. Creer la livraison
livraisonId, err := db.CreateLivraison(etapeId)

// 4. Rattacher chaque produit (et le passer en statut "distribue")
for _, p := range dto.Produits { ... db.AjouterProduitLivraison(...) }

// 5. Marquer l'etape comme livree (avec l'heure reelle)
err = db.MarquerEtapeLivree(etapeId)
```

### Pourquoi vérifier tous les produits AVANT d'en insérer aucun
La boucle de vérification (étape 2) est séparée de la boucle d'insertion (étape 4), et elle passe d'abord. Sans cette séparation, si le 3e produit d'une liste de 5 n'existait pas, on aurait déjà inséré les 2 premiers avant de découvrir le problème — la livraison serait créée à moitié, dans un état incohérent. En vérifiant tout d'abord, on garantit que soit tout passe, soit rien ne passe.

### Le refus du doublon (409 Conflict)
Une étape de tournée ne peut donner lieu qu'à **une seule** livraison. Si le staff clique deux fois, la deuxième tentative est refusée proprement plutôt que de créer deux livraisons pour le même arrêt.

## TelechargerRecapLivraison — le PDF

Route : `GET /livraisons/{id}/pdf`

```go
recap, err := db.GetRecapLivraison(livraisonId)
...
contenuPdf := utils.GenererRecapLivraisonPDF(*recap)

w.Header().Set("Content-Type", "application/pdf")
w.Header().Set("Content-Disposition", "attachment; filename=\"recapitulatif-livraison-...pdf\"")
w.Write(contenuPdf)
```

Le PDF est **généré à la demande**, à chaque appel, à partir des données actuelles en base — il n'est jamais stocké sur le disque du serveur (voir `utils/pdf.go.md` pour le détail de la génération). Les deux en-têtes HTTP déclenchent le téléchargement d'un fichier dans le navigateur, comme pour le CSV du planning en Phase 7.

`ObtenirRecapLivraison` (`GET /livraisons/{id}`) renvoie les mêmes données mais en **JSON** — utile pour que le futur front PHP puisse afficher le récapitulatif à l'écran sans forcément télécharger le PDF.

## Le flux complet à savoir réexpliquer

1. Créer des bénéficiaires (`POST /beneficiaires`).
2. Créer une tournée pour une date, avec un bénévole validé comme chauffeur (`POST /tournees`).
3. Ajouter les arrêts dans l'ordre (`POST /tournees/{id}/etapes`).
4. Sur place, à chaque arrêt : clôturer la livraison avec la liste des produits remis (`POST /tournee-etapes/{id}/livraison`) — ce qui marque l'étape livrée, sort les produits du stock, et rend le PDF disponible.
5. Télécharger/imprimer le récapitulatif à faire signer (`GET /livraisons/{id}/pdf`).
