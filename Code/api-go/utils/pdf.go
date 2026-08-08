package utils

import (
	"bytes"
	"fmt"
	"strings"

	"nomorewaste/models"
)

// echapperTextePdf protege les caracteres qui ont une signification speciale
// dans un PDF : les parentheses delimitent le texte, l'antislash echappe.
func echapperTextePdf(texte string) string {
	texte = strings.ReplaceAll(texte, "\\", "\\\\")
	texte = strings.ReplaceAll(texte, "(", "\\(")
	texte = strings.ReplaceAll(texte, ")", "\\)")
	return texte
}

// remplacerAccents convertit les caracteres accentues en equivalents non
// accentues. Le PDF genere ici utilise la police standard Helvetica avec
// l'encodage par defaut, qui ne gere pas correctement l'UTF-8 multi-octets :
// sans cette conversion, "Bénéficiaire" s'afficherait avec des caracteres
// parasites dans le lecteur PDF.
func remplacerAccents(texte string) string {
	remplacements := map[string]string{
		"é": "e", "è": "e", "ê": "e", "ë": "e",
		"à": "a", "â": "a", "ä": "a",
		"î": "i", "ï": "i",
		"ô": "o", "ö": "o",
		"ù": "u", "û": "u", "ü": "u",
		"ç": "c",
		"É": "E", "È": "E", "Ê": "E",
		"À": "A", "Â": "A",
		"Ô": "O", "Ç": "C",
	}
	for accentue, simple := range remplacements {
		texte = strings.ReplaceAll(texte, accentue, simple)
	}
	return texte
}

// ligneTextePdf produit une instruction PDF qui affiche une ligne de texte a
// une position donnee (x, y) avec une taille de police precise.
func ligneTextePdf(x int, y int, taille int, texte string) string {
	return fmt.Sprintf("BT /F1 %d Tf %d %d Td (%s) Tj ET\n",
		taille, x, y, echapperTextePdf(remplacerAccents(texte)))
}

// GenererRecapLivraisonPDF construit un vrai fichier PDF (format 1.4) sans
// aucune librairie externe. Un PDF est un fichier texte structure en "objets"
// numerotes, suivis d'une table de references (xref) qui indique la position
// exacte de chaque objet dans le fichier.
func GenererRecapLivraisonPDF(recap models.RecapLivraison) []byte {
	var contenu bytes.Buffer

	positionY := 780

	contenu.WriteString(ligneTextePdf(60, positionY, 18, "NO MORE WASTE"))
	positionY -= 25
	contenu.WriteString(ligneTextePdf(60, positionY, 14, "Recapitulatif de livraison"))
	positionY -= 30

	contenu.WriteString(ligneTextePdf(60, positionY, 10, fmt.Sprintf("Livraison n%d", recap.LivraisonId)))
	positionY -= 15
	contenu.WriteString(ligneTextePdf(60, positionY, 10, "Date de livraison : "+formaterDateHeure(recap.DateLivraison)))
	positionY -= 15
	contenu.WriteString(ligneTextePdf(60, positionY, 10,
		fmt.Sprintf("Tournee n%d du %s", recap.TourneeId, formaterDate(recap.DateTournee))))
	positionY -= 30

	contenu.WriteString(ligneTextePdf(60, positionY, 12, "Beneficiaire"))
	positionY -= 18
	contenu.WriteString(ligneTextePdf(60, positionY, 10, recap.BeneficiaireNom+" ("+libelleTypeBeneficiaire(recap.BeneficiaireType)+")"))
	positionY -= 15
	if recap.BeneficiaireAdresse != nil {
		contenu.WriteString(ligneTextePdf(60, positionY, 10, *recap.BeneficiaireAdresse))
		positionY -= 15
	}
	if recap.BeneficiaireVille != nil {
		contenu.WriteString(ligneTextePdf(60, positionY, 10, *recap.BeneficiaireVille))
		positionY -= 15
	}
	positionY -= 20

	contenu.WriteString(ligneTextePdf(60, positionY, 12, "Produits livres"))
	positionY -= 20

	contenu.WriteString(ligneTextePdf(60, positionY, 9, "Code-barre"))
	contenu.WriteString(ligneTextePdf(200, positionY, 9, "Produit"))
	contenu.WriteString(ligneTextePdf(450, positionY, 9, "Quantite"))
	positionY -= 15

	totalArticles := 0
	for _, p := range recap.Produits {
		contenu.WriteString(ligneTextePdf(60, positionY, 9, p.CodeBarre))
		contenu.WriteString(ligneTextePdf(200, positionY, 9, p.Libelle))
		contenu.WriteString(ligneTextePdf(450, positionY, 9, fmt.Sprintf("%d", p.Quantite)))
		positionY -= 14
		totalArticles += p.Quantite
		if positionY < 80 {
			break
		}
	}

	positionY -= 15
	contenu.WriteString(ligneTextePdf(60, positionY, 10,
		fmt.Sprintf("Total : %d article(s) sur %d reference(s)", totalArticles, len(recap.Produits))))

	positionY -= 40
	contenu.WriteString(ligneTextePdf(60, positionY, 9, "Signature du beneficiaire :"))

	return assemblerPdf(contenu.String())
}

// assemblerPdf construit la structure complete du fichier PDF autour du flux
// d'instructions de dessin : catalogue, pages, police, contenu, puis la table
// xref qui donne la position (en octets) de chaque objet.
func assemblerPdf(fluxContenu string) []byte {
	var pdf bytes.Buffer
	positions := make([]int, 6)

	pdf.WriteString("%PDF-1.4\n")

	positions[1] = pdf.Len()
	pdf.WriteString("1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n")

	positions[2] = pdf.Len()
	pdf.WriteString("2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n")

	positions[3] = pdf.Len()
	pdf.WriteString("3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] " +
		"/Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n")

	positions[4] = pdf.Len()
	pdf.WriteString(fmt.Sprintf("4 0 obj\n<< /Length %d >>\nstream\n%s\nendstream\nendobj\n",
		len(fluxContenu), fluxContenu))

	positions[5] = pdf.Len()
	pdf.WriteString("5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n")

	positionXref := pdf.Len()
	pdf.WriteString("xref\n0 6\n")
	pdf.WriteString("0000000000 65535 f \n")
	for i := 1; i <= 5; i++ {
		pdf.WriteString(fmt.Sprintf("%010d 00000 n \n", positions[i]))
	}

	pdf.WriteString("trailer\n<< /Size 6 /Root 1 0 R >>\n")
	pdf.WriteString(fmt.Sprintf("startxref\n%d\n%%%%EOF\n", positionXref))

	return pdf.Bytes()
}

func libelleTypeBeneficiaire(typeBeneficiaire string) string {
	if typeBeneficiaire == "association_caritative" {
		return "association caritative"
	}
	if typeBeneficiaire == "particulier_detresse" {
		return "particulier en detresse"
	}
	return typeBeneficiaire
}
