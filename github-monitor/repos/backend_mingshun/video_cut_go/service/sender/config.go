package sender

type SyncTarget struct {
	User string `yaml:"user"`
	Host string `yaml:"host"`
	Port uint16 `yaml:"port"`
	LAN  string `yaml:"lan"`
	Path string `yaml:"path"`
}

type AiSubtitleServer struct {
	IP         string    `yaml:"IP"`
	Port       uint16    `yaml:"port"`
	Username   string    `yaml:"username"`
	UploadPath string    `yaml:"uploadPath"`
	ApiBaseUrl string    `yaml:"apiBaseUrl"`
	Backup     *Receiver `yaml:"backup"`
}
