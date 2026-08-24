FROM python:3.12-slim

# Without this, stdout is fully block-buffered (no TTY) - log lines can sit
# unflushed and be lost entirely if the process dies abruptly rather than
# exiting normally, which defeats the point of step-by-step logging.
ENV PYTHONUNBUFFERED=1

WORKDIR /app

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY . .

CMD ["python", "main.py"]
