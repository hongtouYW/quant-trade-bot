package common

import (
	"context"
	"fmt"
	"io"
	"net"
	"net/http"
	"net/url"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"time"
)

var (
	dialer = &net.Dialer{
		Resolver: &net.Resolver{
			PreferGo: true,
			Dial: func(ctx context.Context, network, address string) (net.Conn, error) {
				d := net.Dialer{
					Timeout: time.Duration(5000) * time.Millisecond,
				}
				return d.DialContext(ctx, "udp", "8.8.8.8:53")
			},
		},
	}
	dialContext = func(ctx context.Context, network, addr string) (net.Conn, error) {
		return dialer.DialContext(ctx, network, addr)
	}
)

func HttpGet(link string, header http.Header) (*http.Response, error) {
	http.DefaultTransport.(*http.Transport).DialContext = dialContext
	client := http.Client{
		Transport: http.DefaultTransport,
	}
	req, err := http.NewRequest("GET", link, nil)
	if err != nil {
		return nil, err
	}

	req.Header = header
	return client.Do(req)
}

func HttpGetWithoutRedirect(link string, header http.Header) (*http.Response, error) {
	http.DefaultTransport.(*http.Transport).DialContext = dialContext
	client := http.Client{
		Transport: http.DefaultTransport,
		CheckRedirect: func(req *http.Request, via []*http.Request) error {
			return http.ErrUseLastResponse
		},
	}
	req, err := http.NewRequest("GET", link, nil)
	if err != nil {
		return nil, err
	}

	req.Header = header
	return client.Do(req)
}

func HttpSaveToFile(resp *http.Response, path string) error {
	err := os.MkdirAll(filepath.Dir(path), 0750)
	if err != nil {
		return err
	}

	body, err := io.ReadAll(resp.Body)
	if err != nil {
		return err
	}

	return os.WriteFile(path, body, 0666)
}

func GetFilenameFromURL(link string) string {
	parse, _ := url.Parse(link)
	ss := strings.Split(parse.Path, "/")
	if len(ss) == 0 {
		return ""
	}
	return ss[len(ss)-1]
}

func GetExtFromUrl(link string) string {
	ext := filepath.Ext(GetFilenameFromURL(link))
	return strings.Split(ext, "?")[0]
}

func HttpByCurl(location string, opts ...string) ([]byte, error) {
	cOpts := []string{
		"--location", location,
		"-H", `User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/104.0.0.0 Safari/537.36`,
		"-s",
	}
	cOpts = append(cOpts, opts...)

	raw, err := exec.Command("curl", cOpts...).CombinedOutput()
	if err != nil {
		return nil, fmt.Errorf("curl error: %s | %s", raw, err)
	}
	return raw, nil
}
