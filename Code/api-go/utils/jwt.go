package utils

import (
	"fmt"
	"os"
	"time"

	"github.com/golang-jwt/jwt/v5"
)

var JwtSecret = []byte(jwtSecretFromEnv())

func jwtSecretFromEnv() string {
	secret := os.Getenv("JWT_SECRET")
	if secret == "" {
		secret = "nmw_dev_jwt_secret_2026"
	}
	return secret
}

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
