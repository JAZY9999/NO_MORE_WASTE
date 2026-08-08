package config

import "os"

const (
	DbDriver = "postgres"
)

func DbHost() string {
	return os.Getenv("DB_HOST")
}

func DbPort() string {
	return os.Getenv("DB_PORT")
}

func DbUser() string {
	return os.Getenv("DB_USER")
}

func DbPassword() string {
	return os.Getenv("DB_PASSWORD")
}

func DbName() string {
	return os.Getenv("DB_NAME")
}

func ApiPort() string {
	port := os.Getenv("API_PORT")
	if port == "" {
		return "8080"
	}
	return port
}

func SmtpHost() string {
	return os.Getenv("SMTP_HOST")
}

func SmtpPort() string {
	return os.Getenv("SMTP_PORT")
}

func SmtpUser() string {
	return os.Getenv("SMTP_USER")
}

func SmtpPassword() string {
	return os.Getenv("SMTP_PASSWORD")
}

func SmtpFrom() string {
	return os.Getenv("SMTP_FROM")
}
