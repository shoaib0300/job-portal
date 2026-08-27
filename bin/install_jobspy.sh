#!/bin/bash
set -euo pipefail
# Match .ddev/web-build/Dockerfile: avoid NUMPY==1.26.3 on Python 3.13.
sudo apt-get update -qq
sudo DEBIAN_FRONTEND=noninteractive apt-get install -y -qq python3-pip python3-venv
pip3 install --break-system-packages \
  'numpy>=2.1.0' \
  'pandas>=2.1.0,<3.0.0' \
  'beautifulsoup4>=4.12.2,<5.0.0' \
  'markdownify>=0.13.1,<0.14.0' \
  'pydantic>=2.3.0,<3.0.0' \
  'regex>=2024.4.28,<2025.0.0' \
  'requests>=2.31.0,<3.0.0' \
  'tls-client>=1.0.1,<2.0.0'
pip3 install --break-system-packages --no-deps 'python-jobspy>=1.1.0'
python3 -c 'import jobspy; print("jobspy ok")'
