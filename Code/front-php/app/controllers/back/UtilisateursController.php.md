# Le module utilisateurs et rôles — contrôleur et vue

> ⏱️ **Lecture : ~8 min** · 750 mots

> Couvre `app/controllers/back/UtilisateursController.php` et `app/views/back/utilisateurs.php`.

## Pourquoi cet écran existe

`POST /auth/register` crée **toujours** un `adherent` : le rôle y est écrit en dur. Créer un compte pour un membre du personnel imposait donc une requête SQL à la main :

```sql
UPDATE utilisateurs SET role='admin_back' WHERE email='...';
```

Autrement dit, installer l'application sur un serveur neuf demandait d'ouvrir un client PostgreSQL. **Inacceptable pour un produit « packagé pour pouvoir être aisément déployé »**, comme le demande le sujet.

L'API a comblé ce trou (`POST /utilisateurs/`). Cet écran le rend utilisable.

## `admin_back` seulement, jamais `staff_back`

C'est le point à savoir défendre.

```php
if (!Auth::exigerStaff($this->config)) { return; }

if (Auth::role() !== ($this->config['role_admin_back'] ?? 'admin_back')) {
    $_SESSION['message_erreur'] = Langue::t('utilisateurs.reserve_admin');
    Auth::rediriger('/back');
    return;
}
```

**Deux gardes, pas une.** `exigerStaff()` laisse passer `admin_back` **et** `staff_back` — c'est son rôle. Il faut donc vérifier le rôle exact en plus.

Pourquoi cette restriction : **pouvoir créer des comptes, c'est pouvoir se fabriquer un accès**. Un membre du personnel pourrait se créer un second compte administrateur et contourner les limites de son propre rôle.

Les permissions ne se répartissent pas par confiance envers les personnes, mais par **conséquence de ce que l'action permet**.

L'API applique la même règle de son côté. Le front la redouble pour donner une explication lisible plutôt qu'un `403` brut.

## L'entrée de menu se masque

```php
'role' => 'admin_back',   // dans menu_back.php
```

Trouvé en testant : un `staff_back` voyait « Utilisateurs » dans son menu, cliquait, et se faisait renvoyer au tableau de bord. **Un lien qui rebondit donne l'impression d'un site cassé.**

Le bloc de menu saute désormais les entrées dont le `role` ne correspond pas :

```php
if (isset($entree['role']) && Auth::role() !== $entree['role']) {
    continue;
}
```

⚠️ C'est du **confort, pas de la sécurité** : n'importe qui peut taper l'adresse à la main. C'est le contrôleur qui protège réellement — vérifié, un `staff_back` qui force le `POST` est redirigé et aucun compte n'est créé.

Même raisonnement que le bouton désactivé de la fiche d'un bénévole. On a besoin des deux.

## La liste blanche des rôles

```php
private const ROLES = ['admin_back', 'staff_back', 'adherent', 'benevole'];
```

Sans elle, on créerait un compte `super_admin` qu'**aucune garde ne reconnaîtrait**. Son propriétaire pourrait se connecter, puis serait refusé partout — sans que personne comprenne pourquoi, puisque le compte existe bel et bien et que le mot de passe est bon.

C'est un cas où une donnée invalide ne provoque aucune erreur : elle crée juste un utilisateur fantôme.

Cette liste est **recopiée** depuis l'API, comme les délais de rappel. Le défaut est le même, et la solution propre aussi (une route qui l'exposerait).

## Le rôle par défaut est le moins puissant

```php
<option value="<?= Vue::e($r) ?>" <?= $r === 'adherent' ? 'selected' : '' ?>>
```

L'ordre de la liste va du plus puissant au moins puissant — mais le **présélectionné** est `adherent`. Un clic distrait sur « Créer » ne fabrique pas un administrateur.

L'encadré orange le rappelle : *« Un administrateur peut créer d'autres administrateurs. »*

## Ce que l'écran ne fait pas — et le dit

> « Changer un rôle, réinitialiser un mot de passe ou désactiver un compte demanderait des routes que l'API n'expose pas encore. »

La maquette montrait un menu avec ces trois actions. Les coder comme des boutons morts aurait été pire que de ne rien afficher : on cherche, on clique, rien ne se passe.

**Dire ce qui manque vaut mieux que de laisser chercher un bouton qui n'existe pas.**

## Le problème du premier compte

Créer un administrateur exige d'être administrateur. Ça n'a **pas de solution purement applicative**.

C'est le rôle du script d'installation (item 12.1, encore à faire). Mais le trou est réduit à **un seul** compte au lieu d'un par membre du personnel.

## Comment le vérifier soi-même

```bash
# un staff_back tente d'entrer
curl -s -o /dev/null -w "%{http_code} %{redirect_url}\n" -b cookies-staff.txt \
  http://localhost:8080/back/utilisateurs
# -> 302 vers /back

# et s'il force le POST ?
curl -X POST http://localhost:8080/back/utilisateurs -b cookies-staff.txt \
  --data-urlencode "email=pirate@test.fr" --data-urlencode "mot_de_passe=motdepasse123" \
  --data-urlencode "role=admin_back"
# -> 302 vers /back, et AUCUN compte pirate en base

# l'entrée de menu
curl -s -b cookies-staff.txt http://localhost:8080/back | grep -c '/back/utilisateurs'
# -> 0   (1 pour un admin)

# rôle inventé
# -> « Ce rôle n'existe pas. » ; l'API n'est pas appelée

# mot de passe trop court
# -> « Le mot de passe doit faire au moins 8 caractères. »

# email déjà pris
# -> « Email deja utilise » (message de l'API)
```

Vérifié le 2026-08-07, dans les quatre langues.

## Fichiers liés

- [../../views/back/utilisateurs.php.md](../../views/back/utilisateurs.php.md) — la vue
- [../../config/menu_back.php.md](../../config/menu_back.php.md) — la clé `role` des entrées
- [../../middleware/Auth.php.md](../../middleware/Auth.php.md) — `role()` et `exigerStaff()`
- [../../../../api-go/app/utilisateurs.go.md](../../../../api-go/app/utilisateurs.go.md) — la même règle côté API
