package utils

import (
	"net/http"
)

func RequireRole(w http.ResponseWriter, r *http.Request, rolesAutorises ...string) (string, bool) {
	tokenString := r.Header.Get("Authorization")
	email, role, err := VerifyJWT(tokenString)
	if err != nil {
		http.Error(w, "Jeton invalide", http.StatusUnauthorized)
		return "", false
	}

	for _, roleAutorise := range rolesAutorises {
		if role == roleAutorise {
			return email, true
		}
	}

	http.Error(w, "Acces interdit", http.StatusForbidden)
	return "", false
}
