package db

import (
	"database/sql"
	"fmt"
	"time"

	_ "github.com/lib/pq"

	"nomorewaste/config"
)

var Conn *sql.DB

func NewDB() *sql.DB {
	sqlInfo := fmt.Sprintf("host=%s port=%s user=%s password=%s dbname=%s sslmode=disable",
		config.DbHost(), config.DbPort(), config.DbUser(), config.DbPassword(), config.DbName())

	conn, err := sql.Open(config.DbDriver, sqlInfo)
	if err != nil {
		panic(err.Error())
	}

	var pingErr error
	for i := 0; i < 10; i++ {
		pingErr = conn.Ping()
		if pingErr == nil {
			break
		}
		time.Sleep(2 * time.Second)
	}
	if pingErr != nil {
		panic(pingErr.Error())
	}

	fmt.Println("Connected to database !")
	return conn
}
