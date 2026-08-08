# utils/jwt.go — créer et vérifier des tokens de connexion

> ⏱️ **Lecture : ~15 min** · 880 mots, 48 lignes de code

## C'est quoi un JWT, en partant de zéro ?

Imagine que tu te connectes sur un site. Le serveur ne va pas te redemander ton mot de passe à CHAQUE page que tu visites — ce serait pénible et lent. Au lieu de ça, dès la première connexion réussie, le serveur te donne un "badge" (le token JWT) : une longue chaîne de caractères illisible, qui prouve ton identité pour un temps limité. Ensuite, à chaque requête, tu montres ce badge, et le serveur vérifie juste qu'il est valide, sans redemander ton mot de passe.

JWT veut dire "JSON Web Token". Concrètement, c'est un texte codé qui contient des informations (ici : ton email et ton rôle) ET une "signature" — une empreinte mathématique qui prouve que ce badge a bien été fabriqué par le serveur, et que personne ne l'a modifié en cours de route.

## C'est quoi ce fichier ?

Ce fichier contient deux fonctions : une pour FABRIQUER un token (`GenerateJWT`), une pour le VÉRIFIER (`VerifyJWT`).

## La clé secrète

```go
var JwtSecret = []byte(jwtSecretFromEnv())
```

`JwtSecret` est un mot de passe interne, connu SEULEMENT par le serveur (jamais envoyé au client), qui sert à signer et à vérifier les tokens. `[]byte(...)` transforme un texte (`string`) en une liste d'octets (`byte` = un petit nombre de 0 à 255) — beaucoup de fonctions de cryptographie en Go travaillent avec des `[]byte` plutôt que des `string`, car ça correspond mieux à la manière dont les ordinateurs manipulent réellement les données binaires.

```go
func jwtSecretFromEnv() string {
    secret := os.Getenv("JWT_SECRET")
    if secret == "" {
        secret = "nmw_dev_jwt_secret_2026"
    }
    return secret
}
```

Cette fonction lit la clé secrète depuis la variable d'environnement `JWT_SECRET` (définie dans `.env`), avec une valeur de secours si elle est absente — pratique en développement, mais en vrai il faudrait toujours en définir une en production.

## Fonction 1 : GenerateJWT (fabriquer un token)

```go
func GenerateJWT(email string, role string) (string, error) {
    claims := jwt.MapClaims{
        "email": email,
        "role":  role,
        "exp":   time.Now().Add(time.Hour * 8).Unix(),
        "iat":   time.Now().Unix(),
    }
    token := jwt.NewWithClaims(jwt.SigningMethodHS256, claims)
    return token.SignedString(JwtSecret)
}
```

### Les "claims"
`jwt.MapClaims{...}` est un dictionnaire (une liste de paires clé-valeur) qui contient ce qu'on veut mettre DANS le token :
- `"email"` et `"role"` : les infos qu'on veut retrouver plus tard, quand on vérifiera le token.
- `"exp"` (expiration) et `"iat"` (issued at, "émis à") : deux noms STANDARDS du format JWT (définis par la norme, pas inventés par nous) — la date à laquelle le token expire (ici 8 heures après sa création, grâce à `time.Now().Add(time.Hour * 8)`) et la date à laquelle il a été créé.

### La fabrication et la signature
`jwt.NewWithClaims(jwt.SigningMethodHS256, claims)` construit le token brut, avec l'algorithme de signature HS256 (une méthode cryptographique standard). `token.SignedString(JwtSecret)` signe ce token avec notre clé secrète et retourne la chaîne de caractères finale — c'est CETTE chaîne qui est envoyée au client après un login réussi.

### Pourquoi la fonction retourne (string, error)
Comme beaucoup de fonctions Go qui peuvent échouer, elle retourne deux valeurs : le résultat (le token, si tout va bien) et une éventuelle erreur. Si la signature échouait pour une raison quelconque, `err` ne serait pas `nil` et le code appelant devrait gérer ce cas (voir `app/auth.go.md`, dans `Login`).

## Fonction 2 : VerifyJWT (vérifier un token reçu)

```go
func VerifyJWT(tokenString string) (string, string, error) {
    token, err := jwt.Parse(tokenString, func(token *jwt.Token) (any, error) {
        _, ok := token.Method.(*jwt.SigningMethodHMAC)
        if !ok {
            return nil, fmt.Errorf("methode de signature inattendue")
        }
        return JwtSecret, nil
    })
    if err != nil {
        return "", "", err
    }

    claims, ok := token.Claims.(jwt.MapClaims)
    if ok && token.Valid {
        email, _ := claims["email"].(string)
        role, _ := claims["role"].(string)
        return email, role, nil
    }
    return "", "", fmt.Errorf("jeton invalide")
}
```

### Une fonction qui prend une autre fonction en paramètre
```go
jwt.Parse(tokenString, func(token *jwt.Token) (any, error) { ... })
```
C'est un concept un peu déroutant au début : en Go, on peut passer une fonction comme paramètre d'une autre fonction (on appelle ça une "fonction anonyme" ou "closure" quand elle n'a pas de nom). Ici, `jwt.Parse` a besoin qu'on lui dise QUELLE clé secrète utiliser pour vérifier la signature — donc on lui fournit une petite fonction qui, une fois appelée en interne par `jwt.Parse`, renvoie notre `JwtSecret`.

À l'intérieur de cette fonction, on vérifie aussi que l'algorithme utilisé est bien celui attendu (HMAC) — c'est une protection de sécurité connue : sans cette vérification, un attaquant pourrait essayer de forger un token avec un algorithme différent pour tromper le serveur.

### Extraire les informations du token
```go
claims, ok := token.Claims.(jwt.MapClaims)
```
Cette syntaxe `valeur.(Type)` s'appelle une "assertion de type" en Go : `token.Claims` a un type générique, et on demande ici "est-ce que c'est vraiment un `jwt.MapClaims` ? Si oui, donne-le moi sous cette forme précise, sinon dis-moi non (`ok = false`)".

```go
email, _ := claims["email"].(string)
```
Même principe : on récupère la valeur associée à la clé `"email"` dans le dictionnaire, et on vérifie/convertit qu'elle est bien de type `string`. Le `_` ignore volontairement le deuxième résultat (un booléen qui dirait si la conversion a réussi) — ici, si la conversion échoue, `email` sera simplement une chaîne vide, ce qui est acceptable pour ce cas d'usage.

### Ce que retourne VerifyJWT
`(string, string, error)` : l'email, le rôle, et une éventuelle erreur. Si le token est invalide, expiré, ou mal signé, `err` ne sera pas `nil` et le code appelant (dans `app/auth.go` ou `utils/guard.go`) doit refuser l'accès.

## Piège à connaître

Contrairement à ce qu'on voit parfois ailleurs (avec le préfixe `"Bearer "` devant le token dans le header), ce projet garde les choses simples comme montré dans le support de cours : le client doit envoyer le token BRUT dans le header `Authorization`, sans rien devant.

Aussi : si la valeur de `JWT_SECRET` change (par exemple si `.env` n'est pas bien configuré et qu'une valeur aléatoire est générée à chaque redémarrage), TOUS les tokens émis avant deviennent immédiatement invalides — c'est pour ça qu'on fixe cette valeur dans `.env` plutôt que de la générer au hasard.
