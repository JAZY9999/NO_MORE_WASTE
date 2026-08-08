# Phase 3 — Gestion des stocks avec code-barre

> ⏱️ **Lecture : ~5 min** · 447 mots, 4 lignes de code

> **Légende** : 🟥 = écrit noir sur blanc dans le sujet · 🟧 = pas nommé littéralement mais indispensable pour un point du sujet · 🟦 = bonus, absent du sujet (assumé, pas du hors-sujet).
>
> Phase entièrement conforme au sujet, aucun ajout personnel notable ici.

## Le besoin (pourquoi cette phase existe)

Le sujet dit : "Chaque produit rapporté au siège devra être référencé (code barre), stocké et retrouvable très rapidement." C'est une des phrases les plus littéralement citées du sujet.

## Ce qui a été mis en place

### Les emplacements (`app/emplacements.go`)
🟧 Un CRUD simple : entrepôt/zone/rayon/étagère, pour savoir physiquement où se trouve un produit. — le sujet dit "stocké", donc il faut bien un lieu de stockage, même si le mot "emplacement" n'apparaît pas littéralement.

### Les produits (`app/produits.go`, `db/produitsRepository.go`)
- 🟥 `POST /produits` : crée un produit (code-barre + libellé obligatoires, quantité et statut par défaut si non fournis). Refuse un code-barre déjà utilisé (409). — "chaque produit rapporté au siège devra être référencé (code barre)".
- 🟥 `GET /produits?code_barre=XXX` : **la recherche rapide exigée par le sujet**. Une simple recherche par égalité, rendue rapide par un INDEX SQL sur la colonne `code_barre` (défini dans `schema.sql`), pas par une astuce côté Go. — "stocké et retrouvable très rapidement".
- 🟦 `GET /produits?categorie=...&statut=...` : liste filtrée (même route, comportement différent selon les paramètres reçus). — pas demandé, ajouté pour un usage back-office plus confortable (parcourir le stock).
- 🟧 `PUT /produits/{id}` : déplace un produit / change son statut. — nécessaire pour gérer un stock dans la durée, même si non détaillé dans le sujet.

## La logique clé à savoir réexpliquer

**Pourquoi la recherche est "rapide" :** un index, c'est comme l'index alphabétique à la fin d'un livre. Sans lui, pour trouver un produit par son code-barre, la base devrait lire TOUTES les lignes de la table une par une. Avec l'index, elle retrouve directement la bonne ligne, quasi instantanément, même avec des millions de produits.

**Choix assumé (validé avec l'utilisateur)** : pas de décodage d'image de code-barre côté serveur. Une douchette USB physique tape le code comme si c'était un clavier — donc un simple champ texte suffit, pas besoin d'une librairie de traitement d'image.

## Comment le vérifier soi-même

```bash
STAFF_TOKEN=... # se connecter d'abord
curl -X POST http://localhost:8080/api/produits/ -H "Authorization: $STAFF_TOKEN" -H "Content-Type: application/json" -d '{"code_barre":"CB-1","libelle":"Pain"}'
curl "http://localhost:8080/api/produits/?code_barre=CB-1" -H "Authorization: $STAFF_TOKEN"
```

## Pour aller plus loin (fichiers `.md` détaillés)

- [api-go/models/emplacement.go.md](../../Code/api-go/models/emplacement.go.md), [api-go/db/emplacementsRepository.go.md](../../Code/api-go/db/emplacementsRepository.go.md), [api-go/app/emplacements.go.md](../../Code/api-go/app/emplacements.go.md)
- [api-go/models/produit.go.md](../../Code/api-go/models/produit.go.md)
- [api-go/db/produitsRepository.go.md](../../Code/api-go/db/produitsRepository.go.md) — **à lire en priorité** : le rôle de l'index SQL
- [api-go/app/produits.go.md](../../Code/api-go/app/produits.go.md) — la route à double usage (recherche vs liste)

## Ce qu'il reste à faire dans cette phase

Rien — la Phase 3 est entièrement terminée et testée (3.1 à 3.4).
