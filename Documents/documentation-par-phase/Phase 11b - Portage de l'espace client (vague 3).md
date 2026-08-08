# Phase 11b — Portage de l'espace client (vague 3)

> ⏱️ **Lecture : ~12 min** · 1 200 mots

> **Légende** : 🟥 = écrit noir sur blanc dans le sujet · 🟧 = pas nommé littéralement mais indispensable pour un point du sujet · 🟦 = bonus, absent du sujet.
>
> Suite de [Phase 11 - Portage du back-office](Phase%2011%20-%20Portage%20du%20back-office%20(vagues%201%20et%202).md). Le back-office métier était en place ; c'est le **front office** qui n'existait que de nom.

## Le besoin (pourquoi cette phase existe)

> 🟥 *« il y a ici à la fois un back-office (utilisé par NO MORE WASTE) et un **front office (utilisé par les clients de NO MORE WASTE)** »*

Jusqu'ici, un adhérent connecté ne voyait **rien de plus qu'un visiteur de passage**. Les cinq routes `/mon-espace` de l'API étaient codées et testées depuis des semaines, mais aucun écran ne les appelait.

Cette phase répond aussi à :

- 🟥 les services *« accessibles aux adhérents »* — le catalogue public et l'inscription à un créneau ;
- 🟥 *« **chacun** peut s'inscrire pour devenir bénévole à condition de valider un certain nombre de conditions »* — le formulaire public, et l'écran qui dit au candidat **ce qui lui manque**.

## Les sept écrans

| Écran | Adresse | Qui y accède |
|---|---|---|
| Catalogue des services | `/services` | tout le monde |
| Détail d'un service | `/services/{id}` | tout le monde |
| Candidature bénévole | `/benevoles/candidature` | tout le monde |
| Remerciement | `/benevoles/candidature/merci` | tout le monde |
| Espace commerçant | `/mon-espace/commercant` | adhérents |
| Espace bénévole | `/mon-espace/benevole` | bénévoles |
| (2 écrans « compte sans fiche ») | — | selon le cas |

## La faille : agir au nom d'un autre 🟧

C'est le résultat le plus important de cette phase, et il a été trouvé **avant d'écrire une ligne de front**.

La route `POST /creneaux/{id}/inscriptions` demandait un `commercant_id` **dans le corps de la requête**. Or c'est exactement ce que les routes `/mon-espace` avaient été conçues pour éviter.

Testé plutôt que supposé — deux comptes adhérents, deux boutiques :

```
POST /creneaux/1/inscriptions  {"commercant_id": 4}    (envoyé par le propriétaire de la boutique 3)
-> 201 Created
```

**La boutique d'un tiers venait d'être inscrite à sa place.** Les deux suites de tests étaient au vert.

### La correction : deux appelants, deux règles

| Appelant | Règle |
|---|---|
| Personnel | inscrit autrui — c'est son travail. Les identifiants envoyés font foi. |
| Adhérent | ne peut inscrire **que lui-même**. Ses identifiants sont **écrasés** par ceux déduits du jeton. |

Le statut est également imposé : on ne s'inscrit pas directement « présent ».

Six vérifications ont été ajoutées à `tester-espace-client.py`, sous un titre qui énonce la règle : *« agir en son nom propre, et pas au nom d'un autre »*. **Une correction de sécurité sans test peut régresser en silence.**

## Deux trous qui rendaient l'espace client inutilisable 🟧

### 1. Le rattachement d'une boutique à un compte n'existait pas

`CreateCommercant` n'écrivait pas `utilisateur_id`. Une boutique créée par l'API n'était reliée à personne, et son propriétaire ne pouvait pas ouvrir son espace.

La seule façon de faire la liaison était une requête SQL à la main — c'est d'ailleurs ce que faisaient les scripts de test, ce qui **masquait** le problème.

### 2. Même trou côté bénévoles, mais pas la même règle

C'est le point à savoir défendre.

| Route | Garde | Peut-on lire l'identifiant envoyé ? |
|---|---|---|
| `POST /commercants/` | personnel | **oui** — c'est le staff qui décide |
| `POST /benevoles/candidature/` | **publique** | **non** — n'importe qui pourrait s'accrocher au compte d'autrui |

Pour la candidature, le compte est donc déduit **du jeton** quand il y en a un, et la fiche reste anonyme sinon. C'est la même règle que pour l'inscription, appliquée à un cas différent.

> **La question n'est pas « d'où vient la donnée », mais « qui a le droit de la choisir ».**

## Un lien mort dans le menu depuis la vague 1 🟧

`Auth::urlEspace()` renvoyait `/mon-espace` pour un adhérent. Cette adresse n'a jamais existé — le lien « Mon espace » de l'en-tête répondait **404**.

Il avait été écrit avant que l'écran existe, et rien ne l'avait signalé : personne ne s'était connecté en adhérent depuis. C'est le genre de défaut qu'on ne découvre qu'en se mettant réellement dans la peau de l'utilisateur.

Les trois rôles sont désormais vérifiés à chaque fois : adhérent → `/mon-espace/commercant`, bénévole → `/mon-espace/benevole`, personnel → `/back`.

## Le piège du tableau vide en PHP 🟧

L'inscription à un créneau n'a **rien** à envoyer : tout vient du jeton. Un corps vide a d'abord été refusé — « JSON invalide ».

