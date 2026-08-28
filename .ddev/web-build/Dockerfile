## KaamFit: Python JobSpy for LinkedIn (freeworld/php-jobspy)
## Avoid NUMPY==1.26.3 (no Py3.13 wheels) and debian pip uninstall conflicts.
## JobSpy failure must not block ddev start.
RUN apt-get update \
 && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
      python3 python3-pip python3-venv \
 && ( \
      pip3 install --break-system-packages --no-cache-dir --ignore-installed \
        "numpy>=2.1.0" \
        "pandas>=2.1.0,<3.0.0" \
        "beautifulsoup4>=4.12.2,<5.0.0" \
        "markdownify>=0.13.1,<0.14.0" \
        "pydantic>=2.3.0,<3.0.0" \
        "regex>=2024.4.28,<2025.0.0" \
        "requests>=2.31.0,<3.0.0" \
        "tls-client>=1.0.1,<2.0.0" \
      && pip3 install --break-system-packages --no-cache-dir --no-deps "python-jobspy>=1.1.0" \
      && python3 -c "import jobspy; print('jobspy ok')" \
    ) || echo "WARNING: python-jobspy install failed; LinkedIn JobSpy scrape disabled until fixed" \
 && apt-get clean \
 && rm -rf /var/lib/apt/lists/*
