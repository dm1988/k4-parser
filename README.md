# K4 Parser

K4 Parser is a Laravel application for parsing crew roster and schedule documents, reviewing fleet activity, and exporting parsed events as iCalendar data. It can run locally with SQLite or in Docker with MySQL and Redis.

[![Continuous Integration](https://github.com/dm1988/k4-parser/actions/workflows/ci.yml/badge.svg)](https://github.com/dm1988/k4-parser/actions/workflows/ci.yml)

Operational reference: [Artisan commands](#artisan-commands) · [Jobs and queues](#jobs-and-queues) · [Workers](#workers) · [Scheduler](#scheduler) · [Troubleshooting](#queue-and-scheduler-troubleshooting) · [Installed command index](#installed-artisan-command-index).

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

The supported Sail image supplies PHP 8.4, Node.js 24, Composer, Tesseract, and the required PHP extensions. The Compose stack uses MySQL 8.4 and Redis.

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

For frontend development only, run:

```bash
./vendor/bin/sail npm run dev
```

For the complete development process group, including workers, see [Workers](#workers). `sail up -d` starts the web, MySQL and Redis containers; it does not start queue workers or the scheduler.

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
| `APP_VERSION`, `EXTRACTOR_VERSION` | Optional application/extractor version labels |
| `DB_*` | SQLite or MySQL connection settings |
| `REDIS_HOST`, `REDIS_PORT` | Redis connection; use `redis` as the Docker hostname |
| `TESSERACT_PATH` | Tesseract executable path; defaults to `/usr/bin/tesseract` |
| `QUEUE_CONNECTION` | Default queue connection; defaults to `database`. Shared-bid refreshes always use their own `crew-bid-refreshes` connection |
| `DB_QUEUE_CONNECTION`, `DB_QUEUE_TABLE` | Optional database connection name and jobs table for database queues; table defaults to `jobs` |
| `DB_QUEUE`, `DB_QUEUE_RETRY_AFTER` | Default database queue name (`default`) and reservation time (`90` seconds); these do not change the dedicated bid queue's name or 300-second reservation |
| `CACHE_STORE`, `CACHE_PREFIX` | Shared cache used for worker restarts, locks, refresh status, temporary encrypted portal logins and exports; defaults to the database store |
| `QUEUE_FAILED_DRIVER` | Failed-job storage; defaults to `database-uuids` |
| `CREW_BIDS_PORTAL_IMPORT_ENABLED` | Enable scheduled public-package imports; defaults to `false` |
| `CREW_BIDS_PORTAL_IMPORT_ADMIN_ID` | Active, verified bid administrator responsible for portal imports |
| `CREW_BIDS_SUBMISSION_ENABLED` | Enable outbound portal submission; separate from package ingestion and shared-bid refreshes |
| `MAIL_MAILER`, `MAIL_*` | Account email, announcements and system alerts. The example environment uses the `log` mailer |
| `MAIL_ADMIN_ADDRESS` | Recipient for scheduled system-health alerts; must be a valid email address |
| `APP_PORT`, `VITE_PORT` | Optional host ports for the Docker web and Vite services |

After changing environment/configuration values, run `./vendor/bin/sail artisan config:clear` during development or rebuild production configuration with `./vendor/bin/sail artisan config:cache`, then restart workers. Use `optimize:clear` deliberately: it also clears application cache entries and locks, as described below.

## Testing

Run focused tests with the isolated in-memory SQLite database required by `Tests\TestCase`:

```bash
./vendor/bin/sail exec -T -e APP_ENV=testing -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: -e DB_URL= -e CACHE_STORE=array -e SESSION_DRIVER=array laravel.test php artisan test --compact tests/Feature/CrewBidRefreshTest.php
```

Use the same environment prefix with `--filter=testName` or another test file. Omit the file argument to run the complete suite when needed. PHPUnit also sets the queue to `sync` by default; the dedicated bid-refresh connection remains explicitly database-backed and is faked or isolated by its tests.

For a native installation with the same isolated environment:

```bash
APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL= CACHE_STORE=array SESSION_DRIVER=array php artisan test --compact
```

Frontend checks and PHP tooling:

```bash
./vendor/bin/sail npm test
./vendor/bin/sail npm run build
./vendor/bin/sail bin pint --dirty --format agent
./vendor/bin/sail bin phpstan analyse --memory-limit=1G --no-progress
```

The Compose MySQL container also creates a `testing` database on first initialization, but the current base test class requires SQLite `:memory:`. Application or production databases must not be used for tests.

## Production deployment

The included Compose file is optimized for local development: it bind-mounts the source tree and runs Laravel's development server. For production, build an immutable application image and place it behind a production web server or managed container platform.

A typical release should:

1. Provide production `.env` values through the hosting platform or secret manager. Set `APP_ENV=production`, `APP_DEBUG=false`, the canonical `APP_URL`, a persistent `APP_KEY`, and production database credentials.
2. Install optimized PHP dependencies with `composer install --no-dev --classmap-authoritative`.
3. Install and compile frontend assets with `npm ci && npm run build`.
4. Ensure `storage` and `bootstrap/cache` are writable by the application user.
5. Run `php artisan migrate --force` during the release process.
6. Cache framework metadata with `php artisan optimize`.
7. Run supervised workers for the default queue, `crew-bid-refreshes`, and `broadcasts` when announcements are queued. The bid worker is required independently of `QUEUE_CONNECTION`. See [Workers](#workers) for the exact commands and shutdown limits. Restart workers after each release with `php artisan queue:restart`; the process manager must relaunch them.
8. Configure the scheduler to run `php artisan schedule:run` every minute. See [Scheduler](#scheduler) for task frequencies and conditions. All web/worker/scheduler processes must use the same database, shared cache, cache prefix and persistent `APP_KEY`.

Back up MySQL before migrations, terminate TLS at the proxy or platform edge, and keep the application key and API credentials outside the image and source repository.

## Artisan commands

Run commands from the project root. Examples below use Sail; for a native installation, replace `./vendor/bin/sail artisan` with `php artisan`. File arguments must exist inside the container: repository files are mounted at `/var/www/html`, so a relative path such as `storage/app/private/schedule.pdf` works.

Discover all installed commands and the full arguments/options for any command:

```bash
./vendor/bin/sail artisan list
./vendor/bin/sail artisan list --raw
./vendor/bin/sail artisan list crew-bids
./vendor/bin/sail artisan help crew-bids:import-portal
./vendor/bin/sail artisan help queue:work
```

`--help` is also accepted after a command. `--no-interaction` disables prompts; it must not be used for portal discovery or as a way to approve a bulk email send. Commands normally return exit status `0` for success and a nonzero status for failure. The [installed index](#installed-artisan-command-index) lists framework/package commands as well as the application commands documented here.

### Application command reference

| Command and arguments/options | Behavior and requirements |
| --- | --- |
| `parse:schedule {file}` | Extract a Trip Information PDF and print extracted data and parsed schedule JSON, including flight DTOs when available. Requires a readable file. Missing files return `1`; extraction failures return `2`. A flight-DTO-only failure emits a warning while preserving parsed schedule output |
| `app:cleanup-user-uploads` | Delete files older than five days from the configured `user_uploads` and `user_flight_releases` disks. Runs immediately; there is no dry-run option |
| `system:check-alerts` | Check storage free space, signups and extraction volume. Requires valid `MAIL_ADMIN_ADDRESS`; matching alerts dispatch `SystemAlertNotification` on the default queue. Disk thresholds: warning below 20%, critical below 10%. Volume warnings require more than six times the historical daily maximum, using the preceding 30-day history. Critical alerts are throttled for one hour; warnings for 12 hours |
| `crew-bids:import-portal [--period=YYYY-MM] [--fleet=747\|777] [--admin-id=ID]` | Download and validate Lines PDF, Trips PDF and Overview XLSX directly from the public portal. Period defaults to next UTC month; fleet defaults to both; administrator defaults to `CREW_BIDS_PORTAL_IMPORT_ADMIN_ID`. Requires an active, verified administrator with K4 Bids access. Runs synchronously in the CLI/scheduler, independently of the shared-refresh job |
| `crew-bids:discover-portal [--lines=F1,R2]` | Interactive portal inspection using hidden employee-number/password prompts. Does not submit or change bids. Optional comma-separated local IDs allow an offline eligibility comparison. Reports structure rather than credential/page values. Discovery does not establish submission acceptance or official eligibility |
| `crew-bids:enrich-pay-source {package} {files...} [--included-occupied-day=LINE:YYYY-MM-DD ...]` | Recover pay metadata for a published package using its original Lines/Trips/Overview sources. Accepts one to three PDF/XLSX files, at most 12 MB each. Source fingerprints, month, fleet and revision must match. Repeat the option to confirm flying-line occupied dates already included in published Credit. Writes verified `pay_source`; it does not replace the published package payload |
| `mail:security-update --email=ADDRESS` or `mail:security-update --all` | Queue a review copy on the default queue, or interactively confirm a full send to active users with nonempty emails on `broadcasts`. Options are mutually exclusive. Full sends are spaced four seconds apart and protected by the persistent cache key `mailings:security-update:queued`; there is no `--force` option |
| `mail:version-one --email=ADDRESS` or `mail:version-one --all [--force]` | Queue a preview on the default queue or interactively confirm the same active-user audience on `broadcasts`, four seconds apart. `--force` permits an intentional repeated full send despite `mailings:version-one-announcement:queued`; it does not skip confirmation. The preview is queued because the mailable implements `ShouldQueue`, even though its command output says “sent” |
| `inspire` | Print an inspirational quotation; no application data changes |

Portal import outcomes are `missing` (complete source set not yet available), `unchanged` (known fingerprint), `published` (first package for the month/fleet), or `revision_held` (new revision saved unpublished for administrator review). Missing/unchanged packages are successful checks; import errors return failure and preserve existing packages. The scheduled-import enable flag controls scheduling; an explicit CLI import can still be requested when that flag is off, subject to administrator access.

Examples:

```bash
# Parse a schedule PDF and print JSON
./vendor/bin/sail artisan parse:schedule storage/app/private/schedule.pdf

# Import a specific public bid package; use a real accountable administrator ID
./vendor/bin/sail artisan crew-bids:import-portal --period=2026-12 --fleet=777 --admin-id=42

# Inspect the authenticated portal interactively
./vendor/bin/sail artisan crew-bids:discover-portal --lines=F1,R2

# Enrich an existing package using its exact original source set
./vendor/bin/sail artisan crew-bids:enrich-pay-source 42 storage/app/private/Lines.pdf storage/app/private/Trips.pdf storage/app/private/Overview.xlsx --included-occupied-day=F49:2026-11-15

# Queue a single announcement review copy
./vendor/bin/sail artisan mail:security-update --email=review@example.com
./vendor/bin/sail artisan mail:version-one --email=review@example.com

# Intentionally request a full send; these commands ask for confirmation
./vendor/bin/sail artisan mail:security-update --all
./vendor/bin/sail artisan mail:version-one --all
```

K4 bid ingestion, shared reports and submission verification use the portal. Account email, announcements and system alerts remain supported. With `MAIL_MAILER=log`, processed email is written to the application log rather than delivered to a mail provider.

### Common framework operations

| Command | Use |
| --- | --- |
| `about`, `env` | Inspect the application/runtime summary and environment |
| `route:list --except-vendor`, `route:list --path=crew-bids` | Inspect routes without invoking them |
| `config:show queue`, `config:show crew_bids` | Inspect effective configuration; configuration output can contain secrets |
| `migrate:status`, `migrate --force` | Inspect migrations or apply them in a production release. Existing migrations create `jobs`, `job_batches`, `failed_jobs`, cache and lock tables |
| `db:seed`, `migrate --seed` | Populate configured seed data during initial setup; review seeders before using against an existing deployment |
| `key:generate` | Initialize a new installation's application key. Preserve the existing production key: snapshots, temporary logins, exports and encrypted jobs depend on it |
| `optimize`, `optimize:clear` | Build or clear optimized metadata. `optimize:clear` also clears the default cache, including application locks/status and mailing guards; use it deliberately |
| `config:cache`, `config:clear` | Rebuild or remove only the configuration cache after configuration changes |
| `route:cache`, `route:clear`, `view:cache`, `view:clear` | Manage route or compiled-view caches |
| `storage:link` | Create configured public storage links; private portal data must remain private |
| `down`, `up` | Enter or leave maintenance mode; workers normally pause during maintenance unless started with `--force` |
| `dev`, `dev:list`, `serve`, `pail` | Start/inspect development processes, run the development web server, or follow application logs |
| `test --compact [path] [--filter=name]` | Run tests with the isolated environment documented under [Testing](#testing) |

## Jobs and queues

A **connection** selects a backend/configuration; a **queue** selects a named stream on that connection. For the bid job both names are `crew-bid-refreshes`, so both appear in its worker command. `--queue=crew-bid-refreshes` on the default connection alone will not use the bid connection's timeout/reservation configuration.

| Job/message | Trigger | Connection → queue | Attempts and behavior |
| --- | --- | --- | --- |
| `App\Jobs\RefreshCrewBidSnapshot` | Refresh shared bids / prepare PDF or Excel on My bid predictions | Explicit `crew-bid-refreshes` → `crew-bid-refreshes` | One attempt; 240-second job timeout; fail on timeout; 300-second database reservation. Encrypted queue payload contains a temporary credential reference, not the portal password |
| `App\Mail\SecurityUpdateAnnouncement` via Laravel `SendQueuedMailable` | `mail:security-update` | Configured default connection → default queue for review copies, `broadcasts` for full sends | Five attempts; retry delays of 60, 120, 300 and 600 seconds |
| `App\Mail\VersionOneAnnouncement` via Laravel `SendQueuedMailable` | `mail:version-one` | Configured default connection → default queue for previews, `broadcasts` for full sends | Five attempts; same retry delays |
| `App\Notifications\SystemAlertNotification` via Laravel `SendQueuedNotifications` | `system:check-alerts` when a threshold is reached | Configured default connection → default queue | Attempts/timeouts come from the worker; alert delivery uses email |

The default connection is selected by `QUEUE_CONNECTION` (`database` in `.env.example`); its default queue is normally `default` (`DB_QUEUE` for the database connection). Account verification/OTP and password-reset notifications currently run synchronously and do not implement `ShouldQueue`.

The bid connection always uses the database driver and the jobs table configured by `DB_QUEUE_CONNECTION` / `DB_QUEUE_TABLE`, even when the default connection is `sync` or Redis. Other configured connections are `sync`, `database`, `redis`, `beanstalkd`, `sqs`, `deferred`, `background` and `failover`; their presence in configuration does not mean they are running. `sync` executes default jobs during the caller's request; it does not turn the dedicated bid job into a synchronous operation.

### Shared-bid job lifecycle

The web request queues work and redirects immediately. The authenticated page polls a private status endpoint for `queued`, `running`, `completed` or `failed`, updating progress without reloading the page. Users choose **View updated prediction** when ready; requested exports appear as private download links.

Each queued login is encrypted in cache, valid for ten minutes and removed when consumed or when an attempt fails. The worker rechecks account/package access and package identity before contacting Kalitta. The application serializes refreshes per month/fleet/position with a lock. Only a fully validated global report replaces the snapshot; missing ranks or other validation failures preserve the last good snapshot. Status records and encrypted exports have a 15-minute cache lifetime; export access is restricted to the requesting account/package. Web and worker processes must share cache storage and `APP_KEY`; a process-local `array` cache is unsuitable for runtime workers.

Expected portal/validation errors are stored in application refresh status and handled by the job, so a worker may print `DONE` while the page reports a failed refresh. `queue:failed` primarily shows unhandled worker failures/timeouts, not every portal-validation failure. Expired, failed or outdated refreshes require a new form submission with a fresh login; replaying a failed job will not restore discarded credentials.

## Workers

### Start workers manually

Run each required worker in a separate terminal or supervised process:

```bash
# Shared-bid reports and exports: explicit connection AND queue
./vendor/bin/sail artisan queue:work crew-bid-refreshes --queue=crew-bid-refreshes --tries=1 --timeout=240

# Default connection/queue: system alerts and announcement review copies
./vendor/bin/sail artisan queue:work --tries=1 --timeout=60

# Bulk announcements on the configured default connection
./vendor/bin/sail artisan queue:work --queue=broadcasts --tries=5 --timeout=60
```

The mailable classes' five-attempt setting overrides the default worker's `--tries=1` for announcement review copies. For an asynchronous default connection, a default worker is required even if the bid worker is running. A broadcasts worker is required whenever full announcements are queued. Named queues are independent; the default worker does not automatically consume `broadcasts` or the bid queue.

Useful worker options:

| Option/command | Meaning |
| --- | --- |
| `queue:work [connection] --queue=name` | Long-running worker for that connection and queue; use a process manager in production |
| `queue:listen [connection] --queue=name` | Reload the application between jobs; convenient during development and slower than `queue:work` |
| `--queue=default,broadcasts` | Process listed queues in priority order on one connection. A separate broadcasts worker avoids starving announcements behind default work |
| `--once`, `--stop-when-empty` | Process one available job, or drain currently available work and exit. Delayed jobs may not be available yet |
| `--tries=N`, `--backoff=SECONDS` | Worker attempt limit and retry delay when the job itself does not override them |
| `--timeout=SECONDS` | Worker timeout; keep it below the connection's reservation/visibility timeout. Job-specific values take precedence |
| `--sleep=SECONDS`, `--memory=MB` | Idle polling delay and worker memory limit |
| `--max-jobs=N`, `--max-time=SECONDS` | Recycle a worker after an amount of work/time; the process manager must restart it |
| `--force` | Process jobs in maintenance mode; use only when intended |

The dedicated bid job's 240-second timeout is below its 300-second reservation. Default database and Redis reservations are 90 seconds unless changed; the 60-second worker examples fit those defaults. For SQS, configure visibility timeout in SQS. PHP CLI needs `pcntl` for worker timeouts; Sail's PHP image provides it.

### Development process groups

```bash
# Web server, default queue listener, bid listener, logs and Vite
./vendor/bin/sail artisan dev
./vendor/bin/sail artisan dev:list

# Same services plus the scheduler
./vendor/bin/sail composer run dev
```

Neither group starts a `broadcasts` worker. Start one explicitly when testing a full announcement. Artisan `dev` does not register `schedule:work`; start it separately when scheduled work is needed. Use one process group at a time to avoid duplicate web servers, Vite processes or workers. Ctrl+C stops a foreground worker/group.

### Production supervision and releases

Keep the three worker commands above under Supervisor, systemd or the hosting platform's process manager, using `php artisan ...` inside the production application's working directory. Enable automatic restart and log capture. Allow more than 240 seconds for a bid worker to stop gracefully (for example, Supervisor `stopwaitsecs=330`), and ensure host/container shutdown limits honor that grace period. The included Sail Supervisor configuration starts only the web process; it is not a production queue-worker configuration.

After deploying code/configuration, signal all workers using the same shared cache:

```bash
./vendor/bin/sail artisan queue:restart
```

The signal makes daemon workers exit after their current job; it does not launch replacement processes. Supervision must restart them. For a manually started worker, stop it with Ctrl+C and rerun its startup command. For an existing development group, restart the group to pick up process registrations/configuration. Do not change `APP_KEY` to resolve a worker problem.

### Inspect and recover failed work

```bash
# List unhandled failed jobs and their IDs
./vendor/bin/sail artisan queue:failed

# Pause/resume new work on the bid connection/queue; current jobs can finish
./vendor/bin/sail artisan queue:pause crew-bid-refreshes:crew-bid-refreshes
./vendor/bin/sail artisan queue:resume crew-bid-refreshes:crew-bid-refreshes

# Retry one reviewed failed mail/notification job (replace FAILED_JOB_UUID)
./vendor/bin/sail artisan queue:retry FAILED_JOB_UUID

# Remove one failed-job record without retrying it
./vendor/bin/sail artisan queue:forget FAILED_JOB_UUID

# Delete failed-job history older than seven days
./vendor/bin/sail artisan queue:prune-failed --hours=168

# Report backlog thresholds; this emits framework events, not an app email by itself
./vendor/bin/sail artisan queue:monitor database:default,database:broadcasts,crew-bid-refreshes:crew-bid-refreshes --max=100
```

Adjust monitored connection/queue names when overriding defaults. Pausing a queue does not extend the ten-minute credential lifetime, so a long pause requires fresh bid-refresh submissions afterward. Retrying mail can deliver a duplicate if the provider accepted the original attempt before it failed locally. For bid refreshes, use a **fresh submission in My bid predictions**, rather than `queue:retry` or a bulk retry: the worker has consumed/discarded the temporary login. `queue:forget` and pruning delete failure history; they do not undo side effects. `queue:clear` deletes pending work and `queue:flush` deletes all failed-job history; neither is a normal restart step.

## Scheduler

The scheduler runs commands directly; queue workers separately deliver any jobs those commands dispatch. All current schedules use the application's UTC timezone from `config/app.php`.

| Scheduled command | Effective cadence | Conditions and overlap behavior |
| --- | --- | --- |
| `app:cleanup-user-uploads` | Daily at 00:00 UTC | Deletes uploads older than five days. No `onOneServer`/overlap guard is configured; use one scheduler instance for this task |
| `system:check-alerts` | Hourly at minute 00 UTC | Requires `MAIL_ADMIN_ADDRESS`; `onOneServer()` and `withoutOverlapping()` use cache locks. Alerts then need the default queue worker |
| `crew-bids:import-portal` | Hourly at minute 00 UTC on UTC days 8–16 inclusive; otherwise at 00:00, 06:00, 12:00 and 18:00 UTC | Requires `CREW_BIDS_PORTAL_IMPORT_ENABLED=true` and a valid import administrator. Uses `onOneServer()` plus a ten-minute scheduler overlap lock, with an additional per-package import lock |

Inspect schedules without executing tasks, or run the scheduler locally:

```bash
./vendor/bin/sail artisan schedule:list
./vendor/bin/sail artisan schedule:work
```

For production, run `schedule:run` every minute through the platform scheduler or cron. A native deployment's cron entry is:

```cron
* * * * * cd /path/to/k4-parser && php artisan schedule:run >> /path/to/k4-parser/storage/logs/scheduler.log 2>&1
```

Use the deployment's PHP executable and application user, and rotate that log. Inside a production container, run the same Artisan command through its scheduler/process configuration. `schedule:run` evaluates tasks due **now**; invoking it manually can import packages, delete old uploads or queue system alerts. `schedule:work` stays in the foreground and evaluates every minute. Keep a shared cache for scheduler locks, and avoid multiple scheduler instances because cleanup has no one-server guard.

## Queue and scheduler troubleshooting

| Symptom | Checks/action |
| --- | --- |
| Bid refresh stays `queued` | Start the worker with both `crew-bid-refreshes` names. Confirm migrations are applied and web/worker use the same database, cache and application key. `sail up -d` alone is insufficient |
| Worker listens but finds no bid jobs | `queue:work --queue=crew-bid-refreshes` uses the default connection. Use the explicit bid connection shown above; inspect `config:show queue` for effective settings |
| Login expired before the worker started | Ensure the worker is running, then submit a new refresh; queued credentials expire after ten minutes |
| `Undefined array key "lock_key"` from an older attempt | Restart the long-running worker to load the compatibility handling, then submit a new refresh. A failed attempt's login has already been discarded |
| “The global report is missing choice ranks” | Background validation rejected an incomplete/noncontiguous report. The previous snapshot remains available. Retry with a fresh login; queue restarts do not repair report contents |
| Worker prints `DONE`, but refresh status says failed | Expected portal/validation errors are captured in the page's status. Read that message; it may not appear in `queue:failed` |
| Bulk announcements remain pending | Start the `broadcasts` worker on the configured default connection. Recipients also have intentional four-second delivery spacing |
| Completed email produces no inbox delivery | `.env.example` uses `MAIL_MAILER=log`. Configure the intended mail transport, refresh configuration and restart workers |
| Scheduled import is not running | Check `schedule:list`, the UTC day/hour, `CREW_BIDS_PORTAL_IMPORT_ENABLED`, administrator access and the actual scheduler process/cron. `schedule:list` shows the hourly expression; its conditional gate determines whether it runs |
| Overlap lock remains after a process crash | Confirm no matching task is still running. `schedule:clear-cache` clears scheduler overlap locks; it does not clear the app's separate bid/import locks. Avoid clearing the whole cache as a routine fix |

## Installed Artisan command index

This index covers the registered commands in the development installation (Laravel 13.34.0). Commands supplied by development dependencies are absent when installing with `--no-dev`. Use `artisan list` and `artisan help COMMAND` for the authoritative inventory, options and descriptions after dependency upgrades. Generation, installation and database-reset commands can change application files/data; listing them does not mean they are release steps.

Application behavior is defined by [console commands](app/Console/Commands), [scheduled tasks](routes/console.php), [jobs](app/Jobs), [queue configuration](config/queue.php), and [development process registration](app/Providers/AppServiceProvider.php). Keep this reference in sync when those change.

<details>
<summary>All 208 registered commands, grouped by namespace</summary>

| Namespace | Commands |
| --- | --- |
| general | `about`, `clear-compiled`, `completion`, `db`, `dev`, `docs`, `down`, `env`, `help`, `inspire`, `list`, `migrate`, `optimize`, `pail`, `reload`, `serve`, `sloppy`, `test`, `tinker`, `up` |
| app | `app:cleanup-user-uploads` |
| auth | `auth:clear-resets` |
| boost | `boost:add-skill`, `boost:install`, `boost:list-skills`, `boost:mcp`, `boost:update` |
| breeze | `breeze:install` |
| cache | `cache:clear`, `cache:forget`, `cache:prune-stale-tags` |
| cashier | `cashier:webhook` |
| channel | `channel:list` |
| config | `config:cache`, `config:clear`, `config:publish`, `config:show` |
| crew-bids | `crew-bids:discover-portal`, `crew-bids:enrich-pay-source`, `crew-bids:import-portal` |
| db | `db:monitor`, `db:seed`, `db:show`, `db:table`, `db:wipe` |
| debugbar | `debugbar:clear`, `debugbar:find`, `debugbar:get`, `debugbar:install-skill`, `debugbar:queries` |
| dev | `dev:list` |
| env | `env:decrypt`, `env:encrypt` |
| event | `event:cache`, `event:clear`, `event:list` |
| filament | `filament:about`, `filament:assets`, `filament:cache-components`, `filament:check-translations`, `filament:clear-cached-components`, `filament:install`, `filament:optimize`, `filament:optimize-clear`, `filament:upgrade` |
| icons | `icons:cache`, `icons:clear` |
| install | `install:api`, `install:broadcasting` |
| key | `key:generate` |
| lang | `lang:publish` |
| livewire | `livewire:attribute`, `livewire:config`, `livewire:configure-s3-upload-cleanup`, `livewire:convert`, `livewire:form`, `livewire:layout`, `livewire:publish`, `livewire:stubs` |
| mail | `mail:security-update`, `mail:version-one` |
| make | `make:cache-table`, `make:cast`, `make:channel`, `make:class`, `make:command`, `make:component`, `make:config`, `make:controller`, `make:enum`, `make:event`, `make:exception`, `make:factory`, `make:filament-cluster`, `make:filament-exporter`, `make:filament-form`, `make:filament-form-field`, `make:filament-importer`, `make:filament-infolist-entry`, `make:filament-issue`, `make:filament-livewire-form`, `make:filament-livewire-schema`, `make:filament-livewire-table`, `make:filament-page`, `make:filament-panel`, `make:filament-relation-manager`, `make:filament-resource`, `make:filament-rich-content-custom-block`, `make:filament-schema`, `make:filament-schema-component`, `make:filament-table`, `make:filament-table-column`, `make:filament-theme`, `make:filament-user`, `make:filament-widget`, `make:interface`, `make:job`, `make:job-middleware`, `make:listener`, `make:livewire`, `make:mail`, `make:mcp-app-resource`, `make:mcp-prompt`, `make:mcp-resource`, `make:mcp-server`, `make:mcp-tool`, `make:middleware`, `make:migration`, `make:model`, `make:notification`, `make:notifications-table`, `make:observer`, `make:policy`, `make:provider`, `make:queue-batches-table`, `make:queue-failed-table`, `make:queue-table`, `make:request`, `make:resource`, `make:rule`, `make:scope`, `make:seeder`, `make:session-table`, `make:test`, `make:trait`, `make:view` |
| mcp | `mcp:inspector`, `mcp:start` |
| migrate | `migrate:fresh`, `migrate:install`, `migrate:refresh`, `migrate:reset`, `migrate:rollback`, `migrate:status` |
| model | `model:prune`, `model:show` |
| optimize | `optimize:clear` |
| package | `package:discover` |
| parse | `parse:schedule` |
| queue | `queue:clear`, `queue:failed`, `queue:flush`, `queue:forget`, `queue:listen`, `queue:monitor`, `queue:pause`, `queue:prune-batches`, `queue:prune-failed`, `queue:restart`, `queue:resume`, `queue:retry`, `queue:retry-batch`, `queue:work` |
| roster | `roster:scan` |
| route | `route:cache`, `route:clear`, `route:list` |
| sail | `sail:add`, `sail:install`, `sail:publish` |
| schedule | `schedule:clear-cache`, `schedule:interrupt`, `schedule:list`, `schedule:pause`, `schedule:resume`, `schedule:run`, `schedule:test`, `schedule:work` |
| schema | `schema:dump` |
| sloppy | `sloppy:agents`, `sloppy:architecture`, `sloppy:baseline`, `sloppy:ci`, `sloppy:diff`, `sloppy:fix`, `sloppy:health`, `sloppy:help`, `sloppy:mcp`, `sloppy:review`, `sloppy:rules`, `sloppy:watch` |
| storage | `storage:link`, `storage:unlink` |
| stub | `stub:publish` |
| system | `system:check-alerts` |
| vendor | `vendor:publish` |
| view | `view:cache`, `view:clear` |

</details>

## License

This project is built on Laravel, which is licensed under the [MIT License](https://opensource.org/licenses/MIT).
