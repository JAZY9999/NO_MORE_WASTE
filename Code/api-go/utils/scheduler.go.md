# utils/scheduler.go — le "robot" qui tourne tout seul en arrière-plan

> ⏱️ **Lecture : ~10 min** · 864 mots, 46 lignes de code

## D'abord, c'est quoi une goroutine ? (concept central de ce fichier)

Normalement, un programme Go exécute ses instructions les unes après les autres, dans l'ordre. Une **goroutine** est une façon de dire à Go : "démarre ce bloc de code EN PARALLÈLE, sans attendre qu'il finisse pour continuer le reste du programme". C'est un peu comme dans un restaurant : au lieu qu'un seul serveur fasse toutes les tâches une par une (prendre une commande, PUIS attendre que la cuisine finisse, PUIS servir), on peut avoir un serveur qui prend les commandes pendant qu'un cuisinier prépare les plats en même temps, sans que l'un attende l'autre.

En Go, pour démarrer une goroutine, on écrit juste le mot `go` devant un appel de fonction :

```go
go maFonction()
```

Le programme continue IMMÉDIATEMENT à la ligne suivante, sans attendre que `maFonction()` se termine. `maFonction()` s'exécute "à côté", en tâche de fond.

## Pourquoi on a besoin d'une goroutine ici

Notre serveur web (`app.go`) doit rester disponible pour répondre aux requêtes HTTP (login, création de commerçant, etc.) EN PERMANENCE. Mais on veut AUSSI qu'un "robot" vérifie chaque jour, tout seul, s'il y a des adhésions à relancer par email — sans que quelqu'un ait besoin de cliquer sur un bouton. Si on faisait ça de façon normale (pas en goroutine), le programme se bloquerait sur cette vérification et ne pourrait plus répondre aux vraies requêtes des utilisateurs pendant ce temps. La goroutine permet aux deux de tourner EN MÊME TEMPS : le serveur web d'un côté, le robot de vérification de l'autre.

## Le code, en détail

```go
func DemarrerSchedulerRappels() {
    go func() {
        for {
            ExecuterJobRappels()
            time.Sleep(24 * time.Hour)
        }
    }()
}
```

### `go func() { ... }()`
C'est une "fonction anonyme" (une fonction sans nom, définie directement à l'endroit où elle est utilisée) démarrée en goroutine. Le `()` final signifie qu'on l'appelle immédiatement.

### `for { ... }` (une boucle infinie, volontairement)
Un `for` sans condition d'arrêt tourne pour toujours (jusqu'à ce que le programme entier s'arrête). C'est voulu : notre "robot" doit vérifier les rappels indéfiniment, tant que l'application tourne.

### `time.Sleep(24 * time.Hour)`
Après avoir exécuté le job une fois, la goroutine "s'endort" pendant 24 heures avant de recommencer. `time.Hour` est une valeur fournie par le package `time` qui représente "une heure" ; en la multipliant par 24, on obtient "24 heures". C'est ce mécanisme simple (boucle + pause) qui remplace un vrai outil de planification externe comme `cron` — pas besoin d'installer quoi que ce soit d'autre, tout se passe dans le programme Go lui-même.

### Où cette fonction est appelée
Dans `app.go`, juste avant `http.ListenAndServe(...)` :
```go
utils.DemarrerSchedulerRappels()
```
Comme `DemarrerSchedulerRappels` lance une goroutine et retourne IMMÉDIATEMENT (elle ne bloque pas), le programme continue sa route vers `http.ListenAndServe(...)` qui, lui, démarre vraiment le serveur web. Les deux tournent alors en parallèle : le serveur web répond aux requêtes, et en arrière-plan, le robot vérifie les rappels toutes les 24h.

## ExecuterJobRappels : ce que fait le "robot" à chaque passage

```go
func ExecuterJobRappels() {
    envoyerRappelsRenouvellement(30, "j30")
    envoyerRappelsRenouvellement(7, "j7")
    envoyerRelancesExAbonnes(180, "ex_abonne")
}
```

Trois vérifications à chaque exécution :
1. Adhésions dont il reste exactement 30 jours avant expiration → rappel "j30"
2. Adhésions dont il reste exactement 7 jours avant expiration → rappel "j7"
3. Adhésions expirées/résiliées depuis exactement 180 jours (environ 6 mois) → relance "ex_abonne" (le mail "ça fait longtemps")

Cette fonction est aussi appelée directement (sans passer par la goroutine) par l'endpoint `POST /admin/jobs/rappels-adhesions` (voir `app/rappels.go.md`), pour pouvoir démontrer tout le système en direct sans attendre 24 heures.

## envoyerRappelsRenouvellement : le détail d'un envoi

```go
func envoyerRappelsRenouvellement(joursAvant int, typeRappel string) {
    adhesions, err := db.ListAdhesionsARenouveler(joursAvant)
    ...
    for _, a := range adhesions {
        if a.Email == nil {
            continue
        }

        dejaEnvoye, err := db.RappelDejaEnvoye(a.AdhesionId, typeRappel)
        ...
        if dejaEnvoye {
            continue
        }

        // construction du message et envoi
        err = EnvoyerEmail(*a.Email, sujet, corps)
        ...
        err = db.EnregistrerRappelEnvoye(a.AdhesionId, typeRappel, *a.Email)
        ...
    }
}
```

### `if a.Email == nil { continue }`
Certains commerçants n'ont peut-être pas d'adresse email enregistrée (le champ est optionnel, voir `models/commercant.go.md`). `continue` dans une boucle `for` veut dire "passe directement à l'élément suivant, sans exécuter le reste du code pour celui-ci" — on ne peut évidemment pas envoyer un email sans adresse.

### La vérification "déjà envoyé" (le point le plus important pour éviter le spam)
```go
dejaEnvoye, err := db.RappelDejaEnvoye(a.AdhesionId, typeRappel)
if dejaEnvoye {
    continue
}
```
Comme le job tourne CHAQUE JOUR, sans cette vérification, un commerçant à J-7 recevrait le même email de rappel 7 fois de suite (une fois par jour jusqu'à expiration) ! `RappelDejaEnvoye` regarde dans la table `adhesion_rappels` (voir `db/rappelsRepository.go.md`) si CE type précis de rappel (`"j7"`, `"j30"`, ou `"ex_abonne"`) a déjà été envoyé pour CETTE adhésion précise. Si oui, on saute cette adhésion pour ce passage du job.

### Après l'envoi réussi : `EnregistrerRappelEnvoye`
Dès que l'email part avec succès, on enregistre immédiatement en base qu'il a été envoyé — c'est cette trace qui permettra, le lendemain, à `RappelDejaEnvoye` de dire "oui, déjà fait" et d'éviter le doublon.

## Piège à connaître

Le calcul "adhésions dont il reste EXACTEMENT 30 jours" (`WHERE (a.date_fin - CURRENT_DATE) = 30`, voir `db/rappelsRepository.go.md`) suppose que le job tourne bien une fois par jour, pile. Si le conteneur `api-go` redémarre et rate un jour (par exemple le serveur était éteint), une adhésion pourrait "sauter" le seuil de 30 jours sans jamais recevoir ce rappel précis (elle recevrait quand même le rappel à J-7 si celui-là tombe pile). C'est une limite acceptée pour ce projet étudiant — un vrai système de production utiliserait plutôt une condition `<=` avec une vérification "déjà envoyé" plus robuste, mais ça ajouterait de la complexité pour un bénéfice marginal ici.
