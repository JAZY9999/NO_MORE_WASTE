# app/services.go — services, créneaux, inscriptions, planning

> ⏱️ **Lecture : ~5 min** · 555 mots, 18 lignes de code

## C'est quoi ce fichier ?

Onze handlers couvrant les trois volets demandés par le sujet pour les services : **propositions** (le catalogue de services), **plannings** (créneaux + affectation des bénévoles + export), **inscriptions** (qui participe).

## Les services

`CreerService` : classique, protégé par rôle staff.

`ListerServices` et `ObtenirService` : **routes publiques, sans `RequireRole`**. C'est volontaire : le sujet dit que les services sont "accessibles aux adhérents de l'association" — le catalogue doit donc être consultable depuis le front-office. C'est la deuxième famille de routes publiques du projet, après la candidature bénévole (voir `app/benevoles.go.md`).

## Les créneaux

`CreerCreneau` (`POST /services/{id}/creneaux`) : crée une date/heure de service. Vérifie d'abord que le service existe (404 sinon), applique des valeurs par défaut (`capacite_max=1`, `statut="ouvert"`), et écrase `ServiceId` avec l'id de l'URL — le même principe de sécurité que partout ailleurs dans le projet.

## AffecterBenevoleCreneau — LE handler le plus important de cette phase

C'est ici que se réalise concrètement l'"affectation à un service donné" du sujet, avec **deux règles métier cumulatives** :

```go
if benevole.Statut != "valide" {
    http.Error(w, "Impossible d'affecter : ce benevole n'est pas valide (...)", http.StatusBadRequest)
    return
}

if service != nil && service.CompetenceRequiseId != nil {
    aLaCompetence, err := db.BenevoleADejaCompetence(*dto.BenevoleId, *service.CompetenceRequiseId)
    ...
    if !aLaCompetence {
        http.Error(w, "Impossible d'affecter : ce benevole n'a pas la competence requise par ce service", http.StatusBadRequest)
        return
    }
}
```

### Règle 1 : le bénévole doit être `"valide"`
C'est le lien direct avec la Phase 6 : un bénévole n'atteint le statut `"valide"` que si TOUTES ses conditions (documents) ont été validées (voir `app/benevoles.go.md`). Cette vérification traduit donc exactement la phrase du sujet : *"chacun peut s'inscrire (…) **à condition de valider un certain nombre de conditions**"* — les conditions doivent être remplies AVANT l'affectation.

### Règle 2 : le bénévole doit avoir la compétence requise
Si le service exige une compétence précise (`competence_requise_id` rempli), on vérifie que le bénévole la possède, en réutilisant `db.BenevoleADejaCompetence` écrit en Phase 6. C'est la traduction de *"prenant en compte les différentes capacités qu'ils ont (chauffeurs, cuisiniers, plombiers, …)"*.

Si le service n'exige aucune compétence (`competence_requise_id` vaut `nil`), cette seconde vérification est simplement sautée — n'importe quel bénévole validé peut être affecté.

## Les inscriptions

`InscrireACreneau` : accessible aux rôles staff **et `adherent`** — c'est la première route du projet ouverte aux adhérents, cohérent avec le sujet ("services accessibles aux adhérents").

Trois vérifications avant d'inscrire :
1. Le créneau existe (404 sinon).
2. Le créneau n'est pas annulé (400 sinon).
3. **La capacité n'est pas atteinte** : `CompterInscriptionsActives` (voir `db/servicesRepository.go.md`) compte les inscrits non annulés et compare à `capacite_max`. Si c'est plein → `409 Conflict` avec le message "Ce creneau est complet".

### 🔒 La faille corrigée en portant l'espace client

**C'est le point le plus important de ce fichier**, et il n'était pas là au départ.

La route lisait `commercant_id` **dans le corps de la requête**. Autrement dit, un adhérent décidait lui-même de qui il inscrivait. Testé plutôt que supposé — deux comptes adhérents, deux boutiques :

