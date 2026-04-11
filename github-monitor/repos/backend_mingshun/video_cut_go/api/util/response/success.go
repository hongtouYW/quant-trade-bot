package response

import (
	"github.com/gin-gonic/gin"
	"net/http"
)

func Success(c *gin.Context, result interface{}) {
	c.JSON(http.StatusOK, Msg{
		Code: http.StatusOK,
		Msg:  "success",
		Data: result,
	})
	c.Abort()
}
