# Visite guidée du site — vérifier chaque écran à la main, dans un navigateur

> ⏱️ **Compter 30 à 40 min** pour tout parcourir sans se presser, à cocher au fur et à mesure.

## Ce que ce guide vérifie, et ce qu'il ne vérifie pas

Deux suites **automatiques** existent déjà et prouvent que l'API répond correctement, requête par requête :

```bash
cd Code
python tests/tester-tous-les-endpoints.py   # -> doit afficher 80/80
python tests/tester-espace-client.py        # -> doit afficher 23/23
```

Elles ne regardent jamais un écran. Ce guide-ci fait l'inverse : il vérifie **ce que voit un vrai visiteur** dans son navigateur — mise en forme, messages d'erreur, redirections, traduction — pas le contenu brut d'une réponse JSON.

## Avant de commencer

Remplace `<SITE>` par l'adresse réelle :

- En production : `https://upcycleconnect.uk`
- En local : `http://localhost:8000` (ou le port choisi dans `.env`)

Les trois comptes ci-dessous existent déjà sur toute installation où les deux scripts automatiques ont été lancés au moins une fois (ils les créent s'ils manquent) :

| Rôle | Email | Mot de passe | Donne accès à |
|---|---|---|---|
| Administrateur (`admin_back`) | `staff2@nomorewaste.fr` | `motdepasse123` | `<SITE>/back` en entier |
| Commerçant adhérent (`adherent`) | `client.test@nomorewaste.fr` | `motdepasse123` | `<SITE>/mon-espace/commercant` |
| Bénévole (`benevole`) | `benevole.test@nomorewaste.fr` | `motdepasse123` | `<SITE>/mon-espace/benevole` |

⚠️ `tester-tous-les-endpoints.py` **vide la table des traductions** à chaque lancement (comportement documenté). Si tu viens de le relancer, refais d'abord « Fichiers vers base » sur `/back/traductions` — sinon tu verras des clés brutes (`nav.connexion`) à la place du texte pendant toute la visite.

---

## 1. Le site public — sans être connecté

Ouvre `<SITE>/` dans une fenêtre **privée/incognito**, pour être sûr de ne pas être déjà connecté.

- [ ] **Accueil** (`/`) — la page se charge, les icônes Bootstrap s'affichent (pas de carré vide = la police d'icônes est bien chargée), aucun texte n'est une clé brute du style `accueil.mission`.
- [ ] **Changer de langue** via le menu en haut à droite (`fr`, `en`, `it`, `pt`) — le texte change, tu restes sur la même page (l'adresse devient `?lang=it`).
- [ ] **Catalogue des services** (`/services`) — accessible sans connexion : c'est la vitrine publique de l'association.
- [ ] **Détail d'un service** (clique depuis la liste) — le bouton d'inscription à un créneau doit renvoyer vers `/connexion` si tu n'es pas connecté, jamais une erreur brute.
- [ ] **Candidature bénévole** (`/benevoles/candidature`) — remplis le formulaire avec un email inédit, envoie : tu dois arriver sur `/benevoles/candidature/merci`, jamais une page blanche.
- [ ] **404** — va sur une adresse inventée (`<SITE>/n-importe-quoi`) : une vraie page d'erreur habillée doit s'afficher, jamais la page blanche par défaut du serveur.

## 2. Connexion / déconnexion

- [ ] `/connexion` avec un mauvais mot de passe → message d'erreur affiché **en haut de la page**, pas de plantage.
- [ ] `/connexion` avec `staff2@nomorewaste.fr` / `motdepasse123` → redirection automatique vers `/back`.
- [ ] `/deconnexion` → retour à l'accueil ; retourner ensuite sur `/back` doit renvoyer vers `/connexion`.

## 3. Back-office — connecté en administrateur

Connecte-toi avec `staff2@nomorewaste.fr`.

- [ ] **Tableau de bord** (`/back`) — la barre latérale affiche les 5 sections (Pilotage / Réseau / Logistique / Activités / Administration), l'entrée de la page courante est surlignée.
- [ ] **Commerçants** (`/back/commercants`) — la liste s'affiche ; le filtre par ville (`?ville=Paris`) doit au moins retrouver « Commerce de test espace client » ; ouvre une fiche, puis crée-en une nouvelle via « Nouveau commerçant ».
- [ ] **Adhésions** (`/back/adhesions`) — ouvre une fiche : le nom du commerçant doit s'afficher, jamais un simple identifiant numérique.
- [ ] **Stocks** (`/back/stocks`) et **Emplacements** (`/back/emplacements`) — crée un emplacement, puis un produit qui y est rattaché, vérifie qu'il apparaît dans la liste avec le bon statut.
- [ ] **Collectes** (`/back/collectes`) — ouvre le détail d'une collecte, essaie le formulaire d'ajout d'un produit par code-barre.
- [ ] **Bénévoles** (`/back/benevoles`) — ouvre la fiche du bénévole de test (nom « Test », prénom « Espace ») : son statut doit être `candidat` tant que personne ne l'a validé.
- [ ] **Services & créneaux** (`/back/services`) puis **Planning** (`/back/plannings`) — télécharge le CSV et ouvre-le : les heures doivent être au format `HH:MM`, jamais `0000-01-01T...`.
- [ ] **Bénéficiaires** (`/back/beneficiaires`) — crée-en un, vérifie qu'il apparaît dans la liste.
- [ ] **Tournées** (`/back/tournees`) — ouvre une tournée, clôture une étape si possible, télécharge le PDF récapitulatif : il doit s'ouvrir comme un vrai PDF.
- [ ] **Campagnes** (`/back/campagnes`) — crée-en une, ouvre sa fiche et regarde la liste des destinataires **avant** de cliquer sur « Déclencher » (un envoi réel ne s'annule pas : ne clique que si tu veux vraiment tester l'envoi).
- [ ] **Utilisateurs** (`/back/utilisateurs`) — crée un compte `staff_back` de test, vérifie qu'il apparaît dans la liste.
- [ ] **Traductions** (`/back/traductions`) — l'écran qui posait le souci de permissions : « Base vers fichiers » puis « Fichiers vers base » doivent réussir sans message rouge.

## 4. Espace client — connecté en commerçant

Déconnecte-toi, reconnecte-toi avec `client.test@nomorewaste.fr`.

- [ ] `/mon-espace/commercant` — la fiche affichée est « Commerce de test espace client », jamais celle d'un autre commerçant.
- [ ] Demande une collecte avec une date **future** → confirmation affichée.
- [ ] Demande une collecte avec une date **passée** → message d'erreur, pas de création silencieuse.
- [ ] Essaie `/back` avec ce compte → refusé, renvoyé vers `/` (jamais un écran back-office à moitié affiché).
- [ ] Essaie `/mon-espace/benevole` avec ce compte → refusé aussi : chaque espace est réservé à son rôle.

## 5. Espace client — connecté en bénévole

Déconnecte-toi, reconnecte-toi avec `benevole.test@nomorewaste.fr`.

- [ ] `/mon-espace/benevole` — la fiche affichée est la sienne.
- [ ] Ce compte est encore `candidat` (jamais validé par un admin) : le planning doit s'afficher **vide**, pas en erreur — c'est le comportement attendu, pas un bug (voir `tester-espace-client.py.md`).
- [ ] Essaie `/back` et `/mon-espace/commercant` avec ce compte → refusés tous les deux.

## 6. Un dernier point technique, pas cliquable mais important pour un site public

- [ ] `.env` et `.git` ne doivent jamais être accessibles depuis l'extérieur :
  ```bash
  curl -s -o /dev/null -w "%{http_code}\n" <SITE>/.env
  curl -s -o /dev/null -w "%{http_code}\n" <SITE>/.git/config
  ```
  Attendu : `403` ou `404`, jamais `200`.

## 7. Après la visite

- [ ] Si une campagne ou une relance a réellement été déclenchée pendant la visite, note-le : c'est la seule action de ce guide qui n'est pas rejouable à l'identique.
- [ ] Si `/back/traductions` n'a pas été synchronisé pendant la visite, fais « Fichiers vers base » avant de montrer le site à quelqu'un d'autre.
- [ ] Relance les deux scripts automatiques pour repartir sur une base propre :
  ```bash
  python tests/tester-tous-les-endpoints.py   # doit rester 80/80
  python tests/tester-espace-client.py        # doit rester 23/23
  ```

## Fichiers liés

- [tester-tous-les-endpoints.py.md](tester-tous-les-endpoints.py.md) — la même vérification, côté API, en 80 requêtes automatiques
- [tester-espace-client.py.md](tester-espace-client.py.md) — pourquoi ces comptes existent et comment ils sont rattachés à leur fiche
- [../install.sh.md](../install.sh.md) — comment le compte administrateur est créé la toute première fois
