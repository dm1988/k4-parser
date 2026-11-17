<?php

namespace App\Services\CrewBids;

use App\Exceptions\BidPortalException;
use Closure;
use Redis;
use SensitiveParameter;
use Throwable;

class CrewBidSecretStore
{
    private ?Redis $redis = null;

    /** @param array<string, mixed> $value */
    public function put(string $key, #[SensitiveParameter] array $value, int $seconds): void
    {
        if ($this->execute(fn (Redis $redis) => $redis->setex('k4-vault:'.$key, $seconds, json_encode($value, JSON_THROW_ON_ERROR))) !== true) {
            throw new BidPortalException('The secure K4 memory store could not accept this operation.');
        }
    }

    /** @return array<string, mixed>|null */
    public function get(string $key): ?array
    {
        return $this->decode($this->execute(fn (Redis $redis) => $redis->get('k4-vault:'.$key)));
    }

    /** @return array<string, mixed>|null */
    public function pull(string $key): ?array
    {
        return $this->decode($this->execute(fn (Redis $redis) => $redis->getDel('k4-vault:'.$key)));
    }

    public function forget(string $key): void
    {
        $this->execute(fn (Redis $redis) => $redis->del('k4-vault:'.$key));
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function replace(string $key, #[SensitiveParameter] array $before, #[SensitiveParameter] array $after, int $seconds): bool
    {
        $script = 'if redis.call("GET", KEYS[1]) == ARGV[1] then redis.call("SETEX", KEYS[1], ARGV[3], ARGV[2]); return 1 end; return 0';

        return $this->execute(fn (Redis $redis) => $redis->eval($script, ['k4-vault:'.$key, json_encode($before, JSON_THROW_ON_ERROR), json_encode($after, JSON_THROW_ON_ERROR), $seconds], 1)) === 1;
    }

    public function available(): void
    {
        $this->execute(fn (Redis $redis) => $redis->ping());
    }

    /** @param Closure(Redis): mixed $operation */
    private function execute(#[SensitiveParameter] Closure $operation): mixed
    {
        try {
            return $operation($this->connection());
        } catch (Throwable) {
            $this->redis = null;
            throw new BidPortalException('The secure K4 memory store is unavailable. Try again after service is restored.');
        }
    }

    private function connection(): Redis
    {
        if ($this->redis !== null) {
            return $this->redis;
        }
        if (! app()->environment('local', 'testing') && (! config('crew_bids.secrets.tls') || ! config('crew_bids.secrets.password') || ! config('crew_bids.secrets.runtime_attested'))) {
            throw new BidPortalException('The secure K4 memory store has not been approved for this environment.');
        }
        $redis = new Redis;
        $host = (config('crew_bids.secrets.tls') ? 'tls://' : '').config('crew_bids.secrets.host');
        $context = config('crew_bids.secrets.tls') ? ['stream' => ['verify_peer' => true, 'verify_peer_name' => true]] : null;
        $redis->connect($host, config('crew_bids.secrets.port'), 2.0, null, 0, 2.0, $context);
        if (config('crew_bids.secrets.password')) {
            $redis->auth([config('crew_bids.secrets.username'), config('crew_bids.secrets.password')]);
        }
        $save = $redis->config('GET', 'save');
        $aof = $redis->config('GET', 'appendonly');
        $slowlog = $redis->config('GET', 'slowlog-log-slower-than');
        $slowlogLength = $redis->config('GET', 'slowlog-max-len');
        if (! is_array($save) || ($save['save'] ?? null) !== '' || ! is_array($aof) || ($aof['appendonly'] ?? null) !== 'no'
            || ! is_array($slowlog) || (string) ($slowlog['slowlog-log-slower-than'] ?? '') !== '-1'
            || ! is_array($slowlogLength) || (string) ($slowlogLength['slowlog-max-len'] ?? '') !== '0') {
            $redis->close();
            throw new BidPortalException('The secure K4 memory store must have persistence and command logging disabled.');
        }

        return $this->redis = $redis;
    }

    /** @return array<string, mixed>|null */
    private function decode(#[SensitiveParameter] mixed $value): ?array
    {
        if (! is_string($value)) {
            return null;
        }
        try {
            $decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new BidPortalException('The secure K4 operation expired or became invalid. Try again.');
        }

        return is_array($decoded) ? $decoded : null;
    }
}
