# Documentation par phase

> ⏱️ **Lecture : ~5 min** · 896 mots

Un document par phase du projet, qui résume : le besoin métier (pourquoi cette phase existe), ce qui a été codé, la logique clé à savoir réexpliquer sans IA, comment tester soi-même, et les liens vers les fichiers `.md` détaillés (un par fichier de code, dans `Code/`).

C'est un niveau de lecture intermédiaire entre :
- **`Documents/TODO-Mission1-NoMoreWaste.md`** : l'avancement coché phase par phase, très condensé.
- **Les fichiers `.md` à côté de chaque fichier de code** (dans `Code/api-go/...`) : l'explication ligne par ligne, très détaillée.
- **`Documents/JOURNAL-DE-BORD.md`** : le fil chronologique complet, avec le contexte de chaque décision.

## Légende des tags (identique à la todo)

Chaque document marque désormais chaque fonctionnalité listée avec un tag, pour distinguer d'un coup d'œil ce qui répond au cahier des charges de ce qui a été ajouté en plus :

- 🟥 **[SUJET]** — écrit noir sur blanc dans le sujet de rattrapage. Ce que le jury peut questionner point par point.
- 🟧 **[ADAPTATION]** — pas nommé littéralement, mais indispensable pour réaliser correctement un point du sujet (un choix d'implémentation nécessaire, pas un extra).
- 🟦 **[BONUS]** — absent du sujet, ajouté pour aller plus loin. **Ce n'est pas du hors-sujet** : c'est ce qui montre qu'on a réfléchi au-delà de la commande, et ça se valorise à l'oral. En cas de manque de temps, c'est en revanche ce qu'il faut repousser en premier — le jury cherchera d'abord les 🟥.

Chaque document de phase commence par un rappel de cette légende et, si pertinent, un avertissement sur les points 🟦 les plus significatifs de la phase.

## ⏱️ Combien de temps pour tout lire

Chaque document affiche son temps de lecture. Le tableau ci-dessous donne, pour chaque phase, le temps du document seul **et** le temps si l'on lit aussi les fichiers `.md` détaillés qu'il référence.

| Phase | Le document | Avec les fichiers liés |
|---|---|---|
| [Phase 0 - Socle et infrastructure](Phase%200%20-%20Socle%20et%20infrastructure.md) | ~5 min | **~40 min** |
| [Phase 1 - Authentification et roles](Phase%201%20-%20Authentification%20et%20roles.md) | ~5 min | **~1 h 15** |
| [Phase 10 - Consolidation API](Phase%2010%20-%20Consolidation%20API.md) | ~15 min | **~1 h** |
| [Phase 2 - Commercants et adhesions](Phase%202%20-%20Commercants%20et%20adhesions.md) | ~5 min | **~1 h 50** |
| [Phase 3 - Stocks et code-barre](Phase%203%20-%20Stocks%20et%20code-barre.md) | ~5 min | **~40 min** |
| [Phase 4 - Collectes](Phase%204%20-%20Collectes.md) | ~5 min | **~25 min** |
| [Phase 5 - Tournees et PDF](Phase%205%20-%20Tournees%20et%20PDF.md) | ~10 min | **~35 min** |
| [Phase 6 - Benevoles](Phase%206%20-%20Benevoles.md) | ~10 min | **~30 min** |
| [Phase 7 - Services et planning](Phase%207%20-%20Services%20et%20planning.md) | ~10 min | **~45 min** |
| [Phase 8-9 - Front PHP, multilingue et back-office](Phase%208-9%20-%20Front%20PHP,%20multilingue%20et%20back-office.md) | ~10 min | **~1 h 15** |
| [Phase 11 - Portage du back-office (vagues 1 et 2)](Phase%2011%20-%20Portage%20du%20back-office%20(vagues%201%20et%202).md) | ~15 min | **~2 h 30** |
| [Phase 11b - Portage de l'espace client (vague 3)](Phase%2011b%20-%20Portage%20de%20l'espace%20client%20(vague%203).md) | ~12 min | **~1 h 30** |
| [Phase 11c - Portage des écrans d'administration (vague 4)](Phase%2011c%20-%20Portage%20des%20ecrans%20d'administration%20(vague%204).md) | ~14 min | **~2 h** |

> **Ces durées valent pour une première lecture attentive**, pas pour du survol. Relire pour *maîtriser* — l'objectif des sessions de live coding — prend en général le double.
>
> Elles sont indicatives : un sujet déjà connu se lit deux fois plus vite.
>
> Toute la documentation du projet représente plus de **20 h** (le front-office seul compte plus de 100 fichiers `.md`, `Code/` et `Documents/` compris).


## Phases terminées (API Go)

1. [Phase 0 - Socle et infrastructure](Phase%200%20-%20Socle%20et%20infrastructure.md) — Docker, PostgreSQL, structure du projet. *Aucun 🟦 notable.*
2. [Phase 1 - Authentification et roles](Phase%201%20-%20Authentification%20et%20roles.md) — inscription, connexion, JWT, système de rôles. *1 🟦 mineur (`GET /auth/me`).*
3. [Phase 2 - Commercants et adhesions](Phase%202%20-%20Commercants%20et%20adhesions.md) — CRUD commerçants/adhésions, **rappel automatique** (goroutine). ⚠️ **Contient le plus gros 🟦 du projet : le système de campagnes segmentées, non demandé par le sujet.**
4. [Phase 3 - Stocks et code-barre](Phase%203%20-%20Stocks%20et%20code-barre.md) — produits, emplacements, recherche rapide par code-barre. *Quasi 100% 🟥/🟧, conforme au sujet.*
5. [Phase 4 - Collectes](Phase%204%20-%20Collectes.md) — CRUD collectes, rattachement de produits scannés. *Quasi 100% 🟥/🟧, conforme au sujet.*
6. [Phase 6 - Benevoles](Phase%206%20-%20Benevoles.md) — candidature publique, validation conditionnée, compétences (module le plus riche). *Quasi 100% 🟥/🟧, la logique en 3 étapes est demandée mot pour mot.*
7. [Phase 7 - Services et planning](Phase%207%20-%20Services%20et%20planning.md) — catalogue de services, créneaux, affectation conditionnée, inscriptions, **planning quotidien CSV envoyé par email**. *Conforme au sujet ; un point à justifier : CSV au lieu de `.xlsx` (contrainte du cours).*
8. [Phase 5 - Tournees et PDF](Phase%205%20-%20Tournees%20et%20PDF.md) — bénéficiaires, tournées, étapes, clôture de livraison, **récapitulatif PDF généré sans aucune librairie**. *Conforme au sujet ; un point à justifier : PDF écrit à la main (contrainte du cours).*

9. [Phase 10 - Consolidation API](Phase%2010%20-%20Consolidation%20API.md) — relecture des 63 routes : erreurs 500 rendues traçables, codes HTTP corrigés, **collection Postman des 66 requêtes** + script de vérification. *Phase de relecture : aucune route ajoutée.*

> ✅ **L'API Go est terminée et consolidée** (phases métier 1 à 7, puis relecture 10).

## Front PHP

10. [Phase 8-9 - Front PHP, multilingue et back-office](Phase%208-9%20-%20Front%20PHP,%20multilingue%20et%20back-office.md) — socle FlightPHP, **site en 4 langues**, séparation back-office / front-office, page de connexion, liste des commerçants avec filtre. *Couvre en une fois les items 1.3, 2.4, Phase 8 et Phase 9.*

11. [Phase 11 - Portage du back-office (vagues 1 et 2)](Phase%2011%20-%20Portage%20du%20back-office%20(vagues%201%20et%202).md) — les deux gabarits (barre latérale sombre / en-tête clair), le menu décrit une fois, puis **les six modules cités par le sujet** : bénévoles, collectes, stocks, emplacements, tournées, services. Le **récapitulatif PDF** et le **planning CSV** deviennent enfin téléchargeables. *Quatre défauts de l'API trouvés en portant les écrans, alors que les deux suites de tests étaient au vert.*

12. [Phase 11b - Portage de l'espace client (vague 3)](Phase%2011b%20-%20Portage%20de%20l'espace%20client%20(vague%203).md) — espace commerçant, espace bénévole, catalogue public des services et candidature bénévole. Le **front office** cesse d'exister seulement de nom. *Une faille d'autorisation trouvée avant d'écrire une ligne de front : un adhérent pouvait inscrire la boutique d'un tiers à un créneau.*

13. [Phase 11c - Portage des écrans d'administration (vague 4)](Phase%2011c%20-%20Portage%20des%20ecrans%20d'administration%20(vague%204).md) — adhésions (le **rappel automatique** enfin visible et pilotable), bénéficiaires, fiche commerçant complète (`PUT /commercants/{id}` ajouté), utilisateurs et rôles, campagnes d'emailing. *Suivie d'une relecture complète de toute la documentation du projet, pas seulement des fichiers touchés cette semaine.*

> ✅ **Les 22 écrans du back-office sont en place**, testés dans les 4 langues. Documentation à 100% : chaque `.php` et `.go` a son `.md`, 409 liens croisés vérifiés, aucun mort.

## Phases restantes

- **Phase 12** — Déploiement sur un serveur réel avec HTTPS, script d'installation (fin de projet) — résout au passage le problème du tout premier compte administrateur

Un nouveau document sera ajouté ici à chaque phase terminée, avec le même marquage 🟥/🟧/🟦.
