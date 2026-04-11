#!/bin/bash
GOOS=linux GOARCH=amd64 go build -o "check_missing_file" .