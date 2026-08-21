<?php

namespace App\ValueObjects;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use JsonSerializable;
use Throwable;

final readonly class FlightTime implements JsonSerializable
{
    private const UTC_TIMEZONES = [
        'UTC',
        'Etc/UTC',
        'Etc/GMT',
        'GMT',
        'Z',
        '+00:00',
    ];

    public CarbonImmutable $value;

    public string $timezone;

    public function __construct(string|DateTimeInterface $value, string $timezone)
    {
        $timezone = trim($timezone);

        if ($timezone === '') {
            throw new InvalidArgumentException('Flight time timezone must be a valid timezone identifier.');
        }

        try {
            $timezoneObject = new DateTimeZone($timezone);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException(
                'Flight time timezone must be a valid timezone identifier.',
                previous: $exception,
            );
        }

        if (
            ! in_array($timezoneObject->getName(), self::UTC_TIMEZONES, true)
            && ! in_array($timezoneObject->getName(), DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true)
        ) {
            throw new InvalidArgumentException('Flight time timezone must be a valid timezone identifier.');
        }

        try {
            $dateTime = is_string($value)
                ? self::parseString($value, $timezoneObject)
                : CarbonImmutable::instance($value);
        } catch (InvalidArgumentException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new InvalidArgumentException(
                'Flight time value must be a valid date and time.',
                previous: $exception,
            );
        }

        $this->value = $dateTime->setTimezone($timezoneObject);
        $this->timezone = $timezoneObject->getName();
    }

    public static function from(string|DateTimeInterface $value, string $timezone): self
    {
        return new self($value, $timezone);
    }

    public static function utc(string|DateTimeInterface $value): self
    {
        return new self($value, 'UTC');
    }

    public static function local(string|DateTimeInterface $value, string $timezone): self
    {
        return new self($value, $timezone);
    }

    /** @param array{instant: string, value: string, basis: 'utc'|'local', timezone: string} $data */
    public static function fromArray(array $data): self
    {
        $time = self::utc($data['instant']);

        return $data['basis'] === 'utc'
            ? $time
            : $time->inTimezone($data['timezone']);
    }

    public function isUtc(): bool
    {
        return in_array($this->timezone, self::UTC_TIMEZONES, true);
    }

    public function basis(): string
    {
        return $this->isUtc() ? 'utc' : 'local';
    }

    public function toUtc(): self
    {
        return $this->inTimezone('UTC');
    }

    public function inTimezone(string $timezone): self
    {
        return new self($this->value, $timezone);
    }

    public function sameInstantAs(self $other): bool
    {
        return $this->value->equalTo($other->value);
    }

    public function equals(self $other): bool
    {
        return $this->timezone === $other->timezone
            && $this->sameInstantAs($other);
    }

    public function toIso8601String(): string
    {
        return $this->value->toIso8601String();
    }

    /** @return array{instant: string, value: string, basis: 'utc'|'local', timezone: string} */
    public function toArray(): array
    {
        return [
            'instant' => $this->toUtc()->toIso8601String(),
            'value' => $this->toIso8601String(),
            'basis' => $this->basis(),
            'timezone' => $this->timezone,
        ];
    }

    /** @return array{instant: string, value: string, basis: 'utc'|'local', timezone: string} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private static function parseString(string $value, DateTimeZone $timezone): CarbonImmutable
    {
        $value = trim($value);

        if (in_array($timezone->getName(), self::UTC_TIMEZONES, true)) {
            if (preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|\+00:00)\z/', $value) !== 1) {
                throw new InvalidArgumentException('Flight time value must be a complete ISO-8601 UTC date and time.');
            }

            return CarbonImmutable::parse($value)->setTimezone($timezone);
        }

        if (preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\z/', $value) !== 1) {
            throw new InvalidArgumentException('Flight time value must be a valid date and time.');
        }

        $wallClock = CarbonImmutable::createFromFormat('!Y-m-d\TH:i:s', $value, 'UTC');
        $transitions = $timezone->getTransitions(
            $wallClock->getTimestamp() - 86_400,
            $wallClock->getTimestamp() + 86_400,
        );

        $candidates = [];

        foreach (array_unique(array_column($transitions, 'offset')) as $offset) {
            $candidate = CarbonImmutable::createFromTimestampUTC(
                $wallClock->getTimestamp() - (int) $offset,
            )->setTimezone($timezone);

            if ($candidate->format('Y-m-d\TH:i:s') === $value) {
                $candidates[] = $candidate;
            }
        }

        if (count($candidates) !== 1) {
            throw new InvalidArgumentException(
                'Flight time value is ambiguous or does not exist in the requested timezone.',
            );
        }

        return $candidates[0];
    }
}
