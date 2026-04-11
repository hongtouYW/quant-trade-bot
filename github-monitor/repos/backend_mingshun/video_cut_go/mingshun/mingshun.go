package mingshun

type MingShun struct {
	*Config
}

var m *MingShun

func Init(config *Config) error {
	if err := config.validate(); err != nil {
		return err
	}

	m = new(MingShun)
	m.Config = config

	return nil
}

type Response[T any] struct {
	Message string `json:"message"`
	Code    int    `json:"code"`
	Data    T      `json:"data"`
}
