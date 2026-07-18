<?php

namespace App\Actions;

use App\DTOs\ParsedEventDTO;
use App\DTOs\ParserResultData;
use App\Enums\MetadataKey;
use Illuminate\Support\Str;

class BuildParserResult
{
    /**
     * @param  list<string>  $filters
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>  $meta
     */
    public function handle(
        string $type,
        string $source,
        ?string $documentType,
        array $parsed,
        array $filters = [],
        mixed $file = null,
        ?string $mime = null,
        array $meta = [],
    ): ParserResultData {
        return new ParserResultData(
            type: $type,
            source: $source,
            documentType: $documentType,
            file: $file,
            mime: $mime,
            parsed: $this->attachDownloadIds($parsed),
            filters: $filters,
            meta: $meta,
            parseKey: (string) Str::ulid(),
        );
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function attachDownloadIds(array $parsed): array
    {
        $events = [];

        foreach (($parsed['calendar_events'] ?? []) as $event) {
            $downloadId = (string) Str::ulid();

            if ($event instanceof ParsedEventDTO && method_exists($event, 'withDownloadId')) {
                $events[] = $event->withDownloadId($downloadId);

                continue;
            }

            if (is_array($event)) {
                $event[MetadataKey::DownloadId->value] = $downloadId;
                $events[] = $event;
            }
        }

        $parsed['calendar_events'] = $events;

        return $parsed;
    }
}
