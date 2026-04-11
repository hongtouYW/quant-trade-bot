package main

import (
	"errors"
	"github.com/gin-gonic/gin"
	"net/http"
	"video_cut_go/api/util/response"
	"video_cut_go/cmd/mingshun_middle/service/check_video_cuted"
)

func main() {
	router := gin.Default()
	router.GET("/check/cuted/:id", func(c *gin.Context) {
		id := c.Param("id")
		if id == "" {
			response.Error(c, http.StatusBadRequest, errors.New("invalid query"))
			return
		}

		result, err := check_video_cuted.Run(id)
		if err != nil {
			response.Error(c, http.StatusBadRequest, err)
			return
		}

		response.Success(c, result)

	})
	_ = router.Run(":9966")
}
