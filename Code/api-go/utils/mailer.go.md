# utils/mailer.go — envoyer un vrai email

> ⏱️ **Lecture : ~10 min** · 631 mots, 43 lignes de code

## C'est quoi ce fichier ?

Une seule fonction, `EnvoyerEmail`, qui envoie un vrai email via un serveur SMTP externe (Brevo, un service tiers d'envoi d'emails). C'est la brique de base utilisée par tout le système de rappels (`utils/scheduler.go`) et de campagnes (`app/campagnes.go`).

## C'est quoi le SMTP, en une phrase

SMTP (Simple Mail Transfer Protocol) est le protocole standard utilisé pour envoyer des emails sur internet. Notre programme Go ne "sait" pas envoyer d'email tout seul — il se connecte à un serveur SMTP (ici celui de Brevo) qui, lui, se charge de la livraison réelle vers la boîte du destinataire.

## Le code, en détail

```go
func EnvoyerEmail(destinataire string, sujet string, corps string) error {
    adresseServeur := config.SmtpHost() + ":" + config.SmtpPort()

    auth := smtp.PlainAuth("", config.SmtpUser(), config.SmtpPassword(), config.SmtpHost())

    message := fmt.Sprintf("From: %s\r\nTo: %s\r\nSubject: %s\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n%s",
        config.SmtpFrom(), destinataire, sujet, corps)

    err := smtp.SendMail(adresseServeur, auth, config.SmtpFrom(), []string{destinataire}, []byte(message))
    if err != nil {
        return fmt.Errorf("EnvoyerEmail (destinataire=%s) : %w", destinataire, err)
    }
    return nil
}
```

### `smtp.PlainAuth(...)`
Prépare les identifiants de connexion au serveur SMTP (utilisateur + mot de passe, lus depuis `.env` via le package `config`). C'est l'équivalent d'un "login" avant de pouvoir envoyer quoi que ce soit.

### La construction manuelle du message
```go
message := fmt.Sprintf("From: %s\r\nTo: %s\r\nSubject: %s\r\n...\r\n\r\n%s", ...)
```
Un email brut, au format SMTP, est en réalité juste du texte avec une structure précise : des lignes d'en-tête (`From:`, `To:`, `Subject:`), une ligne vide, puis le contenu du message. `\r\n` (retour chariot + saut de ligne) est la façon dont ce protocole exige de séparer les lignes — c'est une convention imposée par le standard SMTP, pas un choix arbitraire. Le paquet stdlib `net/smtp` de Go est volontairement bas niveau : il ne construit pas ce texte pour nous, il faut l'écrire à la main.

### `smtp.SendMail(...)`
La fonction qui fait vraiment la connexion au serveur, l'authentification, et l'envoi du message. Elle prend : l'adresse du serveur (host:port), les identifiants d'authentification, l'expéditeur, la liste des destinataires (ici un seul), et le message brut construit juste avant.

## Configuration (dans `.env`)

```
SMTP_HOST=smtp-relay.brevo.com
SMTP_PORT=587
SMTP_USER=... (ton adresse email Brevo)
SMTP_PASSWORD=... (la cle SMTP generee dans Brevo, PAS le mot de passe du compte)
SMTP_FROM="NO MORE WASTE <no-reply@nomorewaste.example.com>"
```

Brevo (anciennement Sendinblue) est un service tiers gratuit (jusqu'à un certain volume) qui fournit un serveur SMTP prêt à l'emploi — pas besoin de gérer soi-même un serveur mail, ce qui serait beaucoup plus complexe et sujet à être bloqué par les fournisseurs de messagerie (Gmail, Outlook...) qui n'aiment pas les serveurs SMTP "faits maison" et inconnus.

## EnvoyerEmailAvecPieceJointe — envoyer un fichier attaché (ajouté en Phase 7)

Un email avec pièce jointe n'est pas du simple texte : c'est un message **MIME multipart**, c'est-à-dire un message découpé en plusieurs "parties" (ici : le texte du message, puis le fichier), séparées par une chaîne unique appelée **boundary**.

```go
var tampon bytes.Buffer
ecrivain := multipart.NewWriter(&tampon)

entetes := fmt.Sprintf("From: %s\r\nTo: %s\r\nSubject: %s\r\nMIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=%s\r\n\r\n",
    config.SmtpFrom(), destinataire, sujet, ecrivain.Boundary())
tampon.WriteString(entetes)
```

### Le boundary
`multipart.NewWriter` génère automatiquement une chaîne aléatoire unique (le boundary). On l'annonce dans l'en-tête `Content-Type: multipart/mixed; boundary=...` pour que le logiciel de messagerie du destinataire sache où commence et où finit chaque partie du message.

### La partie texte
```go
entetesTexte := textproto.MIMEHeader{}
entetesTexte.Set("Content-Type", "text/plain; charset=UTF-8")
partieTexte, err := ecrivain.CreatePart(entetesTexte)
partieTexte.Write([]byte(corps))
```
`CreatePart` ouvre une nouvelle section du message, avec ses propres en-têtes. Ici : du texte simple en UTF-8.

### La partie fichier, encodée en base64
```go
entetesFichier.Set("Content-Type", "text/csv; charset=UTF-8")
entetesFichier.Set("Content-Transfer-Encoding", "base64")
entetesFichier.Set("Content-Disposition", fmt.Sprintf("attachment; filename=%q", nomFichier))
...
encodeur := base64.NewEncoder(base64.StdEncoding, partieFichier)
encodeur.Write(contenuFichier)
encodeur.Close()
```

**Pourquoi base64 ?** Le protocole email ne sait transporter que du texte simple, pas des octets bruts quelconques. Le base64 transforme n'importe quelle suite d'octets en une chaîne de caractères que l'email peut transporter sans les abîmer ; le logiciel du destinataire fait l'opération inverse pour reconstituer le fichier.

`Content-Disposition: attachment; filename="..."` dit au logiciel de messagerie : "ceci est un fichier à télécharger, pas à afficher dans le corps du message", et lui donne son nom.

`encodeur.Close()` est **obligatoire** : le base64 travaille par blocs, et sans ce `Close()`, les derniers octets resteraient en attente et le fichier arriverait tronqué.

## Piège à connaître

Si `SMTP_USER`/`SMTP_PASSWORD` sont invalides (comme les valeurs `change_me` par défaut dans `.env` avant configuration), `smtp.SendMail` retourne une erreur (`535 5.7.8 Authentication failed` typiquement) — mais elle n'est PAS levée en `panic`, juste retournée normalement (`error`). Le code appelant (`utils/scheduler.go`, `app/rappels.go`, `app/campagnes.go`) doit systématiquement vérifier cette erreur et réagir proprement (logguer, passer au destinataire suivant) plutôt que de planter tout le programme pour un seul email qui échoue.
