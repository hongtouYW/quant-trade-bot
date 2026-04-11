package bind

import (
	"errors"
	"github.com/gin-gonic/gin"
	"github.com/gin-gonic/gin/binding"
	"io"
)

func JSON(c *gin.Context, obj any) error {
	if err := c.ShouldBindWith(obj, binding.JSON); err != nil {
		if err == io.EOF {
			return errors.New("require body data")
		}
		return err
	}
	return nil
}
