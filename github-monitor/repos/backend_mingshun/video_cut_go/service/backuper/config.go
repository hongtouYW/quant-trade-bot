package backuper

type Config struct {
	Method map[string]SyncInfo `yaml:"method"`
	Local  string              `yaml:"local"`
}

type SyncInfo struct {
	Type string       `yaml:"type"`
	Http HttpSyncInfo `yaml:"http"`
}

type BackupConfig struct {
	Identifier  string
	Name        string
	File        string
	Destination string
	Extra       map[string]string
}
