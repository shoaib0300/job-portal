## KaamMilo: Python JobSpy for LinkedIn via freeworld/php-jobspy
RUN apt-get update \
 && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
      python3 python3-pip python3-venv \
 && pip3 install --break-system-packages --no-cache-dir "python-jobspy>=1.1.0" \
 && apt-get clean \
 && rm -rf /var/lib/apt/lists/*
