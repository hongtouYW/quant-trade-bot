package sender

import (
	"encoding/json"
)

type AiSubtitle struct {
	Enable   bool     `json:"enable"`
	Language []string `json:"language"`
}

func (s *AiSubtitle) UnmarshalJSON(data []byte) error {
	var b bool
	if err := json.Unmarshal(data, &b); err == nil {
		s.Enable = b
		s.Language = nil
		return nil
	}

	type Alias AiSubtitle
	var temp Alias
	if err := json.Unmarshal(data, &temp); err != nil {
		return err
	}
	*s = AiSubtitle(temp)
	return nil
}

/*
Success:
{
  "message": "success",
  "data": {
    "video_id": "video_001",
    "wav_file_path": "storage/download_short.wav", // 请求中提供的相对路径
    "md5_hash_file": "f37016675c3d8c42757c4538eea601be",
    "file_size": 969788,
    "third_party_id": 1,
    "languages": ["en", "zh"], // 最终使用的语言列表（来自请求或默认）
    "job_id": 1 // 在数据库中创建的任务记录 ID
  },
  "code": 200
}

Error:
{
  "error": "Invalid request payload", // 或 "File validation failed", "Invalid third_party_id", "Invalid language code"
  "details": "...", // 具体的错误信息
  "code": 400
}
*/

type AiSubtitleResponse struct {
	Error   string `json:"error"`
	Details string `json:"details"`
	Code    int    `json:"code"`
}
