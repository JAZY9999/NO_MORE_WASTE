# Checklist de test manuel — API NO MORE WASTE

> ⏱️ **Usage : à cocher au fur et à mesure**, pas à lire d'une traite.

## Avant de commencer

Deux suites **automatiques** existent déjà et couvrent l'essentiel :

```bash
cd Code
python tests/tester-tous-les-endpoints.py   # -> doit afficher 80/80
python tests/tester-espace-client.py        # -> doit afficher 23/23
```

**Lance-les d'abord.** Si l'un des deux nombres n'est pas au maximum, inutile de continuer cette checklist : quelque chose de plus fondamental est cassé (conteneurs pas démarrés, base de données pas à jour...). Regarde `docker compose ps` et `docker compose logs api-go`.

Cette checklist-ci sert à autre chose : **comprendre et démontrer** que l'API fonctionne, pas juste obtenir un chiffre. Chaque commande dit ce qu'elle vérifie et pourquoi c'est important — utile pour une session de live coding, ou pour te rassurer toi-même avant une démonstration.

### Se connecter une fois pour toutes

```bash
export TOKEN=$(curl -s -X POST http://localhost:8080/api/auth/login/ \
  -H "Content-Type: application/json" \
  -d '{"email":"staff2@nomorewaste.fr","mot_de_passe":"motdepasse123"}' \
  | python -c "import sys,json;print(json.load(sys.stdin)['token'])")
echo $TOKEN
```

⚠️ **Le jeton part BRUT dans l'en-tête**, sans le préfixe `Bearer ` (simplification du cours) :
```bash
curl http://localhost:8080/api/... -H "Authorization: $TOKEN"
```

---

## 1. Authentification — la base de tout le reste

- [ ] **Connexion avec un bon mot de passe** → `200`, un jeton dans la réponse.
- [ ] **Connexion avec un mauvais mot de passe** → message volontairement vague (« Email ou mot de passe incorrect »), jamais « email inconnu » (ne pas révéler quels comptes existent) :
  ```bash
  curl -s -w "\n%{http_code}\n" -X POST http://localhost:8080/api/auth/login/ \
    -H "Content-Type: application/json" \
    -d '{"email":"staff2@nomorewaste.fr","mot_de_passe":"faux"}'
  ```
- [ ] **Inscription publique** (`/auth/register/`) → crée toujours un `adherent`, jamais un rôle choisi :
  ```bash
  curl -s -w "\n%{http_code}\n" -X POST http://localhost:8080/api/auth/register/ \
    -H "Content-Type: application/json" \
    -d '{"email":"test-checklist@nomorewaste.fr","mot_de_passe":"motdepasse123"}'
  ```
- [ ] **Mot de passe trop court** (moins de 5 caractères) → `400`, message explicite.
- [ ] **Email déjà utilisé** → `409 Conflict`, pas `500`.
- [ ] **`GET /auth/me/`** avec le jeton → renvoie l'email et le rôle du compte connecté (c'est cette route, et pas le décodage du JWT, qui sert de source de vérité côté front — voir `Auth.php.md`).
- [ ] **Sans jeton du tout**, sur n'importe quelle route protégée → `401`, jamais un plantage :
  ```bash
  curl -s -w "\n%{http_code}\n" http://localhost:8080/api/commercants/
  ```
- [ ] **Avec un jeton trafiqué** (change un caractère du vrai jeton) → `401` aussi.

---

## 2. Commerçants & adhésions

- [ ] **Créer un commerçant** avec juste la raison sociale (seul champ obligatoire) :
  ```bash
  curl -s -w "\n%{http_code}\n" -X POST http://localhost:8080/api/commercants/ \
    -H "Authorization: $TOKEN" -H "Content-Type: application/json" \
    -d '{"raison_sociale":"Test Checklist"}'
  ```
