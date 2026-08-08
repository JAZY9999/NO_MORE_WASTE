# Phase 7 — Services, plannings, inscriptions

> ⏱️ **Lecture : ~10 min** · 854 mots, 11 lignes de code

> **Légende** : 🟥 = écrit noir sur blanc dans le sujet · 🟧 = pas nommé littéralement mais indispensable pour un point du sujet · 🟦 = bonus, absent du sujet (assumé, pas du hors-sujet).
>
> Phase entièrement conforme au sujet. Le seul point à savoir justifier : le format du fichier de planning (CSV au lieu de `.xlsx`) — voir l'explication ci-dessous.

## Le besoin (pourquoi cette phase existe)

Le sujet demande "la gestion des services (propositions, plannings, inscriptions)" et précise : "tous les jours, des plannings sont créés, édités et envoyés aux différents bénévoles sous la forme de fichiers Excel."

## Ce qui a été mis en place (11 routes)

### Les services (les "propositions")
- 🟥 `POST /services` : crée une offre de service, parmi les types cités par le sujet (conseil anti-gaspi, cours de cuisine, partage de véhicules, échange de services, réparation, gardiennage). Peut exiger une compétence précise.
- 🟥 `GET /services`, `GET /services/{id}` : **routes publiques** (sans authentification) — le sujet dit que les services sont "accessibles aux adhérents", donc le catalogue doit être consultable depuis le front-office.

### Les créneaux (les "plannings")
- 🟥 `POST /services/{id}/creneaux` : crée une date/heure où le service est proposé (lieu, capacité maximale).
- 🟧 `GET /services/{id}/creneaux` : liste les créneaux d'un service.
- 🟥 `PUT /creneaux/{id}/affectation` : **affecte un bénévole à un créneau** — c'est l'"affectation à un service donné" du sujet, avec deux règles cumulatives (voir ci-dessous).

### Les inscriptions
- 🟥 `POST /creneaux/{id}/inscriptions` : inscrit un commerçant ou un utilisateur à un créneau. Accessible aux adhérents (pas seulement au staff). Refuse si le créneau est complet (409) ou annulé (400).
- 🟧 `GET /creneaux/{id}/inscriptions` : liste les inscrits d'un créneau.

### Le planning quotidien envoyé aux bénévoles
- 🟥 Un **job automatique** tourne chaque jour (dans la même goroutine que les rappels d'adhésion) : pour chaque bénévole ayant au moins un créneau ce jour-là, il génère son planning en CSV et le lui envoie par email **en pièce jointe**.
- 🟧 `GET /plannings?date=...` : télécharge directement le CSV du planning d'une date (pour le back-office, sans attendre l'email).
- 🟦 `POST /admin/jobs/plannings?date=...` : déclenche l'envoi manuellement. Pas demandé, ajouté pour pouvoir démontrer le système à l'oral sans attendre le lendemain.

## Les deux règles d'affectation — à savoir réexpliquer

Pour affecter un bénévole à un créneau, DEUX conditions doivent être remplies :

1. **Le bénévole doit être au statut `"valide"`** — donc avoir passé toutes ses conditions (documents validés, voir Phase 6). C'est le lien direct avec la phrase du sujet : "à condition de valider un certain nombre de conditions".
2. **Le bénévole doit posséder la compétence exigée par le service**, si celui-ci en demande une. Un cours de cuisine exigeant la compétence `cuisinier` ne peut pas être confié à quelqu'un qui ne l'a pas. C'est la traduction de "prenant en compte les différentes capacités qu'ils ont".

Ce scénario a été testé de bout en bout : refus si non validé → refus si compétence manquante → succès une fois les deux remplies.

## Le point à justifier à l'oral : pourquoi du CSV et pas du `.xlsx`

Le sujet dit "fichiers Excel". Mais la consigne du cours ESGI interdit toute librairie externe (hors driver de base de données), et Go ne sait pas produire de `.xlsx` natif sans librairie tierce comme `excelize`.

**La réponse retenue** : générer un **CSV** avec le package standard `encoding/csv`, qu'Excel ouvre nativement en double-cliquant. C'est le meilleur compromis entre l'esprit du sujet et la contrainte technique du cours — et l'énoncé d'examen du cours cite lui-même `encoding/csv` comme moyen d'export attendu.

Deux détails techniques qui font que le fichier s'ouvre correctement dans Excel français :
- un **BOM UTF-8** au début du fichier, sinon les accents s'affichent en charabia ;
- un séparateur **point-virgule** au lieu de la virgule, sinon Excel met toute la ligne dans une seule colonne.

## Comment le vérifier soi-même

```bash
STAFF_TOKEN=... # se connecter

# Catalogue public, sans token
curl http://localhost:8080/api/services/

# Telecharger le planning du jour (CSV)
curl "http://localhost:8080/api/plannings/" -H "Authorization: $STAFF_TOKEN"

# Declencher l'envoi des plannings par email
curl -X POST "http://localhost:8080/api/admin/jobs/plannings/" -H "Authorization: $STAFF_TOKEN"
```

## Pour aller plus loin (fichiers `.md` détaillés)

- [api-go/models/service.go.md](../../Code/api-go/models/service.go.md) — les 4 structs
- [api-go/db/servicesRepository.go.md](../../Code/api-go/db/servicesRepository.go.md) — la requête à 3 tables jointes du planning, le contrôle de capacité
- [api-go/utils/planning.go.md](../../Code/api-go/utils/planning.go.md) — **à lire en priorité** : la génération CSV, le BOM, le format de date Go
- [api-go/utils/mailer.go.md](../../Code/api-go/utils/mailer.go.md) — l'envoi avec pièce jointe (MIME multipart, base64)
- [api-go/utils/schedulerPlanning.go.md](../../Code/api-go/utils/schedulerPlanning.go.md) — le regroupement par bénévole avec une `map`
- [api-go/app/services.go.md](../../Code/api-go/app/services.go.md) — les 11 handlers, les deux règles d'affectation

## Changement de schéma effectué dans cette phase

Une colonne `email` a été ajoutée à la table `benevoles`. Raison : pour envoyer un planning par email à un bénévole, il faut son adresse — or la table ne la stockait pas (elle pointait seulement vers `utilisateurs` via un lien optionnel jamais rempli par la candidature). Un bénévole peut désormais fournir son email dès sa candidature, sans avoir besoin d'un compte de connexion.

## Ce qu'il reste à faire dans cette phase

Rien — la Phase 7 est entièrement terminée et testée (7.1 à 7.3). Comme pour la Phase 2, l'envoi réel des emails nécessite de renseigner de vraies clés SMTP Brevo dans `.env`.
