package common

import (
	"bytes"
	"context"
	"errors"
	"os/exec"
	"strings"
)

//func BashExecute(command string) (string, error) {
//	output, err := exec.Command("/bin/bash", "-c", command).CombinedOutput()
//	if err != nil {
//		return "", errors.New(strings.TrimSpace(string(output)))
//	}
//
//	return strings.TrimSpace(string(output)), nil
//}

func BashExecute(command string) (string, error) {
	var stdout, stderr bytes.Buffer

	cmd := exec.Command("/bin/bash", "-c", command)
	cmd.Stdout = &stdout
	cmd.Stderr = &stderr

	err := cmd.Run()

	stdoutStr := strings.TrimSpace(stdout.String())
	stderrStr := strings.TrimSpace(stderr.String())

	if err != nil {
		if stderrStr != "" {
			// stderr 有内容时，将 stderr 拼到错误信息里
			return stdoutStr, errors.New(stderrStr + ", " + err.Error())
		}
		// stderr 为空时，用 stdout 内容作为错误提示
		return stdoutStr, errors.New(stdoutStr + ", " + err.Error())
	}

	return stdoutStr, nil
}

func BashExecuteContext(ctx context.Context, command string) (string, error) {
	return ExecuteContext(ctx, "/bin/bash", "-c", command)
}

func ShExecuteContext(ctx context.Context, command string) (string, error) {
	return ExecuteContext(ctx, "/bin/sh", "-c", command)
}

func ExecuteContext(ctx context.Context, name string, arg ...string) (string, error) {
	var stdout, stderr bytes.Buffer

	cmd := exec.CommandContext(ctx, name, arg...)
	cmd.Stdout = &stdout
	cmd.Stderr = &stderr

	err := cmd.Run()

	stdoutStr := strings.TrimSpace(stdout.String())
	stderrStr := strings.TrimSpace(stderr.String())

	if err != nil {
		if stderrStr != "" {
			// stderr 有内容时，将 stderr 拼到错误信息里
			return stdoutStr, errors.New(stderrStr + ", " + err.Error())
		}
		// stderr 为空时，用 stdout 内容作为错误提示
		return stdoutStr, errors.New(stdoutStr + ", " + err.Error())
	}

	return stdoutStr, nil
}