- [ ] **Sans raison sociale** → `400`.
- [ ] **Lister** les commerçants → jamais `null` si la liste est vide, toujours `[]` (vérifie ce détail : c'est un piège Go classique, voir `commercants.go.md`).
- [ ] **Modifier partiellement** (`PUT /commercants/{id}`) — n'envoyer QUE la ville — et vérifier que les autres champs (email, SIRET...) n'ont pas été effacés :
  ```bash
  curl -s -X PUT http://localhost:8080/api/commercants/1 \
    -H "Authorization: $TOKEN" -H "Content-Type: application/json" -d '{"ville":"Lyon"}'
  curl -s http://localhost:8080/api/commercants/1 -H "Authorization: $TOKEN"
  ```
- [ ] **Rattacher un compte déjà pris** à un second commerçant → `409` (contrainte `UNIQUE` sur `utilisateur_id`).
- [ ] **Créer une adhésion** pour un commerçant, avec `date_fin` **avant** `date_debut` → l'API l'**accepte** (aucune validation d'ordre des dates côté API). Ce n'est pas un bug caché : c'est une limite connue, à savoir expliquer si on te la fait remarquer.
- [ ] **`GET /adhesions/`** → renvoie bien le nom du commerçant à côté de chaque adhésion (jointure), pas juste un `commercant_id` brut.
- [ ] **Filtrer par statut invalide** (`?statut=nimportequoi`) → `400`, pas une liste vide trompeuse.

---

## 3. Le rappel automatique de renouvellement — le point le plus cité du sujet

- [ ] **`GET /adhesions/a-renouveler/`** → ne renvoie que les adhésions à **J-30 ou J-7 exactement**, pas toutes celles qui arrivent bientôt.
- [ ] **Créer une adhésion qui expire dans exactement 30 jours** (date du jour + 30), puis vérifier qu'elle apparaît dans la liste ci-dessus :
  ```bash
  J30=$(python -c "import datetime;print((datetime.date.today()+datetime.timedelta(days=30)).isoformat())")
  curl -s -X POST http://localhost:8080/api/commercants/1/adhesions \
    -H "Authorization: $TOKEN" -H "Content-Type: application/json" \
    -d "{\"date_debut\":\"2026-01-01\",\"date_fin\":\"$J30\",\"statut\":\"active\"}"
  curl -s http://localhost:8080/api/adhesions/a-renouveler/ -H "Authorization: $TOKEN"
  ```
- [ ] **Déclencher le job manuellement** (`POST /admin/jobs/rappels-adhesions/`) → regarder les journaux (`docker compose logs api-go`) pour voir une tentative d'envoi par adhésion sélectionnée.
- [ ] **Relancer manuellement une adhésion sans email** → `400`, pas de tentative d'envoi.
- [ ] **Relancer avec le SMTP non configuré** → `502` (pas `500` : ce n'est pas un bug du serveur, c'est le service externe qui refuse).
- [ ] **Consulter l'historique** (`GET /adhesions/{id}/historique-rappels`) après un envoi réussi → le type de rappel (`j30`, `j7`, `manuel`) et la date apparaissent.
- [ ] **Un même type de rappel n'est jamais envoyé deux fois** : relancer le job sur la même adhésion à J-30 une seconde fois ne doit pas dupliquer l'entrée d'historique.

---

## 4. Stocks : produits et emplacements

- [ ] **Créer un emplacement** sans entrepôt (seul champ obligatoire) → `400`.
- [ ] **Créer un produit** avec un code-barre → `201`.
- [ ] **Rechercher par code-barre exact** (`?code_barre=...`) → renvoie **un objet**, pas une liste.
- [ ] **Rechercher un code-barre inconnu** → `404` (pas une erreur serveur : c'est une information normale).
- [ ] **Créer un produit avec un `emplacement_id` inexistant** → `400` (violation de clé étrangère traduite proprement, pas un `500` avec un message SQL brut).
- [ ] **Déplacer un produit** (`PUT /produits/{id}`) vers un emplacement inexistant → même vérification.
- [ ] **Filtrer par statut** (`?statut=en_stock`) → ne renvoie que les produits en stock, jamais les distribués.

---

## 5. Collectes

- [ ] **Créer une collecte pour un commerçant ET un particulier en même temps** — doit être refusée (une collecte a une seule source, jamais les deux).
- [ ] **Créer une collecte sans aucune source** — doit aussi être refusée.
- [ ] **Scanner un produit sur une collecte** (`POST /collectes/{id}/produits`) → crée le produit **et** le rattache en une seule requête.
- [ ] **Changer le statut vers `realisee`** → vérifie que `date_realisee` se remplit automatiquement côté API (le front n'a pas à l'envoyer).
- [ ] **Statut invalide** (`?statut=xyz` ou dans le corps) → `400`.

---

## 6. Bénévoles, documents et compétences

- [ ] **Candidature anonyme** (`POST /benevoles/candidature/`, sans jeton) → `201`, statut `candidat`, `utilisateur_id` à `NULL`.
- [ ] **Candidature avec un jeton valide** → `utilisateur_id` rempli avec le compte connecté.
- [ ] **Candidature qui tente de forcer un `utilisateur_id` dans le corps** → ignoré, le champ reste `NULL` (route publique, ne jamais faire confiance à un identifiant envoyé par le client).
- [ ] **Valider un bénévole qui a un document non validé** → refusé (règle : TOUS les documents doivent être validés).
- [ ] **Valider le dernier document, puis le bénévole** → doit maintenant réussir.
- [ ] **Ajouter deux fois la même compétence** à un bénévole → `409` (doublon).
- [ ] **Retirer une compétence qu'il n'a pas** → `204`, pas `404` : un `DELETE` SQL qui ne trouve aucune ligne à supprimer ne remonte pas d'erreur. Bon réflexe à connaître, ce n'est volontairement pas signalé comme un cas d'erreur.

---

## 7. Services, créneaux, inscriptions et planning

- [ ] **`GET /services/`** sans aucun jeton → doit fonctionner (route publique, catalogue consultable par tous).
- [ ] **Créer un service avec un type inventé** → `400` (contrainte `CHECK`, sept valeurs seulement : `conseil_anti_gaspi`, `cours_cuisine`, `partage_vehicule`, `echange_service`, `reparation`, `gardiennage`, `autre`).
- [ ] **S'inscrire à un créneau en tant qu'adhérent, en forgeant un `commercant_id` d'un autre commerçant dans le corps** → l'inscription doit se faire **en son propre nom**, jamais celui visé dans le corps (faille corrigée pendant le projet — voir `services.go.md`).
- [ ] **S'inscrire à un créneau déjà complet** → `409`.
- [ ] **S'inscrire à un créneau annulé** → `400`.
- [ ] **Affecter un bénévole non validé** à un créneau → refusé.
- [ ] **Affecter un bénévole sans la compétence requise** par le service → refusé, message explicite.
- [ ] **Télécharger le planning** (`GET /plannings/?date=...`) pour une date sans aucun créneau → un CSV avec juste l'en-tête, pas une erreur.
- [ ] **Vérifier le format du CSV** : ouvrir le fichier, les heures doivent être `HH:MM`, jamais `0000-01-01T...`.

---

## 8. Bénéficiaires

- [ ] **Créer un bénéficiaire avec un type inventé** → `400` (deux valeurs seulement : `association_caritative`, `particulier_detresse`).
- [ ] **Lister** → jamais `null`, toujours `[]` si vide.

---

## 9. Tournées, étapes et le PDF exigé par le sujet

- [ ] **Créer une tournée**, lui ajouter deux étapes.
- [ ] **`GET /tournees/{id}/etapes`** → chaque étape a bien un champ `livraison_id` (`null` tant qu'elle n'est pas clôturée — sans lui, impossible de construire le lien vers le PDF).
- [ ] **Clôturer une livraison** (`POST /tournee-etapes/{id}/livraison`) avec un produit qui n'existe pas → `400`, la livraison n'est pas créée.
- [ ] **Clôturer avec un produit valide** → le produit passe automatiquement au statut `distribue` (vérifier avec `GET /produits/{id}`).
- [ ] **Clôturer la même étape une seconde fois** → `409` (une étape n'a qu'une seule livraison).
- [ ] **Télécharger le PDF** (`GET /livraisons/{id}/pdf`) → un vrai fichier PDF (`%PDF-1.4` au début, `%%EOF` à la fin) :
  ```bash
  curl -s -o recap.pdf http://localhost:8080/api/livraisons/1/pdf -H "Authorization: $TOKEN"
  head -c 8 recap.pdf; echo; tail -c 6 recap.pdf
  ```
- [ ] **Les heures des étapes** (`heure_prevue`, `heure_reelle`) → format `HH:MM`, jamais `0000-01-01T...`.

---

## 10. Campagnes

- [ ] **Créer une campagne** sans critère → doit viser TOUS les commerçants.
- [ ] **Prévisualiser les destinataires** (`GET /campagnes/{id}/destinataires`) AVANT de déclencher — vérifier que la liste correspond bien aux critères choisis.
- [ ] **Déclencher** → l'API renvoie `{"nombre_envoyes": N}`, le nombre **réellement envoyé**, pas le nombre de destinataires visés (les deux diffèrent si un email manque ou si l'envoi échoue).

---

## 11. Comptes et rôles — le dernier trou comblé

- [ ] **Un `adherent` tente de créer un compte** (`POST /utilisateurs/`) → `403`.
- [ ] **Un `staff_back` (non admin) tente de créer un compte** → `403` aussi (réservé à `admin_back` seul : créer des comptes, c'est pouvoir se fabriquer un accès).
- [ ] **Un `admin_back` crée un compte avec un rôle inventé** (`super_admin`) → `400` (liste blanche des 4 rôles).
- [ ] **Un `admin_back` crée un compte `staff_back`** → `201`, réussit.

---

## 12. Traductions et multilingue

- [ ] **`GET /traductions/?langue=fr`** sans jeton → fonctionne (route publique : le site doit s'afficher pour un visiteur non connecté).
- [ ] **Créer deux fois la même clé pour la même langue** → `409` (contrainte `UNIQUE` sur `cle` + `code_langue`).
- [ ] **Import** (`POST /traductions/import`) → ajoute et met à jour, mais **ne supprime jamais** une clé absente du fichier (piège déjà rencontré deux fois dans ce projet — voir le journal de bord).
- [ ] **Les 4 langues répondent** : `fr`, `en`, `it`, `pt` — vérifie qu'aucune n'est en retard par rapport aux autres :
  ```bash
  for L in fr en it pt; do
    curl -s "http://localhost:8080/api/traductions/?langue=$L" -H "Authorization: $TOKEN" \
      | python -c "import sys,json;print('$L :', len(json.load(sys.stdin) or []), 'cles')"
  done
  ```

---

## 13. Espace client (`/mon-espace/*`) — l'isolation est le point critique

- [ ] **Un commerçant A ne peut jamais voir les données d'un commerçant B** : aucune route `/mon-espace/*` n'accepte d'identifiant fourni par le client, tout part du jeton.
- [ ] **Un bénévole qui appelle `/mon-espace/commercant`** → `403`.
- [ ] **Un adhérent qui appelle `/mon-espace/benevole`** → `403`.
- [ ] **Le personnel (`admin_back`/`staff_back`) qui appelle `/mon-espace/*`** → `403` aussi (le personnel passe par le back-office, pas par l'espace client).
- [ ] **Demander une collecte avec une date passée** → `400`.
- [ ] **Un candidat bénévole (statut non `valide`) qui demande son planning** → liste vide, pas une erreur. `MonPlanning` ne vérifie pas explicitement le statut : la liste est vide simplement parce qu'un candidat n'a jamais pu être affecté à un créneau (`AffecterBenevoleCreneau` l'exige en amont). Une garde de plus, mais qui ne coûterait rien à ajouter si ce raisonnement venait à changer.

---

## 14. Sécurité transversale — à vérifier une seule fois, mais partout applicable

- [ ] **`.env` et `.git` ne sont jamais servis publiquement** :
  ```bash
  curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8080/.env
  curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8080/.git/config
  ```
  Attendu : `403` ou `404`, jamais `200`.
- [ ] **Une erreur serveur ne révèle jamais de détail technique** au client (pas de nom de table, pas de trace Go, pas de requête SQL) — la vraie cause doit être dans `docker compose logs api-go`, jamais dans la réponse HTTP.
- [ ] **La base de données injoignable** → l'API répond `503`, pas un plantage brutal :
  ```bash
  docker compose stop postgres
  curl -s -w "\n%{http_code}\n" http://localhost:8080/api/
  docker compose start postgres
  ```

---

## Après cette checklist

Relance les deux suites automatiques une dernière fois — plusieurs commandes ci-dessus ont créé des données de test :

```bash
python tests/tester-tous-les-endpoints.py   # reinitialise les donnees, doit rester 80/80
python tests/tester-espace-client.py        # doit rester 23/23
```

Puis restaure les traductions si tu as touché à la table (le premier script vide `traductions` en fin de course) :

```bash
# se connecter au back-office, puis
curl -X POST http://localhost:8080/back/traductions -d "action=importer" -b cookies.txt
```
