# ERN 3 Indexer
## S3 XML to Solr Processor

This project processes large amounts of XML files stored in an AWS S3 bucket, extracts metadata, and indexes it into a Solr server. The application is designed to be scalable, idempotent, and fault-tolerant using Redis for tracking processed files.

---

## Features

- **Environment Configuration:** Uses `.env` for simplifying setup and securing sensitive credentials.
- **Idempotency:** Tracks processed files using Redis.
- **Parallel Processing:** Supports distributing file chunks across processes.
- **Batch Upload:** Sends data to Solr in batches for efficiency.
- **Recurring Job:** Designed to run as a cron job.

---

## Requirements

- **PHP 8.0+**
- **Composer (Dependency Manager for PHP)**
- **Docker (For Redis setup)**
- Solr server running locally or remotely.
- AWS S3 bucket containing XML files.

---

## Setup Instructions

### 1. Clone the Repository
```bash
git clone <repository-url> s3-to-solr-processor
cd s3-to-solr-processor
```

### 2. Install Dependencies
Install PHP libraries using Composer:
```bash
composer install
```

### 3. Set Up `.env` File
Copy the example `.env` file:
```bash
cp .env.example .env
```

Fill in the following values in the `.env` file:
- AWS credentials (`AWS_REGION`, `AWS_ACCESS_KEY`, `AWS_SECRET_KEY`).
- S3 bucket name (`S3_BUCKET`) and folder prefix (`S3_PREFIX`).
- Solr endpoint (`SOLR_URL`).
- Redis connection info (`REDIS_HOST`, `REDIS_PORT`).
- Processing configuration (`CHUNK_SIZE`, `BATCH_SIZE`).

### 4. Set Up Redis with Docker
Start Redis using Docker Compose:
```bash
docker-compose up -d
```

Verify Redis is running with:
```bash
redis-cli ping
# Expected: PONG
```

### 5. Run the Script

#### Run Manually
To process the XML files manually, run:
```bash
php main.php
```

#### Set Up a Nightly Cron Job
1. Open your crontab editor:
```bash
crontab -e
```

2. Add the following line to schedule the script to run at midnight:
```bash
0 0 * * * php /path/to/main.php >> /path/to/logs/process.log 2>&1
```

Replace `/path/to/main.php` with the absolute path to your script.

---

## Logs
- **Processed Files:** Logged to Redis automatically under keys `processed:<s3-key>`.
- **Errors:** Any errors are printed to the console and can be added to an error log system manually if needed.

---

## Troubleshooting

### Redis is Unreachable
If Redis is not running, restart the container:
```bash
docker-compose up -d
```

### AWS S3 Access Denied
Ensure AWS credentials in `.env` are correct and have appropriate permissions for the S3 bucket.

### Solr Indexing Errors
Verify your Solr server is running and accessible at the URL specified in the `.env` file.


---

## Logs
Errors and messages are logged:
- **Processed Files:** Appended to `processed_files.log`.
- **Errors:** Appended to `error_files.log`.

Adjust the logging paths in the script if needed.

---

## Dependencies

- **AWS SDK for PHP:** For interacting with AWS S3.
- **Predis:** Redis client for PHP.
- **Docker:** For local Redis setup.

--- 

### **How It Works**
1. Fetches files from S3 and checks whether Redis has marked a file as processed.
2. Divides unprocessed files into chunks based on `chunkSize` (e.g., 50 files per chunk).
3. Processes each chunk sequentially or in parallel (if launched in separate processes).
4. Records successfully processed file keys in Redis for idempotency.
5. Posts parsed data to Solr in batches of `batchSize`.

### **Cron Job for Nightly Processing**
Run the script nightly using a cron job:
``` bash
0 0 * * * php /path/to/main.php >> /path/to/logs/process.log 2>&1
```
This runs every midnight and appends logs to `process.log`.

---

## License
This project is open-source and free to use under the MIT license.

