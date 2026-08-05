# Codex Usage Rules

Follow these rules for every remaining task:
1. Work on one task at a time marked by ##.
2. Run only focused tests while implementing a task.
3. Run Pint after PHP files change.
4. Larastan once at the final integration checkpoint, not after every small edit.
5. Preserve unrelated working-tree changes.
6. Update TODO.md with outcomes instead of adding another plan or duplicate checklist.
7. Create a commit message for each task

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

## Complete - Filament - User - is active

- [x] Allow admins to modify another user's `is_active` value from the User form and display the status in the table.
- [x] Prevent the current admin from deactivating themselves and losing panel access.
- [x] Add focused coverage for deactivation, reactivation, table status display, and self-deactivation protection.
- [x] Run the focused User resource test and Pint after the local Docker service is started.
- Outcome so far: The User form includes an Active toggle, and the users table displays the boolean status without using Filament's policy-bypassing inline toggle column.

## Complete - Verified users stat

- [x] Keep the registered-user total in its existing Filament widget.
- [x] Add a parallel Filament widget showing users with verified email addresses.
- [x] Keep both user widgets together in the dashboard sort order.
- [x] Add focused coverage for mixed users, zero verified users, and dashboard visibility.
- [x] Tighten the dashboard into a two-by-two stat-card grid with a full-width chart below it.

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
