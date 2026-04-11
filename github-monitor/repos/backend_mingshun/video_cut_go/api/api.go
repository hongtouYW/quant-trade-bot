package api

import (
	"github.com/gin-gonic/gin"
	"log"
)

func Start() {
	router := gin.Default()
	router.GET("/metrics", metricsHandler)

	apiGroup := router.Group("/api")
	apiGroup.GET("/info", info)
	apiGroup.GET("/log/*identifier", getLog)
	err := router.Run(":5566")
	if err != nil {
		log.Fatal(err)
	}
}
