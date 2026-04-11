package signal

import (
	"log"
	"os"
	"os/signal"
	"syscall"
)

func WaitForProgramClose(handler func()) {
	quit := make(chan os.Signal, 1)
	signal.Notify(quit, syscall.SIGINT, syscall.SIGTERM)
	<-quit
	log.Println("Server stopping...")
	handler()
	log.Println("Server stopped!")
}
