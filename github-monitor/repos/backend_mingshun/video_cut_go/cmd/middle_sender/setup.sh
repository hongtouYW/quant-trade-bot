#!/bin/bash
# set timezone
timedatectl set-timezone Asia/Singapore
# add service
cp middle_sender.service /usr/lib/systemd/system/
systemctl daemon-reload
mkdir /home/middle_sender
cd /home/middle_sender


