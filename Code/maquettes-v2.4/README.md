# Maquettes V2.4 — ✅ VERSION RETENUE

> ⏱️ **Lecture : ~15 min** · 1672 mots, 12 lignes de code

> **Décision du 2026-08-03 : c'est cette version qui sera mise en production.**
> Elle remplace la V2.3, dont elle reprend intégralement le parti pris visuel.
>
> ⚠️ **Rien n'est encore porté** : ce sont des maquettes statiques, indépendantes du vrai front. Le portage vers `../front-php/app/views/` reste à faire — voir la marche à suivre en tête de `Documents/TODO-Mission1-NoMoreWaste.md`.

**Ouvre `sommaire.html`** (double-clic, ni Docker ni PHP). `index.html` est l'accueil public du site.

**32 écrans** — contre 14 en V2.3.

## Pourquoi une V2.4

En croisant les **24 tables** de la base et les **71 routes** de l'API avec les écrans de la V2.3, **7 domaines n'avaient aucune interface**. Ils existaient dans l'API, testés, fonctionnels — mais invisibles.

La V2.4 comble ces trous et ajoute ce qui manquait pour que la navigation tienne debout.

## Les 7 domaines qui n'avaient aucun écran

| Écran ajouté | Pourquoi c'était gênant |
|---|---|
| **Adhésions et rappels** | Le *« système de rappel automatique de renouvellement »* est **le point le plus cité du sujet**. Il était codé, testé… et impossible à montrer en démonstration. |
| **Bénéficiaires** | Associations caritatives et particuliers en détresse — **cités dans le sujet**. Créables uniquement par l'API. |
| **Utilisateurs et rôles** | C'est le **dernier trou connu de l'API** : créer un compte staff passe encore par une requête SQL. |
| **Emplacements de stock** | Le bouton existait en V2.3… et ne menait nulle part. |
| **Tournées (liste)** | La V2.3 avait le *détail* d'une tournée, mais aucun chemin pour y arriver. |
| **Catalogue services + compétences** | On pouvait affecter une compétence, pas en créer. Idem pour les services. |
| **Campagnes ciblées** | 4 routes, le plus gros ajout du projet — invisible sans écran. |

## Ce qui a été ajouté pour la fluidité

Compléter la liste ne suffisait pas. Quatre écrans manquaient pour que la navigation ait du sens :

- **Fiche commerçant** — une liste sans fiche est une impasse : on voyait des lignes sans pouvoir rien ouvrir. La fiche regroupe les infos, l'historique des adhésions, les collectes et les rappels envoyés.
- **Détail d'une collecte** — avec le champ de scan en tête, puisque c'est le geste qu'on fait *pendant* la collecte.
- **Formulaire de création d'un commerçant** — les boutons « Ajouter » de la V2.3 ne menaient nulle part. Avec une option pour créer l'adhésion dans la foulée, parce que c'est le geste qui suit toujours.
- **Mon compte** — accessible en cliquant sur son nom en bas de la barre latérale.
- **Configuration des rappels** — accessible par le bouton « Configurer » de l'écran Adhésions (voir ci-dessous).

Plus l'écran **Traductions**, déjà codé et testé mais absent des maquettes (réalisé après la V2.3).

## L'écran de configuration des rappels

Aujourd'hui, **tout le paramétrage du rappel automatique est écrit en dur** dans `utils/scheduler.go` :

```go
time.Sleep(24 * time.Hour)
envoyerRappelsRenouvellement(30, "j30")
envoyerRappelsRenouvellement(7, "j7")
envoyerRelancesExAbonnes(180, "ex_abonne")
sujet := "Votre adhesion NO MORE WASTE arrive a echeance"
```

Passer de 30 à 45 jours, ou corriger une faute dans l'objet d'un email, impose donc de modifier le code, reconstruire l'image et redéployer. **Pour un chiffre.**

L'écran `back-adhesions-config.html` sort ces réglages du code — la même démarche que pour les traductions. Il permet de régler :

