package app

import (
	"encoding/json"
	"net/http"

	"nomorewaste/utils"
)

func AdminPing(w http.ResponseWriter, r *http.Request) {
	email, ok := utils.RequireRole(w, r, "admin_back", "staff_back")
	if !ok {
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{"message": "acces autorise", "email": email})
}
