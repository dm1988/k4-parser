# Codex Usage Rules

Follow these rules for every remaining task:
1. Work on one task at a time marked by ##.
2. Run only focused tests while implementing a task.
3. Run Pint after PHP files change.
4. Larastan once at the final integration checkpoint, not after every small edit.
5. Preserve unrelated working-tree changes.
6. Update TODO.md with outcomes instead of adding another plan or duplicate checklist.
7. Create a commit message for each task

## API: trusted-client schedule extraction

### Goal and trust boundary

* Add a versioned API for the iPhone app and Ben Napier's Crew Room service to submit schedules and download the generated calendar file.
* Keep the API deny-by-default: only explicitly provisioned clients may connect.
* Treat this as a server-to-server API. The iPhone app must call through an approved backend; a distributed mobile app is not a known host and cannot safely hold a shared client certificate.
* Do not use CORS or the request `Host` header as client authentication. CORS only constrains browsers, and Laravel trusted-host validation protects the destination host rather than proving who sent a request.

### Client authentication and network controls

* Expose the API only over HTTPS through the production reverse proxy or API gateway; prevent direct public access to the application server.
* Require mutual TLS (mTLS). Issue a separate client certificate for each approved integration so it can be identified, rotated, and revoked independently.
* Allowlist each client's fixed egress IP/CIDR at the edge when stable addresses are available; use this as a second control, not as a replacement for mTLS.
* Configure the proxy to validate the client-certificate chain and revocation status, strip any inbound certificate identity headers, and forward a verified client identity to Laravel only over the trusted internal connection.
* Add an application middleware that maps the verified identity to an enabled API client and rejects missing, unknown, disabled, expired, or mismatched clients before controller code runs.
* Store client names, certificate fingerprints/subjects, status, ownership, and last-used timestamps without storing private keys. Keep CA material and certificate secrets in the deployment secret store.
* Give each client an explicit `schedule:extract` capability and apply a named rate limiter keyed by verified client ID plus source IP.
* Apply request-size limits at both the proxy and Laravel layers, and structured audit logging without logging uploaded schedule contents, certificates, or credentials.

### API contract

* Register `routes/api.php` in `bootstrap/app.php` and place the contract under `/api/v1`.
* `POST /api/v1/schedule-extractions` accepts `multipart/form-data` with either one PDF or up to five JPEG, PNG, or WebP images. Reject mixed PDF/image batches and multiple PDFs; validate both extension and actual MIME type with the existing 12 MB per-file limit.
* Accept optional `event_types[]` filters and an `Idempotency-Key` header so client retries cannot create duplicate work.
* Persist uploads to a private disk, create an extraction owned by the authenticated API client, dispatch a queued job, and return `202 Accepted` with the extraction UUID, status, and status URL.
* `GET /api/v1/schedule-extractions/{extraction}` returns only that client's job status (`pending`, `processing`, `completed`, or `failed`). A completed response includes event counts, expiry time, and the calendar download URL; failures expose a stable public error code without internal exception details.
* `GET /api/v1/schedule-extractions/{extraction}/calendar` returns one full-calendar `.ics` file only when the URL signature is valid, has not expired, the extraction belongs to the authenticated client, and the job completed successfully. Per-event downloads are out of scope for v1.
* Use a Form Request, thin controllers, and API Resources to produce a stable snake_case JSON envelope instead of exposing internal DTO shapes. Document `401` for failed client authentication, `403` for ownership/disabled-client failures, `404` for unknown resources, `413` for oversized requests, `422` for invalid uploads or unparseable schedules, and `429` for throttling.

### Extraction and storage implementation

* Extract the reusable schedule-extraction workflow from the Livewire/session boundary so the web UI and API call the same validation, parsing, enrichment, logging, and ICS generation services.
* Remove the API path's dependency on `auth()`, `session()`, and `EngineResultCache`; use explicit API-client ownership and a persistent result repository while preserving the existing web cache isolation.
* Do not pass `UploadedFile` instances to the queue. Store inputs first and give the job private storage paths plus the API client and extraction identifiers.
* Extend the extraction record, or add a related API-extraction record, to attribute requests to an API client and track queue status, idempotency key, input paths, result path, expiry, error code, and timestamps. Add indexes for client/idempotency and status/created-at lookups; do not represent service clients as browser users.
* Generate the `.ics` artifact on a private disk. Use a short-lived signed route, aligned with the result retention period, that still requires client authentication and ownership; do not return a public storage URL.
* Delete source uploads and generated downloads after the configured retention period, including failed and abandoned jobs, while retaining non-sensitive request metadata for auditing.
* Make the job idempotent, set an explicit timeout and retry/backoff policy, prevent concurrent processing of the same extraction, and mark terminal failures consistently.

### Verification and rollout

* Add PHPUnit feature tests for valid trusted-proxy identity, missing/spoofed/unknown/revoked client identity, client capability checks, ownership isolation, per-client throttling, route middleware, and JSON content types.
* Test one PDF, one image, multiple images, mixed/unsupported/oversized files, empty extraction results, parser failures, duplicate idempotency keys, and successful retry behavior.
* Test the queued state transitions, private storage and cleanup, audit metadata, expiring signed download URL, tampered/expired signatures, cross-client download attempts, and JSON error shapes.
* Add staging ingress tests for mTLS validation, revoked/expired certificates, spoofed forwarded identity headers, source allowlists, and direct Laravel-origin bypass attempts.
* Add a deployment runbook for issuing, rotating, and revoking each client certificate; emergency client disablement; configuring trusted proxies and firewall rules; and confirming that the Laravel origin cannot be reached directly.
* Roll out to a staging hostname first, provision one client at a time, verify logs and rate limits, then enable production access.
* Run focused API and web-extraction regression tests, then run Pint for changed PHP files before the task is complete.

