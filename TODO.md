# Codex Usage Rules

Follow these rules for every remaining task:
1. Work on one task at a time marked by ##.
2. Run only focused tests while implementing a task.
3. Run Pint after PHP files change.
4. Larastan once at the final integration checkpoint, not after every small edit.
5. Preserve unrelated working-tree changes.
6. Update TODO.md with outcomes instead of adding another plan or duplicate checklist.
7. Create a commit message for each task

## Completed: Release 1
- All tests pass

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
CACHE_STORE=redis        # Laravel 11+ (use CACHE_DRIVER=redis on Laravel 10 or older)

# Redis Connection details
REDIS_CLIENT=phpredis   # Change to 'predis' if using composer package
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null     # Add password if configured in redis.conf
REDIS_PORT=6379

```


3. **Update config/database.php (Optional):** Prevent Redis key collision.
By default, Laravel stores cache and session data under separate Redis database indexes or prefixes. Make sure your `config/database.php` has distinct databases assigned for cache and sessions to avoid key collisions:

```php
'redis' => [
    'client' => env('REDIS_CLIENT', 'phpredis'),

    'default' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_DB', '0'),
    ],

    'cache' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_CACHE_DB', '1'),
    ],
],

```


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

## API
* Use certificate

## Flight plan extractor improvements
* Extract ETOPs EENT, EEXP and ETP

## Dark mode
