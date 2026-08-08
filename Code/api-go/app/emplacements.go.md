# app/emplacements.go — gérer les emplacements de stock

> ⏱️ **Lecture : ~5 min** · 111 mots

## C'est quoi ce fichier ?

Trois handlers classiques (créer / lister / obtenir un emplacement), exactement sur le même modèle que `app/commercants.go.md` — aucune nouveauté technique ici. C'est volontaire : une fois qu'on a compris le pattern "CRUD protégé par rôle" une première fois, on peut le reproduire tel quel pour chaque nouvelle ressource simple du projet.

## Utilité dans le flux global

Ces routes servent de prérequis au module Produits (voir `app/produits.go.md`) : avant de pouvoir dire "ce produit est rangé à tel endroit" (`emplacement_id` sur un produit), il faut que cet emplacement existe déjà en base, créé via `POST /emplacements`.
