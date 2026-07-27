<?php

namespace App\Services\Infrastructure;

use App\DTOs\ExtractedEventDTO;
use App\DTOs\ExtractedResultData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use LogicException;

class EngineResultCache
{
    public function put(ExtractedResultData $result): void
    {
        $parseKey = $result->parseKey ?? '';
        $ttlMinutes = config('cache.extracted_results_ttl', 60);
        $normalizedResult = $this->normalizeForCache($result);
        $cache = Cache::memo();

        $cache->put($this->sessionCacheKey($parseKey), $normalizedResult, now()->addMinutes($ttlMinutes));
        $cache->put($this->parseKeyCacheKey($parseKey), [
            'owner_id' => auth()->id(),
            'result' => $normalizedResult,
        ], now()->addMinutes($ttlMinutes));

        session(['latest_parse_key' => $parseKey]);
    }

    public function get(string $parseKey): ?ExtractedResultData
    {
        $cache = Cache::memo();
        $sessionResult = $cache->get($this->sessionCacheKey($parseKey));

        if (is_array($sessionResult)) {
            return ExtractedResultData::fromArray($sessionResult);
        }

        $cached = $cache->get($this->parseKeyCacheKey($parseKey));

        if (! is_array($cached)
            || ! array_key_exists('owner_id', $cached)
            || $cached['owner_id'] !== auth()->id()
            || ! is_array($cached['result'] ?? null)) {
            return null;
        }

        return ExtractedResultData::fromArray($cached['result']);
    }

    public function resolveForRequest(Request $request): ?ExtractedResultData
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

    public function latest(): ?ExtractedResultData
    {
        $parseKey = session('latest_parse_key');

        return is_string($parseKey) && $parseKey !== '' ? $this->get($parseKey) : null;
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
        if ($value instanceof ExtractedEventDTO) {
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
