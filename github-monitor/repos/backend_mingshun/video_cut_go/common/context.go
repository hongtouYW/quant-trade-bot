package common

import (
	"context"
	"time"
)

func TimeoutContext(duration time.Duration) (context.Context, context.CancelFunc) {
	return context.WithTimeout(context.Background(), duration)
}
