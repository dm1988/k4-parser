<?php

namespace App\View\Models\Parser;

use App\Enums\ParserEventType;
use Carbon\CarbonImmutable;

readonly class ParserEventViewModel
{
    public function __construct(
        public string $title,
        public string $type,
        public string $typeLabel,
        public string $typeDescription,
        public string $typeIcon,
        public string $scheduleLabel,
        public string $durationLabel,
        public ?string $tailNumber,
        public bool $isDeadhead,
        public string $badgeColor,
        public string $downloadUrl,
    ) {}

    public static function fromArray(array $event, int $index): self
    {
        $start = CarbonImmutable::parse($event['start']);
        $end = CarbonImmutable::parse($event['end']);
        $sameDay = $start->isSameDay($end);
        $durationMinutes = $start->diffInMinutes($end);
        $hours = intdiv($durationMinutes, 60);
        $minutes = $durationMinutes % 60;
        $eventType = ParserEventType::fromValue($event['type'] ?? null);

        return new self(
            title: (string) ($event['title'] ?? 'Untitled event'),
            type: $eventType->value,
            typeLabel: $eventType->label(),
            typeDescription: $eventType->description(),
            typeIcon: $eventType->icon(),
            scheduleLabel: $sameDay
                ? $start->format('M j • g:i A').' - '.$end->format('g:i A')
                : $start->format('M j, g:i A').' -> '.$end->format('M j, g:i A'),
            durationLabel: $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m",
            tailNumber: self::tailNumber($event),
            isDeadhead: (bool) data_get($event, 'metadata.deadhead', data_get($event, 'is_deadhead', false)),
            badgeColor: $eventType->badgeColor(),
            downloadUrl: route('parse.export.event', ['eventIndex' => $index]),
        );
    }

    private static function tailNumber(array $event): ?string
    {
        $tailNumber = strtoupper((string) data_get($event, 'metadata.tail_number', ''));

        return $tailNumber === '' ? null : $tailNumber;
    }
}
