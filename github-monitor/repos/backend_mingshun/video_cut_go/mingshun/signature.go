package mingshun

import (
	"crypto/md5"
	"fmt"
	"io"
	"strconv"
	"time"
)

const (
	privateKey = "GYxwT4bf45RVC219tmct"
	publicKey  = "scrap-GFqS42zriX"
)

type signature struct {
	PublicKey string `json:"public_key"`
	Timestamp string `json:"timestamp"`
	Hash      string `json:"hash"`
}

func newSignature() signature {
	h := md5.New()
	timestamp := time.Now().Unix()
	_, _ = io.WriteString(h, fmt.Sprintf("%s%s%d", publicKey, privateKey, timestamp))

	return signature{
		PublicKey: publicKey,
		Timestamp: strconv.Itoa(int(timestamp)),
		Hash:      fmt.Sprintf("%x", h.Sum(nil)),
	}
}
