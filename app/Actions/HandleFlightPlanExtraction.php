<?php

namespace App\Actions;

use App\DTOs\AirportData;
use App\Exceptions\FlightRouteNotFoundException;
use App\Models\ExtractRequest;
use App\Models\User;
use App\Services\FlightPlan\Extractor\FlightRouteExtractor;
use App\Services\Infrastructure\ExtractRequestLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class HandleFlightPlanExtraction
{
    private const RESULT_KEYS = [
        'departure',
        'destination',
        'alternate',
        'departure_airport',
        'destination_airport',
        'alternate_airport',
        'departure_runway',
        'arrival_runway',
        'departure_sid',
        'arrival_star',
        'etps',
        'eent_coordinates',
        'eexp_coordinates',
        'initial_altitude',
        'duration',
        'route',
    ];

    public function __construct(
        private readonly FlightRouteExtractor $extractor,
        private readonly ExtractRequestLogger $extractRequestLogger,
    ) {}

    /** @return array<string, mixed> */
    public function handle(User $user, UploadedFile $uploadedFile): array
    {
        $disk = Storage::disk('user_flight_releases');
        $path = $uploadedFile->store('', 'user_flight_releases');

        if (! is_string($path)) {
            throw new RuntimeException('The flight release could not be stored for extraction.');
        }

        $startedAt = null;
        $extractRequest = null;

        try {
            $startedAt = hrtime(true);
            $extractRequest = $this->extractRequestLogger->start(
                $user->getKey(),
                'pdf',
                'flight_plan',
                $uploadedFile,
            );
            $flightPlan = $this->extractor->extractFlightPlanData($disk->path($path));
            $flightPlan['route'] = $this->extractor->formatForIcaoDisplay($flightPlan['route']);

            $this->extractRequestLogger->complete(
                $extractRequest,
                $startedAt,
                detectedEventCount: 1,
                detectedFlightCount: 1,
                detectedHotelCount: 0,
            );

            return $this->normalizeFlightPlan($flightPlan);
        } catch (FlightRouteNotFoundException $exception) {
            $this->recordFailure($extractRequest, $startedAt, $exception);

            Log::warning('Flight release route extraction failed', [
                'filename' => $uploadedFile->getClientOriginalName(),
                'mime_type' => $uploadedFile->getMimeType(),
                'size' => $uploadedFile->getSize(),
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        } catch (Throwable $throwable) {
            $this->recordFailure($extractRequest, $startedAt, $throwable);

            throw $throwable;
        } finally {
            $disk->delete($path);
        }
    }

    private function recordFailure(?ExtractRequest $extractRequest, ?int $startedAt, Throwable $throwable): void
    {
        if ($extractRequest !== null && $startedAt !== null) {
            $this->extractRequestLogger->error($extractRequest, $startedAt, $throwable);
        }
    }

    /**
     * @param  array<string, mixed>  $flightPlan
     * @return array<string, mixed>
     */
    private function normalizeFlightPlan(array $flightPlan): array
    {
        foreach (['departure_airport', 'destination_airport', 'alternate_airport'] as $key) {
            if (($flightPlan[$key] ?? null) instanceof AirportData) {
                $flightPlan[$key] = $flightPlan[$key]->toArray();
            }
        }

        return array_intersect_key($flightPlan, array_flip(self::RESULT_KEYS));
    }
}
