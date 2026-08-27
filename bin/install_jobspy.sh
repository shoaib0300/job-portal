#!/bin/bash
set -euo pipefail
sudo apt-get update -qq
sudo DEBIAN_FRONTEND=noninteractive apt-get install -y -qq python3-pip python3-venv
pip3 install --break-system-packages 'python-jobspy>=1.1.0'
python3 -c 'import jobspy; print("jobspy ok")'
