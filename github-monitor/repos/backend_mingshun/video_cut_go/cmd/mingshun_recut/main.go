package main

import (
	"bufio"
	"log"
	"os"
	"video_cut_go/mingshun"
)

func main() {
	file, err := os.Open("id.list")
	if err != nil {
		log.Fatal(err)
	}
	defer file.Close()

	if err = mingshun.Init(&mingshun.Config{
		URL: "https://api.minggogogo.com",
	}); err != nil {
		log.Fatal(err)
	}

	scanner := bufio.NewScanner(file)
	for scanner.Scan() {
		if scanner.Text() == "" {
			continue
		}

		text := scanner.Text()

		log.Println(text, "processing")

		if err = mingshun.VideoRecut("", text); err != nil {
			log.Fatal(err)
		}
	}

	if scanner.Err() != nil {
		log.Fatal(scanner.Err())
	}

}
