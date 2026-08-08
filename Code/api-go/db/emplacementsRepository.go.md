# db/emplacementsRepository.go — les requêtes SQL pour les emplacements

> ⏱️ **Lecture : ~5 min** · 74 mots

## C'est quoi ce fichier ?

Rien de nouveau techniquement par rapport aux repositories précédents (`db/commercantsRepository.go.md`) : `CreateEmplacement` (`INSERT ... RETURNING id`), `GetEmplacementById` (`QueryRow` + `Scan`, gestion de `sql.ErrNoRows`), `ListEmplacements` (`Query` + boucle `for rows.Next()`). Ce fichier est volontairement simple — c'est le module le plus basique de la Phase 3, servant surtout de "brique" pour rattacher des produits à un endroit précis (voir `db/produitsRepository.go.md`).
