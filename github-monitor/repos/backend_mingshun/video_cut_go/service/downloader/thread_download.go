package downloader

import (
	"fmt"
	"io"
	"net/http"
	"os"
	"path/filepath"
	"sync"
	"video_cut_go/common"
	"video_cut_go/logger"
)

type ThreadDownloader struct {
	id            string
	url           string
	filename      string
	contentLength int
	acceptRanges  bool
	numThreads    int
	header        http.Header
	log           *logger.Logger
}

func NewThreadDownloader(id, url string, numThreads int, output string, header http.Header, log *logger.Logger) (*ThreadDownloader, error) {
	httpDownload := new(ThreadDownloader)
	httpDownload.url = url
	httpDownload.numThreads = numThreads
	httpDownload.filename = output
	httpDownload.header = header
	httpDownload.log = log
	httpDownload.acceptRanges = false

	res, err := http.Head(url)
	if err != nil {
		return httpDownload, nil
	}

	httpDownload.contentLength = int(res.ContentLength)
	if len(res.Header["Accept-Ranges"]) != 0 && res.Header["Accept-Ranges"][0] == "bytes" {
		httpDownload.acceptRanges = true
	}

	httpDownload.id = id
	httpDownload.log = log
	return httpDownload, nil
}

func (h *ThreadDownloader) Download() error {
	_ = os.MkdirAll(filepath.Dir(h.filename), 0750)
	f, err := os.Create(h.filename)
	if err != nil {
		return err
	}
	defer f.Close()

	if h.acceptRanges {
		var wg sync.WaitGroup
		for _, ranges := range h.Split() {
			wg.Add(1)
			go func(start, end int) {
				defer wg.Done()
				if err = h.download(start, end); err != nil {
					h.log.Errorf(h.id, "thread download error: %s %s", h.url, err)
				}
			}(ranges[0], ranges[1])
		}
		wg.Wait()
	} else {
		h.log.Infof(h.id, "switch thread download to normal download")
		resp, err := common.HttpGet(h.url, h.header)
		if err != nil {
			return err
		}
		if err = common.HttpSaveToFile(resp, h.filename); err != nil {
			return err
		}
	}
	return nil
}

func (h *ThreadDownloader) Split() [][]int {
	var ranges [][]int
	blockSize := h.contentLength / h.numThreads
	for i := 0; i < h.numThreads; i++ {
		var start = i * blockSize
		var end = (i+1)*blockSize - 1
		if i == h.numThreads-1 {
			end = h.contentLength - 1
		}
		ranges = append(ranges, []int{start, end})
	}
	return ranges
}

func (h *ThreadDownloader) download(start, end int) error {
	req, err := http.NewRequest("GET", h.url, nil)
	if err != nil {
		return err
	}
	for k, v := range h.header {
		req.Header[k] = v
	}
	req.Header.Set("Range", fmt.Sprintf("bytes=%v-%v", start, end))

	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()

	return save2file(h.filename, int64(start), resp)
}

func save2file(filename string, offset int64, resp *http.Response) error {
	f, err := os.OpenFile(filename, os.O_WRONLY, 0660)
	if err != nil {
		return err
	}
	f.Seek(offset, 0)
	defer f.Close()

	// Use io.Copy to efficiently copy data from the response body to the file
	_, err = io.Copy(f, resp.Body)
	if err != nil {
		return err
	}
	return nil
}
