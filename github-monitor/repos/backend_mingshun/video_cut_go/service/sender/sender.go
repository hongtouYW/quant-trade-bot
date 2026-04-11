package sender

import (
	"context"
	"encoding/json"
	"errors"
	"github.com/ClickHouse/clickhouse-go/v2"
	"github.com/redis/go-redis/v9"
	"go.uber.org/zap"
	"sync"
	"time"
	"video_cut_go/common"
	"video_cut_go/logger"
	"video_cut_go/mingshun"
)

const RedisKeyName = "sender"

type Sender struct {
	wg      *sync.WaitGroup
	ctx     context.Context
	rdb     *redis.Client
	log     *logger.Logger
	current string
	status  string
}

var sr *Sender

// func Init(ctx context.Context, wg *sync.WaitGroup, rdb *redis.Client, server *AiSubtitleServer, ck clickhouse.Conn) error {
func Init(ctx context.Context, wg *sync.WaitGroup, rdb *redis.Client, ck clickhouse.Conn) error {
	if sr != nil {
		return errors.New("sender already initialize")
	}

	ckHook := logger.NewClickHouseVideoCutGo(ck, "sender")

	lg := logger.Config{
		File:       "logs/sender.log",
		Level:      "info",
		MaxSize:    516,
		MaxBackups: 7,
		MaxAge:     7,
		Compress:   true,
		Callback:   mingshun.CALLBACK_API_VIDEO,
	}
	log, err := logger.New(&lg, zap.Hooks(ckHook.Run))
	if err != nil {
		return err
	}

	ctxT, cancelT := context.WithTimeout(context.Background(), 3*time.Second)
	defer cancelT()

	status := rdb.Ping(ctxT)
	if status.Err() != nil {
		return status.Err()
	}

	sr = new(Sender)
	sr.ctx = ctx
	sr.wg = wg
	sr.rdb = rdb
	sr.log = log
	sr.status = "initialized"

	//UpdateAiSubtitleServer(server)

	go sr.Run()
	return nil
}

func (s *Sender) getNewConfig() (*Config, error) {
	s.current = ""
	str, err := s.rdb.BLPop(context.Background(), 5*time.Second, RedisKeyName).Result()
	if errors.Is(err, redis.Nil) {
		return nil, nil
	} else if err != nil {
		return nil, err
	}

	config := new(Config)
	if err = json.Unmarshal([]byte(str[1]), config); err != nil {
		return nil, err
	}
	s.current = config.Identifier
	return config, config.validate()
}

func (s *Sender) Run() {
	defer s.wg.Done()
	s.status = "running"
	s.log.Info("sender running")
	for {
		s.send()
		if s.ctx.Err() != nil {
			s.status = "stopped"
			s.log.Info("sender stopped")
			_ = s.log.Sync()
			return
		}
	}
}

func (s *Sender) Callback(callback *mingshun.Callback) {
	err := mingshun.UpdateMsgCallback(callback)
	if err != nil {
		s.log.Errorf(callback.Identifier, "send mingshun callback error: %s", err)
	}
}

// AiSubtitle 更新这个function需要也更新subtitle_service
//func (s *Sender) AiSubtitle(id, wavFile, remoteWav string, languages []string) error {
//	type Payload struct {
//		VideoId      string   `json:"video_id"`
//		WavFile      string   `json:"wav_file_path"`
//		Checksum     string   `json:"md5_hash_file"`
//		Size         int64    `json:"file_size"`
//		ThirdPartyId int      `json:"third_party_id"`
//		Languages    []string `json:"languages"`
//	}
//
//	stat, err := os.Stat(wavFile)
//	if err != nil {
//		return err
//	}
//
//	md5, err := common.MD5(wavFile)
//	if err != nil {
//		return err
//	}
//
//	marshal, err := json.Marshal(&Payload{
//		VideoId:      id,
//		WavFile:      remoteWav,
//		Checksum:     md5,
//		Size:         stat.Size(),
//		ThirdPartyId: 1,
//		Languages:    languages,
//	})
//	if err != nil {
//		return err
//	}
//
//	apiUrl, _ := url.JoinPath(aiSubtitleServer.ApiBaseUrl, "transcribes")
//	req, err := http.NewRequest("POST", apiUrl, bytes.NewBuffer(marshal))
//	if err != nil {
//		return err
//	}
//	req.Header.Set("Content-Type", "application/json")
//
//	resp, err := http.DefaultClient.Do(req)
//	if err != nil {
//		return err
//	}
//	defer resp.Body.Close()
//
//	body, err := io.ReadAll(resp.Body)
//	if err != nil {
//		return fmt.Errorf("read response error: %s", err)
//	}
//
//	if resp.StatusCode != http.StatusOK {
//		return fmt.Errorf("aiSubtitle response error: %s", body)
//	}
//
//	var response AiSubtitleResponse
//	if err = json.Unmarshal(body, &response); err != nil {
//		return fmt.Errorf("unmarshal response error: %s", err)
//	}
//
//	if response.Code != 200 {
//		return fmt.Errorf("%s: %s", response.Error, response.Details)
//	}
//
//	return nil
//}

func GetCurrent() string {
	return sr.current
}

func GetQueueCount() (int64, error) {
	ctx, cancel := common.TimeoutContext(3 * time.Second)
	defer cancel()

	return sr.rdb.LLen(ctx, RedisKeyName).Result()
}

func GetQueue() ([]string, error) {
	ctx, cancel := common.TimeoutContext(3 * time.Second)
	defer cancel()

	results, err := sr.rdb.LRange(ctx, RedisKeyName, 0, -1).Result()
	if err != nil {
		return nil, err
	}

	identifierList := make([]string, 0, len(results))
	for _, result := range results {
		config := new(Config)
		if err = json.Unmarshal([]byte(result), config); err != nil {
			return nil, err
		}

		identifierList = append(identifierList, config.Identifier)
	}

	return identifierList, nil
}

//func UpdateAiSubtitleServer(server *AiSubtitleServer) {
//	fmt.Printf("before UpdateAiSubtitleServer: %+v\n", aiSubtitleServer)
//	aiSubtitleServer = server
//	fmt.Printf("after UpdateAiSubtitleServer: %+v\n", aiSubtitleServer)
//}

type Metrics struct {
	ProcessingId string
	Progress     string
	Queue        int64
	Status       string
}

func GetMetrics() Metrics {
	queue, _ := GetQueueCount()
	if sr.status == "stopped" {
		return Metrics{
			ProcessingId: "",
			Progress:     "stopped",
			Status:       "0",
		}
	} else {
		return Metrics{
			ProcessingId: sr.current,
			Progress:     sr.status,
			Queue:        queue,
			Status:       "1",
		}
	}
}
