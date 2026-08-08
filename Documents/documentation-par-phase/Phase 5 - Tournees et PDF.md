# Phase 5 — Tournées de distribution & récapitulatif PDF

> ⏱️ **Lecture : ~10 min** · 810 mots, 16 lignes de code

> **Légende** : 🟥 = écrit noir sur blanc dans le sujet · 🟧 = pas nommé littéralement mais indispensable pour un point du sujet · 🟦 = bonus, absent du sujet (assumé, pas du hors-sujet).
>
> Phase entièrement conforme au sujet. Un point à savoir justifier : la façon dont le PDF est produit (sans librairie externe) — voir l'explication ci-dessous.

## Le besoin (pourquoi cette phase existe)

Le sujet demande de "gérer les tournées de distribution (associations caritatives, particuliers en détresse, …)" et précise : **"Chaque livraison donnera lieu à l'émission d'un récapitulatif au format PDF"**.

## Ce qui a été mis en place (12 routes)

### Les bénéficiaires
- 🟥 `POST /beneficiaires`, `GET /beneficiaires?type=...` : gère les destinataires des dons, avec les deux catégories citées par le sujet (`association_caritative`, `particulier_detresse`).

### Les tournées et leurs étapes
- 🟥 `POST /tournees` : crée une journée de distribution, avec un bénévole chauffeur. **Vérifie que ce bénévole est au statut "valide"** (lien avec la Phase 6).
- 🟧 `GET /tournees?statut=...`, `GET /tournees/{id}`, `PUT /tournees/{id}` : consultation et changement de statut.
- 🟥 `POST /tournees/{id}/etapes` : ajoute un arrêt chez un bénéficiaire, avec un ordre de passage et une heure prévue — c'est ce qui fait qu'une tournée est bien un **circuit** avec plusieurs points de livraison.
- 🟧 `GET /tournees/{id}/etapes` : liste les arrêts d'une tournée, triés par ordre de passage.

### La livraison et son PDF — le cœur de la phase
- 🟥 `POST /tournee-etapes/{id}/livraison` : clôture un arrêt. Enchaîne cinq opérations : vérifie qu'aucune livraison n'existe déjà (409 sinon), vérifie que tous les produits existent, crée la livraison, rattache les produits **et les passe au statut "distribue" dans le stock**, marque l'étape comme livrée avec l'heure réelle.
- 🟥 `GET /livraisons/{id}/pdf` : **le récapitulatif PDF exigé par le sujet**, généré à la demande.
- 🟦 `GET /livraisons/{id}` : les mêmes données en JSON — pas demandé, ajouté pour que le futur front puisse afficher le récapitulatif à l'écran sans télécharger le PDF.

## Le point à justifier à l'oral : comment générer un PDF sans librairie ?

Le sujet exige un PDF. Le cours ESGI interdit toute librairie externe (pas de `gofpdf`). La solution : **écrire le fichier PDF nous-mêmes**, car un PDF n'est pas un format binaire opaque — c'est un fichier **texte structuré** qui suit une grammaire précise.

Le fichier produit contient :
- un en-tête `%PDF-1.4` ;
- cinq objets numérotés (catalogue, liste de pages, page A4, contenu, police Helvetica) ;
- des instructions de dessin (`BT /F1 12 Tf 60 780 Td (mon texte) Tj ET`) ;
- une table `xref` qui donne la position en octets de chaque objet ;
- un trailer et `%%EOF`.

Le résultat est un vrai `.pdf` de ~1,5 Ko qui s'ouvre dans n'importe quel lecteur. Deux subtilités à connaître : dans un PDF, l'origine (0,0) est en **bas à gauche** (d'où un `positionY` qui décroît à chaque ligne), et les parenthèses du texte doivent être échappées (`\(`) car elles servent de délimiteurs.

Limite assumée : les accents sont convertis en équivalents simples (`é` → `e`), car gérer l'UTF-8 complet demanderait d'embarquer une police entière avec sa table d'encodage dans le fichier.

## Le lien avec les autres phases

Cette phase est celle qui **connecte le plus de modules** :
- **Phase 3 (stocks)** : les produits livrés passent automatiquement au statut `distribue` — ils sortent du stock disponible.
- **Phase 6 (bénévoles)** : seul un bénévole `valide` peut être affecté comme chauffeur.
- **Phase 4 (collectes)** : le circuit complet du sujet est bouclé — les produits collectés chez les commerçants sont stockés, puis redistribués via les tournées.

## Le flux complet à savoir réexpliquer

1. Créer un bénéficiaire (`POST /beneficiaires`).
2. Créer une tournée avec un bénévole validé (`POST /tournees`).
3. Ajouter les arrêts dans l'ordre (`POST /tournees/{id}/etapes`).
4. À chaque arrêt, clôturer la livraison avec la liste des produits remis (`POST /tournee-etapes/{id}/livraison`).
5. Télécharger le récapitulatif à faire signer (`GET /livraisons/{id}/pdf`).

## Comment le vérifier soi-même

```bash
STAFF_TOKEN=... # se connecter

curl -X POST http://localhost:8080/api/beneficiaires/ -H "Authorization: $STAFF_TOKEN" -H "Content-Type: application/json" \
  -d '{"type":"association_caritative","nom":"Restos du Coeur","ville":"Paris"}'

curl -X POST http://localhost:8080/api/tournees/ -H "Authorization: $STAFF_TOKEN" -H "Content-Type: application/json" \
  -d '{"date_tournee":"2026-07-31","benevole_id":1}'

curl -X POST http://localhost:8080/api/tournees/1/etapes -H "Authorization: $STAFF_TOKEN" -H "Content-Type: application/json" \
  -d '{"beneficiaire_id":1,"ordre":1}'

curl -X POST http://localhost:8080/api/tournee-etapes/1/livraison -H "Authorization: $STAFF_TOKEN" -H "Content-Type: application/json" \
  -d '{"produits":[{"produit_id":1,"quantite":3}]}'

curl -o recap.pdf http://localhost:8080/api/livraisons/1/pdf -H "Authorization: $STAFF_TOKEN"
```

## Pour aller plus loin (fichiers `.md` détaillés)

- [api-go/models/tournee.go.md](../../Code/api-go/models/tournee.go.md) — les 6 structs, le modèle tournée/étape en deux niveaux
- [api-go/db/tourneesRepository.go.md](../../Code/api-go/db/tourneesRepository.go.md) — la requête à 4 tables jointes, le double effet sur le stock
- [api-go/utils/pdf.go.md](../../Code/api-go/utils/pdf.go.md) — **à lire en priorité** : comment est fait un fichier PDF, la table xref
- [api-go/app/tournees.go.md](../../Code/api-go/app/tournees.go.md) — les 12 handlers, les 5 opérations de la clôture de livraison

## Ce qu'il reste à faire dans cette phase

Rien — la Phase 5 est entièrement terminée et testée (5.1 à 5.4). Testé de bout en bout le 2026-07-31 : création bénéficiaire/tournée/étape, clôture de livraison (201), refus du doublon (409), produits passés en "distribue", étape marquée "livre" avec heure réelle automatique, PDF de 1584 octets structurellement valide avec tout le contenu attendu.
