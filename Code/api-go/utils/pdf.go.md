# utils/pdf.go — générer un vrai PDF sans aucune librairie

> ⏱️ **Lecture : ~5 min** · 794 mots, 7 lignes de code

## Le point à savoir justifier à l'oral

Le sujet exige : *"Chaque livraison donnera lieu à l'émission d'un récapitulatif au format PDF"*. Mais la consigne du cours ESGI interdit toute librairie externe (hors driver de base de données) — donc pas de `gofpdf` ni équivalent.

**La solution retenue** : écrire le fichier PDF nous-mêmes, octet par octet. C'est possible parce qu'un PDF n'est pas un format binaire mystérieux : c'est un **fichier texte structuré** qui suit une grammaire précise. Le résultat est un vrai `.pdf` qui s'ouvre dans n'importe quel lecteur (Acrobat, navigateur, aperçu Windows).

## Comment est fait un fichier PDF (les bases)

Un PDF minimal contient :
1. Une ligne d'en-tête : `%PDF-1.4` (la version du format).
2. Une série d'**objets numérotés** (`1 0 obj ... endobj`), qui décrivent la structure du document.
3. Une **table de références** (`xref`) qui indique, pour chaque objet, sa position exacte **en nombre d'octets depuis le début du fichier**.
4. Un **trailer** qui dit combien il y a d'objets et lequel est la racine.

Les 5 objets utilisés ici :
- **Objet 1 — Catalog** : la racine du document, pointe vers la liste des pages.
- **Objet 2 — Pages** : la liste des pages (ici une seule).
- **Objet 3 — Page** : la page elle-même, avec sa taille (`MediaBox [0 0 595 842]` = format A4 en points) et une référence vers son contenu et sa police.
- **Objet 4 — Contents** : le "flux" d'instructions de dessin (voir plus bas).
- **Objet 5 — Font** : la police utilisée, ici Helvetica (une des 14 polices standard que tout lecteur PDF connaît, donc pas besoin de l'embarquer dans le fichier).

## Les instructions de dessin

```go
func ligneTextePdf(x int, y int, taille int, texte string) string {
    return fmt.Sprintf("BT /F1 %d Tf %d %d Td (%s) Tj ET\n", ...)
}
```

Chaque ligne de texte du document est produite par une petite séquence d'instructions :
- `BT` = *Begin Text* (début d'un bloc de texte)
- `/F1 12 Tf` = utiliser la police F1 en taille 12
- `60 780 Td` = se positionner aux coordonnées (60, 780)
- `(mon texte) Tj` = dessiner ce texte
- `ET` = *End Text*

**Attention aux coordonnées** : dans un PDF, l'origine (0,0) est en **bas à gauche** de la page, pas en haut à gauche comme on en a l'habitude. C'est pourquoi le code part de `positionY := 780` (proche du haut d'une page de 842 points de haut) et **décrémente** `positionY` à chaque nouvelle ligne pour descendre.

## Les deux fonctions de nettoyage du texte

### `echapperTextePdf`
Dans un PDF, le texte à afficher est écrit entre parenthèses : `(Bonjour) Tj`. Donc si le texte lui-même contient une parenthèse (par exemple `Yaourts (bio)`), le lecteur PDF croirait que le texte s'arrête là et le fichier serait corrompu. Cette fonction échappe les parenthèses et les antislashs en les préfixant d'un `\`.

### `remplacerAccents`
La police Helvetica en encodage PDF par défaut ne gère pas l'UTF-8 multi-octets. Sans conversion, "Bénéficiaire" s'afficherait avec des caractères parasites. On remplace donc les caractères accentués par leur équivalent simple (`é` → `e`). C'est une limite assumée : gérer correctement les accents demanderait d'embarquer une police complète avec sa table d'encodage dans le PDF, ce qui multiplierait la complexité du code par dix pour un projet étudiant.

## La table xref — la partie la plus délicate

```go
positions[1] = pdf.Len()
pdf.WriteString("1 0 obj\n...")
```

Avant d'écrire chaque objet, on note sa position (`pdf.Len()` donne le nombre d'octets déjà écrits). À la fin, on écrit la table `xref` avec ces positions, formatées **exactement sur 10 chiffres** (`%010d`) suivies de ` 00000 n `. Ce format est rigide : le moindre écart d'un caractère et le lecteur PDF refuse d'ouvrir le fichier.

La ligne `0000000000 65535 f ` en tête de table est obligatoire : c'est l'objet numéro 0, qui est toujours un objet "libre" par convention du format.

`startxref` à la toute fin donne la position de la table xref elle-même — c'est par là que le lecteur commence sa lecture du fichier, en remontant depuis la fin.

## Ce que contient le récapitulatif produit

En-tête "NO MORE WASTE", le numéro et la date de la livraison, la tournée d'origine, le bloc bénéficiaire (nom, type, adresse, ville), puis un tableau des produits livrés (code-barre, libellé, quantité), un total, et une ligne "Signature du bénéficiaire" — le document est pensé pour être imprimé et signé lors de la remise.

## Piège à connaître

Le PDF est **généré à la demande** à chaque appel de `GET /livraisons/{id}/pdf`, jamais stocké sur le disque. La colonne `pdf_genere_path` en base ne contient donc pas un chemin de fichier réel, mais l'URL de la route qui le produit. C'est un choix volontaire : pas de fichiers à gérer/nettoyer sur le serveur, et le PDF reflète toujours l'état actuel des données.
