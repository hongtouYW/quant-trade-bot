package logger

import (
	"context"
	"fmt"
	"github.com/ClickHouse/clickhouse-go/v2"
	"go.uber.org/zap/zapcore"
	"strings"
	"time"
	"video_cut_go/k8s"
)

type ClickHouseSubtitle struct {
	conn clickhouse.Conn
}

func NewClickHouseSubtitle(conn clickhouse.Conn) *ClickHouseSubtitle {
	return &ClickHouseSubtitle{conn: conn}
}

func (h *ClickHouseSubtitle) Run(entry zapcore.Entry) error {
	id, msg := "", entry.Message
	msgs := strings.Split(entry.Message, ": ")
	if len(msgs) > 1 && !strings.Contains(msgs[0], " ") {
		id = msgs[0]
		msg = strings.Join(msgs[1:], ": ")
	}
	ctx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
	defer cancel()
	err := h.conn.Exec(ctx, `
        INSERT INTO subtitle_service_logs_v2 (timestamp, level, id, message, pod_name, namespace, node_name) VALUES (?, ?, ?, ?, ?, ?, ?)
    `, entry.Time, entry.Level.CapitalString(), id, msg,
		k8s.PodName, k8s.Namespace, k8s.NodeName)

	if err != nil {
		return fmt.Errorf("failed to insert log into ClickHouse: %w", err)
	}
	return nil
}
