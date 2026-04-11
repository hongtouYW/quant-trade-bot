package response

import (
	"github.com/gin-gonic/gin"
)

type Msg struct {
	Code int         `json:"code"`
	Msg  string      `json:"msg"`
	Data interface{} `json:"data"`
}

func Error(c *gin.Context, code int, err error) {
	c.JSON(code, Msg{
		Code: code,
		Msg:  err.Error(),
		Data: nil,
	})
	c.Abort()
}