| Réglage | Aujourd'hui |
|---|---|
| Activation du job | en dur (toujours actif) |
| Fréquence et heure d'exécution | `24 * time.Hour`, au démarrage du conteneur |
| Les échéances (J-30, J-7, ex-adhérent 180 j) | en dur, non modifiables, non supprimables |
| Objet et corps de chaque email | en dur dans le Go |
| Règles anti-spam | une seule, en dur |

Trois détails pensés pour l'usage réel :

- **Un interrupteur par échéance** plutôt qu'une suppression : on suspend une relance sans perdre son modèle d'email.
- **Un envoi de test** vers sa propre adresse, avec les variables remplacées par des exemples. Indispensable : ces modèles partent à de *vrais* commerçants.
- **La règle anti-doublon est cochée et verrouillée** (champ `disabled`) — c'est elle qui empêche d'envoyer le même email tous les jours pendant un mois. La rendre décochable serait un piège.

⚠️ **Rien de tout ça n'existe côté API.** L'écran suppose une table `parametres_rappels` et des routes `GET/PUT /parametres/rappels`.

### Les profils de rappel (évolution maquettée, non codée)

Les échéances ci-dessus s'appliquent à **tous** les commerçants. L'écran `back-adhesions-profils.html` propose d'adapter le rythme au cas par cas :

| Profil | Échéances | Pour qui |
|---|---|---|
| Standard | J-30, J-7 | par défaut |
| Rapproché | J-60, J-30, J-14, J-7 | gros partenaires |
| Léger | J-15 | ceux qui ont demandé moins de mails |

**Pourquoi des profils et non des « exceptions par groupe »** — c'est le point à savoir défendre. Avec des exceptions, il faudrait répondre à chaque envoi : *quelle règle s'applique à ce commerçant ?* Et la réponse dépendrait d'un ordre de priorité invisible : un commerçant dans deux groupes, une exception partielle, une exception qui en annule une autre. C'est le genre de logique très difficile à déboguer.

Avec des profils, chaque commerçant en a **exactement un**. La question devient « quel est son profil ? » : une seule réponse, toujours. Le profil s'affiche d'ailleurs sur la fiche du commerçant.

**Ce qui rend l'écran utilisable** — une liste de profils avec des nombres de jours reste abstraite, alors quatre choses la rendent concrète :

- **Une frise chronologique** par profil : on voit d'un coup d'œil si le rythme est dense ou espacé. `J-60 · J-30 · J-14 · J-7 · 🏁 · J+90` en dit plus qu'une liste de nombres.
- **Un simulateur** : on saisit une date de fin d'adhésion, on obtient les **dates réelles d'envoi** (« jeudi 24 décembre 2026 »). C'est la réponse à « concrètement, il reçoit un mail quand ? » — et ça fait apparaître les cas gênants, comme un envoi la veille de Noël.
- **L'édition des échéances** dans une modale : ajouter, retirer, changer le délai et le sens (avant/après).
- **L'affectation en masse** : cases à cocher + « appliquer à la sélection ». On classe rarement les commerçants un par un ; trier par cotisation aide à repérer les partenaires à suivre de près.

Un choix à noter : **un profil décide *quand* envoyer, pas *quoi* envoyer.** Les modèles d'email restent communs et se modifient dans la configuration. Sinon, corriger une faute de frappe imposerait de la corriger dans chaque profil.

Côté base ce serait : `profils_rappel`, `profil_echeances`, et une colonne `commercants.profil_rappel_id`. Aucune résolution de conflits.

**Ce n'est pas demandé par le sujet** — le rappel actuel (J-30/J-7 pour tout le monde) y répond déjà. À présenter comme une piste d'amélioration réfléchie, pas comme un manque.

## Les 4 écrans ajoutés après l'audit du sujet

Un audit du sujet contre les maquettes a révélé deux exigences sans écran :

