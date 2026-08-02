# BOA PDF

A Laravel 12 + PHP 8.3 web app for managing PDFs: upload, view in-browser,
merge, compress, convert, and sign.

Stack: Laravel 12 · PHP 8.3 · Blade · Alpine.js · Tailwind CSS 4 (Vite) · PDF.js · MySQL/SQLite · Redis queues.

## Features

| Feature | Backed by |
|---|---|
| Upload + library | Storage facade (`DOCUMENTS_DISK`), validated `UploadPdfRequest` |
| Camera scan | `PdfFromImagesService` |
| In-browser view | PDF.js with Alpine viewer |
| Merge / compress / convert / sign | Queued jobs (`ProcessPdf*Job`) + FPDI / Ghostscript / Poppler / OCR / LibreOffice |
| Daily cleanup + retention | `pdf:cleanup`, `pdf:prune-expired-documents` |

## First-time setup (Herd / local)

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm ci && npm run build
# optional demo user: php artisan db:seed
```

## Docker (production-oriented)

```bash
cp .env.docker.example .env
# set APP_KEY, MYSQL_PASSWORD, MYSQL_ROOT_PASSWORD
docker compose build
docker compose up -d
```

Services: `nginx` (:8080), `app` (PHP 8.3-FPM), `mysql`, `redis`, `worker` (`queue:work`), `scheduler`.
PDF files live on the `pdf_storage` volume. Health: `http://localhost:8080/up`.

Dev bind mounts: `docker compose -f docker-compose.yml -f docker-compose.dev.yml up`.

## Tests

```bash
php artisan test --compact
```

## Notes

- Long PDF operations run on the queue; keep a worker running in production.
- Set `DOCUMENTS_DISK=s3` (and AWS_* / disk config) for multi-node storage.
- Production: `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, real `MAIL_*`.