La cause : en PHP, un tableau vide est à la fois une liste et un dictionnaire, et `json_encode([])` produit `"[]"`, pas `"{}"`. L'API attend toujours un **objet** dans un corps de requête.

Corrigé une fois pour toutes dans `ApiClient` :

```php
json_encode($donnees === [] ? new \stdClass() : $donnees)
```

Un tableau non vide n'a jamais eu ce problème : dès qu'il a des clés, `json_encode` produit un objet. Toutes les actions POST du back-office ont été rejouées pour vérifier que ce changement central ne cassait rien.

## Des choix d'affichage à savoir défendre

**Trois chiffres justes plutôt que quatre dont un inventé.** La maquette montrait « 312 articles donnés ». L'obtenir demanderait un appel par collecte, et l'espace client n'a pas accès à la route des produits — réservée au personnel. Le chiffre a été retiré. Un compteur approximatif sur un écran client se remarque tout de suite et discrédite les autres.

**Le seuil des 30 jours n'est pas décoratif.** L'écran passe à l'orange quand il reste 30 jours d'adhésion, parce que c'est exactement le moment où part le premier rappel par email. Un autre seuil ferait dire deux choses différentes au site et au mail.

**Un 404 qui n'est pas une erreur.** Quand un compte n'a aucune fiche rattachée, l'API répond 404 — et c'est le bon code : ce n'est pas le compte qui est introuvable, c'est la fiche. Laisser passer ce 404 afficherait « page introuvable » : faux, et inquiétant. Deux écrans dédiés expliquent ce qui manque. Même raisonnement que le code-barre inconnu de l'écran des stocks.

**Le bouton d'inscription a quatre états.** Complet, adhérent, visiteur anonyme, et — celui qu'on oublie — connecté mais pas adhérent. Dire « Se connecter » à un bénévole déjà connecté l'enverrait tourner en rond.

## Ce que les écrans ne font pas, et pourquoi

Un bénévole ne peut pas **déposer** un justificatif depuis son espace. La route existe mais elle est réservée au personnel et attend un chemin de fichier, pas un envoi. Un vrai téléversement demanderait de gérer le stockage, les types autorisés, la taille et l'accès aux fichiers : un chantier à part, hors du périmètre du sujet.

L'écran **montre** l'état du dossier et dit à qui s'adresser — c'est déjà ce qui manquait au candidat pour comprendre pourquoi il reste bloqué.

## Comment tester soi-même

```bash
# la faille, telle qu'elle était (deux comptes, deux boutiques)
curl -X POST http://localhost:8080/api/creneaux/1/inscriptions \
  -H "Authorization: $JETON_ADHERENT_A" -d '{"commercant_id": <boutique de B>}'
# -> 201, mais c'est bien A qui est inscrit

# le lien du menu, pour les trois rôles
# -> /mon-espace/commercant, /mon-espace/benevole, /back : 200 dans les trois cas

# l'isolation, dans les six sens
# bénévole -> espace commerçant   : 302 vers /
# adhérent -> espace bénévole     : 302 vers /
# adhérent -> back-office         : 302 vers /
# anonyme  -> les trois           : 302 vers /connexion

# la candidature, corps forgé
curl -X POST http://localhost:8080/api/benevoles/candidature/ \
  -d '{"nom":"Pirate","prenom":"X","utilisateur_id":8}'
# -> 201, mais utilisateur_id reste NULL

# les pages publiques, sans aucune connexion
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8080/services
# -> 200
```

**Le test à ne jamais sauter** : les quatre langues sur chaque écran. Un libellé oublié reste en français quand on change de langue.

## Résultats vérifiés

- `tester-tous-les-endpoints.py` → **77/77**
- `tester-espace-client.py` → **23/23** (17 avant, +6 sur la nouvelle règle), rejouable
- 7 nouveaux écrans × 4 langues, sans une seule clé non résolue
- Les 9 écrans du back-office rechargés après modification d'`ApiClient` et d'`Auth`
- **352 clés** de traduction par langue, base et fichiers alignés
- Chaque `.php` du front a son `.md` ; **349 liens** vérifiés, aucun mort

## Reste à faire

- Vague 4 : adhésions, bénéficiaires, fiche commerçant, campagnes, utilisateurs, profils de rappel
- 🟥 Déploiement sur serveur réel avec HTTPS (11.2)
- 🟥 Script d'installation (12.1)
- Une route `PUT /commercants/{id}` : sans elle, une boutique créée sans compte ne peut plus être rattachée. L'écran « fiche commerçant » de la vague 4 en aura besoin.

## Fichiers liés

- [../../Code/front-php/app/controllers/front/EspaceCommercantController.php.md](../../Code/front-php/app/controllers/front/EspaceCommercantController.php.md)
- [../../Code/front-php/app/controllers/front/EspaceBenevoleController.php.md](../../Code/front-php/app/controllers/front/EspaceBenevoleController.php.md)
- [../../Code/front-php/app/controllers/front/ServicesPublicsController.php.md](../../Code/front-php/app/controllers/front/ServicesPublicsController.php.md)
- [../../Code/front-php/app/controllers/front/CandidatureController.php.md](../../Code/front-php/app/controllers/front/CandidatureController.php.md)
- [Phase 11 - Portage du back-office (vagues 1 et 2).md](Phase%2011%20-%20Portage%20du%20back-office%20(vagues%201%20et%202).md) — la phase précédente
