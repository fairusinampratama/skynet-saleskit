FROM python:3.12-slim

WORKDIR /app

ENV PYTHONDONTWRITEBYTECODE=1
ENV PYTHONUNBUFFERED=1

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libgomp1 \
        libglib2.0-0 \
        libgl1 \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY docker/ocr/requirements.txt /tmp/requirements.txt
RUN python -m pip install --no-cache-dir --upgrade pip \
    && python -m pip install --no-cache-dir -r /tmp/requirements.txt \
    && rm -f /tmp/requirements.txt

COPY docker/ocr/paddle_ktp_service.py /app/paddle_ktp_service.py

EXPOSE 8080

CMD ["uvicorn", "paddle_ktp_service:app", "--host", "0.0.0.0", "--port", "8080"]
