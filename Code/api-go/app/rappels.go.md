# app/rappels.go — les routes pour piloter les rappels depuis le back-office

> ⏱️ **Lecture : ~8 min** · 550 mots

## C'est quoi ce fichier ?

Quatre routes qui permettent au staff, depuis le back-office PHP (l'écran des adhésions, voir `front-php/app/controllers/back/AdhesionsController.php.md`), de consulter et de piloter le système de rappels automatiques, sans devoir attendre que le job planifié (`utils/scheduler.go`) se déclenche tout seul.

C'est le point le plus cité du sujet : un rappel qui tourne sans qu'on puisse le montrer en démonstration ne vaut pas mieux que s'il n'existait pas. Ces quatre routes existent pour ça.

## Fonction 1 : ListerAdhesionsARenouveler

Route : `GET /adhesions/a-renouveler`

```go
func ListerAdhesionsARenouveler(w http.ResponseWriter, r *http.Request) {
    ...
    renouvelerJ30, err := db.ListAdhesionsARenouveler(30)
    ...
    renouvelerJ7, err := db.ListAdhesionsARenouveler(7)
    ...
    resultat := append(renouvelerJ30, renouvelerJ7...)
    ...
}
```

Combine les deux listes (adhésions à J-30 et J-7) en une seule réponse. `append(renouvelerJ30, renouvelerJ7...)` : les trois petits points après `renouvelerJ7` sont une syntaxe Go qui veut dire "prends chaque élément de cette liste et ajoute-les un par un", plutôt que d'ajouter la liste entière comme un seul élément imbriqué. C'est ce qui permet d'obtenir un tableau JSON à plat, avec toutes les adhésions concernées mélangées, plutôt que deux tableaux séparés.

## Fonction 2 : RelancerAdhesion

Route : `POST /adhesions/{id}/relancer`

Permet au staff de déclencher manuellement l'envoi d'un email pour UNE adhésion précise, sans attendre le job automatique — utile en démonstration, ou si le staff veut relancer un commerçant en dehors des seuils automatiques (J-30/J-7).

```go
adhesion, err := db.GetAdhesionById(id)
...
commercant, err := db.GetCommercantById(adhesion.CommercantId)
...
if commercant == nil || commercant.Email == nil {
    http.Error(w, "Ce commercant n'a pas d'adresse email enregistree", http.StatusBadRequest)
    return
}
```

On récupère d'abord l'adhésion, PUIS le commerçant qui lui est rattaché (via `adhesion.CommercantId`), pour avoir accès à son email — l'adhésion elle-même ne contient pas l'email, seulement l'id du commerçant.

Le type de rappel enregistré ici est `"manuel"` (voir `db.EnregistrerRappelEnvoye(id, "manuel", ...)`), différent de `"j30"`/`"j7"`/`"ex_abonne"` — ça permet de distinguer, dans l'historique, un rappel automatique d'une relance faite à la main par le staff.

### 🔄 `ErreurEmail` (502), pas `ErreurServeur` (500)

```go
err = utils.EnvoyerEmail(*commercant.Email, sujet, corps)
if err != nil {
    utils.ErreurEmail(w, r, err)   // <- et non utils.ErreurServeur(...)
    return
}
```

Trouvé en testant l'écran des adhésions : la relance répondait **500 « Erreur d'envoi de l'email »**. Doublement faux. Le serveur va très bien — c'est le service d'envoi (Brevo) qui refuse, généralement parce que les identifiants SMTP ne sont pas renseignés dans le `.env`. Et le message ne disait pas quoi vérifier.

`utils.ErreurEmail` (voir `utils/erreurs.go.md`) répond **502 Bad Gateway** — un service **extérieur** n'a pas répondu comme attendu — avec un message qui dit explicitement de vérifier le `.env`. Même raisonnement que le 503 du health check quand la base est injoignable.

## Fonction 3 : ObtenirHistoriqueRappels

Route : `GET /adhesions/{id}/historique-rappels`

Simple route de consultation : retourne la liste de tous les rappels déjà envoyés pour cette adhésion (via `db.ListHistoriqueRappels`, voir `db/rappelsRepository.go.md`). Utile pour un tableau de bord back-office qui afficherait, pour chaque adhésion, "dernier contact : rappel J-7 envoyé le [date]".

## Fonction 4 : DeclencherJobRappels

Route : `POST /admin/jobs/rappels-adhesions`

```go
func DeclencherJobRappels(w http.ResponseWriter, r *http.Request) {
    _, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
    if !ok {
        return
    }

    utils.ExecuterJobRappels()

    w.Header().Set("Content-Type", "application/json")
    json.NewEncoder(w).Encode(map[string]string{"message": "job de rappels execute"})
}
```

Cette route appelle EXACTEMENT la même fonction (`utils.ExecuterJobRappels()`) que celle utilisée automatiquement par la goroutine toutes les 24h (voir `utils/scheduler.go.md`) — pas de code dupliqué, juste un deuxième moyen de la déclencher, à la demande. C'est indispensable pour une démonstration ou une soutenance : sans cette route, il faudrait attendre jusqu'à 24h pour voir le système fonctionner en vrai.

## Pourquoi toutes ces routes sont réservées au staff (`RequireRole`)

Ce sont des opérations sensibles (envoi d'emails à de vrais commerçants, déclenchement de traitements en masse) — aucune de ces routes n'a de sens côté "front-office" public, donc toutes sont protégées exactement comme les routes de commerçants/adhésions (voir `utils/guard.go.md`).
