package k8s

import (
	"os"
)

var (
	// Namespace is the namespace of the current pod
	Namespace = os.Getenv("NAMESPACE")
	// PodName is the name of the pod
	PodName = os.Getenv("POD_NAME")
	// NodeName is the name of the node
	NodeName = os.Getenv("NODE_NAME")
)

func init() {
	if Namespace == "" {
		Namespace = getCurrentNamespace()
	}
}

func getCurrentNamespace() string {
	namespace, err := os.ReadFile("/var/run/secrets/kubernetes.io/serviceaccount/namespace")
	if err != nil {
		return "default"
	}

	return string(namespace)
}
