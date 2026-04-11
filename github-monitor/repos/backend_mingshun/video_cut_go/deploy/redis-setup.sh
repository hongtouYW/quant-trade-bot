#!/bin/bash
# Get the IP address of eth1
LAN_IP=$(hostname -I | grep -Eo '192\.168\.[0-9]+\.[0-9]+|10\.[0-9]+\.[0-9]+\.[0-9]+')

# Check if LAN_IP is not empty
if [ -z "$LAN_IP" ]; then
    echo "No IP address found for eth1."
    exit 1
fi

# Update the Redis configuration file
echo "bind 127.0.0.1 $LAN_IP"
if ! grep -q '^bind ' /etc/redis.conf; then
    echo "bind 127.0.0.1 $LAN_IP" | sudo tee -a /etc/redis.conf
else
    sudo sed -i "s/^bind .*/bind 127.0.0.1 $LAN_IP/" /etc/redis.conf
fi

# Restart Redis service to apply changes
sudo systemctl restart redis
