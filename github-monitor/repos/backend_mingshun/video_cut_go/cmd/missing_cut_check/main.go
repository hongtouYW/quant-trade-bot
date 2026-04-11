package main

import (
	"bufio"
	"encoding/json"
	"fmt"
	"io"
	"log"
	"net/http"
	"os"
	"video_cut_go/api/util/response"
	"video_cut_go/mingshun"
)

func main() {
	file, err := os.Open("node.list")
	if err != nil {
		log.Fatal(err)
	}
	defer file.Close()

	processingMap := make(map[string]bool)
	scanner := bufio.NewScanner(file)
	for scanner.Scan() {
		if scanner.Text() == "" {
			continue
		}

		text := scanner.Text()

		log.Println(text, "processing")
		resp, err := http.Get(fmt.Sprintf("http://%s:5566/api/info", text))
		if err != nil {
			log.Fatal(err)
		}
		body, err := io.ReadAll(resp.Body)
		if err != nil {
			log.Fatal(err)
		}

		var result response.Msg
		if err = json.Unmarshal(body, &result); err != nil {
			log.Fatal(err)
		}

		if http.StatusOK != resp.StatusCode {
			log.Fatal(text, result.Msg)
		}

		if result.Data.(map[string]interface{})["processing"].(bool) {
			processingMap[result.Data.(map[string]interface{})["downloader"].(string)] = true
			processingMap[result.Data.(map[string]interface{})["imager"].(string)] = true
			processingMap[result.Data.(map[string]interface{})["editor"].(string)] = true
			processingMap[result.Data.(map[string]interface{})["sender"].(string)] = true
			if result.Data.(map[string]interface{})["queue"] != nil {
				for _, q := range result.Data.(map[string]interface{})["queue"].([]interface{}) {
					processingMap[q.(string)] = true
				}
			}
		}
	}

	if scanner.Err() != nil {
		log.Fatal(scanner.Err())
	}

	fmt.Println("currently processing:", len(processingMap))

	if err = mingshun.Init(&mingshun.Config{
		URL: "https://api.minggogogo.com",
	}); err != nil {
		log.Fatal(err)
	}

	portal, err := os.ReadFile("portal.list")
	if err != nil {
		log.Fatal(err)
	}

	var portalList []string
	if err = json.Unmarshal(portal, &portalList); err != nil {
		log.Fatal(err)
	}

	fmt.Println("Portal processing:", len(portalList))
	for _, p := range portalList {
		if !processingMap[p] {
			if err = mingshun.VideoRecut(p, "?"); err != nil {
				log.Fatal(err)
			}
		}
	}
}
