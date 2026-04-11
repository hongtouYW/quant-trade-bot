package main

import (
	"flag"
	"fmt"
	"github.com/gin-gonic/gin"
	"log"
	"net/http"
	"video_cut_go/cmd/api_log/bill"
	"video_cut_go/cmd/api_log/database"
	"video_cut_go/cmd/api_log/database/model"
	"video_cut_go/cmd/api_log/logger"
)

var config *Config
var configFile string

func init() {
	flag.StringVar(&configFile, "c", "config.yml", "Configuration File")
	flag.Parse()

	config = new(Config)
	err := config.ReadFromFile(configFile)
	if err != nil {
		log.Fatal(err)
	}

	if err = logger.Init(config.Logger); err != nil {
		log.Fatal(err)
	}

	if err = database.Init(config.Database); err != nil {
		log.Fatal(err)
	}
}

func main() {

	router := gin.Default()
	router.POST("/new/cut", func(c *gin.Context) {
		param := new(model.CutJson)
		err := c.BindJSON(param)
		if err != nil {
			c.String(http.StatusBadRequest, err.Error())
			return
		}

		if err = param.Create(); err != nil {
			c.String(http.StatusInternalServerError, err.Error())
			return
		}

		c.String(http.StatusOK, "success")
	})

	router.PUT("/update/cut", func(c *gin.Context) {
		param := new(model.CutJson)
		err := c.BindJSON(param)
		if err != nil {
			c.String(http.StatusBadRequest, err.Error())
			return
		}

		if err = param.Update(); err != nil {
			c.String(http.StatusInternalServerError, err.Error())
			return
		}

		c.String(http.StatusOK, "success")
	})

	router.GET("/bill", bill.User)

	err := router.Run(fmt.Sprintf(":%d", config.Port))
	if err != nil {
		log.Fatal(err)
	}
}
