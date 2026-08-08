# app/campagnes.go — piloter les campagnes ciblées depuis le back-office

> ⏱️ **Lecture : ~10 min** · 446 mots, 34 lignes de code

## C'est quoi ce fichier ?

Quatre routes pour créer une campagne d'email ciblée, la prévisualiser (voir qui va la recevoir AVANT de l'envoyer), et la déclencher réellement.

## Fonction 1 : CreerCampagne

Route : `POST /campagnes`

Rien de nouveau dans la logique par rapport à `CreerCommercant` (voir `app/commercants.go.md`) : lecture du JSON, vérification que les 3 champs obligatoires (`nom`, `sujet_email`, `corps_email`) sont présents, création en base, réponse `201 Created` avec l'id.

## Fonction 2 : ListerCampagnes

Route : `GET /campagnes`

Simple liste, comme `ListerCommercants`.

## Fonction 3 : PrevisualiserCampagne

Route : `GET /campagnes/{id}/destinataires`

```go
campagne, err := db.GetCampagneById(id)
...
destinataires, err := db.ResoudreDestinatairesCampagne(*campagne)
...
json.NewEncoder(w).Encode(destinataires)
```

Cette route ne fait AUCUN envoi d'email — elle sert uniquement à répondre à la question "si je déclenche cette campagne maintenant, qui va la recevoir ?". Très utile côté back-office pour que le staff puisse vérifier le ciblage avant d'envoyer réellement (par exemple, afficher "12 commerçants correspondent à ces critères" avec leurs noms, avant de cliquer sur "Envoyer").

## Fonction 4 : DeclencherCampagne (l'envoi réel)

Route : `POST /campagnes/{id}/declencher`

```go
destinataires, err := db.ResoudreDestinatairesCampagne(*campagne)
...
nombreEnvoyes := 0
for _, d := range destinataires {
    if d.Email == nil {
        continue
    }

    corpsPersonnalise := strings.ReplaceAll(campagne.CorpsEmail, "{{raison_sociale}}", d.RaisonSociale)

    err = utils.EnvoyerEmail(*d.Email, campagne.SujetEmail, corpsPersonnalise)
    if err != nil {
        continue
    }

    err = db.EnregistrerCampagneEnvoi(campagne.Id, d.CommercantId)
    if err != nil {
        continue
    }
    nombreEnvoyes++
}
```

### La personnalisation du message : `strings.ReplaceAll`
```go
corpsPersonnalise := strings.ReplaceAll(campagne.CorpsEmail, "{{raison_sociale}}", d.RaisonSociale)
```
Le staff écrit le corps de son email UNE SEULE FOIS, avec un "placeholder" (un texte qui sera remplacé) au format `{{raison_sociale}}` — par exemple : `"Bonjour {{raison_sociale}}, ça fait longtemps..."`. `strings.ReplaceAll` remplace TOUTES les occurrences de ce texte par le vrai nom du commerçant destinataire, pour que chaque email envoyé soit personnalisé, même si le staff n'a écrit le texte qu'une fois.

### Pourquoi la boucle continue même si un envoi échoue (`continue`, pas `return`)
```go
if err != nil {
    continue
}
```
Contrairement à d'autres handlers où une erreur arrête tout (`return` immédiat), ici on utilise `continue` : si l'envoi échoue pour UN destinataire (adresse invalide, problème réseau ponctuel...), on ne veut surtout pas empêcher l'envoi aux AUTRES destinataires de la même campagne. On passe juste au suivant, et `nombreEnvoyes` ne compte que les envois réellement réussis — c'est ce nombre qui est renvoyé au staff à la fin (`{"nombre_envoyes": 8}` par exemple), pour qu'il sache combien de personnes ont vraiment reçu l'email.

## Piège à connaître

`DeclencherCampagne` peut être appelée PLUSIEURS FOIS sur la même campagne (rien ne l'en empêche techniquement) — chaque appel renverra à nouveau un email à TOUS les destinataires qui correspondent aux critères à ce moment-là, sans vérifier si un envoi a déjà eu lieu pour eux (contrairement au système de rappels d'adhésion, voir `db/rappelsRepository.go.md`, qui vérifie explicitement `RappelDejaEnvoye` avant chaque envoi). C'est un choix assumé pour ce projet : une "campagne" est pensée comme une action ponctuelle et volontaire du staff (il sait qu'il déclenche un envoi en cliquant), contrairement au job automatique de rappels qui tourne tout seul et doit absolument éviter les doublons.
