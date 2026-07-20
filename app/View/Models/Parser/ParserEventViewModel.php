<?php

namespace App\View\Models\Parser;

use App\Enums\MetadataKey;
use App\Enums\ParserEventType;
use Carbon\CarbonImmutable;

readonly class ParserEventViewModel
{
    public function __construct(
        public string $title,
        public string $type,
        // public string $origin,
        // public string $destination,
        public string $typeLabel,
        public string $typeDescription,
        public string $typeIcon,
        public string $scheduleLabel,
        public string $durationLabel,
        public ?string $tailNumber,
        public ?string $hotel,
        public bool $isDeadhead,
        public string $badgeColor,
        public string $downloadUrl,
        public ?string $start = null,
        public ?string $end = null,
    ) {}

    public static function fromArray(array $event, string $parseKey): self
    {
        $start = CarbonImmutable::parse($event['start']);
        $end = CarbonImmutable::parse($event['end']);
        $sameDay = $start->isSameDay($end);
        $durationMinutes = (int) $start->diffInMinutes($end);
        $hours = intdiv($durationMinutes, 60);
        $minutes = $durationMinutes % 60;
        $eventType = ParserEventType::fromEvent($event);
        $downloadId = (string) ($event[MetadataKey::DownloadId->value] ?? '');
        $utcStart = $start->setTimezone('UTC');
        $utcEnd = $end->setTimezone('UTC');

        return new self(
            title: (string) ($event['title'] ?? 'Untitled event'),
            type: $eventType->value,
            typeLabel: $eventType->label(),
            typeDescription: $eventType->description(),
            typeIcon: $eventType->icon(),
            // origin: (string) $event['origin'] ?? '',
            // destination: (string) $event['destination'] ?? '',
            scheduleLabel: $sameDay
                ? $utcStart->format('M j').' • '.$utcStart->format('Hi \Z').' - '.$utcEnd->format('Hi \Z')
                : $utcStart->format('M j, Hi \Z').' -> '.$utcEnd->format('M j, Hi \Z'),
            durationLabel: $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m",
            tailNumber: self::tailNumber($event),
            hotel: self::metadataString($event, 'hotel'),
            isDeadhead: (bool) data_get($event, 'metadata.'.MetadataKey::Deadhead->value, data_get($event, 'is_deadhead', false)),
            badgeColor: $eventType->badgeColor(),
            downloadUrl: route('parse.export.event', ['eventId' => $downloadId, 'parse_key' => $parseKey]),
            start: $start->toIso8601String(),
            end: $end->toIso8601String(),
        );
    }

    public function headingDateLabel(): string
    {
        if ($this->start === null) {
            return $this->scheduleLabel;
        }

        return CarbonImmutable::parse($this->start)->format('M j');
    }

    private static function tailNumber(array $event): ?string
    {
        $tailNumber = strtoupper((string) data_get($event, 'metadata.'.MetadataKey::TailNumber->value, ''));

        return $tailNumber === '' ? null : $tailNumber;
    }

    private static function metadataString(array $event, string $key): ?string
    {
        $value = data_get($event, "metadata.{$key}");

        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
