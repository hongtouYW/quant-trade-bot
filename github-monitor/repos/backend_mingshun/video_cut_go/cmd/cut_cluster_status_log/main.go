package main

import (
	"context"
	"fmt"
	metav1 "k8s.io/apimachinery/pkg/apis/meta/v1"
	"k8s.io/client-go/kubernetes"
	"k8s.io/client-go/rest"
	"log"
	"os"
)

func getNamespace() string {
	v, ok := os.LookupEnv("NAMESPACE")
	if ok {
		return v
	}
	return "default"
}

func main() {
	defer dbClose()

	config, err := rest.InClusterConfig()
	if err != nil {
		panic(err)
	}

	clientSet, err := kubernetes.NewForConfig(config)
	if err != nil {
		panic(err)
	}

	namespace := getNamespace()
	fmt.Printf("Namespace: %s\n", namespace)

	pods, err := clientSet.CoreV1().Pods(namespace).List(context.Background(), metav1.ListOptions{
		LabelSelector: "app=video_cut_go",
	})
	if err != nil {
		panic(err)
	}

	logs, err := getCurrentHourLog()
	if err != nil {
		panic(err)
	}

	if len(logs) == 0 {
		ips := make([]string, 0, len(pods.Items))
		for _, pod := range pods.Items {
			ips = append(ips, pod.Status.PodIP)
		}

		results, errs := GetCutInfos(ips)
		printErrors("GetCutInfos failure", errs)

		if len(results) == 0 {
			return
		}

		datas := make([]CutRealServerStatusLog, 0, len(results))
		for _, pod := range pods.Items {
			data := CutRealServerStatusLog{
				Namespace: pod.Labels["project"],
				Server:    pod.Name,
				Status:    0,
			}
			if result, ok := results[pod.Status.PodIP]; ok {
				data.Status = boolToInt(result.Data.Processing)
			}
			log.Printf("Create log: %s %s %d", data.Namespace, data.Server, data.Status)

			datas = append(datas, data)
		}

		if err = createLogs(datas); err != nil {
			panic(err)
		}
	} else {
		podMap := make(map[string]string, len(pods.Items))
		for _, pod := range pods.Items {
			podMap[pod.Name] = pod.Status.PodIP
		}

		ips := make([]string, 0, len(pods.Items))
		for _, l := range logs {
			if l.Status == 0 {
				if ip, ok := podMap[l.Server]; ok {
					ips = append(ips, ip)
				}
			}
		}

		results, errs := GetCutInfos(ips)
		printErrors("GetCutInfos failure", errs)

		if len(results) == 0 {
			return
		}

		statusMap := make(map[string]int, len(pods.Items))
		for ip, result := range results {
			statusMap[ip] = boolToInt(result.Data.Processing)
		}

		updateID := make([]uint, 0, len(results))
		for _, l := range logs {
			if l.Status != 0 {
				continue
			}

			ip, ok := podMap[l.Server]
			if !ok {
				continue
			}

			status, ok2 := statusMap[ip]
			if !ok2 {
				continue
			}

			if status != 1 {
				continue
			}

			log.Printf("Update status: %d", l.ID)
			updateID = append(updateID, l.ID)
		}

		if err = updateLogsStatus(updateID); err != nil {
			panic(err)
		}
	}
}

func printErrors(header string, errs map[string]error) {
	for ip, err := range errs {
		log.Printf("%s %s: %v", header, ip, err)
	}
}

func boolToInt(b bool) int {
	if b {
		return 1
	}

	return 0
}
