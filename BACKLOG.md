# Backlog

## Check if Redis switch is needed




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
