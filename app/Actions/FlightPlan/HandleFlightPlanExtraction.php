<?php

namespace App\Actions\FlightPlan;

use App\Exceptions\FlightRouteNotFoundException;
use App\Models\ExtractRequest;
use App\Models\User;
use App\Services\FlightPlan\Extractor\ExtractFlightPlanData;
use App\Services\FlightPlan\FlightPlanResultSerializer;
use App\Services\Infrastructure\ExtractRequestLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class HandleFlightPlanExtraction
{
    public function __construct(
        private readonly ExtractFlightPlanData $extractor,
        private readonly BuildFlightPlanData $builder,
        private readonly FlightPlanResultSerializer $serializer,
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
            $parsedFlightPlan = $this->extractor->extractFile($disk->path($path));
            $flightPlan = $this->serializer->serialize($this->builder->handle($parsedFlightPlan));

            $this->extractRequestLogger->complete(
                $extractRequest,
                $startedAt,
                detectedEventCount: 1,
                detectedFlightCount: 1,
                detectedHotelCount: 0,
            );

            return $flightPlan;
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
}