```
POST /creneaux/1/inscriptions  {"commercant_id": 4}
   (envoyé par le propriétaire de la boutique 3)
-> 201 Created
```

**La boutique d'un tiers venait d'être inscrite à sa place.** Les deux suites de tests étaient au vert : aucune ne posait la question « et si j'envoyais l'identifiant de quelqu'un d'autre ? ».

La correction distingue **deux appelants, deux règles** :

| Appelant | Règle |
|---|---|
| Personnel | inscrit autrui — c'est son travail (quelqu'un appelle au téléphone). Les identifiants envoyés font foi. |
| Adhérent | ne peut inscrire **que lui-même**. Ses identifiants sont **écrasés** par ceux déduits de son jeton, quoi qu'il envoie. |

```go
if utilisateur.Role == "adherent" {
    commercant, _ := db.GetCommercantByUtilisateurId(utilisateur.Id)
    if commercant != nil {
        i.CommercantId = &commercant.Id   // sa boutique, jamais une autre
        i.UtilisateurId = nil
    } else {
        i.CommercantId = nil              // adhérent sans boutique :
        i.UtilisateurId = &utilisateur.Id // il s'inscrit en son nom propre
    }
}
```

C'est la règle déjà appliquée par les routes `/mon-espace` : **un identifiant fourni par le client ne désigne jamais QUI agit**. Cette information vient du jeton, et de lui seul.

Le **statut** est imposé pour la même raison : on ne s'inscrit pas directement « présent ».

### Un corps vide est accepté

```go
if err != nil && !errors.Is(err, io.EOF) { … }
```

Quand un adhérent s'inscrit lui-même, il n'a **rien** à envoyer — tout vient du jeton. Exiger un objet JSON vide `{}` serait une formalité sans utilité.

`io.EOF` signale exactement le corps vide ; toute autre erreur reste un vrai JSON invalide. Pour le personnel, un corps vide tombe alors sur le `400` « commercant_id ou utilisateur_id est obligatoire » — ce qui est correct, il doit dire qui il inscrit.

### Les six tests de non-régression

Une correction de sécurité sans test peut régresser en silence. `tests/tester-espace-client.py` porte désormais une section entière intitulée *« agir en son nom propre, et pas au nom d'un autre »* :

```
[OK] un adherent peut s'inscrire a un creneau
[OK] l'inscription est faite en SON nom, pas celui du tiers
[OK] le statut d'inscription est impose par l'API
[OK] le personnel doit preciser qui il inscrit
[OK] une candidature anonyme est acceptee
[OK] le compte vise dans le corps est ignore
```

## Le planning

`TelechargerPlanning` (`GET /plannings?date=...`) : renvoie **directement le fichier CSV** dans la réponse HTTP, au lieu du JSON habituel.

```go
w.Header().Set("Content-Type", "text/csv; charset=UTF-8")
w.Header().Set("Content-Disposition", "attachment; filename=\"planning-"+date+".csv\"")
w.Write(contenuCSV)
```

Ces deux en-têtes changent le comportement du navigateur : au lieu d'afficher le contenu, il propose de **télécharger** un fichier portant le nom indiqué. C'est la première route du projet qui ne renvoie pas du JSON.

Si aucune date n'est fournie en paramètre, on utilise la date du jour (`time.Now().Format("2006-01-02")`).

`DeclencherJobPlannings` (`POST /admin/jobs/plannings?date=...`) : déclenche manuellement l'envoi par email de tous les plannings — même principe que le déclencheur manuel des rappels d'adhésion (voir `app/rappels.go.md`), indispensable pour démontrer le système sans attendre 24h.

## Piège à connaître

`app.AffecterBenevoleCreneau` (ce handler) et `db.AffecterBenevoleCreneau` (la fonction du repository) portent le même nom. Ce n'est pas un problème en Go car ils sont dans des **packages différents** — le préfixe (`app.` ou `db.`) les distingue toujours sans ambiguïté. C'est le même cas que pour les fonctions de compétences en Phase 6.
