package utils

import (
	"bytes"
	"encoding/base64"
	"fmt"
	"mime/multipart"
	"net/smtp"
	"net/textproto"

	"nomorewaste/config"
)

func EnvoyerEmail(destinataire string, sujet string, corps string) error {
	adresseServeur := config.SmtpHost() + ":" + config.SmtpPort()

	auth := smtp.PlainAuth("", config.SmtpUser(), config.SmtpPassword(), config.SmtpHost())

	message := fmt.Sprintf("From: %s\r\nTo: %s\r\nSubject: %s\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n%s",
		config.SmtpFrom(), destinataire, sujet, corps)

	err := smtp.SendMail(adresseServeur, auth, config.SmtpFrom(), []string{destinataire}, []byte(message))
	if err != nil {
		return fmt.Errorf("EnvoyerEmail (destinataire=%s) : %s", destinataire, err.Error())
	}
	return nil
}

// EnvoyerEmailAvecPieceJointe envoie un email contenant un fichier attache.
// Un email avec piece jointe n'est pas du simple texte : il faut construire un
// message "MIME multipart", c'est-a-dire un message decoupe en plusieurs parties
// (ici : le texte du message, puis le fichier), separees par une chaine unique
// appelee "boundary". Le fichier est encode en base64 car un email ne peut
// transporter que du texte, pas des octets bruts.
func EnvoyerEmailAvecPieceJointe(destinataire string, sujet string, corps string, nomFichier string, contenuFichier []byte) error {
	adresseServeur := config.SmtpHost() + ":" + config.SmtpPort()
	auth := smtp.PlainAuth("", config.SmtpUser(), config.SmtpPassword(), config.SmtpHost())

	var tampon bytes.Buffer
	ecrivain := multipart.NewWriter(&tampon)

	entetes := fmt.Sprintf("From: %s\r\nTo: %s\r\nSubject: %s\r\nMIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=%s\r\n\r\n",
		config.SmtpFrom(), destinataire, sujet, ecrivain.Boundary())
	tampon.WriteString(entetes)

	entetesTexte := textproto.MIMEHeader{}
	entetesTexte.Set("Content-Type", "text/plain; charset=UTF-8")
	partieTexte, err := ecrivain.CreatePart(entetesTexte)
	if err != nil {
		return fmt.Errorf("EnvoyerEmailAvecPieceJointe (partie texte) : %s", err.Error())
	}
	partieTexte.Write([]byte(corps))

	entetesFichier := textproto.MIMEHeader{}
	entetesFichier.Set("Content-Type", "text/csv; charset=UTF-8")
	entetesFichier.Set("Content-Transfer-Encoding", "base64")
	entetesFichier.Set("Content-Disposition", fmt.Sprintf("attachment; filename=%q", nomFichier))
	partieFichier, err := ecrivain.CreatePart(entetesFichier)
	if err != nil {
		return fmt.Errorf("EnvoyerEmailAvecPieceJointe (partie fichier) : %s", err.Error())
	}
	encodeur := base64.NewEncoder(base64.StdEncoding, partieFichier)
	encodeur.Write(contenuFichier)
	encodeur.Close()

	err = ecrivain.Close()
	if err != nil {
		return fmt.Errorf("EnvoyerEmailAvecPieceJointe (fermeture) : %s", err.Error())
	}

	err = smtp.SendMail(adresseServeur, auth, config.SmtpFrom(), []string{destinataire}, tampon.Bytes())
	if err != nil {
		return fmt.Errorf("EnvoyerEmailAvecPieceJointe (destinataire=%s) : %s", destinataire, err.Error())
	}
	return nil
}
