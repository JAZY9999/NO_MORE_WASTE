# config/config.go — où on range les réglages du projet

> ⏱️ **Lecture : ~5 min** · 523 mots, 8 lignes de code

## C'est quoi ce fichier ?

Ce fichier ne contient AUCUNE logique métier (rien qui gère des commerçants, des collectes, etc.). Il sert juste à répondre à des questions simples comme "quelle est l'adresse de la base de données ?" ou "sur quel port l'API doit-elle écouter ?".

## Pourquoi séparer ça dans son propre fichier/dossier ?

Imagine que l'adresse de la base de données soit écrite en dur, tapée directement, dans 10 fichiers différents du projet. Le jour où on change de serveur, il faudrait modifier ces 10 fichiers un par un, avec le risque d'en oublier un. En centralisant tout ici, il n'y a qu'un seul endroit à connaître.

## Notions Go utilisées ici

### `const ( ... )`
Une "constante" : une valeur qui ne changera jamais pendant l'exécution du programme. Ici, `DbDriver = "postgres"` veut dire "le type de base de données qu'on utilise est Postgres" — écrit une fois, utilisé partout.

### `func DbHost() string { ... }`
Une fonction qui ne prend rien en paramètre et qui retourne un texte (`string`). Le mot après les parenthèses (ici `string`) indique toujours le TYPE de ce que la fonction va retourner.

### `os.Getenv("DB_HOST")`
`os` est un package Go qui donne accès à des informations sur le système d'exploitation/l'environnement d'exécution. `Getenv` lit une "variable d'environnement" — une information transmise au programme de l'extérieur, sans être écrite dans le code.

## D'où viennent ces variables concrètement ?

Elles sont définies dans le fichier `.env` à la racine du projet (par exemple `DB_HOST=postgres`). Quand Docker démarre le conteneur `api-go`, il lit ce fichier `.env` (grâce à la ligne `env_file: .env` dans `docker-compose.yml`) et rend ces valeurs disponibles au programme Go via `os.Getenv(...)`.

Exemple concret : `config.DbHost()` retourne `"postgres"` — ce n'est pas une vraie adresse internet, c'est simplement le NOM du service Docker de la base de données. Docker fait la traduction tout seul en interne (un peu comme un carnet d'adresses automatique entre conteneurs).

## Le cas particulier de ApiPort()

```go
func ApiPort() string {
    port := os.Getenv("API_PORT")
    if port == "" {
        return "8080"
    }
    return port
}
```

Ici, si la variable d'environnement `API_PORT` n'existe pas (elle est vide), on retourne quand même une valeur par défaut (`"8080"`), pour que le programme ne plante pas bêtement. C'est un choix volontaire de tolérance — mais on ne fait PAS ça pour les informations de connexion à la base de données (`DbHost`, `DbUser`, etc.) : si elles manquent, on préfère que la connexion échoue clairement plutôt que de se connecter silencieusement au mauvais endroit.

## Piège à connaître

Ce fichier ne vérifie jamais que les variables sont correctes (par exemple, il ne teste pas que `DB_PORT` est bien un nombre). C'est un choix de simplicité pour ce projet : la vérification arrive plus tard, au moment où on essaie vraiment de se connecter (voir `db/db.go.md`).

## Les fonctions SMTP (SmtpHost, SmtpPort, SmtpUser, SmtpPassword, SmtpFrom)

Ajoutées pour le système de rappels/campagnes par email (voir `utils/mailer.go.md`). Même principe que les fonctions `Db*` : chacune lit une variable d'environnement (`SMTP_HOST`, `SMTP_PORT`, etc.), définie dans `.env`. Le fournisseur utilisé est Brevo (service tiers d'envoi d'emails), dont le serveur SMTP est `smtp-relay.brevo.com` sur le port `587`.