| Écran | Exigence couverte |
|---|---|
| `erreur-404.html` | *« prévoir réécriture d'URL, **codes d'erreurs** etc. »* — les fichiers de `nginx/errors/` gardaient l'ancien habillage vert |
| `erreur-500.html` | idem. **Aucun détail technique affiché** : un message SQL renseignerait un attaquant sur la structure interne. La cause réelle part dans les journaux |
| `front-espace-commercant.html` | *« front office (utilisé par **les clients**) »* — un adhérent connecté ne voyait rien de plus qu'un visiteur |
| `front-espace-benevole.html` | idem, côté bénévole |

**L'espace commerçant** montre l'adhésion en premier (c'est elle qui conditionne tout le reste), ses collectes, et surtout un bouton **« demander une collecte »** — l'action principale d'un commerçant, qui n'existait nulle part.

**L'espace bénévole** rend visible **le blocage de validation**, avec le détail document par document. C'est la même règle que `back-benevole-detail`, vue de l'autre côté : sans ça, un bénévole ne comprend pas pourquoi il n'est affecté à aucune mission.

Les pages d'erreur utilisent l'en-tête **front** (aéré) et non la barre latérale : une erreur peut survenir n'importe où, y compris avant toute connexion.

## Le menu passe à 5 sections

Il suit le **parcours métier**, pas l'ordre des tables :

```
Pilotage        Tableau de bord
Réseau          Commerçants · Adhésions · Bénévoles · Bénéficiaires
Logistique      Collectes · Stocks · Emplacements · Tournées
Activités       Créneaux · Catalogue · Campagnes
Administration  Utilisateurs · Traductions
```

On entre dans le réseau (qui donne), on collecte, on stocke, on distribue. Puis viennent les activités, et enfin l'administration — ce qui ne relève pas du quotidien.

**Détail qui compte** : les écrans de détail n'ont pas d'entrée de menu (ils s'ouvrent depuis une liste), mais **l'entrée parente reste surlignée**. Sans ça, ouvrir une fiche ferait perdre toute position dans le menu.

## Ce qui n'a pas changé

Le parti pris visuel de la V2.3, tel quel :

- back-office **dense** — barre latérale sombre, tableaux compacts, onglets de sous-navigation ;
- front-office **aéré** — espace, typographie large, listes séparées par des filets ;
- une couleur = un sens (bleu = navigation/action, vert = état positif, orange = à traiter, rouge = bloquant) ;
- **zéro CSS écrit à la main** — tout en classes Bootstrap.

## Couverture

**19 domaines d'API, 0 sans écran.** Vérifié automatiquement en croisant `app.go` et le contenu des maquettes.

## Ce qui demanderait d'enrichir l'API

À connaître avant qu'on te pose la question — ces éléments figurent dans les maquettes mais **n'existent pas encore côté API** :

1. Le **tri** des colonnes et la **pagination** des listes (paramètres `tri` et `page`).
2. La **création d'un compte avec choix du rôle** (`POST /auth/register/` crée toujours un `adherent`).
3. La **modification de son propre profil** et le changement de mot de passe.
4. La **configuration des rappels** (délais, modèles d'email, fréquence) — tout est en dur dans `utils/scheduler.go`.
5. Les **profils de rappel** — évolution maquettée, non codée.

Tout le reste consomme des routes existantes et testées (75/75).

## Les écrans

**Front-office (7)** — `index`, `front-services`, `front-service-detail`, `front-candidature`, `front-connexion`, `front-espace-commercant`, `front-espace-benevole`

**Pages d'erreur (2)** — `erreur-404`, `erreur-500`

**Back-office (22)** — `back-tableau-de-bord`, `back-commercants`, `back-commercant-detail`, `back-commercant-nouveau`, `back-adhesions`, `back-adhesions-config`, `back-adhesions-profils`, `back-benevoles`, `back-benevole-detail`, `back-beneficiaires`, `back-collectes`, `back-collecte-detail`, `back-stocks`, `back-emplacements`, `back-tournees`, `back-tournee-detail`, `back-services`, `back-catalogue`, `back-campagnes`, `back-utilisateurs`, `back-traductions`, `back-profil`

**+ `sommaire.html`**, le point d'entrée.

⚠️ Ne déplace pas ce dossier hors de `Code/` : le chemin `../front-php/...` vers Bootstrap casserait.
