package metrics

import (
	"github.com/prometheus/client_golang/prometheus"
	"github.com/prometheus/client_golang/prometheus/promauto"
)

var (
	K8sConfigVersion = promauto.NewGaugeVec(
		prometheus.GaugeOpts{
			Name: "k8s_config_version",
			Help: "Current ConfigMap Version",
		},
		[]string{"name"},
	)

	K8sConfigReconnect = promauto.NewCounterVec(
		prometheus.CounterOpts{
			Name: "k8s_config_reconnect",
			Help: "ConfigMap Reconnect Times",
		},
		[]string{"name"},
	)
)
