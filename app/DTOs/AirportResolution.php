<?php

namespace App\DTOs;

use App\Enums\AirportResolutionStatus;
use App\Exceptions\AirportResolutionException;

final readonly class AirportResolution
{
    private function __construct(
        public string $requestedCode,
        public AirportResolutionStatus $status,
        public ?AirportData $airport,
    ) {
        if (
            $this->status === AirportResolutionStatus::Found
            && $this->airport === null
        ) {
            throw AirportResolutionException::missingData();
        }

        if (
            $this->status !== AirportResolutionStatus::Found
            && $this->airport !== null
        ) {
            throw AirportResolutionException::unexpectedData();
        }
    }

    public static function found(
        string $requestedCode,
        AirportData $airport,
    ): self {
        return new self(
            requestedCode: self::normalizeCode($requestedCode),
            status: AirportResolutionStatus::Found,
            airport: $airport,
        );
    }

    public static function missing(string $requestedCode): self
    {
        return new self(
            requestedCode: self::normalizeCode($requestedCode),
            status: AirportResolutionStatus::Missing,
            airport: null,
        );
    }

    public static function unavailable(string $requestedCode): self
    {
        return new self(
            requestedCode: self::normalizeCode($requestedCode),
            status: AirportResolutionStatus::Unavailable,
            airport: null,
        );
    }

    public function wasFound(): bool
    {
        return $this->status === AirportResolutionStatus::Found;
    }

    public function isMissing(): bool
    {
        return $this->status === AirportResolutionStatus::Missing;
    }

    public function isUnavailable(): bool
    {
        return $this->status === AirportResolutionStatus::Unavailable;
    }

    /**
     * @return array{
     *     requested_code: string,
     *     status: string,
     *     airport: array<string, mixed>|null
     * }
     */
    public function toArray(): array
    {
        return [
            'requested_code' => $this->requestedCode,
            'status' => $this->status->value,
            'airport' => $this->airport?->toArray(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $code = self::normalizeCode(
            is_scalar($data['requested_code'] ?? null)
                ? (string) $data['requested_code']
                : ''
        );

        $status = AirportResolutionStatus::from(
            is_scalar($data['status'] ?? null)
                ? (string) $data['status']
                : AirportResolutionStatus::Unavailable->value
        );

        return match ($status) {
            AirportResolutionStatus::Found => self::found(
                $code,
                AirportData::fromArray(
                    is_array($data['airport'] ?? null)
                        ? $data['airport']
                        : []
                ),
            ),
            AirportResolutionStatus::Missing => self::missing($code),
            AirportResolutionStatus::Unavailable => self::unavailable($code),
        };
    }

    private static function normalizeCode(string $code): string
    {
        return strtoupper(trim($code));
    }
}
