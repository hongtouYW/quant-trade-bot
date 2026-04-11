package util

import "fmt"

func WrapError(msg string, err *error) {
	if *err != nil {
		*err = fmt.Errorf("%s: %w", msg, *err)
	}
}