## Completed: Flight plan extractor improvements
1. [x] Use storage/app/private/test_data/CKS025625KLAX.pdf as sample data
2. [x] Extract: PLANNED TO DEPT RUNWAY: 25R   SUMMR2 SCTRR
3. [x] Extract ETOPs ETPs
4. [x] Consider adding navigation fix value object storing name, type, and coordinates
5. [x] Extract EENT
6. [x] Extract EEXP

## Dark mode

* Configure Tailwind CSS v3 with `darkMode: 'class'`.
* Define a three-state theme preference: `light`, `dark`, or `system`.
* Add an inline `<head>` initialization script to apply the saved preference before the page renders and prevent a flash of the wrong theme.
* Persist the preference in `localStorage`; when `system` is selected, respond to changes from `prefers-color-scheme`.
* Add an accessible theme control to the desktop and mobile navigation with clear labels, keyboard support, and the current state exposed to assistive technology.
* Apply the theme initialization and controls consistently to the authenticated and guest layouts.
* Add semantic light/dark styles to the shared component classes in `resources/css/app.css`, including cards, headers, buttons, badges, and file inputs.
* Add `dark:` variants throughout shared Blade components before updating individual pages, covering backgrounds, text, borders, shadows, form controls, validation states, dropdowns, modals, and focus rings.
* Update the dashboard, schedule extractor, flight-plan extractor, profile, and authentication pages to use the shared dark-mode patterns.
* Decide whether the already-dark welcome and privacy-policy pages should remain fixed-dark or honor the saved theme, then make their behavior consistent with that decision.
* Verify Filament's admin-panel theme setting separately so it follows the same default and does not conflict with the application preference.
* Add tests that assert the theme initializer and accessible control are rendered in both layouts.
* Manually verify light, dark, and system modes at mobile and desktop breakpoints, including refresh behavior and live operating-system theme changes.
* Run the focused PHPUnit tests and `vendor/bin/sail npm run build` to confirm the Blade and Tailwind changes compile successfully.

## Current focus: Filament - User - is active

- [x] Allow admins to modify another user's `is_active` value from the User form and display the status in the table.
- [x] Prevent the current admin from deactivating themselves and losing panel access.
- [x] Add focused coverage for deactivation, reactivation, table status display, and self-deactivation protection.
- [ ] Run the focused User resource test and Pint after the local Docker service is started.
- Outcome so far: The User form includes an Active toggle, and the users table displays the boolean status without using Filament's policy-bypassing inline toggle column.

## Registered users stat
- filament widget
- shows registered users

## Cron email alerts
## Flight Plan extract request log to database

* Record the authenticated user, `source_type = pdf`, and `parser_type = flight_plan`.
* Create the row immediately before extraction with `status = partial`.
* Reuse the existing request logger to capture the UUID, file hash, file size, duration, and application/extractor versions.
* Mark the request `success` after extraction.
* Mark the request `failed` with `FlightRouteNotFoundException` or another exception type when extraction fails.
* Keep the current redirect, validation message, temporary-file deletion, and application logging behavior unchanged.
* Add `Flight Plan` to the parser filter in the Filament Extract Requests table.
* Generalize the schedule-specific logger into an `ExtractRequestLogger` with a completion method that accepts explicit counts.
* For a successful flight-plan extraction, record:
  * `detected_event_count = 1`
  * `detected_flight_count = 1`
  * `detected_hotel_count = 0`
  * `page_count = null`, unless PDF page counting is added
* Add tests covering successful extraction, recognized extraction failure, unexpected exceptions, file cleanup, correct user/hash/size metadata, and confirmation that invalid uploads do not create rows.

## Context engineering
- Add CONTEXT.md
- Identify branding

## Identify places to incorporate Crew Compass
- Airport info - Complete
- Layover guides available - per city
- Places available - places count - per city

# Backlog

## Switch SESSION_DRIVER and CACHE_STORE to an in-memory store like Redis or Memcached 
* In production to prevent session updates (update sessions set payload = ...) from competing with application queries.
Moving your Laravel app from database-backed storage to **Redis** for sessions and cache will drastically drop your database I/O and lower latency.

---

> **A Quick Warning Before Deploying:**
> Switching `SESSION_DRIVER` will **log out all active users**, because their session data currently lives in your SQL database, not Redis. Plan to run this during a minor maintenance window or off-peak hours.

---

### Step-by-Step Migration

1. **Install Redis PHP Extension or Package:** Server / App dependency.
Laravel uses either the native PHP extension (`ext-redis`) or the `predis/predis` package to talk to Redis. The native extension performs best.

Check if the extension is enabled:

```bash
php -m | grep redis

```

If it's not installed, either install `php-redis` on your OS

2. **Configure Environment Variables:** .env changes.
Open your production `.env` file and update your Redis connection and driver keys:

```ini
# Driver configuration
SESSION_DRIVER=redis
CACHE_STORE=redis

# Redis Connection details
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null     # Add password if configured in redis.conf
REDIS_PORT=6379
QUEUE_CONNECTION=redis

```

sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan optimize



4. **Clear and Recache App Configuration:** Apply changes live.
Because production environments cache `.env` values, you must clear and regenerate the config cache for the new drivers to take effect:

```bash
php artisan config:clear
php artisan cache:clear
php artisan config:cache

```


---

### Post-Migration Verification & Clean Up

1. **Verify Redis is receiving keys:**
Run `redis-cli` on your server and inspect incoming keys:
```bash
redis-cli
> KEYS *

```


2. **Clean up old database tables (Optional):**
Once everything is running smoothly on Redis, you can drop or archive the old `sessions` and `cache` tables from your SQL database to free up disk space.
