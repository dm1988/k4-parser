<?php

namespace App\Services;

use App\DTOs\ParsedEventDTO;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use LogicException;

class ParserResultCache
{
    /**
     * @param  array<string, mixed>  $result
     */
    public function put(array $result): void
    {
        $parseKey = (string) ($result['parse_key'] ?? '');
        $ttlMinutes = config('cache.parsed_results_ttl', 60);
        $normalizedResult = $this->normalizeForCache($result);

        Cache::put($this->sessionCacheKey($parseKey), $normalizedResult, now()->addMinutes($ttlMinutes));
        Cache::put($this->parseKeyCacheKey($parseKey), $normalizedResult, now()->addMinutes($ttlMinutes));

        session(['latest_parse_key' => $parseKey]);
    }

    public function get(string $parseKey): mixed
    {
        return Cache::get($this->sessionCacheKey($parseKey))
            ?? Cache::get($this->parseKeyCacheKey($parseKey));
    }

    public function resolveForRequest(Request $request): mixed
    {
        $parseKey = $request->query('parse_key');

        if (is_string($parseKey) && $parseKey !== '') {
            return $this->get($parseKey);
        }

        $latestParseKey = session('latest_parse_key');

        if (is_string($latestParseKey) && $latestParseKey !== '') {
            return $this->get($latestParseKey);
        }

        return null;
    }

    private function sessionCacheKey(string $parseKey): string
    {
        return 'sessions:'.$this->sessionCacheNamespace().":parsed_results:{$parseKey}";
    }

    private function parseKeyCacheKey(string $parseKey): string
    {
        return "parsed_results:{$parseKey}";
    }

    private function sessionCacheNamespace(): string
    {
        $namespace = session('parsed_results_namespace');

        if (is_string($namespace) && $namespace !== '') {
            return $namespace;
        }

        $namespace = (string) Str::ulid();
        session(['parsed_results_namespace' => $namespace]);

        return $namespace;
    }

    private function normalizeForCache(mixed $value): mixed
    {
        if ($value instanceof ParsedEventDTO) {
            return $this->normalizeForCache($value->toArray());
        }

        if ($value instanceof \JsonSerializable) {
            return $this->normalizeForCache($value->jsonSerialize());
        }

        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        if (is_array($value)) {
            $normalized = [];

            foreach ($value as $key => $item) {
                $normalized[$key] = $this->normalizeForCache($item);
            }

            return $normalized;
        }

        if (is_object($value)) {
            throw new LogicException('Unsupported object passed to cache boundary: '.$value::class);
        }

        return $value;
    }
}
