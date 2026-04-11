package api

import (
	"github.com/gin-gonic/gin"
	"net/http"
	"strings"
	"video_cut_go/api/util/response"
	"video_cut_go/common"
)

func fetchLog(name, logFile string) (string, error) {
	return common.BashExecute(`awk ' \
    /` + name + `: processing config/ { block=""; in_block=1 } \
    in_block { block = block $0 ORS } \
    /fetching new config/ { output=block; in_block=0 } \
    END { printf "%s", output } \
' ` + logFile + ` | sed '$d' | grep -v 'process ffmpeg'`)
}

func buildLog(name string, logFiles ...string) (string, error) {
	var logBuilder strings.Builder
	for _, logFile := range logFiles {
		log, err := fetchLog(name, logFile)
		if err != nil {
			if err.Error() == "" {
				return logBuilder.String(), nil
			}
			return "", nil
		}
		logBuilder.WriteString(log + "\n")
	}
	return logBuilder.String(), nil
}

func getLog(c *gin.Context) {
	identifier := c.Param("identifier")
	identifier = identifier[1:]
	log, err := buildLog(identifier, "logs/downloader.log", "logs/imager.log", "logs/editor.log", "logs/sender.log")
	if err != nil {
		response.Error(c, http.StatusInternalServerError, err)
		return
	}
	response.Success(c, log)
}
