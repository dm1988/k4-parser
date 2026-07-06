# K4 Parser

K4 Parser is a Laravel application for parsing crew roster and schedule documents, reviewing fleet activity, and exporting parsed events as iCalendar data. It can run locally with SQLite or in Docker with MySQL and Redis.

## Requirements

For the Docker workflow:

- Docker Engine or Docker Desktop with Docker Compose
- Composer 2, or Docker to run Composer in a temporary container

For a native installation:

- PHP 8.3 or later with the extensions required by Laravel, MySQL/SQLite, and image processing
- Composer 2
- Node.js and npm
- Tesseract OCR (`tesseract-ocr` on Debian/Ubuntu)
- MySQL 8.x, or SQLite for a lightweight local setup

The included Docker image supplies PHP 8.5, Node.js 24, Composer, Tesseract, and the required PHP extensions. The Compose stack uses MySQL 8.4 and Redis.

## Docker setup (recommended)

Install the PHP dependencies first so the Laravel Sail executable is available:

```bash
composer install
```

If Composer is not installed on the host, use its Docker image instead:

```bash
docker run --rm -u "$(id -u):$(id -g)" -v "$PWD:/app" composer:2 composer install
```

Create the environment file:

```bash
cp .env.example .env
```

Configure `.env` for the Compose services:

```dotenv
APP_NAME="K4 Parser"
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=k4_parser
DB_USERNAME=sail
DB_PASSWORD=password

REDIS_HOST=redis

WWWGROUP=1000
WWWUSER=1000
```

Use your host user and group IDs for `WWWUSER` and `WWWGROUP` when they are not `1000` (`id -u` and `id -g` on Linux).

Start the stack and initialize the application:

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm install
./vendor/bin/sail npm run build
```

The application is available at `http://localhost` by default. Override `APP_PORT` in `.env` if port 80 is already in use, for example `APP_PORT=8080`.

For frontend development, run:

```bash
./vendor/bin/sail npm run dev
```

Stop the containers with `./vendor/bin/sail down`. Add `-v` only when you intentionally want to delete the MySQL and Redis volumes as well.

## Native setup

The default `.env.example` uses SQLite. Create the database file before running the bundled setup script:

```bash
cp .env.example .env
touch database/database.sqlite
composer run setup
```

`composer run setup` installs PHP and JavaScript dependencies, generates the application key, runs migrations, and builds the frontend assets. Start the development processes with:

```bash
composer run dev
```

To use a locally installed MySQL server instead, replace the `DB_*` values in `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=k4_parser
DB_USERNAME=k4_parser
DB_PASSWORD=change-me
```

Create that database and user in MySQL before running `php artisan migrate --seed`.

## Environment configuration

Never commit `.env`; it contains machine-specific settings and secrets. The most relevant values are:

| Variable | Purpose |
| --- | --- |
| `APP_ENV`, `APP_DEBUG`, `APP_URL` | Runtime environment, error visibility, and public URL |
| `APP_VERSION`, `PARSER_VERSION` | Optional application/parser version labels |
| `DB_*` | SQLite or MySQL connection settings |
| `REDIS_HOST`, `REDIS_PORT` | Redis connection; use `redis` as the Docker hostname |
| `TESSERACT_PATH` | Tesseract executable path; defaults to `/usr/bin/tesseract` |
| `AERODATABOX_API_KEY` | AeroDataBox credential required for live flight synchronization |
| `AERODATABOX_BASE_URL` | AeroDataBox API endpoint |
| `AERODATABOX_THROTTLE_MS` | Delay between API requests; defaults to `1100` ms |
| `APP_PORT`, `VITE_PORT` | Optional host ports for the Docker web and Vite services |

After changing cached environment or configuration values, run `php artisan optimize:clear` natively or `./vendor/bin/sail artisan optimize:clear` in Docker.

## Testing

Run the complete test suite in Docker:

```bash
./vendor/bin/sail test
```

Or run it natively:

```bash
composer test
```

Useful targeted commands include:

```bash
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
php artisan test --filter=RosterParserTest
```

The Compose MySQL container creates a separate `testing` database on its first initialization. PHPUnit also switches cache, queue, mail, and session drivers to in-memory or synchronous testing implementations. Do not point test configuration at a database containing data you need to keep.

Run the code formatter with:

```bash
./vendor/bin/pint
```

## Production deployment

The included Compose file is optimized for local development: it bind-mounts the source tree and runs Laravel's development server. For production, build an immutable application image and place it behind a production web server or managed container platform.

A typical release should:

1. Provide production `.env` values through the hosting platform or secret manager. Set `APP_ENV=production`, `APP_DEBUG=false`, the canonical `APP_URL`, a persistent `APP_KEY`, and production database credentials.
2. Install optimized PHP dependencies with `composer install --no-dev --classmap-authoritative`.
3. Install and compile frontend assets with `npm ci && npm run build`.
4. Ensure `storage` and `bootstrap/cache` are writable by the application user.
5. Run `php artisan migrate --force` during the release process.
6. Cache framework metadata with `php artisan optimize`.
7. Run a supervised queue worker when `QUEUE_CONNECTION` is asynchronous, and restart workers after each release with `php artisan queue:restart`.
8. Configure the scheduler to run `php artisan schedule:run` every minute if scheduled tasks are enabled.

Back up MySQL before migrations, terminate TLS at the proxy or platform edge, and keep the application key and API credentials outside the image and source repository.

## Useful commands

```bash
# Parse a schedule PDF and print JSON
php artisan parse:schedule /path/to/schedule.pdf

# Synchronize AeroDataBox flights for all active aircraft
php artisan aerodatabox:sync-flights

# Synchronize a single aircraft registration
php artisan aerodatabox:sync-flights --tail=N12345
```

Prefix native `php artisan` commands with `./vendor/bin/sail` when using Docker.

## License

This project is built on Laravel, which is licensed under the [MIT License](https://opensource.org/licenses/MIT).
